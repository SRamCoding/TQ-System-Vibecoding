<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Los programas de escritorio que el panel de aplicaciones sabe abrir (plan 1.4z, D6). Una página web no abre cualquier
 * programa: solo los que registran su propio enlace. **Lista cerrada a propósito**: nadie escribe el enlace a mano, así
 * no se puede usar para `javascript:` ni `file:`. Comprobados uno por uno en Windows por el desarrollador (2026-10-30).
 * La base los exige con un CHECK en `aplicaciones` y `aplicaciones_propias`. Sumar uno necesita código. Whiteboard salió
 * el 2026-10-30: Microsoft retiró su aplicación de escritorio.
 */
enum ProgramaEscritorio: string
{
    case Word = 'word';
    case Excel = 'excel';
    case Powerpoint = 'powerpoint';
    case Outlook = 'outlook';
    case Onenote = 'onenote';
    case Teams = 'teams';
    case Whatsapp = 'whatsapp';
    case Claude = 'claude';
    case Zoom = 'zoom';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Word => 'Word',
            self::Excel => 'Excel',
            self::Powerpoint => 'PowerPoint',
            self::Outlook => 'Outlook',
            self::Onenote => 'OneNote',
            self::Teams => 'Teams',
            self::Whatsapp => 'WhatsApp',
            self::Claude => 'Claude',
            self::Zoom => 'Zoom',
        };
    }

    /** El enlace que abre el programa instalado (la primera vez, el navegador pregunta). */
    public function enlace(): string
    {
        return match ($this) {
            self::Word => 'ms-word:',
            self::Excel => 'ms-excel:',
            self::Powerpoint => 'ms-powerpoint:',
            self::Outlook => 'ms-outlook:',
            self::Onenote => 'onenote:',
            self::Teams => 'msteams:',
            self::Whatsapp => 'whatsapp:',
            self::Claude => 'claude:',
            self::Zoom => 'zoommtg:',
        };
    }
}
