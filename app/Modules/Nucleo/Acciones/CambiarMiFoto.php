<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\FotoPerfilService;

/**
 * Cada persona sube o cambia su propia foto de perfil (plan 1.4o, D4): nunca la de otra. Sin permiso: es su propio
 * dato. Las reglas están en FotoPerfilService.
 */
final class CambiarMiFoto extends Accion
{
    public function __construct(private readonly FotoPerfilService $fotos) {}

    public function clave(): string
    {
        return 'nucleo.cambiar-mi-foto';
    }

    public function nombre(): string
    {
        return 'Cambiar mi foto';
    }

    public function campos(): array
    {
        return [Campo::archivo('foto', 'Foto')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->fotos->cambiar($usuario, $datos['foto']);

        return null;
    }
}
