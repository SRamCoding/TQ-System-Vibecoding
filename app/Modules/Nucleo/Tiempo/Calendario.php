<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use Carbon\CarbonImmutable;

/**
 * El día de negocio es el de Lima (docs/convenciones/base-de-datos.md), sin importar la zona del servidor ni la de
 * `config('app.timezone')`: a las 20:00 de Lima ya es mañana en UTC, y una vigencia (`hasta` es el último día
 * vigente) no puede cambiar de día antes de tiempo. Única definición de «hoy» (plan 1.4, D7). Respeta el reloj fijo
 * de las pruebas (`CarbonImmutable::setTestNow`).
 */
final class Calendario
{
    public const ZONA = 'America/Lima';

    /** Ahora mismo, en hora de Lima. */
    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA);
    }

    /** Hoy en Lima, `Y-m-d`. */
    public static function hoy(): string
    {
        return self::ahora()->toDateString();
    }

    /** Ayer en Lima, `Y-m-d`: el último día vigente de algo que se cierra hoy y deja de valer ya. */
    public static function ayer(): string
    {
        return self::ahora()->subDay()->toDateString();
    }
}
