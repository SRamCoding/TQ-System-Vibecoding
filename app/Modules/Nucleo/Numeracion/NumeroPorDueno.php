<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Numeracion;

/**
 * MVP rápido (t1-puente-erp, MV-007; ADR 0023): numeración por dueño. El contador vive en el ámbito del dueño
 * («OP2026.0007», sin guiones) con dos dígitos y sin periodo; el número se guarda y se muestra como
 * «OP-2026-0007 · COT-01». Lo usan cotizaciones y presupuestos.
 */
final class NumeroPorDueno
{
    public static function formato(string $serie): FormatoNumeracion
    {
        return new FormatoNumeracion($serie, ReinicioNumeracion::Nunca, 2);
    }

    /** «OP-2026-0007» → «OP2026.0007». */
    public static function ambito(string $codigoDueno): string
    {
        $partes = explode('-', $codigoDueno);

        return count($partes) === 3 ? "{$partes[0]}{$partes[1]}.{$partes[2]}" : str_replace('-', '', $codigoDueno);
    }

    /** «COT-OP2026.0007-01» con dueño «OP-2026-0007» → «OP-2026-0007 · COT-01». */
    public static function visible(string $numero, string $codigoDueno, string $serie): string
    {
        $correlativo = substr($numero, strrpos($numero, '-') + 1);

        return "{$codigoDueno} · {$serie}-{$correlativo}";
    }

    /** «OP-2026-0007 · COT-01» → «COT-01» (dentro de la ficha de su dueño). */
    public static function corto(string $numero): string
    {
        $posicion = mb_strpos($numero, ' · ');

        return $posicion === false ? $numero : mb_substr($numero, $posicion + 3);
    }
}
