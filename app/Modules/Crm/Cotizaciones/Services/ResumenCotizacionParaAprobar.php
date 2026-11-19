<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Services;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Aprobaciones\DatoParaAprobar;
use App\Modules\Nucleo\Aprobaciones\ResumenParaAprobar;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Seguridad\GrupoSensible;

/**
 * La cotización en «Mis aprobaciones» (D19, P6): cliente, oportunidad y total (la versión ya va en el nombre), y el margen con los puntos contra
 * el mínimo, que son del grupo sensible «Márgenes» (D15): el núcleo los quita para quien no tiene el permiso.
 */
final readonly class ResumenCotizacionParaAprobar implements ResumenParaAprobar
{
    public function datos(int $documentoId): array
    {
        $c = Cotizacion::query()->findOrFail($documentoId);
        $moneda = (string) Moneda::query()->whereKey($c->moneda_id)->value('codigo');
        $datos = [
            new DatoParaAprobar('Cliente', app(CuentasService::class)->resumen($c->cuenta_id)->etiqueta),
            new DatoParaAprobar('Oportunidad', app(OportunidadesService::class)->resumen($c->oportunidad_id)->etiqueta()),
            new DatoParaAprobar('Total', "{$moneda} ".number_format((float) $c->total, 2)),
        ];

        if ($c->margen_porcentaje !== null) {
            $datos[] = new DatoParaAprobar('Margen', "{$c->margen_porcentaje} %", GrupoSensible::Margen);
        }

        if ($c->margen_porcentaje !== null && $c->margen_minimo !== null) {
            $puntos = bcsub($c->margen_porcentaje, $c->margen_minimo, 2);
            $datos[] = new DatoParaAprobar('Contra el mínimo', "{$puntos} puntos (mínimo {$c->margen_minimo} %)", GrupoSensible::Margen);
        }

        return $datos;
    }

    /** La ficha llega con las pantallas del paso 9. */
    public function enlace(int $documentoId): ?string
    {
        return null;
    }
}
