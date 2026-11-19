<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

use App\Modules\Crm\Cuentas\Enums\MotivoNoUsable;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** Una regla de Cuentas impidió la operación (plan 2.20). El mensaje se muestra tal cual. */
final class CuentaRechazada extends ErrorDeNegocio
{
    /** El caso 1 de la D2, también cuando dos personas la crean a la vez. */
    public static function yaExiste(ResumenCuenta $cuenta): self
    {
        return new self("Esta cuenta ya existe: {$cuenta->etiqueta}, que atiende {$cuenta->ejecutivoNombre}. Ábrela en vez de crear otra.");
    }

    /** Para trabajo nuevo (plan 2.21, D6): el mensaje dice qué reactivar. */
    public static function noUsable(MotivoNoUsable $motivo): self
    {
        return new self($motivo->mensaje());
    }

    public static function fichaInactiva(): self
    {
        return new self('Esta ficha está inactiva: revisa sus datos antes de reactivarla en Personas y empresas.');
    }

    public static function fichaRegistradaRecien(): self
    {
        return new self('Alguien acaba de registrar esta empresa en Personas y empresas: vuelve a escribir el RUC para continuar con su ficha.');
    }

    public static function ejecutivoNoDisponible(): self
    {
        return new self('El ejecutivo elegido no está activo o no puede atender cuentas: elige otro.');
    }

    public static function nivelDeGobiernoFalta(): self
    {
        return new self('Una cuenta pública necesita su nivel de gobierno.');
    }

    public static function nivelDeGobiernoEnPrivada(): self
    {
        return new self('Una cuenta privada no lleva nivel de gobierno.');
    }

    public static function noExiste(): self
    {
        return new self('La cuenta no existe.');
    }

    public static function sinPermisoParaEditar(): self
    {
        return new self('Solo puedes editar las cuentas que atiendes.');
    }

    public static function versionSoloParaUna(): self
    {
        return new self('La versión leída solo vale al reasignar una cuenta.');
    }

    public static function yaEstaInactiva(): self
    {
        return new self('La cuenta ya está inactiva.');
    }

    public static function yaEstaActiva(): self
    {
        return new self('La cuenta ya está activa.');
    }

    public static function inactivaNoSeEdita(): self
    {
        return new self('La cuenta está inactiva: reactívala antes de editarla.');
    }

    public static function motivoNoValido(): self
    {
        return new self('Elige un motivo de la lista para desactivar la cuenta.');
    }

    public static function detalleSobrante(): self
    {
        return new self('El detalle solo se escribe cuando el motivo es «Otro».');
    }

    public static function detalleCorto(int $minimo): self
    {
        return new self("Con «Otro», cuenta el motivo en al menos {$minimo} caracteres.");
    }

    /** @param  list<string>  $impedimentos */
    public static function conTrabajoEnCurso(array $impedimentos): self
    {
        return new self('No se puede desactivar: tiene '.implode(', ', $impedimentos).'. Ciérralos o pásalos a otra cuenta primero.');
    }

    public static function fichaSoloPorError(): self
    {
        return new self('La ficha solo se desactiva junto con la cuenta si el motivo es «Creada por error (RUC equivocado)».');
    }

    public static function fechaClienteFutura(): self
    {
        return new self('La fecha «cliente desde» no puede ser futura.');
    }

    public static function faltaComentario(): self
    {
        return new self('Cuenta por qué es cliente (por ejemplo, «cliente desde antes del sistema; contrato 2024»).');
    }

    public static function yaTieneMarcaManual(): self
    {
        return new self('La cuenta ya está marcada como cliente a mano.');
    }

    public static function sinMarcaManual(): self
    {
        return new self('La cuenta no tiene una marca de cliente puesta a mano.');
    }

    public static function tieneGanadas(): self
    {
        return new self('La cuenta es cliente por oportunidades ganadas: no se le quita la marca. Si una ganada no lo era, anula su cierre en Oportunidades.');
    }

    public static function motivoQuitarCorto(int $minimo): self
    {
        return new self("Cuenta por qué se quita la marca, en al menos {$minimo} caracteres.");
    }
}
