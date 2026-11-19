<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aprobaciones;

use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AprobacionesService;

/** Cambia una regla de aprobación y sus firmas; lo ya pedido sigue con las firmas de su momento (plan 1.7, D5). */
final class EditarReglaAprobacion extends Accion
{
    public function clave(): string
    {
        return 'nucleo.aprobaciones.editar-regla';
    }

    public function nombre(): string
    {
        return 'Editar una regla de aprobación';
    }

    public function campos(): array
    {
        return [
            Campo::entero('regla_id', 'Regla')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
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
        return app(AprobacionesService::class)->editarRegla($datos['regla_id'], self::datos($datos), $datos['version']);
    }
}
