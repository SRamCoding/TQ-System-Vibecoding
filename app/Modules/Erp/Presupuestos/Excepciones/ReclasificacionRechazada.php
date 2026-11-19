<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Excepciones;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** La reclasificación de un gasto no se hizo (plan 2.6, D5). No se escribe nada. */
final class ReclasificacionRechazada extends ErrorDeNegocio
{
    /** Mientras no exista la tarea 2.8 del núcleo. */
    public static function todaviaNo(): self
    {
        return new self('La reclasificación llega con su tarea.');
    }
}
