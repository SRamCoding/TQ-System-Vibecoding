<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Adjuntos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AdjuntosService;

/** Quita un archivo de un registro, dejando rastro (plan 1.8, D2). No en un documento que ya no se edita. */
final class QuitarAdjunto extends Accion
{
    public function clave(): string
    {
        return 'nucleo.adjuntos.quitar';
    }

    public function nombre(): string
    {
        return 'Quitar un archivo adjunto';
    }

    public function campos(): array
    {
        return [Campo::entero('adjunto_id', 'Archivo')->conReglas('min:1')];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AdjuntosService::class)->quitar($datos['adjunto_id'], $usuario);
    }
}
