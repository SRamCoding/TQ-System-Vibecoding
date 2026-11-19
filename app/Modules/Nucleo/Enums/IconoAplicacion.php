<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * El ícono de una aplicación del panel de aplicaciones (plan 1.4p, paso 5; plan 1.4x, D5): los de Microsoft 365 en
 * línea, los de las demás que se usan en Techquk y uno genérico. La base lo exige con un CHECK en `aplicaciones` y
 * `aplicaciones_propias`. Los dibujos están en `resources/aplicaciones/` (licencias en `resources/svg/LICENCIAS.md`).
 */
enum IconoAplicacion: string
{
    case Word = 'word';
    case Excel = 'excel';
    case Powerpoint = 'powerpoint';
    case Outlook = 'outlook';
    case Onedrive = 'onedrive';
    case Teams = 'teams';
    case Onenote = 'onenote';
    // Plan 1.4x, D5: de Simple Icons (CC0) en el color de su marca; LinkedIn, el de las redes sociales.
    case Claude = 'claude';
    case Whatsapp = 'whatsapp';
    case Gmail = 'gmail';
    case GoogleDrive = 'google_drive';
    case Linkedin = 'linkedin';
    // Plan 1.4z, D5: Meet (el oficial de Google), Excalidraw (Simple Icons) y la pizarra (dibujo propio: Microsoft no
    // publica el de Whiteboard en su CDN de íconos; administración puede subir el oficial, D9).
    case GoogleMeet = 'google_meet';
    case Excalidraw = 'excalidraw';
    case Whiteboard = 'whiteboard';
    // Plan 1.4z, D9: el que subió administración (un adjunto de la aplicación; solo las de Techquk).
    case Propio = 'propio';
    case Generica = 'generica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Word => 'Word',
            self::Excel => 'Excel',
            self::Powerpoint => 'PowerPoint',
            self::Outlook => 'Outlook',
            self::Onedrive => 'OneDrive',
            self::Teams => 'Teams',
            self::Onenote => 'OneNote',
            self::Claude => 'Claude',
            self::Whatsapp => 'WhatsApp',
            self::Gmail => 'Gmail',
            self::GoogleDrive => 'Google Drive',
            self::Linkedin => 'LinkedIn',
            self::GoogleMeet => 'Google Meet',
            self::Excalidraw => 'Excalidraw',
            self::Whiteboard => 'Pizarra (Whiteboard)',
            self::Propio => 'Propio (súbelo con «Subir ícono»)',
            self::Generica => 'Genérico (para una aplicación sin ícono propio)',
        };
    }

    /**
     * El dibujo como imagen incrustada (`data:`): así sus degradados no chocan con los de otro ícono de la misma página
     * y no se pide nada a otro sitio. El genérico no tiene: la pantalla muestra la inicial del nombre. El propio tampoco: su
     * imagen es la que subió administración (`AplicacionDelPanel::$imagen`).
     */
    public function imagen(): ?string
    {
        static $imagenes = [];

        if ($this === self::Generica || $this === self::Propio) {
            return null;
        }

        return $imagenes[$this->value] ??= 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(dirname(__DIR__).'/resources/aplicaciones/'.$this->value.'.svg'));
    }

    /**
     * Los que puede elegir una persona para sus propias: todos menos «propio», que es solo de las de Techquk.
     *
     * @return list<self>
     */
    public static function paraPropias(): array
    {
        return array_values(array_filter(self::cases(), fn (self $icono): bool => $icono !== self::Propio));
    }
}
