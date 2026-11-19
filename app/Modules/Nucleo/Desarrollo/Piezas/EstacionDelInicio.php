<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;
use App\Modules\Nucleo\Enums\Temporada;

/** La estación de las cajas del inicio (plan 1.4r, D6), a mano en el modo desarrollo: las cuatro sin cambiar la fecha. */
final readonly class EstacionDelInicio implements PiezaDecorativa
{
    public const string CLAVE = 'estacion-inicio';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Estación de las cajas';
    }

    public function opciones(): array
    {
        $estaciones = [];

        foreach ([Temporada::Primavera, Temporada::Verano, Temporada::Otono, Temporada::Invierno] as $estacion) {
            $estaciones[$estacion->value] = $estacion->etiqueta();
        }

        return ['Estación' => $estaciones];
    }
}
