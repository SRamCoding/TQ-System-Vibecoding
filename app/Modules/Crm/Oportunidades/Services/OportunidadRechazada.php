<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;

/** Una regla de Oportunidades impidió la operación (plan 2.21). El mensaje se muestra tal cual. */
final class OportunidadRechazada extends ErrorDeNegocio
{
    /** Un motivo propio de una regla (plan 2.21d: quién cambia la foto). */
    public static function conMotivo(string $mensaje): self
    {
        return new self($mensaje);
    }

    public static function sinPermisoParaEditar(): self
    {
        return new self('Solo su ejecutivo, o quien puede editar todas las oportunidades, puede cambiarla.');
    }

    public static function ejecutivoNoDisponible(): self
    {
        return new self('El ejecutivo elegido no está activo o no puede atender cuentas: elige otro.');
    }

    public static function cerrada(string $numero): self
    {
        return new self("La oportunidad {$numero} ya está cerrada: no se cambia.");
    }

    public static function decisorDeOtraCuenta(): self
    {
        return new self('El decisor tiene que ser un contacto de la empresa de la cuenta.');
    }

    public static function fechaCierreFutura(): self
    {
        return new self('La fecha de cierre real no puede ser futura.');
    }

    public static function noEsAvance(string $codigo): self
    {
        return new self("«{$codigo}» no es un avance de etapa.");
    }

    public static function faltaGanador(): self
    {
        return new self('Si la ganó otra empresa, elige cuál.');
    }

    public static function faltaPrecio(): self
    {
        return new self('Escribe el precio con que ganó y su moneda, o marca «precio desconocido».');
    }

    public static function ganadorSinRespuesta(): self
    {
        return new self('Si no la ganó otra empresa, o no se sabe, no se elige competidor ni precio.');
    }

    public static function precioDesconocidoConPrecio(): self
    {
        return new self('Marcaste «precio desconocido» y escribiste un precio: deja solo uno de los dos.');
    }

    public static function precioIncompleto(): self
    {
        return new self('El precio va con su moneda, y mayor que cero.');
    }

    /** Plan 2.22, P5: con cotización aceptada no se escribe otro monto; si el cliente negoció otro, otra versión. */
    public static function montoDeLaAceptada(CotizacionAceptada $aceptada): self
    {
        return new self("El monto final es el total de la cotización aceptada {$aceptada->identificador} ({$aceptada->moneda} {$aceptada->total}). Si el cliente negoció otro precio, haz una versión nueva y acepta esa.");
    }

    public static function montoNoPositivo(): self
    {
        return new self('El monto tiene que ser mayor que cero.');
    }

    public static function fichaInactiva(): self
    {
        return new self('La ficha del competidor está inactiva en Personas y empresas: elige otra o reactívala.');
    }

    public static function competidorNoExiste(): self
    {
        return new self('Ese competidor ya no está en la lista: vuelve a abrir la oportunidad.');
    }

    public static function competidorRepetido(): self
    {
        return new self('Ese competidor ya está en la lista de esta oportunidad.');
    }
}
