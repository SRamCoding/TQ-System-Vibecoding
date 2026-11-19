<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Una operación sobre un contacto no procede (plan 1.3b). Los mensajes no llevan su nombre, teléfono ni correo:
 * son datos personales (regla 10).
 */
final class ContactoRechazado extends ErrorDeNegocio
{
    public static function noExiste(): self
    {
        return new self('El contacto no existe.');
    }

    public static function partyInactiva(): self
    {
        return new self('La persona o empresa está desactivada: actívala antes de agregarle contactos.');
    }

    public static function faltanNombres(): self
    {
        return new self('Un contacto lleva nombres y apellidos.');
    }

    public static function emailInvalido(): self
    {
        return new self('El correo del contacto no es válido.');
    }

    public static function hastaAntesDeDesde(): self
    {
        return new self('El vínculo no puede terminar antes de empezar.');
    }

    public static function cierreFuturo(): self
    {
        return new self('El vínculo se cierra cuando la persona ya dejó la institución: la fecha no puede ser posterior a hoy.');
    }

    public static function principalCerrado(): self
    {
        return new self('Un contacto con el vínculo cerrado no puede ser el principal.');
    }

    public static function vinculoCerrado(): self
    {
        return new self('Ese contacto ya no está vigente: su vínculo está cerrado.');
    }

    public static function yaEsPrincipal(): self
    {
        return new self('Ese contacto ya es el principal.');
    }

    public static function principalCambiado(): self
    {
        return new self('Otra persona acaba de cambiar el contacto principal. Vuelve a abrir la ficha.');
    }

    public static function faltaCargoNuevo(): self
    {
        return new self('Indica el cargo que ocupa en la nueva institución.');
    }

    public static function trasladoMismaParty(): self
    {
        return new self('El traslado es a otra persona o empresa: para cambiar el cargo aquí, edita el contacto.');
    }

    public static function destinoInactivo(): self
    {
        return new self('La institución de destino está desactivada.');
    }

    public static function faltaFechaDeCierre(): self
    {
        return new self('Indica hasta cuándo estuvo en esta institución.');
    }

    public static function yaTrasladado(): self
    {
        return new self('Ese vínculo ya continúa en otra institución.');
    }

    public static function enUso(): self
    {
        return new self('Ese contacto ya se usa (en una sede, un traslado u otro registro): cierra su vínculo en vez de borrarlo.');
    }
}
