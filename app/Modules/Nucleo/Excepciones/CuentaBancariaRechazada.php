<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Una operación sobre una cuenta bancaria no procede (plan 1.3b, D5). Los mensajes no repiten el número completo:
 * se muestran en pantalla y podrían llegar a un registro.
 */
final class CuentaBancariaRechazada extends ErrorDeNegocio
{
    public static function noExiste(): self
    {
        return new self('La cuenta bancaria no existe.');
    }

    public static function partyInactiva(): self
    {
        return new self('La persona o empresa está desactivada: actívala antes de registrarle cuentas.');
    }

    public static function bancoNoDisponible(): self
    {
        return new self('El banco elegido no está registrado como banco vigente (rol Banco).');
    }

    public static function numeroInvalido(): self
    {
        return new self('El número de cuenta lleva solo dígitos (se aceptan guiones y espacios al escribirlo), hasta 30.');
    }

    public static function cciInvalido(): self
    {
        return new self('El CCI tiene exactamente 20 dígitos.');
    }

    public static function cciNoCorresponde(): self
    {
        return new self('El CCI no es válido: revisa sus dígitos de control.');
    }

    public static function monedaNoDisponible(string $codigo): self
    {
        return new self("La moneda «{$codigo}» no existe o está desactivada.");
    }

    public static function detraccionFueraDelBanco(): self
    {
        return new self('La cuenta de detracciones solo puede ser del Banco de la Nación.');
    }

    public static function detraccionEnOtraMoneda(): self
    {
        return new self('La cuenta de detracciones es en moneda local.');
    }

    public static function detraccionPrincipal(): self
    {
        return new self('La cuenta de detracciones no puede ser la principal: no es una cuenta de pago normal.');
    }

    public static function yaTieneDetraccion(): self
    {
        return new self('Ya tiene una cuenta de detracciones activa: desactívala antes de registrar otra.');
    }

    public static function yaRegistrada(): self
    {
        return new self('Esa cuenta ya está registrada para esta persona o empresa en ese banco.');
    }

    public static function deOtraPersona(): self
    {
        return new self('Esa cuenta (o ese CCI) ya está registrada a nombre de otra persona o empresa. Revisa el número; si es correcto, consúltalo con Tesorería antes de registrarla.');
    }

    public static function cciRepetido(): self
    {
        return new self('Ese CCI ya está en otra cuenta de esta persona o empresa.');
    }

    public static function datosFijos(): self
    {
        return new self('El banco, el número, la moneda y el titular de una cuenta no cambian: desactívala y registra otra.');
    }

    public static function cciYaRegistrado(): self
    {
        return new self('El CCI ya está registrado: no se cambia. Si es otro, desactiva la cuenta y registra una nueva.');
    }

    public static function inactiva(): self
    {
        return new self('La cuenta está desactivada.');
    }

    public static function yaEsPrincipal(): self
    {
        return new self('Esa cuenta ya es la principal en su moneda.');
    }

    public static function principalCambiada(): self
    {
        return new self('Otra persona acaba de cambiar la cuenta principal. Vuelve a abrir la ficha.');
    }

    public static function yaEstaEnEseEstado(bool $activa): self
    {
        return new self($activa ? 'La cuenta ya está activa.' : 'La cuenta ya está desactivada.');
    }

    public static function enUso(): self
    {
        return new self('Esa cuenta ya se usa en pagos o cobros: desactívala en vez de borrarla.');
    }
}
