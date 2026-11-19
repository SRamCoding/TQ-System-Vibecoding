<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * Días de versículo por semana, de 0 a 5: se eligen de lunes a viernes (`interfaz.saludo_dias_versiculo`,
 * regla 6 de `SaludoPorReglas`).
 */
final readonly class ValidadorDiasVersiculo implements ValidadorParametro
{
    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 0 && $valor <= 5 ? null : 'debe estar entre 0 y 5 (se eligen de lunes a viernes).';
    }
}
