<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** El número no sirve para el tipo de documento elegido (plan 1.3, D4). */
final class DocumentoIdentidadInvalido extends ErrorDeNegocio
{
    public static function vacio(): self
    {
        return new self('Escribe el número de documento.');
    }

    public static function caracteres(): self
    {
        return new self('El número de documento solo lleva letras y números, hasta 20 caracteres.');
    }

    public static function longitud(string $nombreTipo, int $longitud, bool $soloDigitos): self
    {
        $que = $soloDigitos ? 'dígitos' : 'caracteres';

        return new self("El {$nombreTipo} tiene {$longitud} {$que}.");
    }

    public static function prefijoRuc(): self
    {
        return new self('El RUC empieza con 10, 15, 16, 17 o 20.');
    }

    public static function digitoVerificador(string $ruc): self
    {
        return new self("El RUC {$ruc} no es válido: su último dígito no corresponde. Revisa que esté bien escrito.");
    }
}
