<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use Illuminate\Support\Str;

/**
 * Títulos y migas de pan de un Resource como se escribe en español: mayúscula solo al inicio («Personas y
 * empresas», no «Personas Y Empresas», que es lo que hace Filament con `Str::ucwords`). Todo Resource la usa
 * (docs/convenciones/interfaz.md, «Mayúsculas»; lo revisa una prueba).
 */
trait EtiquetasEnEspanol
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
