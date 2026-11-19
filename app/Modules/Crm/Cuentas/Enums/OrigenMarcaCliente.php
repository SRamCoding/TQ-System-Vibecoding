<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Enums;

/** Por qué una cuenta es cliente (plan 2.20, D8): una oportunidad ganada o la marca a mano de Gerencia Comercial. */
enum OrigenMarcaCliente: string
{
    case Oportunidad = 'oportunidad';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Oportunidad => 'Oportunidad ganada',
            self::Manual => 'Marcada a mano',
        };
    }
}
