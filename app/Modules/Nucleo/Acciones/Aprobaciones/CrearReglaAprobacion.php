<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Crea una regla de aprobación: qué jefaturas firman, en orden, un paso de un documento en un rango de montos (plan 1.7). */
final class CrearReglaAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.crear-regla';
    }

    public function nombre(): string
    {
        return 'Crear una regla de aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 150),
            Campo::texto('documento_type', 'Tipo de documento', maximo: 120),
            Campo::texto('transicion', 'Paso que se aprueba', maximo: 40),
            Campo::decimal('monto_mas_de', 'Más de (S/)')->opcional(),
            Campo::decimal('monto_hasta', 'Hasta (S/)')->opcional(),
            Campo::decimal('tope_autoaprobacion', 'Tope de autoaprobación (S/)')->opcional(),
            // Plan 2.2, D5: además del monto, cuándo entra la regla.
            Campo::opcion('condicion', 'Cuándo entra', CondicionRegla::class)->opcional(),
            // Cada paso: `documento` (la jefatura del documento) o `unidad:<id>` (la jefatura de esa unidad), en orden.
            Campo::listaDeTextos('pasos', 'Firmas en orden', maximo: 30)->conReglas('min:1'),
        ];
    }

    public function permiso(): string
    {
        return 'aprobaciones.configurar';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function datos(array $datos): DatosRegla
    {
        $monto = fn (mixed $valor): ?string => $valor === null ? null : (string) $valor;

        return new DatosRegla($datos['nombre'], $datos['documento_type'], $datos['transicion'], $monto($datos['monto_mas_de'] ?? null), $monto($datos['monto_hasta'] ?? null), $monto($datos['tope_autoaprobacion'] ?? null), array_values($datos['pasos']), $datos['condicion'] ?? CondicionRegla::Ninguna);
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AprobacionesService::class)->crearRegla(self::datos($datos));
    }
}
