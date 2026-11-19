<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Acción inventada que edita el documento de prueba por su servicio. */
final class RenombrarDocumentoPrueba extends Accion
{
    public function clave(): string
    {
        return 'pruebas.renombrar_documento';
    }

    public function nombre(): string
    {
        return 'Renombrar documento de prueba';
    }

    public function campos(): array
    {
        return [
            Campo::entero('documento_id', 'Documento'),
            Campo::texto('nombre', 'Nombre', maximo: 60),
        ];
    }

    public function permiso(): string
    {
        return 'probar-acciones';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(ServicioDocumentoPrueba::class)->renombrar($datos['documento_id'], $datos['nombre']);
    }
}
