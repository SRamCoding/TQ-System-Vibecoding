<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Efecto interno de prueba que falla al aplicarse: nada del cambio de estado debe quedar. */
final class EfectoQueFalla implements Efecto
{
    public function aplicar(Model $documento): array
    {
        throw new RuntimeException('El efecto de prueba falló.');
    }

    public function revertir(Model $documento, array $datos): void {}
}
