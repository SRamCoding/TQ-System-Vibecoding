<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use InvalidArgumentException;

/**
 * Un aviso que un módulo puede mandar (plan 1.8, D7): su clave, su grupo y si es obligatorio. Lo declara el provider
 * del módulo en RegistroAvisos; el texto de cada envío lo pone quien avisa. Uno obligatorio llega también por correo
 * aunque la persona lo haya apagado para su grupo (D8), salvo que sea solo de campana.
 */
final readonly class DefinicionAviso
{
    public function __construct(
        /** «<modulo>.<aviso>»: `compras.orden-por-aprobar`. */
        public string $clave,
        public string $grupo,
        public bool $obligatorio = false,
        /** Ícono que lo acompaña en la campana. */
        public string $icono = 'heroicon-o-bell',
        /** Falso: solo la campana, nunca correo, ni obligatorio (plan 1.10: el aviso de que un correo no salió). */
        public bool $conCorreo = true,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*\.[a-z0-9.-]+$/', $clave) !== 1 || strlen($clave) > 80) {
            throw new InvalidArgumentException("La clave del aviso va como «modulo.aviso», hasta 80: «{$clave}».");
        }
    }
}
