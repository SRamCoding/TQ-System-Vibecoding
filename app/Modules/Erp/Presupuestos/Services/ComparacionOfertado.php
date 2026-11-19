<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;

/**
 * MVP rápido (t1-puente-erp, MV-008; plan 2.12, D4): lo ofertado (la copia v1 de la cotización aceptada, que es lo
 * vendido) contra el presupuesto vigente, partida por partida, con el margen de cada uno sobre lo vendido. La usan la
 * pestaña «Ofertado vs. presupuesto» y la línea del tablero: un solo cálculo.
 */
final readonly class ComparacionOfertado
{
    /**
     * Null si el proyecto no nació de una cotización (no hay ofertado) o no tiene presupuesto vigente.
     *
     * @return array{ofertado: string, vigente: string, filas: list<array{codigo: string, descripcion: string, ofertado: string, vigente: string, diferencia: string}>, costo_ofertado: string, costo_vigente: string, diferencia: string, venta: ?string, margen_ofertado: ?string, margen_vigente: ?string}|null
     */
    public function de(int $proyectoId): ?array
    {
        $ofertado = Presupuesto::query()->where('proyecto_id', $proyectoId)->whereNotNull('origen_cotizacion_id')->where('numero_version', 1)->first();
        $vigente = Presupuesto::query()->where('proyecto_id', $proyectoId)->where('es_vigente', true)->first();

        if ($ofertado === null || $vigente === null) {
            return null;
        }

        $antes = $this->partidas($ofertado->id);
        $ahora = $this->partidas($vigente->id);
        $filas = [];

        foreach (array_unique([...array_keys($antes), ...array_keys($ahora)]) as $codigo) {
            $o = $antes[$codigo]['parcial'] ?? '0.00';
            $v = $ahora[$codigo]['parcial'] ?? '0.00';
            $filas[] = [
                'codigo' => (string) $codigo,
                'descripcion' => $ahora[$codigo]['descripcion'] ?? $antes[$codigo]['descripcion'],
                'ofertado' => $o,
                'vigente' => $v,
                'diferencia' => bcsub($v, $o, 2),
            ];
        }

        usort($filas, fn (array $a, array $b): int => strnatcmp($a['codigo'], $b['codigo']));
        $filas[] = ['codigo' => 'GG', 'descripcion' => 'Gastos generales', 'ofertado' => bcadd((string) $ofertado->gastos_generales, '0', 2), 'vigente' => bcadd((string) $vigente->gastos_generales, '0', 2), 'diferencia' => bcsub((string) $vigente->gastos_generales, (string) $ofertado->gastos_generales, 2)];

        $costoOfertado = bcadd((string) $ofertado->costo_directo, (string) $ofertado->gastos_generales, 2);
        $costoVigente = bcadd((string) $vigente->costo_directo, (string) $vigente->gastos_generales, 2);
        $venta = app(VentaDelProyecto::class)->venta($proyectoId);

        return [
            'ofertado' => "{$ofertado->numero} v{$ofertado->numero_version}",
            'vigente' => "{$vigente->numero} v{$vigente->numero_version}",
            'filas' => $filas,
            'costo_ofertado' => $costoOfertado,
            'costo_vigente' => $costoVigente,
            'diferencia' => bcsub($costoVigente, $costoOfertado, 2),
            'venta' => $venta,
            'margen_ofertado' => $venta === null ? null : bcsub($venta, $costoOfertado, 2),
            'margen_vigente' => $venta === null ? null : bcsub($venta, $costoVigente, 2),
        ];
    }

    /** @return array<string, array{descripcion: string, parcial: string}> */
    private function partidas(int $presupuestoId): array
    {
        return PartidaDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->get(['codigo', 'descripcion', 'parcial'])
            ->mapWithKeys(fn (PartidaDePresupuesto $p): array => [$p->codigo => ['descripcion' => $p->descripcion, 'parcial' => bcadd((string) $p->parcial, '0', 2)]])
            ->all();
    }
}
