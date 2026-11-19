<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use RuntimeException;

/**
 * La definición en código choca con lo que ya hay en la base (plan 1.5, D1 y D2): quitar un estado con documentos,
 * cambiar una regla que ya quedó escrita en el historial. El sincronizador no cambia nada.
 */
final class SincronizacionEstadosRechazada extends RuntimeException
{
    public static function estadoConDocumentos(string $estado, string $documento, int $cantidad): self
    {
        $siguen = $cantidad === 1 ? '1 documento de tipo '.$documento.' sigue' : "{$cantidad} documentos de tipo {$documento} siguen";

        return new self("No se puede quitar el estado «{$estado}»: {$siguen} en él.");
    }

    public static function cancelacionConTransiciones(string $estado, string $documento): self
    {
        return new self("No se puede cambiar si «{$estado}» de {$documento} es de anulación: hay transiciones que llegan a él y su regla de motivo depende de eso.");
    }

    public static function transicionConHistorial(string $transicion, string $documento, string $que): self
    {
        return new self("No se puede cambiar {$que} de la transición «{$transicion}» de {$documento}: ya está en el historial con la regla anterior.");
    }

    public static function efectoNoRegistrado(string $clave, string $transicion, string $documento): self
    {
        return new self("La transición «{$transicion}» de {$documento} usa el efecto «{$clave}», que nadie registró.");
    }

    public static function revisionSinDocumento(string $clave): self
    {
        return new self("Hay una revisión antes de anular para «{$clave}», que no tiene definición de estados.");
    }

    public static function efectoVivoSinRegistro(string $clave): self
    {
        return new self("Hay documentos con el efecto «{$clave}» aplicado y nadie lo registra: no se podrían anular.");
    }

    public static function sinAlias(string $modelo): self
    {
        return new self("El modelo {$modelo} no tiene alias en el morph map: su tipo de documento no se puede guardar.");
    }
}
