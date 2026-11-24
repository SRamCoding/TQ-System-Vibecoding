<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Observación 9.8 (MV-102): el interruptor «Datos de demo» del modo desarrollo. Encendidos, la sesión usa la base de
 * siempre (con la demo); apagados, la base limpia (solo configuración y lo leído del OECE). Vive en la sesión: no borra
 * nada ni reconstruye la base. Sin el modo desarrollo, siempre encendidos.
 */
final class DatosDemo
{
    public const string CONEXION_DEMO = 'pgsql';

    public const string CONEXION_LIMPIA = 'pgsql_limpia';

    private const string SESION = 'nucleo.datos_demo_apagados';

    public static function encendidos(): bool
    {
        return ! (ModoDesarrollo::activo() && session()->get(self::SESION) === true);
    }

    public static function conexion(): string
    {
        return self::encendidos() ? self::CONEXION_DEMO : self::CONEXION_LIMPIA;
    }

    public static function apagar(): void
    {
        abort_unless(ModoDesarrollo::activo() && self::limpiaLista(), 404);
        session()->put(self::SESION, true);
    }

    public static function prender(): void
    {
        session()->forget(self::SESION);
    }

    /** La base limpia existe y está migrada. */
    public static function limpiaLista(): bool
    {
        try {
            return DB::connection(self::CONEXION_LIMPIA)->getSchemaBuilder()->hasTable('users')
                && DB::connection(self::CONEXION_LIMPIA)->table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /** El nombre de la base en uso, para la barra de desarrollo. */
    public static function nombreBase(): string
    {
        return (string) config('database.connections.'.self::conexion().'.database');
    }
}
