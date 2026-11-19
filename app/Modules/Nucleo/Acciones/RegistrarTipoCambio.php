<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\TipoCambioDelDia;
use App\Modules\Nucleo\Services\TipoCambioService;

/**
 * Registra o corrige el tipo de cambio de un día (carga manual). La carga automática desde SUNAT,
 * cuando se valide, usará el mismo servicio con origen `sunat`.
 */
final class RegistrarTipoCambio extends Accion
{
    public function __construct(private readonly TipoCambioService $tipoCambio) {}

    public function clave(): string
    {
        return 'nucleo.registrar-tipo-cambio';
    }

    public function nombre(): string
    {
        return 'Registrar tipo de cambio';
    }

    public function campos(): array
    {
        return [
            Campo::texto('moneda', 'Moneda', maximo: 3),
            Campo::fecha('fecha', 'Fecha'),
            Campo::decimal('compra', 'Compra', escala: 4),
            Campo::decimal('venta', 'Venta', escala: 4),
        ];
    }

    public function permiso(): string
    {
        return 'tipos_cambio.registrar';
    }

    public function ejecutar(array $datos, User $usuario): TipoCambioDelDia
    {
        return $this->tipoCambio->registrar(
            mb_strtoupper($datos['moneda']),
            $datos['fecha'],
            $datos['compra'],
            $datos['venta'],
            OrigenTipoCambio::Manual,
        );
    }
}
