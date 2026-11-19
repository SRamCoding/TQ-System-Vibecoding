<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

/**
 * Lo que se pide para registrar un usuario (plan 1.4, D4 y D10): su persona (una ficha de DNI, CE o pasaporte), su
 * correo y, si ya se sabe, su puesto. El nombre se copia de la persona; los roles se asignan después.
 */
final readonly class DatosUsuario
{
    public function __construct(
        public int $partyId,
        public string $email,
        public ?int $puestoId = null,
    ) {}
}
