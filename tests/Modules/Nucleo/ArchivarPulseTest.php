<?php

declare(strict_types=1);

use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Pulse no borra al azar: sus mediciones de más de 7 días pasan al archivo histórico (plan 1.12; plan 1.4y, D6).
 * Mediciones de prueba inventadas.
 */

beforeEach(function () {
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    test()->travelTo(CarbonImmutable::parse('2027-03-20 03:45', 'America/Lima'));
});

function medicionPulse(string $tabla, int $diasAtras, string $clave): void
{
    $fila = ['timestamp' => now()->subDays($diasAtras)->getTimestamp(), 'type' => 'slow_request', 'key' => $clave];
    DB::table($tabla)->insert($tabla === 'pulse_entries' ? [...$fila, 'value' => 1200] : [...$fila, 'value' => 'v']);
}

it('Pulse no borra al azar: lo hace la limpieza de cada noche', function () {
    expect(config('pulse.ingest.trim.lottery'))->toBe([0, 1]);
});

it('las mediciones de más de 7 días pasan al archivo histórico y salen de la base; las recientes quedan', function () {
    medicionPulse('pulse_entries', 9, '["GET","/vieja"]');
    medicionPulse('pulse_entries', 2, '["GET","/reciente"]');
    medicionPulse('pulse_values', 10, 'servidor-viejo');

    $this->artisan('nucleo:archivar-pulse')->expectsOutput('Mediciones de Pulse archivadas: 2.')->assertSuccessful();

    expect(DB::table('pulse_entries')->pluck('key')->all())->toBe(['["GET","/reciente"]'])
        ->and(DB::table('pulse_values')->count())->toBe(0)
        ->and(collect(Storage::disk('historico')->allFiles())->map(fn (string $archivo): string => explode('/', $archivo)[0])->sort()->values()->all())->toBe(['pulse_entries', 'pulse_values'])
        ->and(app(ArchivoHistoricoService::class)->leer(Storage::disk('historico')->allFiles('pulse_entries')[0])[0]['key'])->toBe('["GET","/vieja"]');
});

it('sin la clave de cifrado no archiva ni borra nada', function () {
    config(['backup.backup.password' => null]);
    medicionPulse('pulse_entries', 9, '["GET","/vieja"]');

    $this->artisan('nucleo:archivar-pulse')->assertFailed();

    expect(DB::table('pulse_entries')->count())->toBe(1);
});

it('el programador la corre cada noche a las 03:45', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains((string) $evento->command, 'nucleo:archivar-pulse'));

    expect($evento->expression)->toBe('45 3 * * *')->and($evento->timezone)->toBe('America/Lima');
});

it('recorta los resúmenes con el mismo corte que las mediciones, y no borra una medición sin archivarla', function () {
    medicionPulse('pulse_entries', 8, '["GET","/vieja"]');
    DB::table('pulse_aggregates')->insert([
        ['bucket' => now()->subDays(8)->getTimestamp(), 'period' => 60, 'type' => 'slow_request', 'key' => 'k', 'aggregate' => 'count', 'value' => 1, 'count' => 1],
        ['bucket' => now()->subMinutes(30)->getTimestamp(), 'period' => 60, 'type' => 'slow_request', 'key' => 'k', 'aggregate' => 'count', 'value' => 1, 'count' => 1],
    ]);

    $this->artisan('nucleo:archivar-pulse')->assertSuccessful();

    expect(DB::table('pulse_aggregates')->count())->toBe(1)
        ->and(collect(Storage::disk('historico')->allFiles('pulse_entries'))->flatMap(fn (string $archivo): array => app(ArchivoHistoricoService::class)->leer($archivo))->count())->toBe(1);
});
