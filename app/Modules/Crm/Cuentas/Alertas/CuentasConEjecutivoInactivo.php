<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Alertas;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Alertas\FuenteAlertas;
use App\Modules\Nucleo\Services\AlertasService;
use Carbon\CarbonImmutable;

/**
 * Cada mañana, si hay cuentas usables cuyo ejecutivo ya no está activo (plan 2.20, D7). No las reasigna: a quién pasan
 * lo decide una persona. Una por día (el núcleo la hace idempotente por el hecho).
 */
final readonly class CuentasConEjecutivoInactivo implements FuenteAlertas
{
    public function __construct(
        private CuentasService $cuentas,
        private AlertasService $alertas,
    ) {}

    public function revisar(CarbonImmutable $hoy): void
    {
        $cantidad = $this->cuentas->conEjecutivoInactivo(Cuenta::query())->count();

        if ($cantidad > 0) {
            $this->alertas->emitir(CuentasServiceProvider::ALERTA_EJECUTIVO_INACTIVO, $hoy->toDateString(), ['cantidad' => $cantidad], enlace: '/cuentas?aviso=ejecutivo-inactivo');
        }
    }
}
