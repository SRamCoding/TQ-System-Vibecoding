<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\SincronizarTipoCambio;
use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Enums\ResultadoSincronizacion;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\FuenteTipoCambioNoDisponible;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\Pages\ListarTiposCambio;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use App\Modules\Nucleo\TipoCambio\FuenteSunat;
use App\Modules\Nucleo\TipoCambio\FuenteTipoCambio;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Tipo de cambio automático desde SUNAT (ADR 0012). Ninguna prueba llama a SUNAT: Http::fake.
 */

const URL_SUNAT_PRUEBA = 'https://sunat.prueba/tipoCambio.txt';

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv', 'nucleo.tipo_cambio.url_sunat' => URL_SUNAT_PRUEBA]);
    test()->seed(NucleoSeeder::class);
});

/**
 * Lo que responde SUNAT a partir de ahora. Un segundo Http::fake() no reemplaza al primero (se suma
 * detrás), así que el falso es uno solo y lee la respuesta vigente.
 */
function sunatResponde(string $cuerpo, int $estado = 200): void
{
    $yaHabiaFalso = app()->bound('pruebas.sunat');
    app()->instance('pruebas.sunat', [$cuerpo, $estado]);

    if (! $yaHabiaFalso) {
        Http::fake([URL_SUNAT_PRUEBA => fn () => Http::response(...app('pruebas.sunat'))]);
    }
}

function tcGuardado(string $fecha): ?TipoCambio
{
    return TipoCambio::query()->where('fecha', $fecha)->first();
}

test('la fuente lee el archivo de SUNAT y lleva compra y venta a cuatro decimales', function () {
    sunatResponde("10/10/2026|3.45|3.457|\n");

    $lectura = app(FuenteTipoCambio::class)->vigente();

    expect(app(FuenteTipoCambio::class))->toBeInstanceOf(FuenteSunat::class)
        ->and($lectura->moneda)->toBe('USD')
        ->and($lectura->fecha->toDateString())->toBe('2026-10-10')
        ->and($lectura->compra)->toBe('3.4500')
        ->and($lectura->venta)->toBe('3.4570');
});

test('si SUNAT falla o cambia el formato, el error lo dice y no se guarda nada', function (string $cuerpo, int $estado, string $mensaje) {
    sunatResponde($cuerpo, $estado);

    expect(fn () => app(TipoCambioService::class)->sincronizar())->toThrow(FuenteTipoCambioNoDisponible::class, $mensaje);
    expect(TipoCambio::query()->count())->toBe(0);
})->with([
    'error del servidor' => ['', 503, 'HTTP 503'],
    'página en vez de texto' => ['<html>Mantenimiento</html>', 200, 'cambió el formato'],
    'fecha imposible' => ['31/02/2026|3.45|3.457|', 200, 'fecha inválida'],
    'coma decimal' => ['10/10/2026|3,45|3,457|', 200, 'cambió el formato'],
]);

test('sincronizar crea el TC del día con origen SUNAT, a nombre del usuario de sistema', function () {
    sunatResponde('10/10/2026|3.45|3.457|');

    $resultado = app(TipoCambioService::class)->sincronizar();

    expect($resultado)->toBe(ResultadoSincronizacion::Creado)
        ->and(tcGuardado('2026-10-10')?->origen)->toBe(OrigenTipoCambio::Sunat)
        ->and(tcGuardado('2026-10-10')?->created_by)->toBe(app(UsuarioSistema::class)->id());
});

test('repetir no duplica; si SUNAT corrige el valor, se actualiza', function () {
    sunatResponde('10/10/2026|3.45|3.457|');
    app(TipoCambioService::class)->sincronizar();

    expect(app(TipoCambioService::class)->sincronizar())->toBe(ResultadoSincronizacion::SinCambios);

    sunatResponde('10/10/2026|3.45|3.459|');

    expect(app(TipoCambioService::class)->sincronizar())->toBe(ResultadoSincronizacion::Actualizado)
        ->and(tcGuardado('2026-10-10')?->venta)->toBe('3.4590')
        ->and(TipoCambio::query()->count())->toBe(1);
});

test('nunca pisa un valor registrado a mano', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-10-10'), '3.4400', '3.4500', OrigenTipoCambio::Manual);
    sunatResponde('10/10/2026|3.45|3.457|');

    expect(app(TipoCambioService::class)->sincronizar())->toBe(ResultadoSincronizacion::SeRespetaManual)
        ->and(tcGuardado('2026-10-10')?->venta)->toBe('3.4500')
        ->and(tcGuardado('2026-10-10')?->origen)->toBe(OrigenTipoCambio::Manual);
});

test('el registro manual de respaldo sí reemplaza lo que trajo SUNAT', function () {
    sunatResponde('10/10/2026|3.45|3.457|');
    app(TipoCambioService::class)->sincronizar();

    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-10-10'), '3.4400', '3.4500', OrigenTipoCambio::Manual);

    expect(tcGuardado('2026-10-10')?->origen)->toBe(OrigenTipoCambio::Manual);
});

test('el comando sincroniza y, si SUNAT falla, termina con error y deja el motivo en el registro', function () {
    sunatResponde('10/10/2026|3.45|3.457|');
    test()->artisan('nucleo:sincronizar-tipo-cambio')->expectsOutputToContain('creado')->assertSuccessful();

    sunatResponde('', 503);
    test()->artisan('nucleo:sincronizar-tipo-cambio')->expectsOutputToContain('HTTP 503')->assertFailed();
});

test('si falla el último intento del día y no hay tipo de cambio de hoy, avisa al administrador una vez (plan 1.10)', function () {
    test()->travelTo(CarbonImmutable::parse('2026-03-10 09:00', 'America/Lima'));
    Notification::fake();
    $admin = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    sunatResponde('', 503);

    // Un fallo de la mañana no avisa: el siguiente intento suele traerlo.
    test()->artisan('nucleo:sincronizar-tipo-cambio')->assertFailed();
    expect(Notificacion::query()->where('type', AlertasDelNucleo::TIPO_CAMBIO_FALTANTE)->exists())->toBeFalse();

    test()->travelTo(CarbonImmutable::parse('2026-03-10 19:00', 'America/Lima'));
    test()->artisan('nucleo:sincronizar-tipo-cambio')->assertFailed();
    test()->artisan('nucleo:sincronizar-tipo-cambio')->assertFailed();

    $aviso = Notificacion::query()->where('type', AlertasDelNucleo::TIPO_CAMBIO_FALTANTE)->sole();

    expect($aviso->notifiable_id)->toBe($admin->id)
        ->and($aviso->data['title'])->toBe('No se pudo traer el tipo de cambio de hoy (10/03/2026)')
        ->and($aviso->data['actions'][0]['url'])->toEndWith('/configuracion/tipos-cambio');
});

test('si el último intento falla pero ya hay tipo de cambio de hoy, no avisa', function () {
    test()->travelTo(CarbonImmutable::parse('2026-03-10 19:00', 'America/Lima'));
    User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-03-10'), '3.7000', '3.7100', OrigenTipoCambio::Manual);
    sunatResponde('', 503);

    test()->artisan('nucleo:sincronizar-tipo-cambio')->assertFailed();

    expect(Notificacion::query()->where('type', AlertasDelNucleo::TIPO_CAMBIO_FALTANTE)->exists())->toBeFalse();
});

test('el programador lo corre cada 30 minutos de 07:00 a 19:00 en hora de Lima, sin solaparse (plan 1.10)', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'nucleo:sincronizar-tipo-cambio'));
    $expresiones = $eventos->map(fn ($e): string => $e->expression)->values()->all();

    expect($expresiones)->toHaveCount(25)
        ->and($expresiones[0])->toBe('0 7 * * *')
        ->and($expresiones[1])->toBe('30 7 * * *')
        ->and($expresiones[24])->toBe('0 19 * * *')
        ->and($eventos->every(fn ($e): bool => $e->withoutOverlapping && $e->timezone === 'America/Lima'))->toBeTrue();
});

test('la acción «Traer de SUNAT ahora» se registra, pide permiso y sincroniza', function () {
    sunatResponde('10/10/2026|3.45|3.457|');

    expect(app(RegistroAcciones::class)->todas())->toHaveKey('nucleo.sincronizar-tipo-cambio', SincronizarTipoCambio::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.sincronizar-tipo-cambio', [], User::factory()->create()))
        ->toThrow(AccionRechazada::class);

    $resultado = app(EjecutorAcciones::class)->ejecutar('nucleo.sincronizar-tipo-cambio', [], User::factory()->create()->givePermissionTo('tipos_cambio.registrar'));

    expect($resultado)->toBe(ResultadoSincronizacion::Creado);
});

test('el botón de la pantalla trae el TC y avisa; si SUNAT falla, avisa el motivo', function () {
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'tipos_cambio.registrar']));

    sunatResponde('10/10/2026|3.45|3.457|');
    Livewire::test(ListarTiposCambio::class)
        ->callAction('nucleoSincronizarTipoCambio')
        ->assertNotified('Tipo de cambio del día traído de SUNAT.');

    sunatResponde('', 503);
    Livewire::test(ListarTiposCambio::class)
        ->callAction('nucleoSincronizarTipoCambio')
        ->assertNotified('SUNAT respondió con error (HTTP 503) al consultar el tipo de cambio.');
});
