<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Carbon\CarbonImmutable;

/**
 * Una fila del historial de estados como la lee una persona (plan 1.5, D10): de dónde a dónde, por qué y quién. La
 * primera, la creación, no tiene `desde` ni transición. `originadoPor`: la persona cuya acción disparó una
 * transición del sistema.
 */
final readonly class FilaHistorialEstados
{
    public function __construct(
        public CarbonImmutable $fecha,
        public ?string $desde,
        public string $hacia,
        public ?string $transicion,
        public ?string $motivo,
        public ?string $motivoDetalle,
        public ?string $comentario,
        public string $usuario,
        public ?string $originadoPor,
    ) {}
}
