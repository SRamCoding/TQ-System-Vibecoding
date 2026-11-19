<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Enums;

/** El documento con que el cliente acepta una cotización (plan 2.22, D5; desarrollador, P2). */
enum TipoRespaldo: string
{
    case OrdenCompra = 'orden_compra';
    case OrdenServicio = 'orden_servicio';
    case BuenaPro = 'buena_pro';
    case Contrato = 'contrato';
    case Correo = 'correo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::OrdenCompra => 'Orden de compra',
            self::OrdenServicio => 'Orden de servicio',
            self::BuenaPro => 'Buena pro',
            self::Contrato => 'Contrato',
            self::Correo => 'Correo',
        };
    }
}
