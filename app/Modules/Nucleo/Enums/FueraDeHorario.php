<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Para qué situación fuera del horario de la empresa es un saludo o una frase (plan 1.4e). `Cualquiera` sirve en las
 * cuatro; las demás, también son la situación del momento (`HorarioEmpresa::fueraDeHorario`).
 */
enum FueraDeHorario: string
{
    case Temprano = 'temprano';
    case Noche = 'noche';
    case FinDeSemana = 'fin_de_semana';
    case Feriado = 'feriado';
    case Cualquiera = 'cualquiera';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Temprano => 'Temprano (antes del horario)',
            self::Noche => 'Noche (después del horario)',
            self::FinDeSemana => 'Fin de semana',
            self::Feriado => 'Feriado',
            self::Cualquiera => 'Cualquiera fuera de horario',
        };
    }
}
