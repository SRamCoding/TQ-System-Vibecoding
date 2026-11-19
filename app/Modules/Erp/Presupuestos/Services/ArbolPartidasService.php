<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PresupuestadoDePartida;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Armar el árbol de partidas de un proyecto (plan 2.1, D5). Toda escritura sobre un árbol en borrador bloquea su fila
 * de `partida_arboles` (`lockForUpdate`), igual que aprobar: así no se aprueba con una suma vieja mientras otra persona
 * cambia un monto. `es_hoja` lo mantiene este servicio. Aprobar va por `TransicionesService` (la transición «aprobar»).
 * Lo que usa un solo método se pide en ese método, no en el constructor (AGENTS.md: así no cierra ciclos).
 */
final readonly class ArbolPartidasService
{
    private const string MAXIMO = '999999999999.99';

    /**
     * Plan 2.11, D11 (desarrollador, 2026-11-14): crear o armar el árbol (vacío, desde el presupuesto o desde una
     * plantilla) pide ver costos, porque quien lo arma tiene que conocer el costo presupuestado. Lo llaman las acciones
     * de crear y armar: se valida en el servidor, no solo se oculta en la pantalla.
     *
     * @throws ArbolRechazado
     */
    public function exigirVerCostos(User $usuario): void
    {
        if (! app(SeguridadService::class)->puede($usuario, GrupoSensible::Costos->permiso())) {
            throw ArbolRechazado::conMotivo('Armar el árbol de partidas pide el permiso de ver costos: quien lo arma tiene que conocer el costo presupuestado. Pídeselo a quien administra los roles.');
        }
    }

    /** El árbol en borrador de un proyecto. En esta tarea, solo la versión 1. */
    public function crear(int $proyectoId, string $costoPresupuestado, User $usuario): ArbolPartidas
    {
        try {
            $proyecto = app(ProyectosService::class)->resumen($proyectoId);
        } catch (ProyectoRechazado) {
            throw ArbolRechazado::conMotivo('Ese proyecto no existe.');
        }

        if (! $proyecto->aceptaCostos) {
            throw ArbolRechazado::conMotivo("El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': no se le arma un árbol.');
        }

        $costo = $this->monto($costoPresupuestado);

        $yaTiene = ArbolRechazado::conMotivo("El proyecto {$proyecto->codigo} ya tiene su árbol de partidas.");

        if (ArbolPartidas::query()->where('proyecto_id', $proyectoId)->exists()) {
            throw $yaTiene;
        }

        // Dos a la vez: los separa la unicidad (proyecto, versión) de la base, y el segundo recibe el mismo mensaje.
        try {
            return DB::transaction(fn (): ArbolPartidas => app(TransicionesService::class)->crear(new ArbolPartidas([
                'proyecto_id' => $proyectoId,
                'numero_version' => 1,
                'costo_presupuestado' => $costo,
            ]), $usuario));
        } catch (UniqueConstraintViolationException) {
            throw $yaTiene;
        }
    }

    /**
     * Una partida nueva. En la raíz, `PC-NN`; bajo un padre, el código del padre más `.NN` (supuesto ligado al
     * pendiente «Árbol de partidas»). Un padre con monto no recibe hijos: primero se le quita el monto.
     */
    public function agregarPartida(int $arbolId, ?int $padreId, string $codigo, string $nombre, int $responsableUserId, bool $esGastosGenerales = false, ?int $origenTituloId = null, ?int $origenPartidaId = null): PartidaControl
    {
        $codigo = strtoupper(trim($codigo));
        $nombre = trim($nombre);

        if ($codigo === PartidaControl::CODIGO_NO_PRESUPUESTADA) {
            throw ArbolRechazado::conMotivo('La PC-99 es la de no presupuestados: la crea el sistema al aprobar el árbol.');
        }

        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw ArbolRechazado::conMotivo('El nombre de la partida va con texto, de hasta 200 caracteres.');
        }

        $this->exigirResponsable($responsableUserId);

        return DB::transaction(function () use ($arbolId, $padreId, $codigo, $nombre, $responsableUserId, $esGastosGenerales, $origenTituloId, $origenPartidaId): PartidaControl {
            $arbol = $this->arbolEnBorrador($arbolId);
            $padre = null;

            if ($padreId === null) {
                if (preg_match('/^PC-\d{2}$/', $codigo) !== 1) {
                    throw ArbolRechazado::conMotivo("Una partida de la raíz lleva un código como «PC-01»; «{$codigo}» no sirve.");
                }
            } else {
                $padre = PartidaControl::query()->whereKey($padreId)->where('arbol_alta_id', $arbol->id)->lockForUpdate()->first()
                    ?? throw ArbolRechazado::conMotivo('Esa partida padre no es de este árbol.');

                if (! $padre->es_activa) {
                    throw ArbolRechazado::conMotivo("La partida {$padre->codigo} está desactivada: no recibe partidas nuevas.");
                }

                if (preg_match('/^'.preg_quote($padre->codigo, '/').'\.\d{2}$/', $codigo) !== 1) {
                    throw ArbolRechazado::conMotivo("Bajo {$padre->codigo}, el código es «{$padre->codigo}.NN»; «{$codigo}» no sirve.");
                }

                $this->hacerPadre($padre, $arbol);
            }

            if (PartidaControl::query()->where('proyecto_id', $arbol->proyecto_id)->where('codigo', $codigo)->exists()) {
                throw ArbolRechazado::conMotivo("Ya hay una partida {$codigo} en este proyecto.");
            }

            return PartidaControl::query()->create([
                'proyecto_id' => $arbol->proyecto_id,
                'padre_id' => $padre?->id,
                'arbol_alta_id' => $arbol->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'es_hoja' => true,
                'responsable_user_id' => $responsableUserId,
                'es_gastos_generales' => $esGastosGenerales,
                // Plan 2.9b, D6: de qué título o partida del presupuesto salió (solo al nacer desde una propuesta).
                'origen_titulo_id' => $origenTituloId,
                'origen_partida_id' => $origenPartidaId,
                // La base amarra el origen al presupuesto del árbol: una partida no sale de otro presupuesto.
                'origen_presupuesto_id' => $origenTituloId === null && $origenPartidaId === null ? null : $arbol->presupuesto_id,
            ])->refresh();
        });
    }

    /**
     * Confirma una propuesta (plan 2.9b, D3 a D6): crea el árbol en borrador o llena el borrador vacío, con todas sus
     * partidas, sus montos y su origen, en una sola transacción: si algo falla, no queda nada. Aplica los ajustes de la
     * persona (nombres, responsables y partidas quitadas, cuyo monto queda por repartir). Recibe la propuesta venga de
     * donde venga (el presupuesto; la plantilla, en la 2.11), armada en el servidor, nunca desde la pantalla.
     */
    public function crearDesdePropuesta(PropuestaArbol $propuesta, AjustesPropuesta $ajustes, User $usuario): ArbolPartidas
    {
        $nodos = $this->nodosQueQuedan($propuesta, $ajustes);
        $responsables = [];

        foreach ($nodos as $nodo) {
            $responsables[$nodo->codigo] = $ajustes->responsables[$nodo->codigo] ?? $ajustes->responsablePorOmision ?? $propuesta->responsableUserId
                ?? throw ArbolRechazado::conMotivo('Elige quién responde por las partidas: el jefe del proyecto no es un usuario activo, así que no hay uno por omisión.');
        }

        try {
            return DB::transaction(fn (): ArbolPartidas => $this->armar($propuesta, $ajustes, $nodos, $responsables, $usuario));
        } catch (QueryException $e) {
            // Una llave rota (un título o una partida del presupuesto que ya no está): mensaje, no error del sistema.
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            throw ArbolRechazado::conMotivo('El presupuesto cambió mientras revisabas la propuesta: vuelve a abrirla.');
        }
    }

    /**
     * @param  list<NodoPropuesto>  $nodos
     * @param  array<string, int>  $responsables
     */
    private function armar(PropuestaArbol $propuesta, AjustesPropuesta $ajustes, array $nodos, array $responsables, User $usuario): ArbolPartidas
    {
        $consulta = app(ConsultaArbol::class);
        $actual = $consulta->arbolParaLlenar($propuesta->proyectoId);

        // El presupuesto del que salió la propuesta tiene que seguir siendo el aprobado y vigente.
        if ($propuesta->presupuestoId !== null && $consulta->presupuestoAprobado($propuesta->proyectoId)?->id !== $propuesta->presupuestoId) {
            throw ArbolRechazado::conMotivo('El presupuesto vigente del proyecto cambió mientras revisabas la propuesta: vuelve a abrirla.');
        }

        if ($actual?->id !== $propuesta->arbolId) {
            throw ArbolRechazado::conMotivo('El árbol cambió mientras revisabas la propuesta: vuelve a abrirla.');
        }

        $arbol = $actual === null
            ? $this->crear($propuesta->proyectoId, $propuesta->costoPresupuestado, $usuario)
            : $this->arbolEnBorrador($actual->id);

        // Con la fila bloqueada: otra confirmación a la vez sobre el mismo borrador vacío espera y aquí se rechaza.
        if (PartidaControl::query()->where('proyecto_id', $arbol->proyecto_id)->exists()) {
            throw ArbolRechazado::conMotivo('Otra persona acaba de armar este árbol: vuelve a abrir la pestaña para verlo.');
        }

        // D3: el borrador vacío toma el costo del origen, o no cuadraría; D6: y el presupuesto o la plantilla de la que
        // salió (2.11, D3).
        $arbol->fill([
            'costo_presupuestado' => $this->monto($propuesta->costoPresupuestado),
            'presupuesto_id' => $propuesta->presupuestoId,
            'plantilla_version_id' => $propuesta->plantillaVersionId,
        ]);
        $arbol->version = $arbol->version + 1;
        $arbol->save();

        $ids = [];

        foreach ($nodos as $nodo) {
            $ids[$nodo->codigo] = $this->agregarPartida(
                $arbol->id,
                $nodo->padreCodigo === null ? null : $ids[$nodo->padreCodigo],
                $nodo->codigo,
                $ajustes->nombres[$nodo->codigo] ?? $nodo->nombre,
                $responsables[$nodo->codigo],
                $nodo->esGastosGenerales,
                $nodo->origenTituloId,
                $nodo->origenPartidaId,
            )->id;
        }

        foreach ($nodos as $nodo) {
            if ($nodo->esHoja && $nodo->monto !== null) {
                $this->fijarPresupuestado($ids[$nodo->codigo], $nodo->monto, PartidaControl::query()->findOrFail($ids[$nodo->codigo])->version);
            }
        }

        return $arbol->refresh();
    }

    /**
     * Las partidas de la propuesta que quedan tras los ajustes, en orden de código (los padres antes que sus hijas).
     * Quitar un título quita sus hijas; la hoja de gastos generales no se quita (D2, D5).
     *
     * @return list<NodoPropuesto>
     */
    private function nodosQueQuedan(PropuestaArbol $propuesta, AjustesPropuesta $ajustes): array
    {
        $codigos = array_map(fn (NodoPropuesto $n): string => $n->codigo, $propuesta->nodos);
        $ajustados = array_unique([...array_keys($ajustes->nombres), ...array_keys($ajustes->responsables), ...$ajustes->quitar]);

        foreach ($ajustados as $codigo) {
            if (! in_array($codigo, $codigos, true)) {
                throw ArbolRechazado::conMotivo("La partida {$codigo} no está en la propuesta.");
            }
        }

        $quedan = [];

        foreach ($propuesta->nodos as $nodo) {
            $quitada = array_filter($ajustes->quitar, fn (string $q): bool => $nodo->codigo === $q || str_starts_with($nodo->codigo, $q.'.')) !== [];

            if ($quitada && $nodo->esGastosGenerales) {
                throw ArbolRechazado::conMotivo("{$nodo->codigo} es la de gastos generales: no se quita en la propuesta. Si no la necesitas, se desactiva después en el borrador.");
            }

            if (! $quitada) {
                $quedan[] = $nodo;
            }
        }

        if ($quedan === []) {
            throw ArbolRechazado::conMotivo('Quitaste todas las partidas: no queda nada que armar. Para empezar de cero, usa «Crear el árbol».');
        }

        usort($quedan, fn (NodoPropuesto $a, NodoPropuesto $b): int => strcmp($a->codigo, $b->codigo));

        return $quedan;
    }

    /**
     * Cambia el nombre y el responsable de una partida activa del borrador (plan 2.9b, D5). El código, el padre, el
     * monto y el origen no cambian aquí: el origen es histórico (D6), y lo demás tiene sus propias acciones.
     */
    public function editarPartida(int $partidaId, string $nombre, int $responsableUserId, int $versionLeida): PartidaControl
    {
        $nombre = trim($nombre);

        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw ArbolRechazado::conMotivo('El nombre de la partida va con texto, de hasta 200 caracteres.');
        }

        $this->exigirResponsable($responsableUserId);

        return DB::transaction(function () use ($partidaId, $nombre, $responsableUserId, $versionLeida): PartidaControl {
            [, $partida] = $this->partidaParaCambiar($partidaId, $versionLeida);

            if ($partida->es_no_presupuestado) {
                throw ArbolRechazado::conMotivo('La PC-99 es la de no presupuestados: la crea el sistema y no se renombra.');
            }

            if (! $partida->es_activa) {
                throw ArbolRechazado::conMotivo("La partida {$partida->codigo} está desactivada: no se edita.");
            }

            $partida->fill(['nombre' => $nombre, 'responsable_user_id' => $responsableUserId]);
            $this->subirVersion($partida);

            return $partida;
        });
    }

    /** El presupuestado de una hoja en su árbol. Cero también vale (quita el monto). */
    public function fijarPresupuestado(int $partidaId, string $monto, int $versionLeida): PartidaControl
    {
        $monto = $this->monto($monto);

        return DB::transaction(function () use ($partidaId, $monto, $versionLeida): PartidaControl {
            [$arbol, $partida] = $this->partidaParaCambiar($partidaId, $versionLeida);

            if ($partida->es_no_presupuestado) {
                throw ArbolRechazado::conMotivo('La PC-99 no tiene presupuesto: lo que cae ahí es, justamente, lo no presupuestado.');
            }

            if (! $partida->es_hoja || ! $partida->es_activa) {
                throw ArbolRechazado::conMotivo("La partida {$partida->codigo} no es una hoja activa: su presupuestado es la suma de sus hojas.");
            }

            PresupuestadoDePartida::query()->updateOrCreate(['partida_id' => $partida->id, 'arbol_id' => $arbol->id], ['proyecto_id' => $arbol->proyecto_id, 'monto' => $monto]);
            $this->subirVersion($partida);

            return $partida;
        });
    }

    /**
     * Desactiva una partida sin costos, sin monto y sin hijos activos. La fila se bloquea antes de revisar sus costos
     * (D6): quien imputa la bloquea con lectura compartida, así que nunca queda una partida inactiva con costos.
     */
    public function desactivar(int $partidaId, int $versionLeida): PartidaControl
    {
        return DB::transaction(function () use ($partidaId, $versionLeida): PartidaControl {
            [$arbol, $partida] = $this->partidaParaCambiar($partidaId, $versionLeida);

            if ($partida->es_no_presupuestado) {
                throw ArbolRechazado::conMotivo('La PC-99 no se desactiva.');
            }

            if (PartidaControl::query()->where('padre_id', $partida->id)->where('es_activa', true)->exists()) {
                throw ArbolRechazado::conMotivo("La partida {$partida->codigo} tiene partidas activas debajo: primero desactiva esas.");
            }

            if (bccomp($this->montoDe($partida->id, $arbol->id), '0', 2) !== 0) {
                throw ArbolRechazado::conMotivo("La partida {$partida->codigo} tiene presupuestado: primero quita el monto.");
            }

            if (app(ImputacionService::class)->tieneLineasDeLaPartida($partida->id)) {
                throw ArbolRechazado::conMotivo("La partida {$partida->codigo} ya tiene costos: no se desactiva.");
            }

            $partida->es_activa = false;
            $this->subirVersion($partida);

            if ($partida->padre_id !== null && ! PartidaControl::query()->where('padre_id', $partida->padre_id)->where('es_activa', true)->exists()) {
                $padre = PartidaControl::query()->whereKey($partida->padre_id)->lockForUpdate()->firstOrFail();
                $padre->es_hoja = true;
                $this->subirVersion($padre);
            }

            return $partida;
        });
    }

    /** Las hojas activas con las que se puede imputar, sin contar la PC-99. */
    public function hojasActivas(int $arbolId): int
    {
        return PartidaControl::query()->where('arbol_alta_id', $arbolId)->where('es_hoja', true)->where('es_activa', true)->where('es_no_presupuestado', false)->count();
    }

    /** La suma del presupuestado de las hojas del árbol, como texto decimal. */
    public function sumaDeHojas(int $arbolId): string
    {
        $suma = PresupuestadoDePartida::query()
            ->join('partidas_control', 'partidas_control.id', '=', 'partida_presupuestos.partida_id')
            ->where('partida_presupuestos.arbol_id', $arbolId)
            ->where('partidas_control.es_hoja', true)
            ->where('partidas_control.es_activa', true)
            ->sum('partida_presupuestos.monto');

        return bcadd((string) $suma, '0', 2);
    }

    private function arbolEnBorrador(int $arbolId): ArbolPartidas
    {
        $arbol = ArbolPartidas::query()->with('estado')->lockForUpdate()->find($arbolId)
            ?? throw ArbolRechazado::conMotivo('Ese árbol de partidas no existe.');

        if ($arbol->estado->codigo !== DefinicionArbolPartidas::BORRADOR) {
            throw ArbolRechazado::conMotivo('El árbol ya está aprobado: no se edita (los cambios irán en una versión nueva).');
        }

        return $arbol;
    }

    /**
     * @return array{ArbolPartidas, PartidaControl}
     */
    private function partidaParaCambiar(int $partidaId, int $versionLeida): array
    {
        $arbolId = PartidaControl::query()->whereKey($partidaId)->value('arbol_alta_id')
            ?? throw ArbolRechazado::conMotivo('Esa partida no existe.');
        $arbol = $this->arbolEnBorrador((int) $arbolId);
        $partida = PartidaControl::query()->whereKey($partidaId)->lockForUpdate()->firstOrFail();

        if ($partida->version !== $versionLeida) {
            throw VersionDesactualizada::de("la partida {$partida->codigo}");
        }

        return [$arbol, $partida];
    }

    private function hacerPadre(PartidaControl $padre, ArbolPartidas $arbol): void
    {
        if (! $padre->es_hoja) {
            return;
        }

        if ($padre->es_no_presupuestado) {
            throw ArbolRechazado::conMotivo('La PC-99 no tiene partidas debajo.');
        }

        if (bccomp($this->montoDe($padre->id, $arbol->id), '0', 2) !== 0) {
            throw ArbolRechazado::conMotivo("La partida {$padre->codigo} tiene presupuestado: primero quita el monto, y después agrégale partidas debajo.");
        }

        // Un monto en cero, si lo tenía: se borra por su modelo (parte del documento del árbol).
        $montoEnCero = PresupuestadoDePartida::query()->where('partida_id', $padre->id)->where('arbol_id', $arbol->id)->first();

        if ($montoEnCero !== null) {
            $montoEnCero->delete();
        }

        $padre->es_hoja = false;
        $this->subirVersion($padre);
    }

    private function montoDe(int $partidaId, int $arbolId): string
    {
        $monto = PresupuestadoDePartida::query()->where('partida_id', $partidaId)->where('arbol_id', $arbolId)->value('monto');

        return $monto === null ? '0.00' : (string) $monto;
    }

    private function subirVersion(PartidaControl $partida): void
    {
        $partida->version = $partida->version + 1;
        $partida->save();
    }

    private function exigirResponsable(int $userId): void
    {
        $usuario = User::query()->find($userId);

        if ($usuario === null || ! $usuario->es_activo || $usuario->es_sistema) {
            throw ArbolRechazado::conMotivo('El responsable de la partida debe ser un usuario activo.');
        }
    }

    private function monto(string $monto): string
    {
        $monto = trim($monto);

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $monto) !== 1 || bccomp($monto, self::MAXIMO, 2) > 0) {
            throw ArbolRechazado::conMotivo('El monto va en números, sin signo y con hasta dos decimales.');
        }

        return bcadd($monto, '0', 2);
    }
}
