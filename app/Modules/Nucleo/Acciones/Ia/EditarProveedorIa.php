<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Los datos de un proveedor de IA (plan 1.11, D5). Cambiar su implementación o su variable de clave lo vuelve otro
 * servicio u otra cuenta: cae la autorización de los procesos que lo usan.
 */
final class EditarProveedorIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-editar-proveedor';
    }

    public function nombre(): string
    {
        return 'Editar proveedor de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proveedor_id', 'Proveedor'),
            Campo::texto('nombre', 'Nombre', maximo: 120),
            Campo::texto('implementacion', 'Implementación', maximo: 40),
            Campo::booleano('es_externo', 'Saca los datos de la empresa'),
            Campo::texto('variable_clave', 'Variable de la clave', maximo: 80)->opcional(),
            Campo::entero('llamadas_en_paralelo', 'Llamadas en paralelo')->conReglas('min:1', 'max:20'),
            Campo::booleano('es_activo', 'Activo'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->configuracion->editarProveedor($datos['proveedor_id'], $datos['nombre'], $datos['implementacion'], $datos['es_externo'], $datos['variable_clave'] ?? null, $datos['llamadas_en_paralelo'], $datos['version'], $datos['es_activo']);

        return null;
    }
}
