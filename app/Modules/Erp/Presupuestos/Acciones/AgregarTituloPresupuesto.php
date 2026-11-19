<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Acciones;

use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Agrega un título al presupuesto, en la raíz o bajo otro título (plan 2.9). */
final class AgregarTituloPresupuesto extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.agregar-titulo-presupuesto';
    }

    public function nombre(): string
    {
        return 'Agregar título al presupuesto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('presupuesto_id', 'Presupuesto')->conReglas('min:1'),
            Campo::entero('padre_id', 'Título padre')->conReglas('min:1')->opcional(),
            Campo::texto('codigo', 'Código', maximo: 30),
            Campo::texto('nombre', 'Nombre', maximo: 200),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.presupuesto.editar';
    }

    public function ejecutar(array $datos, User $usuario): TituloDePresupuesto
    {
        return app(ArmadoPresupuestoService::class)->agregarTitulo($datos['presupuesto_id'], $datos['padre_id'] ?? null, $datos['codigo'], $datos['nombre']);
    }
}
