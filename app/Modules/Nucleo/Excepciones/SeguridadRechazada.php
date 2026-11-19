<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio de usuarios, roles, permisos o separación de funciones no procede (plan 1.4). */
final class SeguridadRechazada extends ErrorDeNegocio
{
    public static function usuarioNoExiste(): self
    {
        return new self('El usuario no existe.');
    }

    public static function rolNoExiste(): self
    {
        return new self('El rol no existe.');
    }

    public static function personaNoExiste(): self
    {
        return new self('La persona no existe.');
    }

    public static function noEsPersonaNatural(): self
    {
        return new self('Un usuario es una persona: elige su ficha de DNI, carné de extranjería o pasaporte, no la de una empresa ni un RUC.');
    }

    public static function personaInactiva(): self
    {
        return new self('La persona está desactivada: actívala antes de darle una cuenta.');
    }

    public static function personaConCuenta(): self
    {
        return new self('Esta persona ya tiene una cuenta (también cuenta su otra ficha, la de DNI o la de RUC 10).');
    }

    public static function correoInvalido(): self
    {
        return new self('El correo no es válido.');
    }

    public static function correoRepetido(string $correo): self
    {
        return new self("Ya hay una cuenta con el correo {$correo}.");
    }

    /**
     * @param  list<string>  $faltas
     */
    public static function claveDebil(array $faltas): self
    {
        return new self(implode(' ', $faltas));
    }

    public static function claveCorta(int $minimo): self
    {
        return new self("La clave necesita al menos {$minimo} caracteres.");
    }

    public static function yaHayAdministrador(): self
    {
        return new self('Ya hay un administrador activo: el siguiente lo crea él, desde «Usuarios».');
    }

    public static function yaHayGerenteGeneral(): self
    {
        return new self('Ya hay un gerente general activo: el siguiente se lo asigna un administrador, desde «Usuarios».');
    }

    public static function sinRolDeFabrica(string $rol): self
    {
        return new self("Falta el rol «{$rol}»: corre primero `php artisan db:seed --class=\"App\\Modules\\Nucleo\\database\\seeders\\NucleoSeeder\"`.");
    }

    public static function usuarioDeSistema(): self
    {
        return new self('El usuario de sistema no se edita, no inicia sesión y no recibe roles.');
    }

    public static function aSiMismo(): self
    {
        return new self('Nadie cambia sus propios roles, permisos ni su cuenta: pídeselo a otro administrador.');
    }

    public static function parPropio(): self
    {
        return new self('Tienes un cruce abierto de ese par: lo desactiva otra persona.');
    }

    public static function controlPropio(): self
    {
        return new self('Es tu propio cruce: su control lo registra otra persona.');
    }

    public static function rolPropio(string $rol): self
    {
        return new self("Tienes el rol «{$rol}»: sus permisos los cambia otro administrador.");
    }

    public static function rolAdministradorFijo(): self
    {
        return new self('El rol «administrador» no se renombra ni cambia sus permisos: vienen fijos con el sistema.');
    }

    public static function sinAdministrador(): self
    {
        return new self('No se puede: quedaría nadie activo que pueda gestionar usuarios.');
    }

    public static function sinGerenteGeneral(): self
    {
        return new self('No se puede: quedaría sin gerente general activo. Asígnale primero el rol a quien lo reemplaza.');
    }

    public static function rolGerenciaFijo(): self
    {
        return new self('El rol «gerencia general» no se renombra: viene con el sistema. Sus permisos sí se cambian.');
    }

    public static function rolGerenciaSoloGlobal(): self
    {
        return new self('El rol «gerencia general» es de toda la empresa: no se da dentro de un ámbito.');
    }

    public static function yaTieneElRol(string $rol): self
    {
        return new self("Ya tiene el rol «{$rol}».");
    }

    public static function noTieneElRol(string $rol): self
    {
        return new self("No tiene el rol «{$rol}».");
    }

    public static function nombreRolRepetido(string $nombre): self
    {
        return new self("Ya hay un rol llamado «{$nombre}».");
    }

    public static function faltaNombre(): self
    {
        return new self('Escribe un nombre.');
    }

    public static function ambitoDesconocido(string $tipo): self
    {
        return new self("«{$tipo}» no es un ámbito en el que se pueda acotar un rol.");
    }

    public static function ambitoNoDisponible(): self
    {
        return new self('El ámbito no existe o está desactivado.');
    }

    public static function fechasInvalidas(): self
    {
        return new self('«Hasta» no puede ser anterior a «desde» ni a hoy.');
    }

    public static function rolEnAmbitoRepetido(): self
    {
        return new self('Ya tiene ese rol en ese ámbito en fechas que se cruzan.');
    }

    public static function asignacionNoExiste(): self
    {
        return new self('La asignación del rol en el ámbito no existe.');
    }

    public static function asignacionTerminada(): self
    {
        return new self('Esa asignación ya terminó.');
    }

    public static function yaEstaEnEseEstado(bool $activo): self
    {
        return new self($activo ? 'La cuenta ya está activa.' : 'La cuenta ya está desactivada.');
    }

    public static function mismoPermiso(): self
    {
        return new self('Un permiso no es incompatible consigo mismo.');
    }

    public static function faltaMotivo(): self
    {
        return new self('Explica por qué esos dos permisos no deberían juntarse en una persona.');
    }

    public static function parRepetido(): self
    {
        return new self('Ese par de permisos ya está registrado como incompatible.');
    }

    public static function parNoExiste(): self
    {
        return new self('El par de permisos incompatibles no existe.');
    }

    public static function parYaDesactivado(): self
    {
        return new self('El par ya está desactivado.');
    }

    public static function cruceNoExiste(): self
    {
        return new self('El cruce de funciones no existe.');
    }

    public static function cruceCerrado(): self
    {
        return new self('El cruce ya está cerrado: la persona ya no junta esos dos permisos.');
    }

    public static function controlVacio(): self
    {
        return new self('Describe el control compensatorio: qué se revisa y cada cuánto.');
    }

    public static function controlIncompleto(): self
    {
        return new self('Un control compensatorio lleva su descripción y su responsable.');
    }

    public static function responsableNoDisponible(): self
    {
        return new self('El responsable del control tiene que ser una persona con cuenta activa.');
    }

    public static function responsableEsLaPersona(string $persona): self
    {
        return new self("{$persona} no puede ser responsable del control de su propio cruce.");
    }

    public static function controlVencido(): self
    {
        return new self('El control no puede vencer antes de hoy.');
    }
}
