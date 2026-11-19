<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/** Contra qué nombre de la ficha coincidió un nombre escrito (plan 2.20a, N1). */
enum CampoNombre: string
{
    case RazonSocial = 'razon_social';
    case NombreComercial = 'nombre_comercial';

    /** Para el aviso: «mismo nombre», «razón social parecida», «mismo nombre comercial», «nombre comercial parecido». */
    public function aviso(bool $mismoNombre): string
    {
        return match ($this) {
            self::RazonSocial => $mismoNombre ? 'mismo nombre' : 'razón social parecida',
            self::NombreComercial => $mismoNombre ? 'mismo nombre comercial' : 'nombre comercial parecido',
        };
    }
}
