<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\ResumenCuenta;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Edita origen, nivel de gobierno y notas de cualquier cuenta (plan 2.20, D7, Gerencia Comercial). La de las propias
 * es EditarCuentaPropia: el servicio revisa en los dos casos con `puedeSobrePropio`.
 */
class EditarCuenta extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.editar';
    }

    public function nombre(): string
    {
        return 'Editar cuenta';
    }

    public function campos(): array
    {
        return [
            Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::elementoDe('origen_comercial_id', 'Origen', Catalogo::OrigenesComerciales, detalle: 'origen_detalle'),
            Campo::texto('origen_detalle', '¿Cuál es el origen?', maximo: 150)->opcional(),
            Campo::elementoDe('nivel_gobierno_id', 'Nivel de gobierno', Catalogo::NivelesGobierno)->opcional(),
            Campo::texto('notas', 'Notas', maximo: 2000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return CuentasService::PERMISO_EDITAR;
    }

    final public function ejecutar(array $datos, User $usuario): ResumenCuenta
    {
        return app(CuentasService::class)->editar($datos['cuenta_id'], $datos['version'], $datos['origen_comercial_id'], $datos['nivel_gobierno_id'] ?? null, $datos['notas'] ?? null, $usuario, $datos['origen_detalle'] ?? null);
    }
}
