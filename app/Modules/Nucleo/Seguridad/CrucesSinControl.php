<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use App\Modules\Nucleo\Alertas\FuenteAlertas;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;

/**
 * La parte de seguridad de la revisión diaria (plan 1.10, D3 y D4; plan 1.4, D9): primero cierra los cruces de roles
 * acotados vencidos y después, si quedan cruces sin control, manda un solo resumen del día a quienes reciben la alerta.
 */
final readonly class CrucesSinControl implements FuenteAlertas
{
    public const string ALERTA = 'nucleo.cruces-sin-control';

    public function __construct(
        private SeguridadService $seguridad,
        private AlertasService $alertas,
    ) {}

    public function revisar(CarbonImmutable $hoy): void
    {
        $this->seguridad->cerrarCrucesVencidos();
        $pendientes = $this->seguridad->crucesPendientes()->count();

        if ($pendientes > 0) {
            // Sin enlace todavía: la vista de cruces llega con la 1.4c, que lo agrega aquí.
            $this->alertas->emitir(self::ALERTA, $hoy->toDateString(), ['cantidad' => $pendientes]);
        }
    }
}
