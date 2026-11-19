<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Una operación sobre una sede no procede (plan 1.3b). */
final class SedeRechazada extends ErrorDeNegocio
{
    public static function noExiste(): self
    {
        return new self('La sede no existe.');
    }

    public static function partyInactiva(): self
    {
        return new self('La persona o empresa está desactivada: actívala antes de agregarle sedes.');
    }

    public static function faltanDatos(): self
    {
        return new self('Una sede lleva nombre y dirección.');
    }

    public static function nombreRepetido(string $nombre): self
    {
        return new self("Ya hay una sede activa llamada «{$nombre}» (sin contar mayúsculas ni acentos).");
    }

    public static function codigoEstablecimientoInvalido(): self
    {
        return new self('El código de establecimiento SUNAT tiene 4 dígitos (por ejemplo, 0001).');
    }

    public static function codigoEstablecimientoRepetido(string $codigo): self
    {
        return new self("Ya hay una sede activa con el código de establecimiento {$codigo}.");
    }

    public static function coordenadasIncompletas(): self
    {
        return new self('Indica latitud y longitud juntas, o ninguna.');
    }

    public static function coordenadaInvalida(string $cual, string $rango): self
    {
        return new self("La {$cual} es un número entre {$rango}, con 7 decimales como máximo.");
    }

    public static function ubigeoNoDisponible(): self
    {
        return new self('El ubigeo no existe o está desactivado.');
    }

    public static function contactoNoDisponible(): self
    {
        return new self('El contacto elegido no es un contacto vigente de esta persona o empresa.');
    }

    public static function yaEstaEnEseEstado(bool $activa): self
    {
        return new self($activa ? 'La sede ya está activa.' : 'La sede ya está desactivada.');
    }

    public static function enUso(): self
    {
        return new self('Esa sede ya se usa en otros registros: desactívala en vez de borrarla.');
    }
}
