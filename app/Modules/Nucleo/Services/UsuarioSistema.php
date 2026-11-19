<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Models\User;
use RuntimeException;

/**
 * El usuario a nombre del cual actúan seeders, jobs y comandos (created_by, updated_by).
 */
final class UsuarioSistema
{
    private ?int $id = null;

    public function id(): int
    {
        if ($this->id === null) {
            $id = User::query()->where('es_sistema', true)->value('id');

            if ($id === null) {
                throw new RuntimeException('No existe el usuario de sistema: ejecuta las migraciones del núcleo.');
            }

            $this->id = (int) $id;
        }

        return $this->id;
    }
}
