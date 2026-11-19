<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;

final class EditarOportunidadPropia extends EditarOportunidad
{
    public function clave(): string
    {
        return 'crm.oportunidades.editar-propias';
    }

    public function nombre(): string
    {
        return 'Editar una oportunidad que atiendo';
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR_PROPIAS;
    }
}
