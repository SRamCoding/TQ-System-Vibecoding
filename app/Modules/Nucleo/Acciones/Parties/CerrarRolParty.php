<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Cierra el periodo abierto del rol: `hasta` es su último día vigente (D8). Mismo permiso que asignar. */
final class CerrarRolParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'cerrar-rol';
    }

    public function nombre(): string
    {
        return 'Cerrar rol';
    }

    public function campos(): array
    {
        return [
            $this->campoParty(),
            Campo::elementoDe('rol_party_id', 'Rol', Catalogo::RolesParty),
            Campo::fecha('hasta', 'Último día vigente'),
            $this->campoVersion(),
        ];
    }

    public function permiso(): string
    {
        return 'parties.asignar-roles';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parties->cerrarRol(
            $datos['party_id'],
            $this->catalogos->codigoDe(Catalogo::RolesParty, $datos['rol_party_id']),
            $datos['hasta'],
            $datos['version'],
        );

        return null;
    }
}
