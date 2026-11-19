<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

/** El motivo que eligió la persona para una transición que lo exige, con el detalle si es «Otro» (plan 1.5, D9). */
final readonly class MotivoElegido
{
    public function __construct(public int $motivoId, public ?string $detalle = null) {}
}
