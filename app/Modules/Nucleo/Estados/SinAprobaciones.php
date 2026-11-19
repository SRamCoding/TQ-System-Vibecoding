<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Sin aprobaciones configuradas: ninguna transición que las requiere se puede ejecutar. Era la implementación de la
 * 1.5; desde la 1.7 la reemplaza `ReglasDeAprobacion`. Queda para pruebas que no quieren rondas.
 */
final readonly class SinAprobaciones implements AprobacionDeTransicion
{
    public function pendiente(Model $documento, TransicionEstado $transicion): string
    {
        return 'Este paso necesita aprobaciones, que todavía no están configuradas.';
    }

    public function alCambiarEstado(Model&DocumentoConEstados $documento, ?TransicionEstado $hecha, User $quien): void {}
}
