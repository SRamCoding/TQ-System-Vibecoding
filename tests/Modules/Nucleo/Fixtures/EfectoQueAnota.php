<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Efecto interno de prueba: anota en la base que se aplicó, con el monto del documento, y se revierte con el monto
 * que guardó, no con el de hoy.
 */
abstract class EfectoQueAnota implements Efecto
{
    abstract protected function letra(): string;

    public function aplicar(Model $documento): array
    {
        $monto = (string) $documento->getAttribute('monto');
        DB::table('anotaciones_prueba')->insert(['texto' => "aplicar {$this->letra()} {$monto}"]);

        return ['monto' => $monto];
    }

    public function revertir(Model $documento, array $datos): void
    {
        DB::table('anotaciones_prueba')->insert(['texto' => "revertir {$this->letra()} {$datos['monto']}"]);
    }
}
