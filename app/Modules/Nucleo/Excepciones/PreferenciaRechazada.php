<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Un cambio del cumpleaños (plan 1.4b) o de una preferencia no procede: las del registro (plan 1.4g), los módulos de la
 * barra (plan 1.4f) o las categorías de frases. Los mensajes nunca repiten la fecha del cumpleaños: es un dato personal.
 */
final class PreferenciaRechazada extends ErrorDeNegocio
{
    public static function usuarioNoExiste(): self
    {
        return new self('El usuario no existe.');
    }

    public static function cumpleanosIncompleto(): self
    {
        return new self('Escribe el día y el mes del cumpleaños, o deja los dos vacíos.');
    }

    public static function cumpleanosInvalido(): self
    {
        return new self('Ese día no existe en ese mes.');
    }

    /** Sin decir cuál: el mensaje no revela módulos que la persona no puede abrir (plan 1.4f, D4). */
    public static function moduloNoDisponible(): self
    {
        return new self('Uno de los módulos elegidos no está disponible para ti.');
    }

    public static function barraSinModulos(): self
    {
        return new self('Deja al menos un módulo en la barra.');
    }

    public static function claveDesconocida(): self
    {
        return new self('Esa preferencia no existe.');
    }

    public static function valorInvalido(string $etiqueta): self
    {
        return new self("Ese valor no vale para «{$etiqueta}».");
    }

    /** Una lista se cambia con la acción propia de su módulo, no con la general (plan 1.4g, D3). */
    public static function seCambiaConSuAccion(string $etiqueta): self
    {
        return new self("«{$etiqueta}» se cambia desde su propia opción.");
    }
}
