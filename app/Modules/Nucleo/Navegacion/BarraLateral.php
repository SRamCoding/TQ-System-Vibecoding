<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Los anchos de la barra lateral (plan 1.3d, D5 y D6). Único lugar donde se escriben: la migración
 * los repite en su CHECK, el servicio los valida y la vista de navegación los da al arrastre. También el corte entre
 * celular y escritorio (plan 1.4f, D9).
 */
final class BarraLateral
{
    /**
     * Desde este ancho de pantalla, en px, es escritorio: el `lg` de Filament, donde su barra lateral deja de ser un menú
     * desplegable. Por debajo, celular: sin barra ni sus ítems del menú, con la casa para volver al inicio y cada caja
     * en la misma pestaña. Todas las reglas por ancho lo leen de aquí.
     */
    public const int CORTE_ESCRITORIO = 1024;

    /** La barra plegada, solo con íconos (el plegado de Filament). */
    public const int PLEGADA = 0;

    public const int ANCHO_INICIAL = 320;

    public const int ANCHO_MINIMO = 200;

    public const int ANCHO_MAXIMO = 480;

    /** Si se suelta el borde por debajo de este ancho, la barra se pliega. */
    public const int UMBRAL_PLEGADO = 140;

    /** La consulta de CSS del celular: hasta un píxel antes del corte. */
    public static function consultaCelular(): string
    {
        return '(max-width: '.(self::CORTE_ESCRITORIO - 1).'px)';
    }

    /** La consulta de CSS del escritorio. */
    public static function consultaEscritorio(): string
    {
        return '(min-width: '.self::CORTE_ESCRITORIO.'px)';
    }

    public static function esValido(int $ancho): bool
    {
        return $ancho === self::PLEGADA || ($ancho >= self::ANCHO_MINIMO && $ancho <= self::ANCHO_MAXIMO);
    }
}
