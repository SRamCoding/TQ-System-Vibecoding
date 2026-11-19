<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio al organigrama (unidades, puestos, jefaturas) no procede (plan 1.4, D5). */
final class OrganigramaRechazado extends ErrorDeNegocio
{
    public static function unidadNoExiste(): self
    {
        return new self('La unidad organizativa no existe.');
    }

    public static function puestoNoExiste(): self
    {
        return new self('El puesto no existe.');
    }

    public static function faltanDatos(): self
    {
        return new self('Una unidad lleva código y nombre; un puesto, nombre.');
    }

    public static function superiorInactiva(): self
    {
        return new self('La unidad de la que dependería está desactivada: actívala primero.');
    }

    public static function ciclo(string $unidad, string $superior): self
    {
        return new self("«{$unidad}» no puede depender de «{$superior}»: «{$superior}» ya depende de ella.");
    }

    public static function codigoRepetido(string $codigo): self
    {
        return new self("Ya hay una unidad con el código {$codigo}.");
    }

    public static function nombreUnidadRepetido(string $nombre): self
    {
        return new self("Ya hay una unidad llamada «{$nombre}» en el mismo nivel (sin contar mayúsculas).");
    }

    public static function nombrePuestoRepetido(string $nombre): self
    {
        return new self("La unidad ya tiene un puesto llamado «{$nombre}» (sin contar mayúsculas).");
    }

    public static function unidadInactiva(): self
    {
        return new self('La unidad está desactivada: actívala antes de agregarle puestos o designar su jefatura.');
    }

    public static function unidadYaEnEseEstado(string $unidad, bool $activa): self
    {
        return new self("La unidad «{$unidad}» ya está ".($activa ? 'activa.' : 'desactivada.'));
    }

    public static function puestoYaEnEseEstado(string $puesto, bool $activo): self
    {
        return new self("El puesto «{$puesto}» ya está ".($activo ? 'activo.' : 'desactivado.'));
    }

    public static function unidadConDependientes(string $unidad): self
    {
        return new self("«{$unidad}» tiene unidades o puestos activos: desactívalos o muévelos primero.");
    }

    public static function puestoDeOtraUnidad(): self
    {
        return new self('El puesto de jefatura tiene que ser de la misma unidad.');
    }

    public static function puestoInactivo(string $puesto): self
    {
        return new self("El puesto «{$puesto}» está desactivado.");
    }

    public static function jefaturaConVariosOcupantes(string $puesto): self
    {
        return new self("El puesto «{$puesto}» tiene más de un ocupante activo: una jefatura es de una sola persona.");
    }

    public static function jefaturaOcupada(string $puesto): self
    {
        return new self("«{$puesto}» es un puesto de jefatura y ya tiene ocupante: una jefatura es de una sola persona.");
    }

    public static function puestoEnUso(string $puesto): self
    {
        return new self("«{$puesto}» tiene ocupantes activos o es la jefatura de su unidad: reasígnalos primero.");
    }
}
