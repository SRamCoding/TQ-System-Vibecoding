<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Excepciones\FuenteTipoCambioNoDisponible;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Trae el tipo de cambio del día desde SUNAT (ADR 0012). Lo corre el programador varias veces al día;
 * repetirlo no duplica. Firma el usuario de sistema. Si falla y no hay tipo de cambio de hoy, avisa (plan 1.10).
 */
final class SincronizarTipoCambio extends Command
{
    protected $signature = 'nucleo:sincronizar-tipo-cambio';

    protected $description = 'Trae el tipo de cambio del día desde SUNAT';

    public function handle(TipoCambioService $tipoCambio, AlertasService $alertas): int
    {
        try {
            $resultado = $tipoCambio->sincronizar();
        } catch (FuenteTipoCambioNoDisponible $e) {
            // Sin datos sensibles: solo el motivo.
            Log::warning('Tipo de cambio: no se pudo sincronizar.', ['motivo' => $e->getMessage()]);
            $this->error($e->getMessage());
            $this->avisarSiFalta($tipoCambio, $alertas);

            return self::FAILURE;
        }

        $this->info("Tipo de cambio: {$resultado->value}.");

        return self::SUCCESS;
    }

    /**
     * Plan 1.10: si falla el último intento del día y todavía no hay tipo de cambio de hoy, avisa (una vez al día). Los
     * fallos de antes no: el siguiente intento suele traerlo, y mientras tanto vale el de hasta 4 días antes.
     */
    private function avisarSiFalta(TipoCambioService $tipoCambio, AlertasService $alertas): void
    {
        $ahora = Calendario::ahora();
        $hoy = $ahora->startOfDay();
        $ultima = max(array_map('strval', (array) config('nucleo.tipo_cambio.horas')));

        if ($ahora->format('H:i') < $ultima || $tipoCambio->hayDe($hoy)) {
            return;
        }

        $alertas->emitir(
            AlertasDelNucleo::TIPO_CAMBIO_FALTANTE,
            $hoy->toDateString(),
            ['fecha' => $hoy->format('d/m/Y')],
            enlace: route('filament.app.configuracion.resources.tipos-cambio.index'),
        );
    }
}
