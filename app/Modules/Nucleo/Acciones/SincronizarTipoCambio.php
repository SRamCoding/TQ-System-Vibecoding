<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Enums\ResultadoSincronizacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\TipoCambioService;

/**
 * Trae ahora el tipo de cambio de SUNAT, sin esperar al programador (ADR 0012). No pisa un valor
 * registrado a mano.
 */
final class SincronizarTipoCambio extends Accion
{
    public function __construct(private readonly TipoCambioService $tipoCambio) {}

    public function clave(): string
    {
        return 'nucleo.sincronizar-tipo-cambio';
    }

    public function nombre(): string
    {
        return 'Traer de SUNAT ahora';
    }

    public function campos(): array
    {
        return [];
    }

    public function permiso(): string
    {
        return 'tipos_cambio.registrar';
    }

    public function ejecutar(array $datos, User $usuario): ResultadoSincronizacion
    {
        return $this->tipoCambio->sincronizar();
    }
}
