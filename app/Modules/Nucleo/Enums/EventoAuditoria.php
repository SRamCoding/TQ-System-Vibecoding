<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Qué le pasó a la fila auditada (M4). Es un tipo de registro de la bitácora, no un estado de
 * documento: por eso es enum, con su CHECK en la base.
 */
enum EventoAuditoria: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';

    /** Para la pestaña de historial. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creó',
            self::Actualizado => 'Cambió',
            self::Eliminado => 'Borró',
        };
    }
}
