<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

/** «¿Lo ganó otra empresa?» al perder (plan 2.21, D4). Se guarda para que los reportes no lo deduzcan. */
enum GanadorConocido: string
{
    case Si = 'si';
    case No = 'no';
    case NoSeSabe = 'no_se_sabe';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Si => 'Sí',
            self::No => 'No',
            self::NoSeSabe => 'No se sabe',
        };
    }
}
