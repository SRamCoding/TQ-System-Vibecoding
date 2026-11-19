<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * D14 y escenario del v4: bajo el margen mínimo «no se puede enviar al cliente sin aprobación registrada». Desde
 * borrador se emite solo con el margen en el mínimo o más; desde aprobada, con la firma ya registrada.
 */
final readonly class MargenPermiteEmitir implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        if ($documento->estado->codigo === DefinicionCotizacion::APROBADA || ! MargenPideAprobacion::bajoElMinimo($documento)) {
            return null;
        }

        return 'El margen ('.MargenPideAprobacion::texto($documento->margen_porcentaje).') está bajo el mínimo ('
            .MargenPideAprobacion::texto(MargenPideAprobacion::minimo($documento)).'): solicita la aprobación antes de emitirla.';
    }
}
