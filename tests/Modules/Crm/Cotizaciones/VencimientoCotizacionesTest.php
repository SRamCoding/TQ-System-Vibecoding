<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Console\VencerCotizaciones;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * El vencimiento automático (plan 2.22, D10): la tarea diaria pasa a vencidas las emitidas cuyo vencimiento ya pasó.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
});

test('el día del vencimiento sigue emitida; al día siguiente la tarea la vence, una vez', function () {
    $c = test()->cot->emitida();
    $borrador = test()->cot->crear();

    test()->travelTo(CarbonImmutable::parse('2026-11-27 23:00', 'America/Lima'));
    test()->artisan('crm:vencer-cotizaciones')->assertSuccessful();
    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::EMITIDA);

    test()->travelTo(CarbonImmutable::parse('2026-11-28 00:10', 'America/Lima'));
    test()->artisan('crm:vencer-cotizaciones')->expectsOutput('Cotizaciones vencidas: 1.')->assertSuccessful();
    test()->artisan('crm:vencer-cotizaciones')->expectsOutput('Cotizaciones vencidas: 0.')->assertSuccessful();

    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::VENCIDA)
        ->and($borrador->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR)
        ->and(test()->cot->servicio()->nuevaVersion($c->id, CotizacionesDePrueba::motivoVersion(), null, test()->cot->ejecutivo)->numero_version)->toBe(2);
});

test('la tarea está programada cada día', function () {
    $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'crm:vencer-cotizaciones'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('10 0 * * *')
        ->and(class_exists(VencerCotizaciones::class))->toBeTrue();
});
