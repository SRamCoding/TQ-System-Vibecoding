<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\Insumo;

/**
 * La lista de insumos de un presupuesto con APU (plan 2.9, D5 y D10): cada insumo con su cantidad y su monto; aparte,
 * lo que está sin detallar (solo monto) y el ajuste por redondeo con las partidas que lo generan. Insumos + sin
 * detallar + ajuste = costo directo, siempre.
 */
final readonly class ListaDeInsumos
{
    /** Como en S10: mano de obra, materiales, equipos y subcontratos. */
    public const array ORDEN_DE_TIPOS = [Insumo::MANO_OBRA, Insumo::MATERIAL, Insumo::EQUIPO, Insumo::SUBCONTRATO];

    /**
     * @param  list<InsumoEnLista>  $insumos  por tipo (ORDEN_DE_TIPOS) y código
     * @param  list<AjusteDePartida>  $ajustes  solo las partidas con diferencia
     */
    public function __construct(
        public array $insumos,
        public string $sinDetallar,
        public string $ajuste,
        public array $ajustes,
        public string $total,
    ) {}

    /**
     * @param  list<PartidaParaLista>  $partidas
     */
    public static function desde(array $partidas): self
    {
        /** @var array<int, array{linea: LineaParaLista, cantidad: string, monto: string}> $porInsumo */
        $porInsumo = [];
        $sinDetallar = '0.00';
        $ajuste = '0.00';
        $ajustes = [];
        $total = '0.00';

        foreach ($partidas as $partida) {
            $aportes = '0.00';

            foreach ($partida->lineas as $linea) {
                $aporte = CalculoPresupuesto::aporte($partida->metrado, $linea->parcialApu);
                $aportes = bcadd($aportes, $aporte, 2);

                if ($linea->esSinDetallar) {
                    $sinDetallar = bcadd($sinDetallar, $aporte, 2);

                    continue;
                }

                $acumulado = $porInsumo[$linea->insumoId] ?? ['linea' => $linea, 'cantidad' => '0', 'monto' => '0.00'];
                $acumulado['cantidad'] = bcadd($acumulado['cantidad'], CalculoPresupuesto::cantidadEnPartida($partida->metrado, $linea->cantidad), 5);
                $acumulado['monto'] = bcadd($acumulado['monto'], $aporte, 2);
                $porInsumo[$linea->insumoId] = $acumulado;
            }

            $diferencia = bcsub($partida->parcial, $aportes, 2);
            $total = bcadd($total, $partida->parcial, 2);

            if (bccomp($diferencia, '0', 2) !== 0) {
                $ajuste = bcadd($ajuste, $diferencia, 2);
                $ajustes[] = new AjusteDePartida($partida->codigo, $partida->descripcion, $partida->metrado, $partida->unidad, $diferencia);
            }
        }

        $insumos = array_map(fn (array $a): InsumoEnLista => new InsumoEnLista(
            $a['linea']->insumoId,
            $a['linea']->codigo,
            $a['linea']->nombre,
            $a['linea']->tipo,
            $a['linea']->unidad,
            $a['cantidad'],
            $a['monto'],
        ), array_values($porInsumo));
        $orden = array_flip(self::ORDEN_DE_TIPOS);
        usort($insumos, fn (InsumoEnLista $a, InsumoEnLista $b): int => [$orden[$a->tipo] ?? 9, $a->codigo] <=> [$orden[$b->tipo] ?? 9, $b->codigo]);

        return new self($insumos, $sinDetallar, $ajuste, $ajustes, $total);
    }
}
