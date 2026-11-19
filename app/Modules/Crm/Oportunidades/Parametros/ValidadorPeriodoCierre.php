<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Parametros;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Qué cerradas muestra la columna «Cierre» del tablero (plan 2.21, D11.3): las del mes, el trimestre o el año en curso. */
final readonly class ValidadorPeriodoCierre implements ValidadorParametro
{
    public const array PERIODOS = ['mes', 'trimestre', 'anio'];

    public function normalizar(mixed $valor): string
    {
        return mb_strtolower(trim((string) $valor));
    }

    public function rechazo(mixed $valor): ?string
    {
        return in_array($valor, self::PERIODOS, true) ? null : 'debe ser «mes», «trimestre» o «anio».';
    }
}
