<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio en el panel de aplicaciones no procede (plan 1.4p). */
final class AplicacionRechazada extends ErrorDeNegocio
{
    /** Sin decir por qué: una propia de otra persona no se delata (criterio de aceptación). */
    public static function noDisponible(): self
    {
        return new self('Esa aplicación no está en tu panel.');
    }

    /** Plan 1.4z, D9: «Propio» es el ícono subido por administración, solo para las de Techquk. */
    public static function iconoPropio(): self
    {
        return new self('El ícono «Propio» es solo para las aplicaciones de Techquk: elige otro.');
    }

    public static function sinEscritorio(): self
    {
        return new self('Esa aplicación no tiene versión de escritorio: se abre en la web.');
    }

    public static function posicionInvalida(): self
    {
        return new self('Tus aplicaciones principales son 3: elige el lugar 1, 2 o 3.');
    }

    public static function enlaceInvalido(): self
    {
        return new self('El enlace no es una dirección web válida (por ejemplo, https://www.ejemplo.com).');
    }

    /** Paso 3: se abren en otra pestaña desde una pantalla segura; un enlace sin cifrar no. */
    public static function enlaceSinHttps(): self
    {
        return new self('El enlace debe empezar con https:// (una dirección segura).');
    }

    public static function nombreVacio(): self
    {
        return new self('Escribe el nombre de la aplicación.');
    }

    public static function nombreRepetido(): self
    {
        return new self('Ya tienes una aplicación con ese nombre.');
    }
}
