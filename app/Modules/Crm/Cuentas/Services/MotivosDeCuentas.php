<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Estados\FuenteDeMotivos;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Services\MotivosService;
use Carbon\CarbonImmutable;

/**
 * Los «Otro» de desactivar cuentas (plan 2.20, D9) para «Lo más escrito en Otro» de Configuración (plan 1.5, D10): las
 * cuentas hoy inactivas con «Otro», por la fecha en que se desactivaron. Al reactivar, el motivo sale de la cuenta y
 * queda solo en la bitácora.
 */
final class MotivosDeCuentas implements FuenteDeMotivos
{
    public function detallesDeOtro(TipoMotivo $familia, ?CarbonImmutable $desde): array
    {
        if ($familia->codigo !== CuentasServiceProvider::FAMILIA_DESACTIVAR) {
            return [];
        }

        $clave = MotivosService::normalizacionSql('motivo_inactiva_detalle');

        return Cuenta::query()
            ->where('es_activa', false)
            ->whereIn('motivo_inactiva_id', Motivo::query()->select('id')->where('tipo_motivo_id', $familia->id)->where('es_otro', true))
            ->when($desde !== null, fn ($consulta) => $consulta->where('updated_at', '>=', $desde))
            ->selectRaw("{$clave} AS clave, count(*) AS veces, max(coalesce(updated_at, created_at)) AS ultimo_uso, (array_agg(btrim(motivo_inactiva_detalle) ORDER BY coalesce(updated_at, created_at) DESC, id DESC))[1] AS texto")
            ->groupByRaw($clave)
            ->toBase()
            ->get()
            ->map(fn (object $fila): DetalleDeOtro => new DetalleDeOtro($fila->clave, $fila->texto, (int) $fila->veces, CarbonImmutable::parse($fila->ultimo_uso)))
            ->all();
    }
}
