<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;

/** La temperatura del clima (plan 1.4u, D5), a mano en el modo desarrollo: sin esperar a que haga calor o frío. */
final readonly class TemperaturaDelClima implements PiezaDecorativa
{
    public const string CLAVE = 'temperatura';

    public const array TEMPERATURAS = ['calor' => 'Calor', 'templado' => 'Templado', 'frio' => 'Frío'];

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Temperatura';
    }

    public function opciones(): array
    {
        return ['Temperatura' => self::TEMPERATURAS];
    }
}
