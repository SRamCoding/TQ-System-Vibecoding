<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Qué pasó al pedir una sugerencia (plan 1.11, D8). Solo `Encolado` llama al modelo; en los demás, el formulario sigue
 * en manual sin error para el usuario.
 */
enum ResultadoPedidoIa: string
{
    case Encolado = 'encolado';
    case Apagado = 'apagado';
    case SinAutorizacion = 'sin_autorizacion';
    case SinModelo = 'sin_modelo';
    case TopeAlcanzado = 'tope_alcanzado';
    case OrigenNoPermitido = 'origen_no_permitido';
    case AdjuntoNoPermitido = 'adjunto_no_permitido';
    case SinClave = 'sin_clave';
    case SinPrecio = 'sin_precio';

    /** Para la pantalla: qué ve quien pidió la sugerencia. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Encolado => 'La sugerencia está en camino.',
            self::Apagado => 'La sugerencia automática no está disponible.',
            self::SinAutorizacion => 'La sugerencia automática espera la autorización de Gerencia.',
            self::SinModelo => 'La sugerencia automática no tiene un modelo configurado.',
            self::TopeAlcanzado => 'La sugerencia automática llegó a su tope del mes.',
            self::OrigenNoPermitido => 'Este registro no admite sugerencias automáticas.',
            self::AdjuntoNoPermitido => 'Este archivo no se puede enviar para una sugerencia automática.',
            self::SinClave, self::SinPrecio => 'La sugerencia automática no está disponible.',
        };
    }
}
