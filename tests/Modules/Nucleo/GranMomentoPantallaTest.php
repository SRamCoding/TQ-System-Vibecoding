<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\GranMomentoEnPantalla;
use App\Modules\Nucleo\Models\MomentoVisto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Services\GranMomentoService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Plan 1.4d, D5 y D9: el gran momento en la pantalla. Sale solo a quien le toca, esperando a que la pestaña se vea;
 * se marca primero y solo empieza si fue la primera marca. Lo que el navegador pinta lo revisa el desarrollador.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

function elDia(string $fecha): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse("{$fecha} 09:00", Calendario::ZONA));
    Carbon::setTestNow(Carbon::parse("{$fecha} 09:00", Calendario::ZONA));
}

function inicioDelMomento(User $usuario): string
{
    return (string) test()->actingAs($usuario)->get('/')->assertOk()->getContent();
}

test('el 28 de diciembre, a quien no lo vio, la página trae el gran momento esperando a que la pestaña se vea', function () {
    elDia('2026-12-28');

    $html = inicioDelMomento(User::factory()->create());

    expect($html)->toContain('data-tq-gran-momento-pendiente')
        ->toContain('data-tq-gran-momento="navidad-establo"')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('Feliz Navidad')
        // Espera: no empieza solo, lo empieza el evento cuando el servidor confirma la primera marca.
        ->toContain('x-on:tq-gran-momento-empezar.window="empezar()"')
        ->toContain('x-trap.noscroll="abierto"');
});

test('fuera de su ventana, ya visto o con la ambientación apagada, no sale', function () {
    elDia('2026-12-15');
    expect(inicioDelMomento(User::factory()->create()))->not->toContain('data-tq-gran-momento-pendiente');

    elDia('2026-12-28');
    $vista = User::factory()->create();
    $pendiente = app(GranMomentoService::class)->pendientePara($vista, Calendario::ahora());
    MomentoVisto::query()->create(['user_id' => $vista->id, 'gran_momento_id' => $pendiente?->id]);
    expect(inicioDelMomento($vista))->not->toContain('data-tq-gran-momento-pendiente');

    $apagada = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($apagada, 'apariencia.ambientacion', false);
    expect(inicioDelMomento($apagada))->not->toContain('data-tq-gran-momento-pendiente');
});

test('marcar dice si fue la primera vez: la segunda pestaña lo quita sin mostrarlo', function () {
    elDia('2026-12-28');
    $persona = User::factory()->create();
    $id = (int) app(GranMomentoService::class)->pendientePara($persona, Calendario::ahora())?->id;

    $primera = Livewire::actingAs($persona)->test(GranMomentoEnPantalla::class, ['granMomento' => $id]);
    $segunda = Livewire::actingAs($persona)->test(GranMomentoEnPantalla::class, ['granMomento' => $id]);

    expect($primera->instance()->marcar())->toBeTrue()
        ->and($segunda->instance()->marcar())->toBeFalse()
        ->and(MomentoVisto::query()->where('user_id', $persona->id)->count())->toBe(1);
});

test('una marca que no procede (otro gran momento) no rompe: no se muestra', function () {
    elDia('2026-12-28');

    expect(Livewire::actingAs(User::factory()->create())->test(GranMomentoEnPantalla::class, ['granMomento' => 999999])->instance()->marcar())->toBeFalse();
});

test('la ayuda de «Ambientación de la fecha» dice que apagarla también quita el gran momento', function () {
    expect(app(RegistroPreferencias::class)->definicion('apariencia.ambientacion')?->ayuda)->toContain('también quita el gran momento');
});
