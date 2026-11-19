<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PartiesService;

/**
 * Cada persona cambia el teléfono de su propia ficha (plan 1.4h, D2): nunca el de otra ni otro campo, por el servicio
 * de personas, con la versión de la ficha leída al abrir. Vacío lo borra. Queda en la bitácora de la ficha. Sin
 * permiso: es su propio dato.
 */
final class RegistrarMiTelefono extends Accion
{
    public function __construct(private readonly PartiesService $parties) {}

    public function clave(): string
    {
        return 'nucleo.registrar-mi-telefono';
    }

    public function nombre(): string
    {
        return 'Registrar mi teléfono';
    }

    public function campos(): array
    {
        return [
            Campo::texto('telefono', 'Teléfono', maximo: 30)->opcional()->conReglas('present'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        if ($usuario->party_id === null) {
            throw PartyRechazada::cuentaSinFicha();
        }

        $this->parties->editar($usuario->party_id, ['telefono' => $datos['telefono']], $datos['version']);

        return null;
    }
}
