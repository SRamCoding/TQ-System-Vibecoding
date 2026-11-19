<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\RenovarClave;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\Servers;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserJobs;
use Laravel\Pulse\Recorders\UserRequests;

/*
 * Pulse sin datos personales (plan 1.12, D6; corrección del desarrollador): con su configuración de fábrica guardaría
 * nombre y correo de cada persona, el mensaje de los errores y la URL completa de los pedidos. Persona inventada.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

/** Todo lo que Pulse guardó, como texto. */
function pulseGuardado(): string
{
    Pulse::ingest();

    return (string) json_encode([
        DB::table('pulse_entries')->get(),
        DB::table('pulse_aggregates')->get(),
        DB::table('pulse_values')->get(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

it('no guarda nombres, correos, mensajes de error, cadenas de consulta ni a quién hizo el pedido', function () {
    config([
        'pulse.recorders.'.SlowRequests::class.'.threshold' => 0,
        'pulse.recorders.'.SlowOutgoingRequests::class.'.threshold' => 0,
    ]);
    // En las pruebas Pulse está apagado (phpunit.xml); aquí se enciende con sus registradores de verdad.
    Pulse::register(config('pulse.recorders'));
    Pulse::startRecording();
    Http::fake();
    $persona = User::factory()->create(['name' => 'Rosa Inventada', 'email' => 'rosa@inventada.test'])->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    $this->actingAs($persona)->get('/mis-preferencias?buscar=Rosa+Inventada')->assertOk();
    report(new RuntimeException('Sueldo de Rosa Inventada, rosa@inventada.test'));
    Http::get('https://latido.prueba/codigo-secreto-123?nombre=Rosa');

    $guardado = pulseGuardado();

    expect($guardado)
        ->toContain('slow_request')
        ->toContain('RuntimeException')
        ->toContain('latido.prueba')
        ->not->toContain('Rosa')
        ->not->toContain('rosa@')
        ->not->toContain('Sueldo')
        ->not->toContain('buscar=')
        ->not->toContain('codigo-secreto')
        ->not->toContain('user_request')
        ->not->toContain('slow_user_request')
        ->not->toContain('user_job');
});

it('apaga las tarjetas de uso por persona, servidores y caché, y guarda solo una semana', function () {
    expect(config('pulse.recorders.'.UserRequests::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.recorders.'.UserJobs::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.recorders.'.Servers::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.recorders.'.CacheInteractions::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.storage.trim.keep'))->toBe('7 days');

    $tablero = (string) file_get_contents(resource_path('views/vendor/pulse/dashboard.blade.php'));
    expect($tablero)->not->toContain('pulse.usage')->not->toContain('pulse.servers')->not->toContain('pulse.cache');
});

it('entra a Pulse solo quien ve la página de salud', function () {
    $this->actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR))->get('/pulse')->assertOk();
    $this->actingAs(User::factory()->create())->get('/pulse')->assertForbidden();
});

it('quien debe renovar su clave no entra a Pulse: va a renovarla, como en el resto del panel', function () {
    $persona = User::factory()->create(['debe_cambiar_clave' => true])->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    $this->actingAs($persona)->get('/pulse')->assertRedirect(RenovarClave::getUrl());
});
