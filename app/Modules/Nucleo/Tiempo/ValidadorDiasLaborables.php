<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** Los días que trabaja la empresa, del 1 (lunes) al 7 (domingo), separados por comas (plan 1.4e). */
final readonly class ValidadorDiasLaborables implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        // Solo quita espacios, repetidos y ordena: lo que no es un día del 1 al 7 lo rechaza `rechazo`, no se convierte.
        $dias = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $valor)), fn (string $d): bool => $d !== '')));
        sort($dias, SORT_STRING);

        return implode(',', $dias);
    }

    public function rechazo(mixed $valor): ?string
    {
        $partes = array_map('trim', explode(',', (string) $valor));

        foreach ($partes as $parte) {
            if (preg_match('/^[1-7]$/', $parte) !== 1) {
                return 'deben ser números del 1 (lunes) al 7 (domingo), separados por comas: por ejemplo, 1,2,3,4,5.';
            }
        }

        return null;
    }
}
