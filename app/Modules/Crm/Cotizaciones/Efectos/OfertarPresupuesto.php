<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Al emitir, el presupuesto enlazado pasa a ofertado (C8, D8) a nombre de quien emite: una oferta enviada no se cambia.
 * Idempotente (la versión 2 puede usar el mismo). Si está incompleto, falla y la emisión entera se deshace.
 */
final readonly class OfertarPresupuesto implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion || $documento->presupuesto_id === null) {
            return [];
        }

        $quien = User::query()->findOrFail(app(ContextoAuditoria::class)->idActor());
        $resumen = app(PresupuestosService::class)->ofertar($documento->presupuesto_id, $quien);

        return ['presupuesto_id' => $resumen->id, 'estado' => $resumen->estado];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Ofertar no se revierte (D8): fue una oferta enviada, y la emitida no se anula.
    }
}
