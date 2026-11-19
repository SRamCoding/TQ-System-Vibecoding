<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;

final class GuardarCompetidorPropio extends GuardarCompetidor
{
    public function clave(): string
    {
        return 'crm.oportunidades.guardar-competidor-propias';
    }

    public function nombre(): string
    {
        return 'Guardar competidor de una oportunidad que atiendo';
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR_PROPIAS;
    }
}
