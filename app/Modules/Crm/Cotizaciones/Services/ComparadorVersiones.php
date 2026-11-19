<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;

/**
 * El comparador de dos versiones de una cotización (D19): total y margen de cada una, y las líneas nuevas, quitadas y
 * cambiadas. Una línea es «la misma» si tiene el mismo producto del catálogo o, si es libre, la misma descripción (sin
 * mayúsculas ni espacios de más). Devuelve también costo y margen: quien muestra decide según los permisos (D15).
 */
final readonly class ComparadorVersiones
{
    /**
     * @return array{
     *     antes: array{id: int, version: int, total: string, margen: ?string},
     *     despues: array{id: int, version: int, total: string, margen: ?string},
     *     nuevas: list<array{descripcion: string, cantidad: string, subtotal: string, margen: ?string}>,
     *     quitadas: list<array{descripcion: string, cantidad: string, subtotal: string, margen: ?string}>,
     *     cambiadas: list<array{descripcion: string, cambios: list<array{campo: string, antes: string, despues: string, sensible: bool}>}>
     * }
     */
    public function comparar(int $antesId, int $despuesId): array
    {
        $antes = Cotizacion::query()->with('lineas')->find($antesId) ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');
        $despues = Cotizacion::query()->with('lineas')->find($despuesId) ?? throw CotizacionRechazada::conMotivo('Esa cotización no existe.');

        if ($antes->numero !== $despues->numero) {
            throw CotizacionRechazada::conMotivo('Solo se comparan versiones de la misma cotización.');
        }

        if ($antes->numero_version > $despues->numero_version) {
            [$antes, $despues] = [$despues, $antes];
        }

        $porClave = fn (Cotizacion $c): array => $c->lineas->sortBy('orden')
            ->mapWithKeys(fn (LineaDeCotizacion $l): array => [self::clave($l) => $l])->all();
        $lineasAntes = $porClave($antes);
        $lineasDespues = $porClave($despues);
        $fila = fn (LineaDeCotizacion $l): array => [
            'descripcion' => $l->descripcion, 'cantidad' => (string) $l->cantidad, 'subtotal' => (string) $l->subtotal, 'margen' => $l->margen_porcentaje,
        ];
        $cambiadas = [];

        foreach ($lineasDespues as $clave => $nueva) {
            $vieja = $lineasAntes[$clave] ?? null;

            if ($vieja === null) {
                continue;
            }

            $cambios = [];

            foreach (['cantidad' => ['Cantidad', false], 'precio_unitario' => ['P.U.', false], 'descuento_porcentaje' => ['Descuento (%)', false], 'subtotal' => ['Subtotal', false], 'costo_unitario' => ['Costo unitario', true], 'margen_porcentaje' => ['Margen (%)', true]] as $campo => [$etiqueta, $sensible]) {
                $a = (string) $vieja->getAttribute($campo);
                $b = (string) $nueva->getAttribute($campo);

                if ($a !== $b) {
                    $cambios[] = ['campo' => $etiqueta, 'antes' => $a === '' ? '—' : $a, 'despues' => $b === '' ? '—' : $b, 'sensible' => $sensible];
                }
            }

            if ($cambios !== []) {
                $cambiadas[] = ['descripcion' => $nueva->descripcion, 'cambios' => $cambios];
            }
        }

        return [
            'antes' => ['id' => $antes->id, 'version' => $antes->numero_version, 'total' => (string) $antes->total, 'margen' => $antes->margen_porcentaje],
            'despues' => ['id' => $despues->id, 'version' => $despues->numero_version, 'total' => (string) $despues->total, 'margen' => $despues->margen_porcentaje],
            'nuevas' => array_values(array_map($fila, array_diff_key($lineasDespues, $lineasAntes))),
            'quitadas' => array_values(array_map($fila, array_diff_key($lineasAntes, $lineasDespues))),
            'cambiadas' => $cambiadas,
        ];
    }

    private static function clave(LineaDeCotizacion $linea): string
    {
        return $linea->producto_id !== null
            ? "p{$linea->producto_id}"
            : 'd'.mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($linea->descripcion)));
    }
}
