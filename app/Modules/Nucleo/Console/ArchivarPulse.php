<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Historico\HistoricoFallido;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La limpieza diaria de Pulse (plan 1.12, D6; plan 1.4y, D6: «que se almacene como histórico todo»). Pulse ya no borra
 * al azar (`pulse.ingest.trim.lottery` en 0): aquí, sus mediciones de más de 7 días (`pulse_entries`, `pulse_values`)
 * pasan al archivo histórico y salen de la base. Después recorta sus resúmenes (`pulse_aggregates`) como lo hacía él,
 * con el mismo corte: se calculan de las mediciones, que ya quedaron guardadas. Si el histórico falla, no borra nada.
 */
final class ArchivarPulse extends Command
{
    public const int DIAS = 7;

    protected $signature = 'nucleo:archivar-pulse';

    protected $description = 'Pasa al archivo histórico las mediciones de Pulse de más de 7 días';

    public function handle(ArchivoHistoricoService $historico): int
    {
        $ahora = Calendario::ahora();
        $limite = $ahora->subDays(self::DIAS)->getTimestamp();
        // La conexión de Pulse (`PULSE_DB_CONNECTION`; sin ella, la de siempre).
        $base = DB::connection(config('pulse.storage.database.connection'));

        try {
            $archivadas = $historico->archivar('pulse_entries', $base->table('pulse_entries')->where('timestamp', '<=', $limite))
                + $historico->archivar('pulse_values', $base->table('pulse_values')->where('timestamp', '<=', $limite));

            // Los resúmenes, con el mismo corte (revisión de arquitectura: `Storage::trim()` de Pulse recalculaba el suyo
            // con la hora real después de archivar, y borraba sin archivar lo que caía entre los dos cortes). Como Pulse:
            // de cada periodo, lo más viejo que el periodo o que el límite.
            foreach ($base->table('pulse_aggregates')->distinct()->pluck('period') as $periodo) {
                $base->table('pulse_aggregates')->where('period', $periodo)
                    ->where('bucket', '<=', max($ahora->subMinutes((int) $periodo)->getTimestamp(), $limite))
                    ->delete();
            }
        } catch (Throwable $error) {
            // El mensaje solo si es nuestro: el de otro error puede traer SQL o valores, y esta salida va a los registros.
            $this->error('No se archivó nada: '.($error instanceof HistoricoFallido ? $error->getMessage() : $error::class));

            return self::FAILURE;
        }

        $this->info("Mediciones de Pulse archivadas: {$archivadas}.");

        return self::SUCCESS;
    }
}
