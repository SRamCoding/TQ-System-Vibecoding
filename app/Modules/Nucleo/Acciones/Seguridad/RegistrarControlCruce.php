<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Registra o renueva el control compensatorio de un cruce de funciones abierto. Devuelve su id. */
final class RegistrarControlCruce extends AccionSeguridad
{
    protected function operacion(): string
    {
        return 'registrar-control-cruce';
    }

    public function nombre(): string
    {
        return 'Registrar control de un cruce';
    }

    public function permiso(): string
    {
        return 'roles.gestionar';
    }

    public function campos(): array
    {
        return [Campo::entero('cruce_id', 'Cruce')->conReglas('exists:cruces_funciones,id'), ...self::camposDeControl(obligatorio: true)];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->seguridad->registrarControl($datos['cruce_id'], self::datosControl($datos) ?? throw SeguridadRechazada::controlIncompleto(), $usuario);
    }
}
