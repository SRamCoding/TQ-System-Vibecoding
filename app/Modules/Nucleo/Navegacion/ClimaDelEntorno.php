<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\ClimaDelDibujo;
use App\Modules\Nucleo\Desarrollo\Piezas\TemperaturaDelClima;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Lo que el navegador necesita para poner el clima en la página (plan 1.4u, D1): el clima y la temperatura los consulta
 * él a Open-Meteo (nada pasa por el servidor); aquí, si está prendido, la ubicación de respaldo, los umbrales de calor y
 * frío y lo que el modo desarrollo haya forzado (D5). Si es de noche lo pone la vista de navegación (D2).
 */
final class ClimaDelEntorno
{
    /** Desde cuántos grados es calor (el sol, más fuerte) y hasta cuántos es frío (un poco de escarcha). */
    public const int CALOR = 30;

    public const int FRIO = 15;

    /**
     * Los grados de cada temperatura elegida en el modo desarrollo, para la luz de la barra (plan 1.4ac): los extremos
     * del simulador (10 °C, frío entero; 34 °C, calor entero) y el templado al medio.
     */
    public const array GRADOS_FORZADOS = ['frio' => 10, 'templado' => 22, 'calor' => 34];

    /** Cuántos minutos recuerda el navegador el clima antes de volver a consultar. */
    public const int MINUTOS_RECORDADO = 30;

    /**
     * @return array{respaldo: string, forzado: ?string, temperatura: ?string, grados: array<string, int>, calor: int, frio: int, minutos: int}|null
     */
    public static function datos(?User $usuario): ?array
    {
        $parametros = app(ParametrosService::class);

        if (! $usuario instanceof User || ! $parametros->booleano('interfaz.clima_activo')) {
            return null;
        }

        $forzado = ModoDesarrollo::eleccion(ClimaDelDibujo::CLAVE);
        $temperatura = ModoDesarrollo::eleccion(TemperaturaDelClima::CLAVE);

        return [
            'respaldo' => $parametros->texto('interfaz.clima_ubicacion_respaldo'),
            'forzado' => array_key_exists($forzado ?? '', ClimaDelDibujo::CLIMAS) ? $forzado : null,
            'temperatura' => array_key_exists($temperatura ?? '', TemperaturaDelClima::TEMPERATURAS) ? $temperatura : null,
            'grados' => self::GRADOS_FORZADOS,
            'calor' => self::CALOR,
            'frio' => self::FRIO,
            'minutos' => self::MINUTOS_RECORDADO,
        ];
    }
}
