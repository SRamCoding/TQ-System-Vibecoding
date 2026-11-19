<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Adjuntos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AdjuntosService;

/** Adjunta un archivo a un registro (plan 1.8). Quién puede lo dice el registro (`ConAdjuntos`), no un permiso. */
final class AgregarAdjunto extends Accion
{
    public function clave(): string
    {
        return 'nucleo.adjuntos.agregar';
    }

    public function nombre(): string
    {
        return 'Adjuntar un archivo';
    }

    public function campos(): array
    {
        return [
            Campo::texto('adjuntable_type', 'Tipo de registro', maximo: 120),
            Campo::entero('adjuntable_id', 'Registro')->conReglas('min:1'),
            Campo::archivo('archivo', 'Archivo'),
            Campo::texto('tipo', 'Tipo de adjunto', maximo: 20)->opcional(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AdjuntosService::class)->agregar($datos['adjuntable_type'], $datos['adjuntable_id'], $datos['archivo'], $datos['tipo'] ?? null, $usuario);
    }
}
