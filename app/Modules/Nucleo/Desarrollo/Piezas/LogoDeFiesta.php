<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Saludo\LogoTechquk;

/** El logo de la barra superior: el de siempre o el de cada fecha con logo propio. Valor: la ocasión o `normal`. */
final readonly class LogoDeFiesta implements PiezaDecorativa
{
    public const string CLAVE = 'logo';

    public const string NORMAL = 'normal';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Logo';
    }

    public function opciones(): array
    {
        $fechas = [];

        foreach (Ocasion::cases() as $ocasion) {
            if (LogoTechquk::tieneLogoPropio($ocasion)) {
                $fechas[$ocasion->value] = $ocasion->etiqueta();
            }
        }

        return ['De siempre' => [self::NORMAL => 'El de siempre'], 'Fechas con logo propio' => $fechas];
    }
}
