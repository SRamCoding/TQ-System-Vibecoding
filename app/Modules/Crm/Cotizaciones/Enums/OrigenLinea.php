<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Enums;

/** De dónde sale una línea de la cotización (plan 2.22, D6). */
enum OrigenLinea: string
{
    /** Un producto del catálogo (C4): su descripción y unidad se copian al agregarla. */
    case Catalogo = 'catalogo';

    /** Escrita a mano, «por catalogar». */
    case Libre = 'libre';

    /** El presupuesto con APU enlazado (C8): precio y costo salen de su resumen. */
    case Presupuesto = 'presupuesto';
}
