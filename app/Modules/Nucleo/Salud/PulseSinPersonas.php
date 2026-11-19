<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\ResolvesUsers;

/**
 * Pulse nunca sabe quién hizo un pedido (plan 1.12, D6): sin id no guarda a la persona en los pedidos lentos, y no hay
 * nombre, correo ni avatar de Gravatar que buscar. Las tarjetas de uso por persona están apagadas.
 */
final class PulseSinPersonas implements ResolvesUsers
{
    public function key(Authenticatable $user): int|string|null
    {
        return null;
    }

    public function load(Collection $keys): self
    {
        return $this;
    }

    public function find(int|string|null $key): object
    {
        return (object) ['name' => 'Persona'];
    }
}
