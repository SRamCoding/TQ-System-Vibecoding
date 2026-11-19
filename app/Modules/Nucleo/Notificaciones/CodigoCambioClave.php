<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * El código para cambiar la contraseña desde «Mis datos» (plan 1.4h, D3). Va al correo de acceso de la persona.
 */
final class CodigoCambioClave extends Notification
{
    public function __construct(#[SensitiveParameter] private readonly string $codigo, private readonly int $minutos) {}

    /** @return list<string> */
    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(object $notificable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu código para cambiar la contraseña')
            ->greeting('Hola:')
            ->line('Pediste cambiar tu contraseña de TechQuk. Escribe este código en la pantalla:')
            ->line("**{$this->codigo}**")
            ->line("Vale {$this->minutos} minutos y sirve una sola vez.")
            ->line('Si no lo pediste tú, no hagas nada: tu contraseña sigue igual. Avisa a quien administra los usuarios.')
            ->salutation('TechQuk');
    }

    /** Solo para las pruebas: el código que lleva. */
    public function codigo(): string
    {
        return $this->codigo;
    }
}
