<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http;

use App\Modules\Nucleo\Desarrollo\BasesDeDatos;
use App\Modules\Nucleo\Desarrollo\DatosDemo;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Observación 9.8 (MV-102): con «Datos de demo» apagados en el modo desarrollo, la petición usa la base limpia (va
 * después de la sesión, que sigue en la base de siempre). Al terminar una petición que cambió algo, la configuración
 * que cambió se copia a la otra base.
 */
final class UsarBaseDeDatos
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DatosDemo::encendidos() && DatosDemo::limpiaLista()) {
            DB::setDefaultConnection(DatosDemo::CONEXION_LIMPIA);
            config(['database.default' => DatosDemo::CONEXION_LIMPIA]);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET') || ! ModoDesarrollo::disponible()) {
            return;
        }

        try {
            app(BasesDeDatos::class)->sincronizarCambios(DB::getDefaultConnection());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
