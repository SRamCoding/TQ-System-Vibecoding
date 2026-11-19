<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\TransicionesService;

/**
 * Acción propia de ejemplo (plan 1.5, D9): «Registrar adenda» necesita más datos que una transición común, así que el
 * módulo la escribe con su propio permiso y llama al servicio de transiciones, que igual exige el de la transición.
 */
final class RegistrarAdendaPrueba extends Accion
{
    public function clave(): string
    {
        return 'pruebas.registrar-adenda';
    }

    public function nombre(): string
    {
        return 'Registrar adenda de prueba';
    }

    public function campos(): array
    {
        return [
            Campo::entero('documento_id', 'Documento'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::decimal('monto', 'Monto de la adenda'),
        ];
    }

    public function permiso(): string
    {
        return 'pruebas.registrar-adenda';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $documento = DocumentoPrueba::query()->findOrFail($datos['documento_id']);

        return app(TransicionesService::class)->transicionar($documento, 'registrar_adenda', $datos['version'], $usuario);
    }
}
