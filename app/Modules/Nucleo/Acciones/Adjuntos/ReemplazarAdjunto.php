<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Adjuntos;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AdjuntosService;

/** Cambia un archivo por otro del mismo tipo; el anterior queda quitado (plan 1.8, D2). */
final class ReemplazarAdjunto extends Accion
{
    public function clave(): string
    {
        return 'nucleo.adjuntos.reemplazar';
    }

    public function nombre(): string
    {
        return 'Reemplazar un archivo adjunto';
    }

    public function campos(): array
    {
        return [
            Campo::entero('adjunto_id', 'Archivo')->conReglas('min:1'),
            Campo::archivo('archivo', 'Archivo nuevo'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return app(AdjuntosService::class)->reemplazar($datos['adjunto_id'], $datos['archivo'], $usuario);
    }
}
