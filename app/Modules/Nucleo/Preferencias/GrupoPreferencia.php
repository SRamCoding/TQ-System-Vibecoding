<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Preferencias;

/**
 * Los grupos de «Mis preferencias» (plan 1.4g, D6). El orden de los casos es el de la pantalla; un grupo sin
 * preferencias declaradas no aparece. El grupo Saludo salió en el plan 1.4aa (D5): el saludo no se configura.
 */
enum GrupoPreferencia: string
{
    case Apariencia = 'apariencia';
    case Navegacion = 'navegacion';
    case Avisos = 'avisos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Apariencia => 'Apariencia',
            self::Navegacion => 'Navegación',
            self::Avisos => 'Avisos',
        };
    }

    /**
     * Lo que vale para todas las preferencias del grupo, una sola vez sobre la sección (plan 1.4aa, D4). Nula si no hay.
     */
    public function descripcion(): ?string
    {
        return match ($this) {
            self::Avisos => 'Para todas estas preferencias: los avisos siempre llegan a la campana; los obligatorios también llegan por correo aunque lo apagues.',
            default => null,
        };
    }
}
