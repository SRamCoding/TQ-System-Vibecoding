<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Acciones;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** El encabezado y las condiciones del borrador (plan 2.22, D9, D10 y D16). */
final class EditarCotizacion extends Accion
{
    public function clave(): string
    {
        return 'crm.cotizaciones.editar-cotizacion';
    }

    public function nombre(): string
    {
        return 'Editar cotización';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cotizacion_id', 'Cotización')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::entero('contacto_id', 'Atención')->opcional(),
            Campo::elementoDe('moneda_id', 'Moneda', Catalogo::Monedas)->opcional(),
            Campo::entero('dias_validez', 'Días de validez')->conReglas('min:1', 'max:365')->opcional(),
            Campo::entero('plazo_entrega_dias', 'Plazo de entrega (días calendario)')->conReglas('min:0')->opcional(),
            Campo::elementoDe('forma_pago_id', 'Forma de pago', Catalogo::FormasPago)->opcional(),
            Campo::texto('forma_pago_detalle', 'Detalle de la forma de pago', maximo: 200)->opcional(),
            Campo::entero('garantia_meses', 'Garantía (meses)')->conReglas('min:0')->opcional(),
            Campo::texto('lugar_entrega', 'Lugar de entrega', maximo: 200)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return CotizacionesService::PERMISO_EDITAR;
    }

    public function ejecutar(array $datos, User $usuario): Cotizacion
    {
        return app(CotizacionesService::class)->editar($datos['cotizacion_id'], $datos['version'], new DatosCotizacion($datos['contacto_id'] ?? null, $datos['moneda_id'] ?? null, $datos['dias_validez'] ?? null, $datos['plazo_entrega_dias'] ?? null, $datos['forma_pago_id'] ?? null, $datos['forma_pago_detalle'] ?? null, $datos['garantia_meses'] ?? null, $datos['lugar_entrega'] ?? null), $usuario);
    }
}
