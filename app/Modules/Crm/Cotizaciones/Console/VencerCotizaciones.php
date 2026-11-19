<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Console;

use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use Illuminate\Console\Command;

/**
 * El vencimiento automático (plan 2.22, D10; v4 parte 7): las emitidas cuyo vencimiento ya pasó, a vencidas. Corre cada
 * día a las 00:10 de Lima; repetirla no cambia nada. Firma el usuario de sistema.
 */
final class VencerCotizaciones extends Command
{
    protected $signature = 'crm:vencer-cotizaciones';

    protected $description = 'Pasa a vencidas las cotizaciones emitidas cuyo vencimiento ya pasó';

    public function handle(CotizacionesService $cotizaciones): int
    {
        $this->info("Cotizaciones vencidas: {$cotizaciones->vencerLasVencidas()}.");

        return self::SUCCESS;
    }
}
