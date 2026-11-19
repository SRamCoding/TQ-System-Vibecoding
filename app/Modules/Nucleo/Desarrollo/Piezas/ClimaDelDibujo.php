<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;

/**
 * El clima (plan 1.4e2, D5), a mano en el modo desarrollo: sin esperar a que llueva. Desde la 1.4u vale también para
 * los efectos de la estación en las cajas y la barra superior.
 */
final readonly class ClimaDelDibujo implements PiezaDecorativa
{
    public const string CLAVE = 'clima';

    public const array CLIMAS = ['despejado' => 'Despejado', 'nublado' => 'Nublado', 'llovizna' => 'Llovizna', 'lluvia' => 'Lluvia', 'niebla' => 'Niebla', 'tormenta' => 'Tormenta'];

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Clima';
    }

    public function opciones(): array
    {
        return ['Clima' => self::CLIMAS];
    }
}
