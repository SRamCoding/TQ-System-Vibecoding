<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\HuerfanosAdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SaludService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * La revisión semanal de huérfanos del disco de adjuntos (plan 1.12, D4): los pasa a cuarentena y borra los que ya
 * cumplieron su plazo. `--simular` solo lista lo que haría. Se pausa con `adjuntos.revision_huerfanos_activa` (al
 * restaurar una base, `docs/operaciones/restauracion.md`).
 */
final class RevisarHuerfanosAdjuntos extends Command
{
    protected $signature = 'nucleo:revisar-huerfanos {--simular : Solo lista lo que haría, sin mover ni borrar}';

    protected $description = 'Pasa a cuarentena los archivos de adjuntos sin registro y borra los de cuarentena vencida';

    public function handle(HuerfanosAdjuntosService $huerfanos, SaludService $salud, ParametrosService $parametros, ArchivoHistoricoService $historico, AlertasService $alertas): int
    {
        if ($this->option('simular')) {
            $resultado = $huerfanos->revisar(simular: true);
            $this->line('Pasarían a cuarentena: '.$resultado['encontrados'].($resultado['detenida'] ? ' (pasan el tope: la corrida se detendría y avisaría)' : ''));
            foreach ($resultado['huerfanos'] as $ruta) {
                $this->line('  '.$ruta);
            }
            $this->line('Se borrarían de la cuarentena: '.count($resultado['por_borrar']));
            foreach ($resultado['por_borrar'] as $ruta) {
                $this->line('  '.$ruta);
            }

            return self::SUCCESS;
        }

        if (! $parametros->booleano('adjuntos.revision_huerfanos_activa')) {
            $this->info('La revisión de huérfanos está pausada (adjuntos.revision_huerfanos_activa).');

            return self::SUCCESS;
        }

        // Revisión de arquitectura de la 1.12: una corrida a mano mientras corre la programada movía los mismos archivos.
        // `withoutOverlapping()` solo cuida las del programador; el candado, las dos.
        $candado = Cache::lock('nucleo:revisar-huerfanos', 6 * 3600);

        if (! $candado->get()) {
            $this->error('Ya hay una revisión de huérfanos en marcha. Espera a que termine.');

            return self::FAILURE;
        }

        try {
            // En el registro de corridas, solo las cantidades: las rutas no salen de este comando.
            $resultado = $salud->registrar(HuerfanosAdjuntosService::TAREA, function () use ($huerfanos, $historico, $alertas): array {
                $resultado = $huerfanos->revisar(historico: $historico, alertas: $alertas);
                unset($resultado['huerfanos'], $resultado['por_borrar']);

                return $resultado;
            });
        } finally {
            $candado->release();
        }

        $this->info($resultado['detenida']
            ? "Detenida: {$resultado['encontrados']} huérfanos pasan el tope. No se movió nada; se avisó."
            : "A cuarentena: {$resultado['movidos']}. Borrados de la cuarentena: {$resultado['borrados']}.");

        return self::SUCCESS;
    }
}
