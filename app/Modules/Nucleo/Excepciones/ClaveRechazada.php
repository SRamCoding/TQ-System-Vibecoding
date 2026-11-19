<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Un cambio de contraseña que no procede (plan 1.4h, D3 y D5). Los mensajes nunca repiten la contraseña ni el código.
 */
final class ClaveRechazada extends ErrorDeNegocio
{
    /**
     * @param  list<string>  $motivos
     */
    public static function noCumple(array $motivos): self
    {
        return new self(implode(' ', $motivos));
    }

    public static function noCoincide(): self
    {
        return new self('Las dos contraseñas nuevas no coinciden.');
    }

    public static function igualALaActual(): self
    {
        return new self('La contraseña nueva debe ser distinta de la actual.');
    }

    public static function sinCodigo(): self
    {
        return new self('El código venció o ya se usó: pide uno nuevo.');
    }

    public static function codigoIncorrecto(int $quedan): self
    {
        return new self("El código no es correcto. Te quedan {$quedan} intentos.");
    }

    public static function codigoAnulado(): self
    {
        return new self('Se acabaron los intentos: el código se anuló. Pide uno nuevo.');
    }

    public static function esperaReenvio(int $segundos): self
    {
        return new self("Espera {$segundos} segundos para pedir otro código.");
    }

    public static function noDebeRenovar(): self
    {
        return new self('Tu contraseña ya cumple la regla: cámbiala desde «Mis datos».');
    }
}
