<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * El ícono de una red social del catálogo (plan 1.4o, D1). Los de marca son SVG del repositorio
 * (`resources/svg/red-*`, licencias en LICENCIAS.md); una red nueva sin dibujo propio usa el de enlace.
 */
enum IconoRed: string
{
    case Linkedin = 'linkedin';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';
    case X = 'x';
    case SitioWeb = 'sitio_web';
    case Enlace = 'enlace';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Linkedin => 'LinkedIn',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Tiktok => 'TikTok',
            self::Youtube => 'YouTube',
            self::X => 'X',
            self::SitioWeb => 'Sitio web (globo)',
            self::Enlace => 'Enlace (para una red sin ícono propio)',
        };
    }

    /** Nombre del ícono para Blade y Filament. */
    public function componente(): string
    {
        return match ($this) {
            self::SitioWeb => 'heroicon-o-globe-alt',
            self::Enlace => 'heroicon-o-link',
            default => "tq-red-{$this->value}",
        };
    }
}
