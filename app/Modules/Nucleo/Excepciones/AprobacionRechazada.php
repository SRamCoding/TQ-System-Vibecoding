<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use App\Modules\Nucleo\Enums\CondicionRegla;

/** Una firma, una observación o un pedido de aprobación que las reglas no permiten (plan 1.7). No se escribe nada. */
final class AprobacionRechazada extends ErrorDeNegocio
{
    public static function sinRonda(string $documento): self
    {
        return new self("No hay una aprobación pendiente para {$documento}.");
    }

    public static function noTeToca(string $paso): self
    {
        return new self("Esta firma le toca a {$paso}.");
    }

    public static function sinFirmante(string $paso): self
    {
        return new self("Nadie puede firmar por {$paso}: el puesto está vacante en toda la línea. Revisa el organigrama.");
    }

    public static function documentoCambio(string $documento): self
    {
        return new self("{$documento} cambió desde que se pidió la aprobación: las firmas empezaron de cero. Si te toca, vuelve a firmar.");
    }

    /** Plan 2.22, P6: el documento cambió entre abrir la bandeja y firmar. No se firmó nada. */
    public static function cambioDesdeQueLoAbriste(string $documento): self
    {
        return new self("{$documento} cambió desde que lo abriste: no se firmó. Vuelve a abrirlo para ver lo actual.");
    }

    /** Plan 2.2, D5: el exceso nunca pasa sin su regla (v4: «Siempre»). */
    public static function excesoSinRegla(): self
    {
        return new self('Hay líneas que exceden su partida y no hay una regla de aprobación para el exceso: un administrador debe configurarla en Configuración → Aprobaciones.');
    }

    /** Una condición propia del documento (plan 2.22, D14) sin su regla. */
    public static function condicionSinRegla(CondicionRegla $condicion): self
    {
        return new self('No hay una regla de aprobación para «'.mb_lcfirst($condicion->etiqueta()).'», y este documento la cumple: un administrador debe configurarla en Configuración → Aprobaciones.');
    }

    public static function noPresupuestadoSinRegla(): self
    {
        return new self('Hay líneas que van a la PC-99 (gasto no presupuestado) y no hay una regla de aprobación para ese gasto: un administrador debe configurarla en Configuración → Aprobaciones.');
    }

    public static function sinUnidad(string $documento): self
    {
        return new self("No se puede pedir la aprobación: {$documento} no tiene unidad y una regla pide la firma de su jefatura.");
    }

    public static function sinObservacion(string $documento): self
    {
        return new self("{$documento} no se puede observar: su definición no dice a qué estado vuelve.");
    }

    public static function motivo(string $mensaje): self
    {
        return new self($mensaje);
    }

    public static function autoaprobacion(string $mensaje): self
    {
        return new self($mensaje);
    }

    public static function regla(string $mensaje): self
    {
        return new self($mensaje);
    }
}
