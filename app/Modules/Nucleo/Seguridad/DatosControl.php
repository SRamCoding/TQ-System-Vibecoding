<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

/**
 * El control compensatorio de un cruce de funciones (plan 1.4, D9): qué se revisa, quién responde (nunca la persona
 * del cruce) y hasta qué día vale (`Y-m-d`; nulo, sin vencimiento). Un cambio que abre varios cruces usa el mismo
 * control para cada uno: queda una fila por cruce.
 */
final readonly class DatosControl
{
    public function __construct(
        public string $control,
        public int $responsableId,
        public ?string $vigenteHasta = null,
    ) {}
}
