<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Enums;

/**
 * Por qué no se le cuelga trabajo nuevo a una cuenta (plan 2.20, D9 y D12): la cuenta y su ficha son dos
 * interruptores. Si las dos están apagadas, manda la cuenta.
 */
enum MotivoNoUsable: string
{
    case CuentaInactiva = 'cuenta_inactiva';
    case FichaInactiva = 'ficha_inactiva';

    /** La marca corta de la lista y de los selectores. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::CuentaInactiva => 'Inactiva',
            self::FichaInactiva => 'Ficha inactiva',
        };
    }

    /** Lo que se le dice a quien quiso usarla. */
    public function mensaje(): string
    {
        return match ($this) {
            self::CuentaInactiva => 'La cuenta está inactiva: reactívala antes de usarla.',
            self::FichaInactiva => 'La ficha de la empresa está inactiva en Personas y empresas: reactívala primero.',
        };
    }
}
