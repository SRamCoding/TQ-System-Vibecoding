<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * El dinero de un proyecto para su Resumen y su tarjeta de la portada (plan 2.61, D10 y D11), desde los saldos de C1.
 * Montos en soles, texto decimal con dos decimales. **Lleva costos**: solo se pide para quien tiene `campos.ver-costos`.
 *
 * - **Proyección al cierre (D11 y D13):** por partida, lo mayor entre presupuestado y comprometido; más lo que falta
 *   repartir del costo presupuestado (si falta, D13) y lo cargado a la PC-99.
 * - **Semáforo (D11):** ámbar si la proyección pasa lo presupuestado en más de `semaforo_ambar` puntos; rojo si pasa
 *   más de `semaforo_rojo`. Hoy compara costo; con la venta del contrato (D10), el margen.
 */
final readonly class DineroDelProyecto
{
    public const string PARAMETRO_AMBAR = 'presupuestos.semaforo_ambar_puntos';

    public const string PARAMETRO_ROJO = 'presupuestos.semaforo_rojo_puntos';

    public function __construct(
        private ConsultaArbol $arboles,
        private PartidasService $partidas,
        private ParametrosService $parametros,
        private VentaDelProyecto $ventas,
    ) {}

    /**
     * Null si el proyecto no tiene un árbol aprobado (D14: el mismo del que sale el saldo de cada partida).
     *
     * @return array{presupuestado: string, comprometido: string, ejecutado: string, pagado: string, no_presupuestado: string, por_repartir: string, proyeccion: string, desvio: string, tono: ?string, venta: ?string, margen_previsto: ?string, margen_proyectado: ?string, partidas: list<array{id: int, codigo: string, nombre: string, saldo: SaldoPartida, es_no_presupuestado: bool}>}|null
     */
    public function de(int $proyectoId): ?array
    {
        $arbol = $this->arboles->arbolAprobadoDe($proyectoId);

        if ($arbol === null) {
            return null;
        }

        $sumas = ['comprometido' => '0.00', 'ejecutado' => '0.00', 'pagado' => '0.00'];
        $noPresupuestado = '0.00';
        $proyeccion = '0.00';
        $partidas = [];

        foreach ($this->arboles->filas($arbol->id) as $fila) {
            if (! $fila->esHoja || (! $fila->esActiva && ! $fila->esNoPresupuestado)) {
                continue;
            }

            $saldo = $this->partidas->saldo($fila->id);
            $partidas[] = ['id' => $fila->id, 'codigo' => $fila->codigo, 'nombre' => $fila->nombre, 'saldo' => $saldo, 'es_no_presupuestado' => $fila->esNoPresupuestado];

            foreach ($sumas as $campo => $suma) {
                $sumas[$campo] = bcadd($suma, $saldo->{$campo}, 2);
            }

            if ($fila->esNoPresupuestado) {
                $noPresupuestado = bcadd($noPresupuestado, $saldo->comprometido, 2);
                $proyeccion = bcadd($proyeccion, $saldo->comprometido, 2);
            } else {
                $proyeccion = bcadd($proyeccion, bccomp($saldo->comprometido, $saldo->presupuestado, 2) > 0 ? $saldo->comprometido : $saldo->presupuestado, 2);
            }
        }

        $presupuestado = bcadd($arbol->costo_presupuestado, '0', 2);
        $porRepartir = $this->arboles->porRepartir($arbol->id);

        if (bccomp($porRepartir, '0', 2) > 0) {
            $proyeccion = bcadd($proyeccion, $porRepartir, 2);
        }

        $desvio = bccomp($presupuestado, '0', 2) > 0 ? bcmul(bcdiv(bcsub($proyeccion, $presupuestado, 4), $presupuestado, 6), '100', 2) : '0.00';
        $venta = $this->ventas->venta($proyectoId);

        return [
            'presupuestado' => $presupuestado,
            ...$sumas,
            'no_presupuestado' => $noPresupuestado,
            'por_repartir' => $porRepartir,
            'proyeccion' => $proyeccion,
            'desvio' => $desvio,
            'tono' => $this->tono($desvio),
            'venta' => $venta,
            'margen_previsto' => $venta === null ? null : bcsub($venta, $presupuestado, 2),
            'margen_proyectado' => $venta === null ? null : bcsub($venta, $proyeccion, 2),
            'partidas' => $partidas,
        ];
    }

    /** `danger`, `warning` o `success` según cuántos puntos pasa la proyección a lo presupuestado. */
    public function tono(string $desvio): string
    {
        return match (true) {
            bccomp($desvio, $this->parametros->decimal(self::PARAMETRO_ROJO), 2) > 0 => 'danger',
            bccomp($desvio, $this->parametros->decimal(self::PARAMETRO_AMBAR), 2) > 0 => 'warning',
            default => 'success',
        };
    }
}
