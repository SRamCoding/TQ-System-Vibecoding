<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use Carbon\CarbonImmutable;

/**
 * Lo que se escribe de un contacto al agregarlo o editarlo (plan 1.3b, D1). `hasta` y «principal» tienen sus
 * propias operaciones al editar (cerrar vínculo, marcar principal); al agregar se aceptan, para registrar un
 * vínculo que ya terminó o al principal de entrada.
 */
final readonly class DatosContacto
{
    public function __construct(
        public string $nombres,
        public string $apellidos,
        public ?string $cargo = null,
        public ?string $area = null,
        public ?string $telefono = null,
        public ?string $email = null,
        public bool $esDecisor = false,
        public bool $esPrincipal = false,
        public ?CarbonImmutable $desde = null,
        public ?CarbonImmutable $hasta = null,
    ) {}
}
