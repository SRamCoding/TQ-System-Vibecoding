<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Efectos;

use App\Modules\Erp\Proyectos\Events\ProyectoCerrado;
use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/**
 * Al cerrar, avisa con `ProyectoCerrado` (C2). El evento sale después del commit: si la transición falla, no sale.
 * No tiene nada que revertir: un cierre no se anula.
 */
final class AvisarCierreDelProyecto implements Efecto
{
    public function aplicar(Model $documento): array
    {
        ProyectoCerrado::dispatch((int) $documento->getKey(), (string) $documento->getAttribute('codigo'));

        return ['proyecto_id' => (int) $documento->getKey()];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // El aviso de cierre no se deshace: «Cerrado» es final y no tiene salida.
    }
}
