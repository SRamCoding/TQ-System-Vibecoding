<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Abre un periodo del rol desde una fecha, sin fin. No se cruza con otro del mismo rol (D2). */
final class AsignarRolParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'asignar-rol';
    }

    public function nombre(): string
    {
        return 'Asignar rol';
    }

    public function campos(): array
    {
        return [
            $this->campoParty(),
            Campo::elementoDe('rol_party_id', 'Rol', Catalogo::RolesParty),
            Campo::fecha('desde', 'Desde'),
        ];
    }

    public function permiso(): string
    {
        return 'parties.asignar-roles';
    }

    public function ejecutar(array $datos, User $usuario): int
    {
        return $this->parties->asignarRol(
            $datos['party_id'],
            $this->catalogos->codigoDe(Catalogo::RolesParty, $datos['rol_party_id']),
            $datos['desde'],
        );
    }
}
