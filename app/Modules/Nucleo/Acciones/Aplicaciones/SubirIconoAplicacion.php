<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IconosAplicacionService;

/** Administración sube el ícono de una aplicación de Techquk (plan 1.4z, D9): PNG o WEBP, hasta 1 MB. */
final class SubirIconoAplicacion extends Accion
{
    public function __construct(private readonly IconosAplicacionService $iconos) {}

    public function clave(): string
    {
        return 'nucleo.aplicaciones.subir-icono';
    }

    public function nombre(): string
    {
        return 'Subir ícono';
    }

    public function campos(): array
    {
        return [
            Campo::elementoDe('aplicacion_id', 'Aplicación', Catalogo::Aplicaciones),
            Campo::archivo('archivo', 'Ícono (PNG o WEBP, hasta 1 MB)'),
        ];
    }

    public function permiso(): string
    {
        return 'catalogos.editar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->iconos->subir($datos['aplicacion_id'], $datos['archivo'], $usuario)->id;
    }
}
