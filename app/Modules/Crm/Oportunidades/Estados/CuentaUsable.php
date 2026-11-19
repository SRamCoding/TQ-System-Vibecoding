<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Estados;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ganar exige la cuenta usable (plan 2.21, D6): no se entrega al ERP un cliente inactivo. Solo lee: el núcleo también
 * evalúa las condiciones para mostrar las salidas disponibles, fuera de una transacción. El bloqueo exclusivo de la
 * cuenta lo toma `OportunidadesService::ganar`, después de bloquear la oportunidad (orden de la C3).
 */
final readonly class CuentaUsable implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $cuenta = app(CuentasService::class)->resumen((int) $documento->getAttribute('cuenta_id'));

        return $cuenta->usable ? null : $cuenta->motivoNoUsable?->mensaje();
    }
}
