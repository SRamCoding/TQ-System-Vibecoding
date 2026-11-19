<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\PresupuestadoDePartida;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\ResumenProyecto;
use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PlantillasService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que lee la pestaña «Partidas de control» de la ficha del proyecto (plan 2.10, paso 3): el árbol del proyecto, sus
 * partidas en orden de código con el monto de cada una y cuánto falta repartir, y el costo que se sugiere al crearlo
 * (D5). Solo lectura: las escrituras van por ArbolPartidasService y sus acciones.
 */
final readonly class ConsultaArbol
{
    /** El árbol del proyecto (hoy, una sola versión), o nulo si todavía no se armó. */
    public function arbolDe(int $proyectoId): ?ArbolPartidas
    {
        return ArbolPartidas::query()->with('estado')->where('proyecto_id', $proyectoId)->orderByDesc('numero_version')->first();
    }

    /**
     * El último árbol aprobado del proyecto: del que sale el saldo de cada partida (C1). Nulo si ninguno se aprobó
     * (plan 2.61, D14).
     */
    public function arbolAprobadoDe(int $proyectoId): ?ArbolPartidas
    {
        return ArbolPartidas::query()->with('estado')->where('proyecto_id', $proyectoId)
            ->whereHas('estado', fn ($query) => $query->where('codigo', DefinicionArbolPartidas::APROBADO))
            ->orderByDesc('numero_version')->first();
    }

    /**
     * Las partidas del árbol en orden de código («PC-01», «PC-01.01», «PC-02»…), la PC-99 al final.
     *
     * @return list<FilaArbol>
     */
    public function filas(int $arbolId): array
    {
        $arbol = ArbolPartidas::query()->findOrFail($arbolId);
        $partidas = PartidaControl::query()->where('proyecto_id', $arbol->proyecto_id)->orderBy('codigo')->get();
        $montos = PresupuestadoDePartida::query()->where('arbol_id', $arbol->id)->pluck('monto', 'partida_id');
        $responsables = User::query()->whereKey($partidas->pluck('responsable_user_id')->unique()->values()->all())->pluck('name', 'id');
        $hojasActivas = $partidas->filter(fn (PartidaControl $p): bool => $p->es_hoja && $p->es_activa);
        $titulos = TituloDePresupuesto::query()->whereKey($partidas->pluck('origen_titulo_id')->filter()->values()->all())->pluck('codigo', 'id');
        $dePresupuesto = PartidaDePresupuesto::query()->whereKey($partidas->pluck('origen_partida_id')->filter()->values()->all())->get(['id', 'codigo', 'descripcion'])->keyBy('id');
        $origen = fn (PartidaControl $p): ?string => match (true) {
            $p->origen_titulo_id !== null => 'título '.($titulos[$p->origen_titulo_id] ?? '?'),
            $p->origen_partida_id !== null => ($x = $dePresupuesto[$p->origen_partida_id] ?? null) === null ? null : "{$x->codigo} · {$x->descripcion}",
            $p->es_gastos_generales && $arbol->presupuesto_id !== null && $p->arbol_alta_id === $arbol->id => 'gastos generales del presupuesto',
            default => null,
        };

        return $partidas->map(function (PartidaControl $p) use ($montos, $responsables, $hojasActivas, $origen): FilaArbol {
            $presupuestado = $p->es_hoja
                ? bcadd((string) ($montos[$p->id] ?? '0'), '0', 2)
                : $hojasActivas->filter(fn (PartidaControl $h): bool => str_starts_with($h->codigo, $p->codigo.'.'))
                    ->reduce(fn (string $suma, PartidaControl $h): string => bcadd($suma, (string) ($montos[$h->id] ?? '0'), 2), '0.00');

            return new FilaArbol(
                id: $p->id,
                padreId: $p->padre_id,
                codigo: $p->codigo,
                nombre: $p->nombre,
                nivel: substr_count($p->codigo, '.'),
                esHoja: $p->es_hoja,
                esActiva: $p->es_activa,
                esGastosGenerales: $p->es_gastos_generales,
                esNoPresupuestado: $p->es_no_presupuestado,
                responsableUserId: $p->responsable_user_id,
                responsable: (string) ($responsables[$p->responsable_user_id] ?? '—'),
                presupuestado: $presupuestado,
                version: $p->version,
                origen: $origen($p),
            );
        })->sortBy(fn (FilaArbol $f): string => ($f->esNoPresupuestado ? '1' : '0').$f->codigo)->values()->all();
    }

    /** Lo que falta repartir: costo presupuestado menos la suma de las hojas activas (negativo si se pasa). */
    public function porRepartir(int $arbolId): string
    {
        $arbol = ArbolPartidas::query()->findOrFail($arbolId);

        return bcsub($arbol->costo_presupuestado, app(ArbolPartidasService::class)->sumaDeHojas($arbolId), 2);
    }

    /**
     * D5: el costo que se sugiere al crear el árbol, costo directo + gastos generales del presupuesto aprobado vigente
     * del proyecto (flujo 1: 17,750.00 + 1,775.00 = 19,525.00), o nulo si no tiene. Se puede cambiar.
     */
    public function costoSugerido(int $proyectoId): ?string
    {
        $presupuesto = $this->presupuestoAprobado($proyectoId);

        return $presupuesto === null ? null : bcadd($presupuesto->costo_directo, $presupuesto->gastos_generales, 2);
    }

    /**
     * La comprobación única de «el árbol se puede llenar» (plan 2.9b, D3), que usan todos los orígenes (el presupuesto,
     * una plantilla y, a futuro, un archivo): si el proyecto no tiene árbol, devuelve nulo (se crea al confirmar); si
     * tiene un borrador sin ninguna partida, devuelve ese borrador (se llena). Si no, rechaza diciendo por qué y qué
     * hacer: nunca se pisa nada.
     *
     * @throws ArbolRechazado
     */
    public function arbolParaLlenar(int $proyectoId): ?ArbolPartidas
    {
        $proyecto = self::proyecto($proyectoId);
        $arbol = $this->arbolDe($proyectoId);

        if ($arbol === null) {
            return null;
        }

        if ($arbol->estado->codigo !== DefinicionArbolPartidas::BORRADOR) {
            throw ArbolRechazado::conMotivo("El árbol de {$proyecto->codigo} ya está aprobado (v{$arbol->numero_version}): no se vuelve a armar. Sus partidas se cambian con una versión nueva del árbol.");
        }

        if (PartidaControl::query()->where('proyecto_id', $proyectoId)->exists()) {
            throw ArbolRechazado::conMotivo("El árbol de {$proyecto->codigo} (v{$arbol->numero_version}, en borrador) ya tiene partidas: sigue armándolo en su pestaña, o desactiva las que sobren. Armarlo desde otro origen pisaría lo que ya tiene.");
        }

        return $arbol;
    }

    /** Lo mismo, como sí o no, para mostrar u ocultar «Armar el árbol». */
    public function sePuedeLlenar(int $proyectoId): bool
    {
        try {
            $this->arbolParaLlenar($proyectoId);

            return true;
        } catch (ArbolRechazado) {
            return false;
        }
    }

    /**
     * Quién responde por las partidas propuestas si nadie elige otro (plan 2.9b, D4; 2.11, D6): el jefe del proyecto,
     * si es un usuario activo. Un solo lugar para todos los orígenes; no se deduce de puestos ni especialidades (el
     * pendiente «Árbol de partidas» y el organigrama siguen abiertos). Nulo: hay que elegirlo antes de confirmar.
     */
    public function responsablePorOmision(int $proyectoId): ?int
    {
        $jefe = User::query()->find(self::proyecto($proyectoId)->jefeProyectoUserId);

        return $jefe !== null && $jefe->es_activo && ! $jefe->es_sistema ? $jefe->id : null;
    }

    /**
     * «Desde el presupuesto» (plan 2.9b, D1, D2 y D6): un padre por título o subtítulo y una hoja por partida, con el
     * código del presupuesto (01.01 → PC-01.01) y su parcial; más la hoja de gastos generales con el siguiente código
     * raíz libre (si el presupuesto los tiene). Suma costo directo + gastos generales, el costo presupuestado (D5 de la
     * 2.10). Exige un presupuesto aprobado y vigente, y que el árbol se pueda llenar.
     *
     * @throws ArbolRechazado
     */
    public function propuestaDesdePresupuesto(int $proyectoId): PropuestaArbol
    {
        $proyecto = self::proyecto($proyectoId);
        $arbol = $this->arbolParaLlenar($proyectoId);

        if (! $proyecto->aceptaCostos) {
            throw ArbolRechazado::conMotivo("El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': no se le arma un árbol.');
        }

        $presupuesto = $this->presupuestoAprobado($proyectoId)
            ?? throw ArbolRechazado::conMotivo("{$proyecto->codigo} no tiene un presupuesto aprobado: tráelo al proyecto desde su ficha en Presupuestos, o arma el árbol a mano.");

        $filas = app(ConsultaPresupuestos::class)->filas($presupuesto->id);
        $nodos = [];
        $raices = [];

        foreach ($filas as $fila) {
            $codigo = 'PC-'.$fila->codigo;

            if ($codigo === PartidaControl::CODIGO_NO_PRESUPUESTADA) {
                throw ArbolRechazado::conMotivo('El título 99 del presupuesto chocaría con la PC-99, que es de los no presupuestados: cámbiale el código en una versión nueva del presupuesto, o arma el árbol a mano.');
            }

            $padre = $fila->nivel === 0 ? null : 'PC-'.substr($fila->codigo, 0, (int) strrpos($fila->codigo, '.'));
            $tieneHijas = $fila->esTitulo && array_filter($filas, fn (FilaPresupuesto $f): bool => str_starts_with($f->codigo, $fila->codigo.'.')) !== [];

            if ($fila->nivel === 0) {
                $raices[] = (int) $fila->codigo;
            }

            $nodos[] = new NodoPropuesto(
                codigo: $codigo,
                nombre: self::nombre($fila->descripcion),
                padreCodigo: $padre,
                esHoja: ! $tieneHijas,
                monto: $tieneHijas ? null : $fila->parcial,
                origenTituloId: $fila->esTitulo ? $fila->id : null,
                origenPartidaId: $fila->esTitulo ? null : $fila->id,
                origen: $fila->esTitulo ? "título {$fila->codigo}" : "{$fila->codigo} · {$fila->descripcion}",
            );
        }

        if (bccomp($presupuesto->gastos_generales, '0', 2) > 0) {
            $nodos[] = new NodoPropuesto(
                codigo: self::raizLibre($raices),
                nombre: 'Gastos generales',
                padreCodigo: null,
                esHoja: true,
                monto: $presupuesto->gastos_generales,
                esGastosGenerales: true,
                origen: 'gastos generales del presupuesto',
            );
        }

        return new PropuestaArbol(
            proyectoId: $proyectoId,
            arbolId: $arbol?->id,
            costoPresupuestado: bcadd($presupuesto->costo_directo, $presupuesto->gastos_generales, 2),
            responsableUserId: $this->responsablePorOmision($proyectoId),
            nodos: $nodos,
            presupuestoId: $presupuesto->id,
        );
    }

    /**
     * Desde una plantilla (plan 2.11, D3, D7, D8 y D10): la propuesta que arma su tipo con la versión vigente, con el
     * costo elegido. `$versionVista` es la que la persona revisó: si desde entonces hay otra, se rechaza y no se crea
     * nada (D10).
     *
     * @throws ArbolRechazado
     * @throws PlantillaRechazada
     */
    public function propuestaDesdePlantilla(int $proyectoId, int $plantillaId, ?int $versionVista, ?string $costoPresupuestado, User $usuario): PropuestaArbol
    {
        $aplicada = app(PlantillasService::class)->aplicar($plantillaId, ['proyecto_id' => $proyectoId], $usuario);

        if (! $aplicada->propuesta instanceof PropuestaArbol) {
            throw ArbolRechazado::conMotivo("«{$aplicada->nombre}» no es una plantilla de árbol de partidas.");
        }

        if ($versionVista !== null && $versionVista !== $aplicada->plantillaVersionId) {
            throw ArbolRechazado::conMotivo("La plantilla «{$aplicada->nombre}» cambió (ahora v{$aplicada->numero}) mientras revisabas la propuesta: vuelve a abrirla.");
        }

        return $aplicada->propuesta->con($aplicada->plantillaVersionId, $costoPresupuestado);
    }

    /**
     * La propuesta con partidas que trae otro origen (una plantilla, 2.11): la misma comprobación de «el árbol se puede
     * llenar» (D3), el mismo responsable por omisión (D4) y el costo sugerido (2.11, D8). Las partidas llegan sin monto.
     *
     * @param  list<NodoPropuesto>  $nodos
     *
     * @throws ArbolRechazado
     */
    public function propuestaConPartidas(int $proyectoId, array $nodos): PropuestaArbol
    {
        $proyecto = self::proyecto($proyectoId);
        $arbol = $this->arbolParaLlenar($proyectoId);

        if (! $proyecto->aceptaCostos) {
            throw ArbolRechazado::conMotivo("El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': no se le arma un árbol.');
        }

        return new PropuestaArbol(
            proyectoId: $proyectoId,
            arbolId: $arbol?->id,
            costoPresupuestado: $this->costoParaLlenar($proyectoId) ?? '0.00',
            responsableUserId: $this->responsablePorOmision($proyectoId),
            nodos: $nodos,
        );
    }

    /**
     * El costo que se sugiere al llenar el árbol desde un origen sin montos (plan 2.11, D8): el del borrador vacío si ya
     * existe; si no, el del presupuesto aprobado (D5 de la 2.10); nulo si no hay ninguno y la persona lo escribe.
     */
    public function costoParaLlenar(int $proyectoId): ?string
    {
        $arbol = $this->arbolDe($proyectoId);

        return $arbol !== null ? (string) $arbol->costo_presupuestado : $this->costoSugerido($proyectoId);
    }

    /** De qué plantilla salió el árbol: «Videovigilancia v3», o nulo (plan 2.11, D3). */
    public function plantillaDe(ArbolPartidas $arbol): ?string
    {
        return $arbol->plantilla_version_id === null ? null : app(PlantillasService::class)->origen($arbol->plantilla_version_id)?->texto();
    }

    /** El presupuesto aprobado y vigente del proyecto, el que manda en su costo. */
    public function presupuestoAprobado(int $proyectoId): ?Presupuesto
    {
        return Presupuesto::query()
            ->where('proyecto_id', $proyectoId)
            ->where('es_vigente', true)
            ->whereHas('estado', fn (Builder $q): Builder => $q->where('codigo', DefinicionPresupuesto::APROBADO))
            ->first();
    }

    /**
     * La primera raíz libre después de las del presupuesto, sin la 99: PC-04 si hay 01, 02 y 03.
     *
     * @param  list<int>  $raices
     */
    private static function raizLibre(array $raices): string
    {
        $siguiente = $raices === [] ? 1 : max($raices) + 1;

        if ($siguiente >= 99) {
            throw ArbolRechazado::conMotivo('El presupuesto usa todos los códigos de la raíz hasta el 98: no queda uno para los gastos generales. Arma el árbol a mano.');
        }

        return sprintf('PC-%02d', $siguiente);
    }

    /** La partida de control se llama hasta 200 caracteres; la del presupuesto, hasta 300. Se acorta con «…». */
    private static function nombre(string $descripcion): string
    {
        return mb_strlen($descripcion) <= 200 ? $descripcion : mb_substr($descripcion, 0, 199).'…';
    }

    /** @throws ArbolRechazado */
    private static function proyecto(int $proyectoId): ResumenProyecto
    {
        try {
            return app(ProyectosService::class)->resumen($proyectoId);
        } catch (ProyectoRechazado) {
            throw ArbolRechazado::conMotivo('Ese proyecto no existe.');
        }
    }

    /** La siguiente posición libre bajo un padre (o en la raíz): «PC-03», «PC-01.04». La PC-99 no cuenta. */
    public function codigoSiguiente(int $arbolId, ?int $padreId): string
    {
        $arbol = ArbolPartidas::query()->findOrFail($arbolId);
        $padre = $padreId === null ? null : PartidaControl::query()->findOrFail($padreId);
        $prefijo = $padre === null ? 'PC-' : $padre->codigo.'.';
        $mayor = PartidaControl::query()->where('proyecto_id', $arbol->proyecto_id)
            ->where('padre_id', $padre?->id)
            ->where('es_no_presupuestado', false)
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) substr($codigo, strlen($prefijo)))
            ->max() ?? 0;

        return $prefijo.str_pad((string) min($mayor + 1, 98), 2, '0', STR_PAD_LEFT);
    }
}
