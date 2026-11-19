<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Nucleo\Models\Imputacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * La consulta de los gastos no presupuestados (plan 2.6, D7): los de la PC-99, por proyecto y motivo, y los posibles
 * adicionales. Cuenta solo lo aprobado (comprometido mayor que cero), en soles con el tipo de cambio de cada línea y el
 * mismo redondeo que el saldo de una partida (`ROUND` de la suma en la base). La usarán la pantalla de gastos no
 * presupuestados y el margen: por eso vive aquí y no en una pantalla.
 */
final readonly class GastosNoPresupuestadosService
{
    /**
     * @param  ?int  $proyectoId  null: todos los proyectos
     * @return list<GastoPorMotivo> por proyecto y, dentro, por nombre del motivo
     */
    public function porMotivo(?int $proyectoId = null): array
    {
        return $this->aprobados($proyectoId)
            ->join('motivos', 'motivos.id', '=', 'imputaciones.motivo_id')
            ->groupBy('imputaciones.proyecto_id', 'motivos.id', 'motivos.nombre', 'motivos.es_cambio_cliente')
            ->orderBy('imputaciones.proyecto_id')
            ->orderBy('motivos.nombre')
            ->selectRaw('imputaciones.proyecto_id, motivos.id AS motivo_id, motivos.nombre, motivos.es_cambio_cliente, COUNT(*) AS cantidad, ROUND(SUM(imputaciones.monto_comprometido * imputaciones.tipo_cambio), 2) AS total')
            ->toBase()
            ->get()
            ->map(fn (object $fila): GastoPorMotivo => new GastoPorMotivo(
                (int) $fila->proyecto_id,
                (int) $fila->motivo_id,
                (string) $fila->nombre,
                (bool) $fila->es_cambio_cliente,
                (int) $fila->cantidad,
                bcadd((string) $fila->total, '0', 2),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  ?int  $proyectoId  null: todos los proyectos
     * @return list<PosibleAdicional> por fecha y orden de registro
     */
    public function posiblesAdicionales(?int $proyectoId = null): array
    {
        return $this->aprobados($proyectoId)
            ->join('motivos', 'motivos.id', '=', 'imputaciones.motivo_id')
            ->where('motivos.es_cambio_cliente', true)
            ->orderBy('imputaciones.fecha')
            ->orderBy('imputaciones.id')
            ->selectRaw('imputaciones.id, imputaciones.proyecto_id, imputaciones.origen_type, imputaciones.origen_id, motivos.nombre AS motivo, imputaciones.comentario, imputaciones.fecha, ROUND(imputaciones.monto_comprometido * imputaciones.tipo_cambio, 2) AS soles')
            ->toBase()
            ->get()
            ->map(fn (object $fila): PosibleAdicional => new PosibleAdicional(
                (int) $fila->id,
                (int) $fila->proyecto_id,
                (string) $fila->origen_type,
                (int) $fila->origen_id,
                (string) $fila->motivo,
                (string) $fila->comentario,
                CarbonImmutable::parse((string) $fila->fecha),
                bcadd((string) $fila->soles, '0', 2),
            ))
            ->values()
            ->all();
    }

    /** @return Builder<Imputacion> las líneas aprobadas de las PC-99 */
    private function aprobados(?int $proyectoId): Builder
    {
        return Imputacion::query()
            ->whereIn('imputaciones.partida_control_id', PartidaControl::query()->where('es_no_presupuestado', true)->select('id'))
            ->where('imputaciones.monto_comprometido', '>', 0)
            ->when($proyectoId !== null, fn ($query) => $query->where('imputaciones.proyecto_id', $proyectoId));
    }
}
