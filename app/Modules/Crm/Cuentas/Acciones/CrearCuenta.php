<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Acciones;

use App\Modules\Crm\Cuentas\Services\CuentaCreada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Crea una cuenta desde el RUC (plan 2.20, D2), con la ficha si no existe. Incluye asignar a la ficha los tipos
 * «Cliente» y, si es pública, «Entidad pública» (D3), sin el permiso de editar tipos. La pantalla en dos pasos solo la
 * llama; el primer paso (`CuentasService::revisarRuc`) no escribe nada.
 */
final class CrearCuenta extends Accion
{
    public function clave(): string
    {
        return 'crm.cuentas.crear';
    }

    public function nombre(): string
    {
        return 'Crear cuenta';
    }

    public function campos(): array
    {
        return [
            Campo::texto('ruc', 'RUC', maximo: 20),
            Campo::booleano('es_publica', 'Pública'),
            Campo::elementoDe('nivel_gobierno_id', 'Nivel de gobierno', Catalogo::NivelesGobierno)->opcional(),
            Campo::entero('ejecutivo_user_id', 'Ejecutivo')->conReglas('min:1'),
            Campo::elementoDe('origen_comercial_id', 'Origen', Catalogo::OrigenesComerciales, detalle: 'origen_detalle'),
            Campo::texto('origen_detalle', '¿Cuál es el origen?', maximo: 150)->opcional(),
            Campo::texto('notas', 'Notas', maximo: 2000)->opcional(),
            // Solo si la ficha no existe (caso 4).
            Campo::texto('razon_social', 'Razón social', maximo: 200)->opcional(),
            Campo::texto('nombres', 'Nombres', maximo: 100)->opcional(),
            Campo::texto('apellidos', 'Apellidos', maximo: 100)->opcional(),
            Campo::texto('nombre_comercial', 'Nombre comercial', maximo: 200)->opcional(),
            Campo::elementoDe('rubro_id', 'Rubro', Catalogo::Rubros)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'crm.cuentas.crear';
    }

    public function ejecutar(array $datos, User $usuario): CuentaCreada
    {
        return app(CuentasService::class)->crear(new DatosCuenta(
            ruc: $datos['ruc'],
            ejecutivoUserId: $datos['ejecutivo_user_id'],
            origenComercialId: $datos['origen_comercial_id'],
            esPublica: $datos['es_publica'],
            nivelGobiernoId: $datos['nivel_gobierno_id'] ?? null,
            notas: $datos['notas'] ?? null,
            razonSocial: $datos['razon_social'] ?? null,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            nombreComercial: $datos['nombre_comercial'] ?? null,
            rubroId: $datos['rubro_id'] ?? null,
            origenDetalle: $datos['origen_detalle'] ?? null,
        ));
    }
}
