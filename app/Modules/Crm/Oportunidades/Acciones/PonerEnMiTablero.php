<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\TableroDeBotonera;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Hasta 5 por etapa (plan 2.21d, D5); solo para quien la marca. */
final class PonerEnMiTablero extends Accion
{
    public function __construct(private readonly TableroDeBotonera $tablero) {}

    public function clave(): string
    {
        return TableroDeBotonera::FIJAR;
    }

    public function nombre(): string
    {
        return 'Poner una oportunidad en mi tablero';
    }

    public function permiso(): string
    {
        return OportunidadesServiceProvider::PERMISO_VER;
    }

    public function campos(): array
    {
        return [Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->tablero->poner((int) $datos['oportunidad_id'], $usuario);

        return null;
    }
}
