<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio en los avisos de una persona no procede (plan 1.4y). */
final class AvisoRechazado extends ErrorDeNegocio
{
    /** Sin decir por qué: un aviso de otra persona se rechaza igual que uno que no existe. */
    public static function noEncontrado(): self
    {
        return new self('Ese aviso no está entre los tuyos.');
    }
}
