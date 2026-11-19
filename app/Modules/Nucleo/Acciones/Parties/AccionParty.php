<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\PartiesService;

/**
 * Base de las acciones sobre parties y sus roles (plan 1.3, D6, D7 y D12). Clave `nucleo.parties.<operacion>`.
 * Las reglas están en PartiesService; aquí solo los campos y el permiso.
 */
abstract class AccionParty extends Accion
{
    public function __construct(
        protected readonly PartiesService $parties,
        protected readonly CatalogosService $catalogos,
    ) {}

    /** Segmento final de la clave: «registrar», «editar»… */
    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.parties.'.$this->operacion();
    }

    /** La party sobre la que se actúa: la pantalla la fija desde el registro. */
    protected function campoParty(): Campo
    {
        return Campo::entero('party_id', 'Persona o empresa')->conReglas('exists:parties,id');
    }

    /** La versión leída al abrir (bloqueo optimista). */
    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }
}
