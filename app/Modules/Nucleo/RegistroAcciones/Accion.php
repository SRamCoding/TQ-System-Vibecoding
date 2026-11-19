<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

use App\Modules\Nucleo\Models\User;

/**
 * Una acción de usuario registrada («Registrar recepción», «Aprobar orden»): ARCHITECTURE.md,
 * «Registro de acciones». La ejecuta EjecutorAcciones, que antes revisa usuario, permiso y campos;
 * ejecutar() solo llama al servicio del módulo. Sin reglas de negocio aquí.
 */
abstract class Accion
{
    /** Identificador estable: «<modulo>.<accion>», por ejemplo «compras.aprobar-orden». */
    abstract public function clave(): string;

    /** Nombre visible en español. */
    abstract public function nombre(): string;

    /**
     * @return list<Campo>
     */
    public function campos(): array
    {
        return [];
    }

    /** Permiso de spatie que exige, o null si la puede ejecutar cualquier persona con sesión. */
    public function permiso(): ?string
    {
        return null;
    }

    /**
     * Verdadero si el permiso puede estar en un ámbito (una unidad, un proyecto) y lo revisa el servicio con los
     * ámbitos del registro, como las transiciones de estado (plan 1.5, D6 y D9). El ejecutor entonces no lo exige
     * global; sigue revisando que la persona esté activa y no sea el sistema.
     */
    public function permisoPorAmbito(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $datos  ya validados y convertidos al tipo de cada campo
     */
    abstract public function ejecutar(array $datos, User $usuario): mixed;
}
