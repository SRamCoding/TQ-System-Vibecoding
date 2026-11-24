<?php

declare(strict_types=1);

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Crm\Licitaciones\Services\HistoricoSeaceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// MVP rápido (ficha t1-licitaciones; observación 7.4): cada hora, lo nuevo del SEACE (solo lo que falta leer).
Artisan::command('seace:leer', function () {
    try {
        $r = app(ConvocatoriasSeaceService::class)->sincronizar('programada');
        $this->info("Leídas {$r['leidas']}: {$r['nuevas']} nuevas ({$r['a_bandeja']} a la bandeja) y {$r['actualizadas']} actualizadas.");
    } catch (RuntimeException $e) {
        $this->warn($e->getMessage());
    }
})->purpose('Trae las convocatorias nuevas del SEACE (datos abiertos del OECE) a la bandeja');
Schedule::command('seace:leer')->hourly()->withoutOverlapping();

// Observación 8.12 (ficha t1-licitaciones): el histórico del OECE desde sus descargas masivas. Primero el ensayo (solo
// cuenta); la carga es retomable (sigue desde donde quedó) y se pausa desde Configuración → Histórico del SEACE.
Artisan::command('seace:historico {anio=2025} {--ensayo : Solo cuenta procesos, peso y tiempo, sin guardar} {--verificar : Compara totales y revisa 5 procesos al azar}', function (int $anio) {
    $s = app(HistoricoSeaceService::class);

    if ($this->option('verificar')) {
        $v = $s->verificar($anio);
        $this->info("OECE (en tus regiones): {$v['oece']} · guardados como histórico: {$v['guardados']}");
        foreach ($v['muestra'] as $m) {
            $this->line(($m['ok'] ? '✓ ' : '✗ ')."{$m['ocid']} · {$m['detalle']}");
        }

        return;
    }

    if ($this->option('ensayo')) {
        $r = $s->ensayo($anio, fn (int $mes, array $f) => $this->line(sprintf('%02d/%d · %s procesos · %s en tus regiones · %.1f MB · %d s', $mes, $anio, number_format($f['procesos']), number_format($f['en_regiones']), $f['bytes'] / 1048576, $f['segundos'])));
        $this->info(sprintf('Total %d: %s procesos, %s en tus regiones (%s ya estaban), %.1f MB, %d s leyendo; la carga tomaría unos %d min.',
            $anio, number_format($r['procesos']), number_format($r['enRegiones']), number_format($r['yaEstaban']), $r['bytes'] / 1048576, $r['segundos'], (int) ceil($r['estimadoCarga'] / 60)));

        return;
    }

    $r = $s->cargar($anio, fn (int $mes, int $posicion) => $this->line(sprintf('%02d/%d · proceso %s', $mes, $anio, number_format($posicion))));
    $this->info($r['pausada'] ? "Pausada: {$r['guardados']} guardados en esta corrida." : "Listo: {$r['guardados']} guardados.");
})->purpose('Histórico del OECE por año (descargas masivas), en silencio y retomable');
