<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

final class EnviarPropuestaOportunidad extends AvanzarOportunidad
{
    protected function transicion(): string
    {
        return 'enviar_propuesta';
    }

    public function nombre(): string
    {
        return 'Enviar propuesta';
    }
}
