<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Quién firma un paso de una regla de aprobación (plan 1.7, D1): siempre una jefatura, con su CHECK en la base. */
enum FirmanteAprobacion: string
{
    /** La jefatura de una unidad elegida en la regla («Gerencia General»). */
    case UnidadFija = 'unidad_fija';

    /** La jefatura de la unidad del documento («su jefatura»). */
    case UnidadDelDocumento = 'unidad_del_documento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UnidadFija => 'La jefatura de una unidad',
            self::UnidadDelDocumento => 'La jefatura del documento',
        };
    }
}
