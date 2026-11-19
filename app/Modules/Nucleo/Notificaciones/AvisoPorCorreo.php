<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * El correo de un aviso (plan 1.8, D6): el mismo título, texto y enlace que la campana. El Message-ID es estable por
 * aviso: si el job se repite, el servidor de correo puede reconocer el duplicado (`codigo.md`, efectos externos).
 */
final class AvisoPorCorreo extends Notification
{
    public function __construct(
        private readonly int $notificacionId,
        private readonly string $titulo,
        private readonly ?string $texto,
        private readonly ?string $enlace,
    ) {}

    /** @return list<string> */
    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(object $notificable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->titulo)
            ->greeting('Hola:')
            ->withSymfonyMessage(function (Email $correo): void {
                $correo->getHeaders()->addIdHeader('Message-ID', "aviso-{$this->notificacionId}@techquk.local");
            });

        if ($this->texto !== null) {
            $mensaje->line($this->texto);
        }

        if ($this->enlace !== null) {
            $mensaje->action('Abrir en TechQuk', $this->enlace);
        }

        return $mensaje->salutation('— TechQuk');
    }
}
