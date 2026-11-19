<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * El modelo por defecto del sistema, el de los procesos sin modelo propio (plan 1.11, D5). Uno solo, activo y con
 * precio vigente.
 */
final class MarcarModeloPorDefectoIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-marcar-modelo-por-defecto';
    }

    public function nombre(): string
    {
        return 'Marcar modelo por defecto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('modelo_id', 'Modelo'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->configuracion->marcarPorDefecto($datos['modelo_id'], $datos['version']);

        return null;
    }
}
