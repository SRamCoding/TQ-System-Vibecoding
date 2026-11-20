<?php

declare(strict_types=1);

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// MVP rápido (ficha t1-licitaciones v2): cada mañana, las convocatorias nuevas del SEACE a la bandeja.
Artisan::command('seace:leer', function () {
    try {
        $r = app(ConvocatoriasSeaceService::class)->sincronizar();
        $this->info("Leídas {$r['leidas']}: {$r['nuevas']} nuevas para la bandeja.");
    } catch (RuntimeException $e) {
        $this->warn($e->getMessage());
    }
})->purpose('Trae las convocatorias nuevas del SEACE (datos abiertos del OECE) a la bandeja');
Schedule::command('seace:leer')->dailyAt('07:00');
