<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Una operación sobre una party o sus roles no procede (plan 1.3). */
final class PartyRechazada extends ErrorDeNegocio
{
    /** Plan 1.4h, D2: el usuario de sistema no tiene ficha de persona. */
    public static function cuentaSinFicha(): self
    {
        return new self('Tu cuenta no tiene ficha de persona: no hay teléfono que registrar.');
    }

    /** Plan 1.15, D3: la que dice `empresa.ficha`. */
    public static function esLaFichaDeLaEmpresa(string $accion): self
    {
        return new self("Es la ficha de Techquk: elige otra en Configuración inicial antes de {$accion}.");
    }

    public static function noExiste(): self
    {
        return new self('La persona o empresa no existe.');
    }

    public static function duplicada(string $etiqueta, bool $activa): self
    {
        return new self($activa
            ? "Ya está registrada: {$etiqueta}."
            : "Ya está registrada, pero está inactiva: {$etiqueta}.");
    }

    public static function tipoDocumentoNoDisponible(string $codigo): self
    {
        return new self("El tipo de documento «{$codigo}» no existe o está desactivado.");
    }

    public static function faltaTipoPersona(): self
    {
        return new self('Elige si es persona natural o jurídica: el documento no lo dice.');
    }

    public static function tipoPersonaNoCorresponde(string $esperado): self
    {
        return new self("Ese documento es de una {$esperado}.");
    }

    public static function faltanNombres(): self
    {
        return new self('Una persona natural lleva nombres y apellidos.');
    }

    public static function faltaRazonSocial(): self
    {
        return new self('Una persona jurídica lleva razón social.');
    }

    public static function juridicaConNombres(): self
    {
        return new self('Una persona jurídica no lleva nombres ni apellidos: va su razón social.');
    }

    /** D4: en una natural, la razón social es «apellidos nombres» y la arma el sistema. */
    public static function razonSocialDeNatural(): self
    {
        return new self('En una persona natural la razón social no se escribe: se arma con apellidos y nombres.');
    }

    public static function campoNoEditable(string $campo): self
    {
        return new self("El campo «{$campo}» no se cambia por esta vía.");
    }

    public static function ubigeoNoDisponible(): self
    {
        return new self('El ubigeo elegido no existe o está desactivado.');
    }

    public static function emailInvalido(string $email): self
    {
        return new self("«{$email}» no es un correo válido.");
    }

    public static function conCuentaActiva(): self
    {
        return new self('Esta persona tiene una cuenta de usuario activa: desactívala primero en «Usuarios».');
    }

    public static function yaEstaEnEseEstado(bool $activa): self
    {
        return new self($activa ? 'Ya está activa.' : 'Ya está desactivada.');
    }

    /** D5: lo usado no se borra; la base lo impide con sus llaves foráneas. */
    public static function enUso(): self
    {
        return new self('Ya se usó en otros registros y no se puede borrar. Desactívala para que no se elija más.');
    }

    public static function inactiva(): self
    {
        return new self('Está desactivada: actívala antes de asignarle un rol.');
    }

    public static function rubroNoDisponible(): self
    {
        return new self('Ese rubro no existe o está desactivado.');
    }

    public static function rolNoDisponible(string $codigo): self
    {
        return new self("El rol «{$codigo}» no existe o está desactivado.");
    }

    /** Registrar con roles es una sola operación: si un rol no se puede asignar, no queda nada a medias. */
    public static function rolNoDisponibleAlRegistrar(string $rol): self
    {
        return new self("No se registró nada: el rol {$rol} ya no está disponible.");
    }

    public static function periodoSeCruza(string $rol): self
    {
        return new self("Ya tiene el rol {$rol} en esas fechas. Un rol no puede tener dos periodos que se crucen.");
    }

    public static function sinPeriodoAbierto(string $rol): self
    {
        return new self("No tiene el rol {$rol} abierto.");
    }

    public static function hastaAntesDeDesde(string $desde): self
    {
        return new self("La fecha de cierre no puede ser anterior al inicio del periodo ({$desde}).");
    }
}
