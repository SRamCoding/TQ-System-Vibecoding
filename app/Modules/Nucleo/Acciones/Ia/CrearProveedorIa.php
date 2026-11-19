<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Un proveedor de IA nuevo en el catálogo (plan 1.11, D5): Claude API, un modelo propio u otra cuenta. Guarda solo el
 * nombre de la variable de entorno con su clave (IA_CLAVE_…), nunca la clave.
 */
final class CrearProveedorIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-crear-proveedor';
    }

    public function nombre(): string
    {
        return 'Crear proveedor de IA';
    }

    public function campos(): array
    {
        return [
            Campo::texto('codigo', 'Código', maximo: 40)->conReglas('regex:/^[a-z][a-z0-9_]*$/'),
            Campo::texto('nombre', 'Nombre', maximo: 120),
            Campo::texto('implementacion', 'Implementación', maximo: 40),
            Campo::booleano('es_externo', 'Saca los datos de la empresa'),
            Campo::texto('variable_clave', 'Variable de la clave', maximo: 80)->opcional(),
            Campo::entero('llamadas_en_paralelo', 'Llamadas en paralelo')->conReglas('min:1', 'max:20'),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->configuracion->crearProveedor($datos['codigo'], $datos['nombre'], $datos['implementacion'], $datos['es_externo'], $datos['variable_clave'] ?? null, $datos['llamadas_en_paralelo'])->id;
    }
}
