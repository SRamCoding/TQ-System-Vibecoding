<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Un cambio de estado que las reglas del documento no permiten (plan 1.5). No se escribe nada. */
final class TransicionRechazada extends ErrorDeNegocio
{
    public static function noDisponible(string $codigo, string $documento, string $estado): self
    {
        return new self("No se puede hacer «{$codigo}»: {$documento} está en «{$estado}».");
    }

    public static function sinPermiso(string $transicion): self
    {
        return new self("No tienes permiso para «{$transicion}» en este documento.");
    }

    public static function delSistema(string $transicion): self
    {
        return new self("«{$transicion}» lo hace el sistema, no una persona.");
    }

    public static function conAprobacion(string $transicion): self
    {
        return new self("«{$transicion}» se completa con las aprobaciones, no directamente.");
    }

    public static function noEsDelSistema(string $transicion): self
    {
        return new self("«{$transicion}» la hace una persona: el sistema no la ejecuta en su nombre.");
    }

    public static function condicion(string $mensaje): self
    {
        return new self($mensaje);
    }

    public static function faltaMotivo(string $transicion): self
    {
        return new self("Elige el motivo para «{$transicion}».");
    }

    public static function motivoSobrante(string $transicion): self
    {
        return new self("«{$transicion}» no lleva motivo.");
    }

    public static function motivoNoValido(string $transicion): self
    {
        return new self("Ese motivo no está entre los de «{$transicion}» o ya no se usa.");
    }

    public static function detalleCorto(int $minimo): self
    {
        return new self("Con «Otro», describe el motivo en al menos {$minimo} caracteres.");
    }

    public static function detalleSobrante(): self
    {
        return new self('El detalle solo se escribe cuando el motivo es «Otro».');
    }

    public static function sinEstadoInicial(string $documento): self
    {
        return new self("{$documento} no tiene estado inicial: falta sincronizar sus estados.");
    }

    /**
     * @param  non-empty-list<string>  $documentos
     */
    public static function documentosVivos(string $documento, array $documentos): self
    {
        $ultimo = array_pop($documentos);
        $lista = $documentos === [] ? $ultimo : implode(', ', $documentos)." y {$ultimo}";

        return new self("No se puede anular {$documento}: lo usan {$lista}. Anúlalos primero.");
    }
}
