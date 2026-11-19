<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\EstacionDelInicio;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Qué estación visten las cajas del inicio (plan 1.4r) y el entorno (plan 1.4s). Solo la estación: las fechas especiales
 * (Navidad, Fiestas Patrias…) no les cambian nada (1.4r, D2). Sigue la hora de lo decorativo, así la fecha simulada del
 * modo desarrollo también cambia la estación; en el modo, además, se elige con un clic (1.4r, D6). Con la preferencia
 * apagada, ninguna: todo como antes. Un solo interruptor para las cajas y el entorno (1.4s, D4).
 */
final class EstacionDeLaApariencia
{
    public const string PREFERENCIA = 'apariencia.estacion';

    public static function para(User $usuario): ?Temporada
    {
        if (! app(PreferenciasUsuarioService::class)->siNo($usuario, self::PREFERENCIA)) {
            return null;
        }

        $elegida = ModoDesarrollo::eleccion(EstacionDelInicio::CLAVE);

        return Temporada::tryFrom($elegida ?? '') ?? Temporada::deLaFecha(ModoDesarrollo::ahoraDecorativo());
    }
}
