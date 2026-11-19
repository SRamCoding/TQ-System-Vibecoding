<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\OrdenDeSala;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Lo que leen el tablero de gemas, la fila de cifras y la línea de tiempo de Oportunidades (plan 2.21c, D1, D2 y D9).
 * Reúsa las lecturas de la faja (`OportunidadesService`: filas, T.C. de compra de hoy, salas del período) y las de la
 * portada (`PortadaOportunidades`: probabilidad, máximos y atascadas), sin reglas propias. Sin anuladas en ningún lado.
 * Todos ven todas, como la faja (D10 de la 2.21 limita editar, no ver).
 */
final readonly class TableroDeGemas
{
    /** Las que se ven por columna: un cuadrado de 2×2 (D2). */
    public const int VISIBLES = 4;

    public function __construct(
        private OportunidadesService $oportunidades,
        private PortadaOportunidades $portada,
    ) {}

    /**
     * Las seis columnas, en el orden de los lugares: las cuatro etapas abiertas y, al final, Ganadas y Perdidas del
     * período de la columna «Cierre» (`crm.tablero_cierre_periodo`), cada sala como la ve la persona (D15; sin vista,
     * las más recientes de todos).
     *
     * @param  array<string, VistaDeSala>  $vistas  por valor de LugarFaja (solo salas; `vistasDeSalas`)
     * @return array<string, ColumnaTablero> por valor de LugarFaja
     */
    public function columnas(array $vistas = []): array
    {
        $columnas = [];

        foreach (LugarFaja::cases() as $lugar) {
            $columnas[$lugar->value] = $this->columna($lugar, $vistas[$lugar->value] ?? null);
        }

        return $columnas;
    }

    /** Una columna; en una sala, con su orden y su «Mías» (D15). Una etapa abierta no tiene vista. */
    public function columna(LugarFaja $lugar, ?VistaDeSala $vista = null): ColumnaTablero
    {
        if ($vista !== null && ! $lugar->esSala()) {
            throw new InvalidArgumentException("{$lugar->etiqueta()} no es Ganadas ni Perdidas: no tiene vista propia.");
        }

        $vista ??= new VistaDeSala;
        $total = $this->oportunidades->totalDeLugarDeLaFaja($lugar, $vista->soloDe);
        $etapa = $lugar->etapa();
        $probabilidad = $etapa === null ? null : $this->portada->probabilidad($etapa);

        return new ColumnaTablero(
            lugar: $lugar,
            visibles: $this->oportunidades->destacadasDeLaFaja($lugar, self::VISIBLES, $vista->orden, $vista->soloDe),
            cantidad: $total->cantidad,
            totalEnSoles: $total->totalEnSoles,
            sinTipoCambio: $total->sinTipoCambio,
            probabilidad: $probabilidad,
            ponderadoEnSoles: $probabilidad === null ? null : bcdiv(bcmul($total->totalEnSoles, (string) $probabilidad, 4), '100', 2),
            vista: $lugar->esSala() ? $vista : null,
        );
    }

    /**
     * Cómo ve una persona Ganadas o Perdidas (D15): lo que eligió la última vez (su preferencia, en cualquier equipo) o,
     * si nunca eligió, las más recientes de todos.
     */
    public function vistaDeSala(User $quien, LugarFaja $sala): VistaDeSala
    {
        $preferencias = app(PreferenciasUsuarioService::class);
        $orden = OrdenDeSala::from($preferencias->opcion($quien, self::claveDeOrden($sala)));

        return new VistaDeSala($orden, $preferencias->siNo($quien, self::claveDeMias($sala)) ? $quien->id : null);
    }

    /**
     * Las dos, para `columnas`.
     *
     * @return array<string, VistaDeSala> por valor de LugarFaja
     */
    public function vistasDeSalas(User $quien): array
    {
        return [
            LugarFaja::Ganadas->value => $this->vistaDeSala($quien, LugarFaja::Ganadas),
            LugarFaja::Perdidas->value => $this->vistaDeSala($quien, LugarFaja::Perdidas),
        ];
    }

    /** La preferencia con el orden de una sala (`navegacion.oportunidades_ganadas_orden`…). */
    public static function claveDeOrden(LugarFaja $sala): string
    {
        return GrupoPreferencia::Navegacion->value.'.'.self::nombreDePreferencia($sala, 'orden');
    }

    /** La preferencia con el «Mías» de una sala. */
    public static function claveDeMias(LugarFaja $sala): string
    {
        return GrupoPreferencia::Navegacion->value.'.'.self::nombreDePreferencia($sala, 'mias');
    }

    /**
     * Las preferencias que declara el módulo para recordar la vista de cada sala por persona (D15): en el registro del
     * núcleo, en Navegación y sin mostrarse en «Mis preferencias» (se eligen en el tablero; «Restablecer» las vuelve a
     * las más recientes de todos). Se cambian con la acción general `nucleo.cambiar-preferencia`.
     *
     * @return list<DefinicionPreferencia>
     */
    public static function preferencias(): array
    {
        $definiciones = [];

        foreach ([LugarFaja::Ganadas, LugarFaja::Perdidas] as $sala) {
            $opciones = [];

            foreach (OrdenDeSala::de($sala) as $orden) {
                $opciones[$orden->value] = $orden->etiqueta();
            }

            $definiciones[] = DefinicionPreferencia::opcion(GrupoPreferencia::Navegacion, self::nombreDePreferencia($sala, 'orden'),
                "Orden de {$sala->etiqueta()} en el tablero", 'Cuáles cuatro se ven en el tablero de oportunidades.', OrdenDeSala::Recientes->value, $opciones)->sinMostrar();
            $definiciones[] = DefinicionPreferencia::siNo(GrupoPreferencia::Navegacion, self::nombreDePreferencia($sala, 'mias'),
                "Solo mis {$sala->etiqueta()} en el tablero", 'Solo las oportunidades de las que eres el ejecutivo.', false)->sinMostrar();
        }

        return $definiciones;
    }

    private static function nombreDePreferencia(LugarFaja $sala, string $que): string
    {
        if (! $sala->esSala()) {
            throw new InvalidArgumentException("{$sala->etiqueta()} no es Ganadas ni Perdidas.");
        }

        return "oportunidades_{$sala->value}_{$que}";
    }

    /** La fila de cifras (D1): embudo abierto y ponderado, cierran este mes, atascadas y cierre estimado vencido. */
    public function cifras(): CifrasTablero
    {
        $tarjetas = $this->portada->tarjetas();

        return new CifrasTablero(
            embudo: $tarjetas['embudo'],
            esteMes: $tarjetas['esteMes'],
            atascadas: $this->oportunidades->embudo($this->portada->estancadas(Oportunidad::query())),
            vencidas: $this->oportunidades->embudo(Oportunidad::query()->where('oportunidades.fecha_cierre_estimada', '<', Calendario::hoy())),
        );
    }

    /**
     * Las gemas de la línea de tiempo (D9): las abiertas por fecha de cierre estimada, con su marca de vencida.
     *
     * @return list<MarcaLineaDeTiempo>
     */
    public function lineaDeTiempo(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return array_map(
            fn (FilaFaja $fila): MarcaLineaDeTiempo => new MarcaLineaDeTiempo($fila, $fila->cierreEstimado->lessThan($hoy)),
            $this->oportunidades->abiertasPorCierreEstimado(),
        );
    }
}
