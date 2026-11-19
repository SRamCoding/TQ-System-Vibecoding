<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use InvalidArgumentException;

/**
 * Un grupo de avisos (plan 1.8, D8): «Aprobaciones», «Vencimientos». En «Mis preferencias» cada persona elige, por
 * grupo, si además le llegan por correo. Lo declara el módulo dueño.
 */
final readonly class GrupoAvisos
{
    public function __construct(
        /** Minúsculas, cifras y guion bajo: forma la preferencia `avisos.correo_<clave>`. */
        public string $clave,
        public string $nombre,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,50}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave del grupo de avisos va en snake_case: «{$clave}».");
        }
    }

    public function preferenciaCorreo(): string
    {
        return "correo_{$this->clave}";
    }
}
