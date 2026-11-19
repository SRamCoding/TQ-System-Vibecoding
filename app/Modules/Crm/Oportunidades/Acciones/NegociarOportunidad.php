<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

final class NegociarOportunidad extends AvanzarOportunidad
{
    protected function transicion(): string
    {
        return 'negociar';
    }

    public function nombre(): string
    {
        return 'Pasar a negociación';
    }
}
