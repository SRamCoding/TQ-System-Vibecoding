<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Una operación sobre una red social de una party o de un contacto no procede (plan 1.4o). Los mensajes no llevan el
 * enlace: es un dato personal (regla 10).
 */
final class RedSocialRechazada extends ErrorDeNegocio
{
    public static function noExiste(): self
    {
        return new self('La red social no existe.');
    }

    public static function redNoDisponible(): self
    {
        return new self('La red elegida no existe o está desactivada en el catálogo.');
    }

    public static function enlaceInvalido(): self
    {
        return new self('El enlace no es una dirección web válida (por ejemplo, https://www.linkedin.com/in/tu-nombre).');
    }

    public static function enlaceDeOtraRed(string $red, string $dominios): self
    {
        return new self("El enlace no es de {$red}: debe ser de {$dominios}.");
    }

    public static function enlaceRepetido(): self
    {
        return new self('Ese enlace ya está registrado aquí.');
    }

    public static function partyInactiva(): self
    {
        return new self('La persona o empresa está desactivada: actívala antes de agregarle redes.');
    }

    public static function contactoNoDisponible(): self
    {
        return new self('El contacto elegido no es un contacto vigente de esta persona o empresa.');
    }

    /** Plan 1.4o, D3: «solo la veo yo» es de las personas del equipo, no de un cliente ni de un contacto. */
    public static function privadaSoloDelEquipo(): self
    {
        return new self('Solo una persona del equipo puede dejar una red visible únicamente para ella.');
    }

    /** Plan 1.4o, D2: las del equipo, cada persona en «Mis datos». */
    public static function esDelEquipo(): self
    {
        return new self('Las redes de una persona del equipo las cambia ella misma, desde «Mis datos».');
    }

    public static function noEsTuya(): self
    {
        return new self('Esa red no es tuya.');
    }
}
