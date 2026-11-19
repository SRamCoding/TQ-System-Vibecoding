<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\UbigeosService;
use App\Modules\Nucleo\Ubigeos\DiferenciaUbigeos;

/**
 * Trae la lista vigente de distritos del INEI y la aplica a la base: crea, renombra, adopta los agregados
 * a mano que ya publica y desactiva los suyos que ya no están (nunca borra ni reactiva; plan 1.2b, E7).
 * La descarga ocurre antes y fuera de la transacción.
 */
final class ActualizarUbigeos extends Accion
{
    public function __construct(private readonly UbigeosService $ubigeos) {}

    public function clave(): string
    {
        return 'nucleo.actualizar-ubigeos';
    }

    public function nombre(): string
    {
        return 'Actualizar ubigeos del INEI';
    }

    public function campos(): array
    {
        return [];
    }

    public function permiso(): string
    {
        return 'ubigeos.actualizar';
    }

    public function ejecutar(array $datos, User $usuario): DiferenciaUbigeos
    {
        return $this->ubigeos->aplicar($this->ubigeos->descargar());
    }
}
