<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Acciones;

use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\ResumenProyecto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Crea un proyecto interno (plan 2.1, D4): sin contrato, con Techquk como cliente, «en preparación». Los que nacen de
 * un contrato los crea Contratos con `ProyectosService::crearDesdeContrato`.
 */
final class CrearProyectoInterno extends Accion
{
    public function clave(): string
    {
        return 'erp.proyectos.crear-interno';
    }

    public function nombre(): string
    {
        return 'Crear proyecto interno';
    }

    public function campos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::entero('jefe_proyecto_user_id', 'Jefe de proyecto')->conReglas('min:1'),
            Campo::fecha('fecha_inicio', 'Inicio'),
            Campo::fecha('fecha_fin_prevista', 'Fin previsto'),
        ];
    }

    public function permiso(): string
    {
        return 'erp.proyectos.crear';
    }

    public function ejecutar(array $datos, User $usuario): ResumenProyecto
    {
        return app(ProyectosService::class)->crear(new DatosProyecto(
            nombre: $datos['nombre'],
            jefeProyectoUserId: $datos['jefe_proyecto_user_id'],
            fechaInicio: $datos['fecha_inicio'],
            fechaFinPrevista: $datos['fecha_fin_prevista'],
        ), $usuario);
    }
}
