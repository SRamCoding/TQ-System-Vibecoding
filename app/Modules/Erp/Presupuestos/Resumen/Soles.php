<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

/** «S/ 19,525.00» desde un decimal en texto, sin pasar por `float`. */
final class Soles
{
    public static function texto(string $monto): string
    {
        $monto = bcadd($monto, '0', 2);
        $negativo = str_starts_with($monto, '-');
        [$entero, $decimales] = explode('.', ltrim($monto, '-'));
        $miles = strrev(implode(',', str_split(strrev($entero), 3)));

        return ($negativo ? '−' : '').'S/ '.$miles.'.'.$decimales;
    }
}
