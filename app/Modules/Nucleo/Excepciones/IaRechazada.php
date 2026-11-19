<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Una configuración de IA que las reglas no permiten (plan 1.11). No se guarda nada. */
final class IaRechazada extends ErrorDeNegocio
{
    public static function modeloInactivo(string $modelo): self
    {
        return new self("El modelo «{$modelo}» está desactivado: elige uno activo.");
    }

    public static function respaldoIgual(): self
    {
        return new self('El modelo de respaldo tiene que ser otro que el principal.');
    }

    public static function variableClave(string $variable): self
    {
        return new self("«{$variable}» no sirve como variable de la clave: va como IA_CLAVE_ seguido de mayúsculas, números o guion bajo.");
    }

    /** @param list<string> $validas */
    public static function implementacionDesconocida(string $implementacion, array $validas): self
    {
        return new self("«{$implementacion}» no es una implementación de IA del sistema. Puedes usar: ".implode(', ', $validas).'.');
    }

    public static function modeloDeOtroProveedor(): self
    {
        return new self('Un modelo no cambia de proveedor: crea el modelo en el proveedor que corresponde.');
    }

    /** Ajuste del 2026-10-28: sin precio vigente no hay llamada; un modelo propio lleva su precio en 0. */
    public static function sinPrecio(string $modelo): self
    {
        return new self("El modelo «{$modelo}» no tiene precio vigente: cárgalo antes de asignarlo (un modelo propio, con precio 0).");
    }

    public static function topeNegativo(): self
    {
        return new self('El tope del mes no puede ser negativo: 0 deja el proceso sin llamar.');
    }

    public static function encenderSinAutorizacion(): self
    {
        return new self('Este proceso envía datos fuera de la empresa: Gerencia tiene que autorizarlo antes de encenderlo.');
    }

    public static function defectoNoSeDesactiva(string $modelo): self
    {
        return new self("«{$modelo}» es el modelo por defecto: marca otro como defecto antes de desactivarlo.");
    }

    public static function precioPasado(): self
    {
        return new self('Un precio nuevo empieza hoy o después: el costo de las llamadas ya hechas no se recalcula.');
    }

    public static function precioRepetido(string $fecha): self
    {
        return new self("Ese modelo ya tiene un precio desde el {$fecha}.");
    }

    public static function precioNegativo(): self
    {
        return new self('El precio por millón de tokens no puede ser negativo (un modelo propio va en 0).');
    }

    public static function sugerenciaResuelta(string $numero): self
    {
        return new self("La sugerencia {$numero} ya se resolvió.");
    }

    public static function correccionInvalida(string $motivo): self
    {
        return new self("La corrección no se puede guardar: {$motivo}.");
    }

    public static function resultadoNoPermitido(string $motivo): self
    {
        return new self("El registro creado no se puede asociar: {$motivo}.");
    }

    public static function sinPermisoParaResolver(string $proceso): self
    {
        return new self("No tienes el permiso para resolver las sugerencias de «{$proceso}».");
    }
}
