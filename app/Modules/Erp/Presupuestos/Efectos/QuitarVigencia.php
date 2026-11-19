<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/** Al reemplazarse, el presupuesto deja de ser el vigente de su proyecto (plan 2.9, D6). Reemplazado es final. */
final readonly class QuitarVigencia implements Efecto
{
    public function aplicar(Model $documento): array
    {
        $documento->setAttribute('es_vigente', false);
        $documento->save();

        return [];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Reemplazado es un estado final: no se sale de él.
    }
}
