<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Respaldos\RespaldoFallido;
use App\Modules\Nucleo\Respaldos\RespaldosService;
use App\Modules\Nucleo\Services\AlertasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * El respaldo de cada noche (plan 1.12, D3). El programador lo llama a diario y `respaldos.frecuencia` decide si ya
 * toca; `--forzar` respalda igual (para TI, antes de una actualización). Si falla, el aviso ya salió desde el servicio.
 */
final class Respaldar extends Command
{
    protected $signature = 'nucleo:respaldar {--forzar : Respalda aunque todavía no toque}';

    protected $description = 'Respalda la base y los adjuntos, cifrados, si toca según la frecuencia';

    public function handle(RespaldosService $respaldos, AlertasService $alertas): int
    {
        // Revisión de arquitectura de la 1.12: `withoutOverlapping()` solo cuida las corridas del programador; una a mano
        // (`--forzar`) mientras corre la programada sacaba dos respaldos a la vez. El candado vale para las dos.
        $candado = Cache::lock('nucleo:respaldar', 6 * 3600);

        if (! $candado->get()) {
            $this->error('Ya hay un respaldo en marcha. Espera a que termine.');

            return self::FAILURE;
        }

        try {
            if (! $respaldos->respaldarSiToca($alertas, (bool) $this->option('forzar'))) {
                $this->info('Todavía no toca respaldar (frecuencia '.$respaldos->frecuencia()->value.').');

                return self::SUCCESS;
            }
        } catch (Throwable $error) {
            // Solo el mensaje propio (sin clave); de otro error, su clase: un error de la base trae su SQL (regla 10).
            $this->error('El respaldo falló: '.($error instanceof RespaldoFallido ? $error->getMessage() : $error::class.'. El detalle está en el registro de errores.'));

            return self::FAILURE;
        } finally {
            $candado->release();
        }

        $this->info('Respaldo hecho en el disco «'.$respaldos->disco().'».');

        return self::SUCCESS;
    }
}
