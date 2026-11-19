<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Una cotización sin líneas no se emite ni pide aprobación. */
final readonly class TieneLineas implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        return $documento->lineas()->exists() ? null : 'La cotización no tiene líneas.';
    }
}
