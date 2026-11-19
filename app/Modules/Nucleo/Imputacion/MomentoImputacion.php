<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/** Cuál de los tres montos de una línea de costo se mueve (plan 1.9; DBML `imputaciones`). */
enum MomentoImputacion: string
{
    /** Al aprobar la orden, reservar o cerrar la semana de tareo. */
    case Comprometido = 'comprometido';

    /** Al despachar, dar conformidad, cerrar el tareo o aprobar la rendición. Nunca más que lo comprometido. */
    case Ejecutado = 'ejecutado';

    /** Al aplicar el pago: medida de caja, no de costo. */
    case Pagado = 'pagado';

    public function columna(): string
    {
        return "monto_{$this->value}";
    }
}
