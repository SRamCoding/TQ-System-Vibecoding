<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Los campos de crear y editar una oportunidad, en un solo lugar (plan 2.21, D7: catálogos con la pieza común). */
final class CamposOportunidad
{
    /** @return list<Campo> */
    public static function datos(bool $conCuenta): array
    {
        return [
            ...($conCuenta ? [Campo::entero('cuenta_id', 'Cuenta')->conReglas('min:1')] : []),
            Campo::texto('nombre', 'Nombre', maximo: 200),
            Campo::texto('descripcion', 'Descripción', maximo: 2000)->opcional(),
            Campo::decimal('monto_estimado', 'Monto estimado'),
            Campo::elementoDe('moneda_id', 'Moneda', Catalogo::Monedas),
            Campo::fecha('fecha_cierre_estimada', 'Cierre estimado'),
            Campo::elementoDe('linea_solucion_id', 'Línea de solución', Catalogo::LineasSolucion),
            Campo::elementoDe('origen_comercial_id', 'Origen', Catalogo::OrigenesComerciales, detalle: 'origen_detalle')->opcional(),
            Campo::texto('origen_detalle', '¿Cuál es el origen?', maximo: 150)->opcional(),
            ...($conCuenta ? [Campo::entero('ejecutivo_user_id', 'Ejecutivo')->opcional()] : []),
            Campo::booleano('necesidad_confirmada', 'Necesidad confirmada')->opcional(),
            Campo::booleano('presupuesto_confirmado', 'Presupuesto confirmado')->opcional(),
            Campo::entero('decisor_contacto_id', 'Decisor')->opcional(),
        ];
    }

    /** @param  array<string, mixed>  $datos */
    public static function aDatos(array $datos): DatosOportunidad
    {
        return new DatosOportunidad(
            $datos['nombre'],
            $datos['descripcion'] ?? null,
            $datos['monto_estimado'],
            $datos['moneda_id'],
            $datos['fecha_cierre_estimada'],
            $datos['linea_solucion_id'],
            $datos['origen_comercial_id'] ?? null,
            $datos['ejecutivo_user_id'] ?? null,
            (bool) ($datos['necesidad_confirmada'] ?? false),
            (bool) ($datos['presupuesto_confirmado'] ?? false),
            $datos['decisor_contacto_id'] ?? null,
            $datos['origen_detalle'] ?? null,
        );
    }
}
