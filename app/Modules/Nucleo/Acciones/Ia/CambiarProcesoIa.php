<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * El administrador elige los modelos de un proceso de IA (vacío: el por defecto) y lo enciende o apaga (plan 1.11,
 * D5). Un proceso que envía datos fuera no se enciende sin la autorización de Gerencia.
 */
final class CambiarProcesoIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-cambiar-proceso';
    }

    public function nombre(): string
    {
        return 'Cambiar proceso de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proceso_id', 'Proceso'),
            Campo::booleano('esta_activo', 'Encendido'),
            Campo::entero('modelo_id', 'Modelo')->opcional(),
            Campo::entero('modelo_respaldo_id', 'Modelo de respaldo')->opcional(),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->configuracion->cambiarProceso($datos['proceso_id'], $datos['esta_activo'], $datos['modelo_id'] ?? null, $datos['modelo_respaldo_id'] ?? null, $datos['version']);

        return null;
    }
}
