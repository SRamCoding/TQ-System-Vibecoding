<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Models\ModuloOculto;

/**
 * Los módulos que cada persona sacó de su barra, leídos una sola vez por pedido (plan 1.12, prueba de carga: se leían
 * cinco veces). Vive lo que dura el pedido (`scoped`) y no guarda nada más que eso; NavegacionService lo olvida al
 * guardar.
 */
final class OcultosDelPedido
{
    /** @var array<int, list<string>> */
    private array $guardados = [];

    /** @return list<string> */
    public function de(int $usuarioId): array
    {
        return $this->guardados[$usuarioId] ??= array_values(ModuloOculto::query()->where('user_id', $usuarioId)->pluck('clave_modulo')->all());
    }

    public function olvidar(int $usuarioId): void
    {
        unset($this->guardados[$usuarioId]);
    }
}
