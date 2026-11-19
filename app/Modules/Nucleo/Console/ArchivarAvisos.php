<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Historico\HistoricoFallido;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La limpieza diaria de avisos (plan 1.4y, D4 y D6): los leídos hace más de `avisos.dias_leidos` días pasan al archivo
 * histórico y salen de la base. Los no leídos nunca salen. Si el histórico falla, no se borra nada y ya avisó el servicio.
 *
 * Vive aquí y no en NotificacionesService (pedido del desarrollador, 2026-10-29): es mantenimiento, no avisar, y el
 * histórico avisa por AlertasService, que avisa por NotificacionesService. Ponerlo en el constructor de este último
 * cerraba el ciclo NotificacionesService → ArchivoHistoricoService → AlertasService → NotificacionesService y el
 * contenedor se quedaba armándolo sin fin (la app no arrancaba). Lo vigila `ContenedorSinCiclosTest`.
 */
final class ArchivarAvisos extends Command
{
    protected $signature = 'nucleo:archivar-avisos';

    protected $description = 'Pasa al archivo histórico los avisos leídos hace más del plazo';

    public function handle(ParametrosService $parametros, ArchivoHistoricoService $historico): int
    {
        $limite = Calendario::ahora()->subDays($parametros->entero('avisos.dias_leidos'));

        try {
            $archivados = $historico->archivar('notificaciones', DB::table('notificaciones')->whereNotNull('read_at')->where('read_at', '<', $limite));
            $this->info("Avisos archivados: {$archivados}.");
        } catch (Throwable $error) {
            // El mensaje solo si es nuestro: el de otro error puede traer SQL o valores, y esta salida va a los registros.
            $this->error('No se archivó nada: '.($error instanceof HistoricoFallido ? $error->getMessage() : $error::class));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
