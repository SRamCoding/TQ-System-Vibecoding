<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Condiciones;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use Illuminate\Database\Eloquent\Model;

/**
 * D14: se pide aprobación solo con el margen bajo el mínimo (si no, se emite directo). El mínimo es el que quedó en la
 * versión al pedirla o emitirla; mientras no se fija, el del parámetro. Sin margen (subtotal cero) cuenta como bajo.
 */
final readonly class MargenPideAprobacion implements CondicionTransicion
{
    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Cotizacion $documento */
        if (self::bajoElMinimo($documento)) {
            return null;
        }

        return 'El margen ('.self::texto($documento->margen_porcentaje).') no está bajo el mínimo ('.self::texto(self::minimo($documento)).'): emítela sin aprobación.';
    }

    public static function bajoElMinimo(Cotizacion $cotizacion): bool
    {
        return $cotizacion->margen_porcentaje === null || bccomp((string) $cotizacion->margen_porcentaje, self::minimo($cotizacion), 2) < 0;
    }

    public static function minimo(Cotizacion $cotizacion): string
    {
        return (string) ($cotizacion->margen_minimo ?? app(ParametrosService::class)->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO));
    }

    /** «19.4 %», con un decimal, como en pantalla (D14). */
    public static function texto(?string $porcentaje): string
    {
        return $porcentaje === null ? 'sin margen' : number_format((float) $porcentaje, 1, '.', ' ').' %';
    }
}
