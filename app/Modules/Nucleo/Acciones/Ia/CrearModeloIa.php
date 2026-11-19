<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Un modelo nuevo de un proveedor de IA (plan 1.11, D5). Los nombres de los modelos son datos, no código. Se asigna a
 * un proceso recién cuando tiene precio vigente.
 */
final class CrearModeloIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-crear-modelo';
    }

    public function nombre(): string
    {
        return 'Crear modelo de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proveedor_id', 'Proveedor'),
            Campo::texto('codigo', 'Código del modelo', maximo: 100),
            Campo::texto('nombre', 'Nombre', maximo: 120),
            Campo::entero('max_tokens_salida', 'Tokens de salida como máximo')->conReglas('min:1', 'max:200000'),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->configuracion->crearModelo($datos['proveedor_id'], $datos['codigo'], $datos['nombre'], $datos['max_tokens_salida'])->id;
    }
}
