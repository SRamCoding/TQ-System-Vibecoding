<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Nivel de una unidad del organigrama (`unidades_organizativas.tipo`, con su CHECK en la base).
 */
enum TipoUnidadOrganizativa: string
{
    case Gerencia = 'gerencia';
    case Jefatura = 'jefatura';
    case Coordinacion = 'coordinacion';
    case Area = 'area';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Gerencia => 'Gerencia',
            self::Jefatura => 'Jefatura',
            self::Coordinacion => 'Coordinación',
            self::Area => 'Área',
        };
    }
}
