<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\FotoPerfilService;

/** Cada persona quita su propia foto de perfil (plan 1.4o, D4): el menú vuelve a mostrar sus iniciales. */
final class QuitarMiFoto extends Accion
{
    public function __construct(private readonly FotoPerfilService $fotos) {}

    public function clave(): string
    {
        return 'nucleo.quitar-mi-foto';
    }

    public function nombre(): string
    {
        return 'Quitar mi foto';
    }

    public function campos(): array
    {
        return [];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->quitar($usuario);

        return null;
    }
}
