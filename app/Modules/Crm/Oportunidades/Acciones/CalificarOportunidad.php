<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

final class CalificarOportunidad extends AvanzarOportunidad
{
    protected function transicion(): string
    {
        return 'calificar';
    }

    public function nombre(): string
    {
        return 'Calificar oportunidad';
    }
}
