<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;

final class QuitarCompetidorPropio extends QuitarCompetidor
{
    public function clave(): string
    {
        return 'crm.oportunidades.quitar-competidor-propias';
    }

    public function nombre(): string
    {
        return 'Quitar competidor de una oportunidad que atiendo';
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR_PROPIAS;
    }
}
