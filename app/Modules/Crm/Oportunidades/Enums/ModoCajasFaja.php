<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

/** Cómo se dibujan las cajas de un lugar de la faja según cuántas hay (plan 2.21b, D8). */
enum ModoCajasFaja: string
{
    /** Hasta 6: nombre, monto y entidad grabados. */
    case Completas = 'completas';
    /** De 7 a 12: solo el código, en una caja más chica y más juntas. */
    case Codigo = 'codigo';
    /** Más de 12: 11 cajas con su código y una pila «+N más» que abre la tabla del lugar. */
    case CodigoConPila = 'codigo_con_pila';
}
