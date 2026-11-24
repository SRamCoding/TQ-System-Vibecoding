<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Observación 9.3: el ícono de un canal por el que llega un lead (página web, WhatsApp, redes, referido, feria…).
 */
enum IconoCanal: string
{
    case SitioWeb = 'sitio_web';
    case Whatsapp = 'whatsapp';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case Linkedin = 'linkedin';
    case Referido = 'referido';
    case Feria = 'feria';
    case Llamada = 'llamada';
    case Correo = 'correo';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SitioWeb => 'Globo (página web)',
            self::Whatsapp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Tiktok => 'TikTok',
            self::Linkedin => 'LinkedIn',
            self::Referido => 'Personas (referido)',
            self::Feria => 'Puesto (feria o evento)',
            self::Llamada => 'Teléfono',
            self::Correo => 'Sobre (correo)',
            self::Otro => 'Puntos (otro)',
        };
    }

    /** Nombre del ícono para Blade y Filament. */
    public function componente(): string
    {
        return match ($this) {
            self::SitioWeb => 'heroicon-o-globe-alt',
            self::Whatsapp, self::Instagram, self::Facebook, self::Tiktok, self::Linkedin => "tq-red-{$this->value}",
            self::Referido => 'heroicon-o-user-group',
            self::Feria => 'heroicon-o-building-storefront',
            self::Llamada => 'heroicon-o-phone',
            self::Correo => 'heroicon-o-envelope',
            self::Otro => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}
