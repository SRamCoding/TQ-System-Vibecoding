<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * El rubro de la empresa, una sección de la CIIU (plan 2.20a, N4). Acción propia y no parte de «editar», que reemplaza
 * todos los datos generales: así quien atiende una cuenta del CRM corrige el rubro sin poder cambiar la razón social.
 */
final class CambiarRubroParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'cambiar-rubro';
    }

    public function nombre(): string
    {
        return 'Cambiar el rubro';
    }

    public function campos(): array
    {
        return [
            $this->campoParty(),
            Campo::elementoDe('rubro_id', 'Rubro (sección CIIU)', Catalogo::Rubros)->opcional(),
            $this->campoVersion(),
        ];
    }

    public function permiso(): string
    {
        return 'parties.cambiar-rubro';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parties->cambiarRubro($datos['party_id'], $datos['rubro_id'] ?? null, $datos['version']);

        return null;
    }
}
