<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

/** La versión de fábrica de OportunidadEnlazable, mientras Oportunidades no registre la suya: «Oportunidad #12». */
final class OportunidadSinModulo implements OportunidadEnlazable
{
    public function etiqueta(int $oportunidadId): string
    {
        return "Oportunidad #{$oportunidadId}";
    }
}
