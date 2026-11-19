<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\Catalogos\FuenteDeOtros;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Services\MotivosService;
use Carbon\CarbonImmutable;

/** Lo escrito en el origen «Otro» de las cuentas, para «Lo más escrito en Otro» (plan 2.0, D3 y D5). */
final class OtrosDeOrigenDeCuentas implements FuenteDeOtros
{
    public function detallesDeOtro(Catalogo $catalogo, ?CarbonImmutable $desde): array
    {
        if ($catalogo !== Catalogo::OrigenesComerciales) {
            return [];
        }

        $clave = MotivosService::normalizacionSql('origen_detalle');

        return Cuenta::query()
            ->whereNotNull('origen_detalle')
            ->when($desde !== null, fn ($consulta) => $consulta->where('updated_at', '>=', $desde))
            ->selectRaw("{$clave} AS clave, count(*) AS veces, max(coalesce(updated_at, created_at)) AS ultimo_uso, (array_agg(btrim(origen_detalle) ORDER BY coalesce(updated_at, created_at) DESC, id DESC))[1] AS texto")
            ->groupByRaw($clave)
            ->toBase()
            ->get()
            ->map(fn (object $fila): DetalleDeOtro => new DetalleDeOtro($fila->clave, $fila->texto, (int) $fila->veces, CarbonImmutable::parse($fila->ultimo_uso)))
            ->all();
    }
}
