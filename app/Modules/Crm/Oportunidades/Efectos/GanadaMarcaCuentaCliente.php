<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Efectos;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Estados\Efecto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ganar pasa la cuenta a cliente con la fecha de cierre real (plan 2.21, D3; plan 2.20, D8.3). Revertir quita esa marca:
 * la cuenta queda cliente por otra marca o vuelve a prospecto. Perder no toca la marca.
 */
final readonly class GanadaMarcaCuentaCliente implements Efecto
{
    public function aplicar(Model $documento): array
    {
        $cuentaId = (int) $documento->getAttribute('cuenta_id');
        $fecha = CarbonImmutable::parse($documento->getAttribute('fecha_cierre_real'));
        app(CuentasService::class)->marcarCliente($cuentaId, $fecha, (int) $documento->getKey());

        return ['cuenta_id' => $cuentaId, 'oportunidad_id' => (int) $documento->getKey()];
    }

    public function revertir(Model $documento, array $datos): void
    {
        app(CuentasService::class)->revertirCliente((int) $datos['cuenta_id'], (int) $datos['oportunidad_id']);
    }
}
