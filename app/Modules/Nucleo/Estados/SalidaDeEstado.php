<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

/**
 * Una transición que la persona puede hacer ahora desde el estado actual (plan 1.5, D8 y D10). `accion` es la clave de
 * su AccionTransicion; nula si es de acción propia, que su módulo ofrece con la suya.
 */
final readonly class SalidaDeEstado
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public string $hacia,
        public bool $requiereMotivo,
        public ?int $tipoMotivoId,
        public bool $anula,
        public ?string $accion,
    ) {}
}
