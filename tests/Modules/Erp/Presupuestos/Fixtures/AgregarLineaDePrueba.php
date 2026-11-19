<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La acción de agregar una línea, con la elección de imputación entre sus campos (plan 2.2, D2 y D8). */
final class AgregarLineaDePrueba extends Accion
{
    public function clave(): string
    {
        return 'erp.presupuestos.prueba.agregar-linea';
    }

    public function nombre(): string
    {
        return 'Agregar línea de prueba';
    }

    public function campos(): array
    {
        return [
            Campo::entero('orden_id', 'Orden')->conReglas('min:1'),
            Campo::texto('descripcion', 'Descripción', maximo: 60),
            Campo::decimal('monto', 'Monto'),
            Campo::texto('moneda', 'Moneda', maximo: 3),
            Campo::fecha('fecha', 'Fecha'),
            ...EleccionImputacion::campos(),
        ];
    }

    public function permiso(): string
    {
        return 'erp.presupuestos.prueba.agregar-linea';
    }

    public function ejecutar(array $datos, User $usuario): LineaOrdenDePrueba
    {
        return (new ServicioOrdenDePrueba)->agregarLinea($datos['orden_id'], $datos['descripcion'], $datos['monto'], $datos['moneda'], $datos['fecha'], EleccionImputacion::desdeDatos($datos));
    }
}
