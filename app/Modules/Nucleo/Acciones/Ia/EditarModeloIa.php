<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Nombre, tokens de salida y si un modelo de IA está activo (plan 1.11, D5). El modelo por defecto no se desactiva.
 */
final class EditarModeloIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-editar-modelo';
    }

    public function nombre(): string
    {
        return 'Editar modelo de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('modelo_id', 'Modelo'),
            Campo::texto('nombre', 'Nombre', maximo: 120),
            Campo::entero('max_tokens_salida', 'Tokens de salida como máximo')->conReglas('min:1', 'max:200000'),
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
        $this->configuracion->editarModelo($datos['modelo_id'], $datos['nombre'], $datos['max_tokens_salida'], $datos['es_activo'], $datos['version']);

        return null;
    }
}
