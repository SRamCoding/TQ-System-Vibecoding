<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio a un documento, o a una de sus líneas, que su estado no admite (plan 1.5, D5 y D8). */
final class EdicionRechazada extends ErrorDeNegocio
{
    /**
     * Solo nombra las salidas que la persona puede usar ahora; sin ninguna, no nombra ninguna (D8, ajuste 3).
     *
     * @param  list<string>  $salidas
     */
    public static function noEditable(string $documento, string $estado, array $salidas): self
    {
        $mensaje = ucfirst($documento)." está en «{$estado}» y no se puede modificar.";

        if ($salidas !== []) {
            $mensaje .= ' Lo que puedes hacer: «'.implode('» o «', $salidas).'».';
        }

        return new self($mensaje);
    }

    /**
     * @param  list<string>  $campos
     */
    public static function derivado(array $campos): self
    {
        return new self('«'.implode('», «', $campos).'» lo actualizan los documentos que dependen de este: no se cambia a mano.');
    }

    public static function noSeBorra(string $documento): self
    {
        return new self(ucfirst($documento).' no se borra: si ya no sirve, anúlalo.');
    }
}
