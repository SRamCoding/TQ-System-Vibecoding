<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Efectos\PedidoTraerAlProyecto;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Armar un presupuesto (plan 2.9): encabezado, títulos, partidas y sus APU; cambiar `usa_apu` (D9); la versión nueva
 * y «Traer al proyecto» (D6). Toda escritura bloquea la fila del presupuesto (`lockForUpdate`) y recalcula: el P.U. y
 * el parcial de la partida tocada, y los totales del presupuesto, cuya `version` sube (así una transición pedida con
 * una versión vieja no pasa con montos que la persona no vio). Solo en borrador: el modelo rechaza lo demás.
 * Lo que usa un solo método se pide en ese método (AGENTS.md: así no cierra ciclos).
 */
final readonly class ArmadoPresupuestoService
{
    private const string MAXIMO_METRADO = '999999999.999';

    public function crear(DatosPresupuesto $datos, User $usuario): Presupuesto
    {
        $encabezado = $this->encabezado($datos);

        return DB::transaction(function () use ($encabezado, $datos, $usuario): Presupuesto {
            // El número lo toma el motor (versión 1); las versiones siguientes lo heredan (plan 2.22, D2).
            return app(TransicionesService::class)->crear(new Presupuesto([
                ...$encabezado,
                'numero_version' => 1,
                'usa_apu' => $datos->usaApu,
                'decimales_precio_unitario' => ConsultaPresupuestos::decimalesPrecioUnitario(),
                'oportunidad_id' => $datos->oportunidadId,
            ]), $usuario)->refresh();
        });
    }

    /** Nombre, moneda y porcentajes. `usa_apu` se cambia con `cambiarUsaApu()`; la oportunidad no cambia. */
    public function editarEncabezado(int $presupuestoId, DatosPresupuesto $datos, int $versionLeida): Presupuesto
    {
        $encabezado = $this->encabezado($datos);

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($encabezado, $versionLeida): Presupuesto {
            $this->exigirVersion($presupuesto, $versionLeida);
            $presupuesto->fill($encabezado);

            return $this->recalcularTotales($presupuesto);
        });
    }

    /** Un título en la raíz (`01`) o bajo otro (`01.01`). */
    public function agregarTitulo(int $presupuestoId, ?int $padreId, string $codigo, string $nombre): TituloDePresupuesto
    {
        $codigo = trim($codigo);
        $nombre = $this->texto($nombre, 200, 'El nombre del título');

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($padreId, $codigo, $nombre): TituloDePresupuesto {
            $padre = $padreId === null ? null : (TituloDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->find($padreId)
                ?? throw PresupuestoRechazado::conMotivo('Ese título padre no es de este presupuesto.'));
            $this->exigirCodigo($presupuesto, $codigo, $padre?->codigo, 'título');

            $titulo = TituloDePresupuesto::query()->create([
                'presupuesto_id' => $presupuesto->id,
                'padre_id' => $padre?->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'orden' => $this->siguienteOrden($presupuesto->id),
            ]);
            $this->subirVersion($presupuesto);

            return $titulo->refresh();
        });
    }

    /** Solo un título sin títulos ni partidas debajo. */
    public function quitarTitulo(int $tituloId, int $versionLeida): void
    {
        $presupuestoId = (int) (TituloDePresupuesto::query()->whereKey($tituloId)->value('presupuesto_id') ?? throw PresupuestoRechazado::conMotivo('Ese título no existe.'));

        $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($tituloId, $versionLeida): void {
            $titulo = TituloDePresupuesto::query()->lockForUpdate()->findOrFail($tituloId);
            $this->exigirVersionDe($titulo, $versionLeida, "el título {$titulo->codigo}");

            if (TituloDePresupuesto::query()->where('padre_id', $titulo->id)->exists() || PartidaDePresupuesto::query()->where('titulo_id', $titulo->id)->exists()) {
                throw PresupuestoRechazado::conMotivo("El título {$titulo->codigo} tiene títulos o partidas debajo: primero quita esos.");
            }

            $titulo->delete();
            $this->subirVersion($presupuesto);
        });
    }

    /**
     * Una partida bajo un título: código del título más `.NN`. Con APU, el P.U. sale del análisis (aquí no se escribe);
     * sin APU, se escribe (D9). El rendimiento, con su unidad, es opcional: lo usan las líneas de mano de obra y equipo
     * que no llevan uno propio (D12).
     */
    public function agregarPartida(int $presupuestoId, int $tituloId, string $codigo, string $descripcion, int $unidadMedidaId, string $metrado, ?string $precioUnitario = null, ?string $rendimiento = null, ?string $unidadRendimiento = null): PartidaDePresupuesto
    {
        $codigo = trim($codigo);
        $descripcion = $this->texto($descripcion, 300, 'La descripción de la partida');
        $metrado = $this->metrado($metrado);
        [$rendimiento, $unidadRendimiento] = $this->rendimientoEscrito($rendimiento, $unidadRendimiento);

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($tituloId, $codigo, $descripcion, $unidadMedidaId, $metrado, $precioUnitario, $rendimiento, $unidadRendimiento): PartidaDePresupuesto {
            $titulo = TituloDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->find($tituloId)
                ?? throw PresupuestoRechazado::conMotivo('Ese título no es de este presupuesto.');
            $this->exigirCodigo($presupuesto, $codigo, $titulo->codigo, 'partida');
            $this->exigirUnidad($unidadMedidaId, null);

            $partida = PartidaDePresupuesto::query()->create([
                'presupuesto_id' => $presupuesto->id,
                'titulo_id' => $titulo->id,
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'unidad_medida_id' => $unidadMedidaId,
                'metrado' => $metrado,
                'costo_unitario' => $this->precioEscrito($presupuesto, $precioUnitario),
                'parcial' => '0',
                'rendimiento' => $rendimiento,
                'unidad_rendimiento' => $unidadRendimiento,
                'orden' => $this->siguienteOrden($presupuesto->id),
            ]);
            $this->recalcularPartida($presupuesto, $partida);
            $this->recalcularTotales($presupuesto);

            return $partida->refresh();
        });
    }

    /**
     * Lo escrito de la partida, completo: un rendimiento null la deja sin rendimiento. Si el rendimiento cambia, se
     * recalculan las líneas que lo usan; las que llevan uno propio no cambian (D12).
     */
    public function editarPartida(int $partidaId, string $descripcion, int $unidadMedidaId, string $metrado, ?string $precioUnitario, int $versionLeida, ?string $rendimiento = null, ?string $unidadRendimiento = null): PartidaDePresupuesto
    {
        $descripcion = $this->texto($descripcion, 300, 'La descripción de la partida');
        $metrado = $this->metrado($metrado);
        [$rendimiento, $unidadRendimiento] = $this->rendimientoEscrito($rendimiento, $unidadRendimiento);

        return $this->conPartida($partidaId, $versionLeida, function (Presupuesto $presupuesto, PartidaDePresupuesto $partida) use ($descripcion, $unidadMedidaId, $metrado, $precioUnitario, $rendimiento, $unidadRendimiento): PartidaDePresupuesto {
            $this->exigirUnidad($unidadMedidaId, $partida->unidad_medida_id);
            $cambiaRendimiento = $rendimiento !== $partida->rendimiento || $unidadRendimiento !== $partida->unidad_rendimiento;
            $partida->fill([
                'descripcion' => $descripcion,
                'unidad_medida_id' => $unidadMedidaId,
                'metrado' => $metrado,
                'rendimiento' => $rendimiento,
                'unidad_rendimiento' => $unidadRendimiento,
            ]);

            if ($cambiaRendimiento) {
                $this->recalcularHeredadas($partida);
            }

            if (! $presupuesto->usa_apu) {
                $partida->costo_unitario = $this->precioEscrito($presupuesto, $precioUnitario);
            } elseif ($precioUnitario !== null) {
                throw PresupuestoRechazado::conMotivo('Con APU, el precio unitario sale del análisis: no se escribe.');
            }

            $this->recalcularPartida($presupuesto, $partida);
            $this->recalcularTotales($presupuesto);

            return $partida;
        });
    }

    /** La partida y su APU. */
    public function quitarPartida(int $partidaId, int $versionLeida): void
    {
        $this->conPartida($partidaId, $versionLeida, function (Presupuesto $presupuesto, PartidaDePresupuesto $partida): void {
            foreach ($partida->lineasApu()->get() as $linea) {
                $linea->delete();
            }

            $partida->delete();
            $this->recalcularTotales($presupuesto);
        });
    }

    /** Un insumo en el APU de una partida (D4). Un insumo va una sola vez por APU. */
    public function agregarLineaApu(int $partidaId, DatosLineaApu $datos, int $versionLeida): LineaApu
    {
        return $this->conPartida($partidaId, $versionLeida, function (Presupuesto $presupuesto, PartidaDePresupuesto $partida) use ($datos): LineaApu {
            $this->exigirApu($presupuesto);
            $insumo = Insumo::query()->find($datos->insumoId) ?? throw PresupuestoRechazado::conMotivo('Ese insumo no existe.');

            if (! $insumo->es_activo) {
                throw PresupuestoRechazado::conMotivo("El insumo {$insumo->codigo} está desactivado: no entra en análisis nuevos.");
            }

            if (LineaApu::query()->where('presupuesto_partida_id', $partida->id)->where('insumo_id', $insumo->id)->exists()) {
                throw PresupuestoRechazado::conMotivo("El insumo {$insumo->codigo} ya está en el análisis de la partida {$partida->codigo}: cámbialo ahí.");
            }

            $linea = new LineaApu(['presupuesto_partida_id' => $partida->id, 'insumo_id' => $insumo->id]);
            $linea->setRelation('partida', $partida);
            $this->llenarLinea($linea, $insumo, $datos);
            $linea->save();
            $this->recalcularPartida($presupuesto, $partida);
            $this->recalcularTotales($presupuesto);

            return $linea->refresh();
        });
    }

    /** Lo escrito de una línea del APU; el insumo no cambia (para otro insumo, se quita y se agrega). */
    public function editarLineaApu(int $lineaId, DatosLineaApu $datos, int $versionLeida): LineaApu
    {
        return $this->conLinea($lineaId, $versionLeida, function (Presupuesto $presupuesto, PartidaDePresupuesto $partida, LineaApu $linea) use ($datos): LineaApu {
            if ($datos->insumoId !== $linea->insumo_id) {
                throw PresupuestoRechazado::conMotivo('El insumo de una línea no cambia: quítala y agrega el otro.');
            }

            $this->llenarLinea($linea, $linea->insumo, $datos);
            $linea->version++;
            $linea->save();
            $this->recalcularPartida($presupuesto, $partida);
            $this->recalcularTotales($presupuesto);

            return $linea;
        });
    }

    public function quitarLineaApu(int $lineaId, int $versionLeida): void
    {
        $this->conLinea($lineaId, $versionLeida, function (Presupuesto $presupuesto, PartidaDePresupuesto $partida, LineaApu $linea): void {
            $linea->delete();
            $this->recalcularPartida($presupuesto, $partida);
            $this->recalcularTotales($presupuesto);
        });
    }

    /**
     * Cambiar `usa_apu`, solo en borrador (D9). De no a sí: cada partida recibe un APU de un solo insumo, «Precio sin
     * detallar», por su P.U. escrito (no se pierde el precio). De sí a no: cada partida se queda con el P.U. de su APU y
     * los APU se descartan; por eso pide confirmación.
     */
    public function cambiarUsaApu(int $presupuestoId, bool $usaApu, bool $confirmaDescartarApu, int $versionLeida): Presupuesto
    {
        $sinDetallar = $usaApu ? app(InsumosService::class)->sinDetallar() : null;

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($usaApu, $confirmaDescartarApu, $versionLeida, $sinDetallar): Presupuesto {
            $this->exigirVersion($presupuesto, $versionLeida);

            if ($presupuesto->usa_apu === $usaApu) {
                return $presupuesto;
            }

            $partidas = PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->lockForUpdate()->get();

            if (! $usaApu && ! $confirmaDescartarApu && LineaApu::query()->whereIn('presupuesto_partida_id', $partidas->pluck('id'))->exists()) {
                throw PresupuestoRechazado::conMotivo('Sin APU, cada partida se queda con su precio unitario y se descartan sus análisis: confírmalo.');
            }

            $presupuesto->usa_apu = $usaApu;

            foreach ($partidas as $partida) {
                if ($usaApu && $sinDetallar !== null) {
                    $linea = new LineaApu(['presupuesto_partida_id' => $partida->id, 'insumo_id' => $sinDetallar->id]);
                    $linea->setRelation('partida', $partida);
                    $this->llenarLinea($linea, $sinDetallar, new DatosLineaApu($sinDetallar->id, cantidadNeta: '1', precio: $partida->costo_unitario));
                    $linea->save();
                } else {
                    foreach ($partida->lineasApu()->get() as $linea) {
                        $linea->delete();
                    }
                }

                $partida->version++;
                $this->recalcularPartida($presupuesto, $partida);
            }

            return $this->recalcularTotales($presupuesto);
        });
    }

    /**
     * La versión siguiente de un presupuesto aprobado (D6; adenda) u ofertado (plan 2.22, D8: en preventa, otros costos
     * para la cotización): copia todo a versión + 1 en borrador, con el mismo número y proyecto. La de origen sigue como
     * está hasta que la nueva se oferta o se trae al proyecto. Una sola versión en curso a la vez.
     */
    public function nuevaVersion(int $presupuestoId, User $usuario): Presupuesto
    {
        return DB::transaction(function () use ($presupuestoId, $usuario): Presupuesto {
            $origen = Presupuesto::query()->with('estado')->lockForUpdate()->find($presupuestoId)
                ?? throw PresupuestoRechazado::conMotivo('Ese presupuesto no existe.');

            if (! in_array($origen->estado->codigo, [DefinicionPresupuesto::APROBADO, DefinicionPresupuesto::OFERTADO], true)) {
                throw PresupuestoRechazado::conMotivo("La versión nueva sale del presupuesto aprobado u ofertado; {$origen->numero} v{$origen->numero_version} está ".mb_strtolower($origen->estado->nombre).'.');
            }

            $enCurso = Presupuesto::query()->where('numero', $origen->numero)->whereKeyNot($origen->id)->whereHas('estado', fn ($q) => $q->whereIn('codigo', [DefinicionPresupuesto::BORRADOR, DefinicionPresupuesto::OFERTADO]))->value('numero_version');

            if ($enCurso !== null) {
                throw PresupuestoRechazado::conMotivo("{$origen->numero} ya tiene la versión {$enCurso} en curso: sigue con esa.");
            }

            try {
                $nueva = app(TransicionesService::class)->crear(new Presupuesto([
                    ...$origen->only(['numero', 'oportunidad_id', 'proyecto_id', 'nombre', 'moneda_id', 'usa_apu', 'decimales_precio_unitario', 'gastos_generales_pct', 'utilidad_pct', 'igv_pct', 'costo_directo', 'gastos_generales', 'utilidad', 'igv', 'total']),
                    'numero_version' => (int) Presupuesto::query()->where('numero', $origen->numero)->max('numero_version') + 1,
                ]), $usuario);
            } catch (UniqueConstraintViolationException) {
                throw PresupuestoRechazado::conMotivo("Otra persona acaba de crear la versión nueva de {$origen->numero}: ábrela.");
            }

            $this->copiarContenido($origen, $nueva);

            return $nueva->refresh();
        });
    }

    /**
     * «Traer al proyecto» (D6): lo vincula al proyecto y lo aprueba, desde ofertado o directo desde borrador. Exige
     * presupuesto completo (condición) y proyecto que acepte costos (efecto). Una versión nueva va al mismo proyecto.
     */
    public function traerAlProyecto(int $presupuestoId, int $proyectoId, int $versionLeida, User $usuario): Presupuesto
    {
        $presupuesto = Presupuesto::query()->find($presupuestoId) ?? throw PresupuestoRechazado::conMotivo('Ese presupuesto no existe.');

        if ($presupuesto->proyecto_id !== null && $presupuesto->proyecto_id !== $proyectoId) {
            throw PresupuestoRechazado::conMotivo("La versión {$presupuesto->numero_version} de {$presupuesto->numero} es del proyecto de su versión anterior: va a ese.");
        }

        try {
            app(PedidoTraerAlProyecto::class)->durante($proyectoId, fn () => app(TransicionesService::class)->transicionar($presupuesto, DefinicionPresupuesto::TRAER_AL_PROYECTO, $versionLeida, $usuario));
        } catch (UniqueConstraintViolationException) {
            // Otro presupuesto llegó a la vez al mismo proyecto: la base deja uno solo vigente (D6).
            throw PresupuestoRechazado::conMotivo('Otra persona acaba de traer otro presupuesto a ese proyecto: revísalo antes de seguir.');
        }

        return $presupuesto->refresh();
    }

    private function copiarContenido(Presupuesto $origen, Presupuesto $nueva): void
    {
        $titulos = [];

        foreach (TituloDePresupuesto::query()->where('presupuesto_id', $origen->id)->orderBy('codigo')->get() as $titulo) {
            $titulos[$titulo->id] = TituloDePresupuesto::query()->create([
                ...$titulo->only(['codigo', 'nombre', 'orden']),
                'presupuesto_id' => $nueva->id,
                'padre_id' => $titulo->padre_id === null ? null : $titulos[$titulo->padre_id],
            ])->id;
        }

        foreach (PartidaDePresupuesto::query()->where('presupuesto_id', $origen->id)->with('lineasApu')->get() as $partida) {
            $copia = PartidaDePresupuesto::query()->create([
                ...$partida->only(['codigo', 'descripcion', 'unidad_medida_id', 'metrado', 'costo_unitario', 'parcial', 'rendimiento', 'unidad_rendimiento', 'orden']),
                'presupuesto_id' => $nueva->id,
                'titulo_id' => $titulos[$partida->titulo_id],
            ]);

            foreach ($partida->lineasApu as $linea) {
                $nuevaLinea = new LineaApu([
                    ...$linea->only(['insumo_id', 'tipo_cantidad', 'cantidad_neta', 'desperdicio_pct', 'rendimiento', 'unidad_rendimiento', 'cuadrilla', 'horas_jornada', 'rendimiento_propio', 'cantidad', 'precio', 'parcial']),
                    'presupuesto_partida_id' => $copia->id,
                ]);
                $nuevaLinea->setRelation('partida', $copia);
                $nuevaLinea->save();
            }
        }
    }

    /**
     * Lo escrito y lo calculado de una línea (D4): la efectiva a 5 decimales, el precio como foto, el parcial a 4. La mano
     * de obra y el equipo usan su rendimiento propio, si lo traen, o el de la partida (D12). Pide la relación `partida`.
     */
    private function llenarLinea(LineaApu $linea, Insumo $insumo, DatosLineaApu $datos): void
    {
        $precio = $datos->precio !== null && trim($datos->precio) !== ''
            ? InsumosService::precio($datos->precio)
            : (app(InsumosService::class)->precioInicial($insumo, CarbonImmutable::now('America/Lima'))
                ?? throw PresupuestoRechazado::conMotivo("El insumo {$insumo->codigo} no tiene precio de referencia: escribe el precio."));

        if ($insumo->vaPorRendimiento()) {
            $partida = $linea->partida;
            $propio = $this->vacio($datos->rendimiento) === null && $this->vacio($datos->unidadRendimiento) === null ? null
                : $this->rendimientoEscrito($datos->rendimiento, $datos->unidadRendimiento);
            [$rendimiento, $unidad] = $propio ?? [$partida->rendimiento, $partida->unidad_rendimiento];
            $cuadrilla = $this->decimal($datos->cuadrilla, 2, 'La cuadrilla');

            if ($rendimiento === null || $unidad === null) {
                throw PresupuestoRechazado::conMotivo("La partida {$partida->codigo} no tiene rendimiento: escríbelo en la partida o en la línea del insumo.");
            }

            if (bccomp($cuadrilla, '0', 2) <= 0) {
                throw PresupuestoRechazado::conMotivo('La cuadrilla va mayor que cero.');
            }

            $horasJornada = $unidad === LineaApu::POR_JORNADA ? ConsultaPresupuestos::horasJornada() : null;
            $cantidad = CalculoPresupuesto::cantidadPorRendimiento($rendimiento, $unidad, $cuadrilla, $horasJornada);
            $linea->fill([
                'tipo_cantidad' => LineaApu::POR_RENDIMIENTO,
                'cantidad_neta' => null,
                'desperdicio_pct' => '0',
                'rendimiento' => $rendimiento,
                'unidad_rendimiento' => $unidad,
                'rendimiento_propio' => $propio !== null,
                'cuadrilla' => $cuadrilla,
                'horas_jornada' => $horasJornada,
            ]);
        } else {
            $neta = $this->decimal($datos->cantidadNeta, 5, 'La cantidad');
            $desperdicio = $this->decimal($datos->desperdicioPct, 2, 'El desperdicio');

            if (bccomp($neta, '0', 5) <= 0 || bccomp($desperdicio, '100', 2) > 0) {
                throw PresupuestoRechazado::conMotivo('La cantidad va mayor que cero y el desperdicio, de 0 a 100 %.');
            }

            $cantidad = CalculoPresupuesto::cantidadPorCantidad($neta, $desperdicio);
            $linea->fill([
                'tipo_cantidad' => LineaApu::POR_CANTIDAD,
                'cantidad_neta' => $neta,
                'desperdicio_pct' => $desperdicio,
                'rendimiento' => null,
                'unidad_rendimiento' => null,
                'rendimiento_propio' => false,
                'cuadrilla' => null,
                'horas_jornada' => null,
            ]);
        }

        if (bccomp($cantidad, '0', 5) <= 0) {
            throw PresupuestoRechazado::conMotivo('La cantidad efectiva redondeada a 5 decimales da cero: revisa el rendimiento.');
        }

        $linea->fill(['cantidad' => $cantidad, 'precio' => $precio, 'parcial' => CalculoPresupuesto::parcialApu($cantidad, $precio)]);
    }

    /**
     * Las líneas que usan el rendimiento de la partida, con el nuevo (D12). Sin rendimiento en la partida, ninguna puede
     * quedar usándolo. Las que llevan el suyo no cambian.
     */
    private function recalcularHeredadas(PartidaDePresupuesto $partida): void
    {
        $heredadas = LineaApu::query()
            ->where('presupuesto_partida_id', $partida->id)
            ->where('tipo_cantidad', LineaApu::POR_RENDIMIENTO)
            ->where('rendimiento_propio', false)
            ->with('insumo')
            ->get();

        if ($heredadas->isNotEmpty() && $partida->rendimiento === null) {
            throw PresupuestoRechazado::conMotivo("Hay insumos de la partida {$partida->codigo} que usan su rendimiento: dales uno propio antes de quitarlo.");
        }

        foreach ($heredadas as $linea) {
            $linea->setRelation('partida', $partida);
            $this->llenarLinea($linea, $linea->insumo, new DatosLineaApu($linea->insumo_id, cuadrilla: (string) $linea->cuadrilla, precio: $linea->precio));
            $linea->version++;
            $linea->save();
        }
    }

    /**
     * Un rendimiento escrito con su unidad (D12): los dos o ninguno.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function rendimientoEscrito(?string $rendimiento, ?string $unidad): array
    {
        $rendimiento = $this->vacio($rendimiento);
        $unidad = $this->vacio($unidad);

        if ($rendimiento === null && $unidad === null) {
            return [null, null];
        }

        if (! in_array($unidad, [LineaApu::POR_HORA, LineaApu::POR_JORNADA], true)) {
            throw PresupuestoRechazado::conMotivo('El rendimiento va por hora o por jornada.');
        }

        $rendimiento = $this->decimal($rendimiento, 4, 'El rendimiento');

        if (bccomp($rendimiento, '0', 4) <= 0) {
            throw PresupuestoRechazado::conMotivo('El rendimiento va mayor que cero.');
        }

        return [$rendimiento, $unidad];
    }

    private function vacio(?string $valor): ?string
    {
        return $valor === null || trim($valor) === '' ? null : trim($valor);
    }

    /** P.U. y parcial de la partida (D5). Con APU, el P.U. es la suma de sus parciales; sin APU, el escrito. */
    private function recalcularPartida(Presupuesto $presupuesto, PartidaDePresupuesto $partida): void
    {
        $decimales = $presupuesto->decimales_precio_unitario;

        if ($presupuesto->usa_apu) {
            $parciales = LineaApu::query()->where('presupuesto_partida_id', $partida->id)->pluck('parcial')->map(fn ($p): string => (string) $p)->values()->all();
            $partida->costo_unitario = CalculoPresupuesto::precioUnitario($parciales, $decimales);
        } else {
            $partida->costo_unitario = CalculoPresupuesto::redondear($partida->costo_unitario, $decimales);
        }

        $partida->parcial = CalculoPresupuesto::parcialPartida($partida->metrado, $partida->costo_unitario);
        $partida->save();
    }

    private function recalcularTotales(Presupuesto $presupuesto): Presupuesto
    {
        $costoDirecto = bcadd((string) PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->sum('parcial'), '0', 2);
        $totales = CalculoPresupuesto::totales($costoDirecto, $presupuesto->gastos_generales_pct, $presupuesto->utilidad_pct, $presupuesto->igv_pct);
        $presupuesto->fill([
            'costo_directo' => $totales->costoDirecto,
            'gastos_generales' => $totales->gastosGenerales,
            'utilidad' => $totales->utilidad,
            'igv' => $totales->igv,
            'total' => $totales->total,
        ]);
        $this->subirVersion($presupuesto);

        return $presupuesto;
    }

    /**
     * @template T
     *
     * @param  callable(Presupuesto): T  $tarea
     * @return T
     */
    private function conPresupuesto(int $presupuestoId, callable $tarea): mixed
    {
        return DB::transaction(function () use ($presupuestoId, $tarea): mixed {
            $presupuesto = Presupuesto::query()->with('estado')->lockForUpdate()->find($presupuestoId)
                ?? throw PresupuestoRechazado::conMotivo('Ese presupuesto no existe.');

            if ($presupuesto->estado->codigo !== DefinicionPresupuesto::BORRADOR) {
                throw PresupuestoRechazado::conMotivo("El presupuesto {$presupuesto->numero} v{$presupuesto->numero_version} está ".mb_strtolower($presupuesto->estado->nombre).': no se edita (los cambios van en una versión nueva).');
            }

            return $tarea($presupuesto);
        });
    }

    /**
     * @template T
     *
     * @param  callable(Presupuesto, PartidaDePresupuesto): T  $tarea
     * @return T
     */
    private function conPartida(int $partidaId, int $versionLeida, callable $tarea): mixed
    {
        $presupuestoId = (int) (PartidaDePresupuesto::query()->whereKey($partidaId)->value('presupuesto_id') ?? throw PresupuestoRechazado::conMotivo('Esa partida no existe.'));

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($partidaId, $versionLeida, $tarea): mixed {
            $partida = PartidaDePresupuesto::query()->lockForUpdate()->findOrFail($partidaId);
            $this->exigirVersionDe($partida, $versionLeida, "la partida {$partida->codigo}");
            $partida->version++;
            $partida->setRelation('presupuesto', $presupuesto);

            return $tarea($presupuesto, $partida);
        });
    }

    /**
     * @template T
     *
     * @param  callable(Presupuesto, PartidaDePresupuesto, LineaApu): T  $tarea
     * @return T
     */
    private function conLinea(int $lineaId, int $versionLeida, callable $tarea): mixed
    {
        $partidaId = (int) (LineaApu::query()->whereKey($lineaId)->value('presupuesto_partida_id') ?? throw PresupuestoRechazado::conMotivo('Esa línea del análisis no existe.'));
        $presupuestoId = (int) PartidaDePresupuesto::query()->whereKey($partidaId)->value('presupuesto_id');

        return $this->conPresupuesto($presupuestoId, function (Presupuesto $presupuesto) use ($lineaId, $partidaId, $versionLeida, $tarea): mixed {
            $this->exigirApu($presupuesto);
            $partida = PartidaDePresupuesto::query()->lockForUpdate()->findOrFail($partidaId);
            $partida->setRelation('presupuesto', $presupuesto);
            $linea = LineaApu::query()->with('insumo')->lockForUpdate()->findOrFail($lineaId);
            $linea->setRelation('partida', $partida);
            $this->exigirVersionDe($linea, $versionLeida, "la línea de {$linea->insumo->codigo} en la partida {$partida->codigo}");
            $partida->version++;

            return $tarea($presupuesto, $partida, $linea);
        });
    }

    /**
     * Un código libre en el presupuesto (ni título ni partida lo tienen). En la raíz, `NN`; bajo un título, el código
     * del título más `.NN`.
     */
    private function exigirCodigo(Presupuesto $presupuesto, string $codigo, ?string $deQuien, string $que): void
    {
        $patron = $deQuien === null ? '/^\d{2}$/' : '/^'.preg_quote($deQuien, '/').'\.\d{2}$/';

        if (preg_match($patron, $codigo) !== 1) {
            $esperado = $deQuien === null ? 'NN (por ejemplo, «01»)' : "«{$deQuien}.NN»";

            throw PresupuestoRechazado::conMotivo("El código de la {$que} es {$esperado}; «{$codigo}» no sirve.");
        }

        if (TituloDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->where('codigo', $codigo)->exists()
            || PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->where('codigo', $codigo)->exists()) {
            throw PresupuestoRechazado::conMotivo("Ya hay un título o una partida {$codigo} en este presupuesto.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function encabezado(DatosPresupuesto $datos): array
    {
        if (! Moneda::query()->whereKey($datos->monedaId)->exists()) {
            throw PresupuestoRechazado::conMotivo('Esa moneda no existe.');
        }

        return [
            'nombre' => $this->texto($datos->nombre, 200, 'El nombre del presupuesto'),
            'moneda_id' => $datos->monedaId,
            'gastos_generales_pct' => $this->porcentaje($datos->gastosGeneralesPct, 'gastos generales'),
            'utilidad_pct' => $this->porcentaje($datos->utilidadPct, 'utilidad'),
            'igv_pct' => $this->porcentaje($datos->igvPct, 'IGV'),
        ];
    }

    private function precioEscrito(Presupuesto $presupuesto, ?string $precio): string
    {
        if ($presupuesto->usa_apu) {
            if ($precio !== null) {
                throw PresupuestoRechazado::conMotivo('Con APU, el precio unitario sale del análisis: no se escribe.');
            }

            return '0';
        }

        return $precio === null ? '0' : InsumosService::precio($precio);
    }

    private function exigirApu(Presupuesto $presupuesto): void
    {
        if (! $presupuesto->usa_apu) {
            throw PresupuestoRechazado::conMotivo('Este presupuesto va sin APU: el precio unitario se escribe en cada partida.');
        }
    }

    private function exigirUnidad(int $unidadMedidaId, ?int $actual): void
    {
        if ($unidadMedidaId !== $actual && ! UnidadMedida::query()->whereKey($unidadMedidaId)->where('es_activa', true)->exists()) {
            throw PresupuestoRechazado::conMotivo('Esa unidad de medida no existe o está desactivada.');
        }
    }

    private function exigirVersion(Presupuesto $presupuesto, int $versionLeida): void
    {
        if ($presupuesto->version !== $versionLeida) {
            throw VersionDesactualizada::de("el presupuesto {$presupuesto->numero}");
        }
    }

    private function exigirVersionDe(Model $fila, int $versionLeida, string $que): void
    {
        if ((int) $fila->getAttribute('version') !== $versionLeida) {
            throw VersionDesactualizada::de($que);
        }
    }

    private function subirVersion(Presupuesto $presupuesto): void
    {
        $presupuesto->version++;
        $presupuesto->save();
    }

    private function siguienteOrden(int $presupuestoId): int
    {
        return min(32767, 1 + max(
            (int) TituloDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->max('orden'),
            (int) PartidaDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->max('orden'),
        ));
    }

    private function texto(string $texto, int $maximo, string $que): string
    {
        $texto = trim($texto);

        if ($texto === '' || mb_strlen($texto) > $maximo) {
            throw PresupuestoRechazado::conMotivo("{$que} va con texto, de hasta {$maximo} caracteres.");
        }

        return $texto;
    }

    private function metrado(string $metrado): string
    {
        $metrado = trim($metrado);

        if (preg_match('/^\d{1,9}(\.\d{1,3})?$/', $metrado) !== 1 || bccomp($metrado, self::MAXIMO_METRADO, 3) > 0) {
            throw PresupuestoRechazado::conMotivo('El metrado va en números, sin signo y con hasta tres decimales.');
        }

        return bcadd($metrado, '0', 3);
    }

    private function porcentaje(string $pct, string $que): string
    {
        $pct = trim($pct);

        if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $pct) !== 1 || bccomp($pct, '100', 2) > 0) {
            throw PresupuestoRechazado::conMotivo("El porcentaje de {$que} va de 0 a 100, con hasta dos decimales.");
        }

        return bcadd($pct, '0', 2);
    }

    private function decimal(?string $valor, int $decimales, string $que): string
    {
        $valor = trim((string) $valor);

        if (preg_match('/^\d{1,9}(\.\d{1,'.$decimales.'})?$/', $valor) !== 1) {
            throw PresupuestoRechazado::conMotivo("{$que} va en números, sin signo y con hasta {$decimales} decimales.");
        }

        return bcadd($valor, '0', $decimales);
    }
}
