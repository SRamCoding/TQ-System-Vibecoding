<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ContextoAuditoria;

/** Acción inventada con un campo de cada tipo. Devuelve lo que recibió y a nombre de quién corrió. */
final class AccionDePrueba extends Accion
{
    public function clave(): string
    {
        return 'pruebas.accion';
    }

    public function nombre(): string
    {
        return 'Acción de prueba';
    }

    public function campos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 10),
            Campo::entero('cantidad', 'Cantidad')->conReglas('min:1'),
            Campo::decimal('monto', 'Monto'),
            Campo::fecha('fecha_entrega', 'Fecha de entrega'),
            Campo::booleano('es_urgente', 'Urgente'),
            Campo::opcion('area', 'Área', AreaModulo::class),
            Campo::texto('nota', 'Nota')->opcional(),
            Campo::elementoDe('unidad_medida_id', 'Unidad de medida', Catalogo::UnidadesMedida)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'probar-acciones';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return ['datos' => $datos, 'actor' => app(ContextoAuditoria::class)->idActor()];
    }
}
