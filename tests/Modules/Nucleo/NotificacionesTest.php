<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Jobs\EnviarCorreoDeAviso;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\AvisoPorCorreo;
use App\Modules\Nucleo\Notificaciones\DefinicionAviso;
use App\Modules\Nucleo\Notificaciones\GrupoAvisos;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\Services\NotificacionesService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * M8 · Notificaciones (plan 1.8, D6 a D8), con avisos de prueba; las alertas del núcleo (1.10) son las de AlertasTest. El correo se captura (Notification::fake); la cola corre en el acto (`sync`).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    $registro = app(RegistroAvisos::class);
    $registro->registrarGrupo(new GrupoAvisos('pruebas', 'Pruebas'));
    $registro->registrar(new DefinicionAviso('nucleo.prueba-normal', 'pruebas'));
    $registro->registrar(new DefinicionAviso('nucleo.prueba-urgente', 'pruebas', obligatorio: true));
    Notification::fake();
    Filament::setCurrentPanel('app');
});

function noAvisar(User $persona, string $clave = 'nucleo.prueba-normal', string $unica = 'orden:15:por-aprobar'): array
{
    return app(NotificacionesService::class)->avisar($clave, $persona, 'Tienes una orden por aprobar', 'OC-2026-0015 por S/ 4 500.00', 'https://techquk.test/ordenes/15', $unica);
}

test('el aviso llega a la campana y por correo, con su título, su texto y su enlace', function () {
    $persona = User::factory()->create();

    noAvisar($persona);

    $aviso = $persona->unreadNotifications()->sole();
    expect($aviso)->toBeInstanceOf(Notificacion::class)
        ->and($aviso->data['format'])->toBe('filament')
        ->and($aviso->data['title'])->toBe('Tienes una orden por aprobar')
        ->and($aviso->correo_enviado_at)->not->toBeNull();
    Notification::assertSentTo($persona, AvisoPorCorreo::class, fn (AvisoPorCorreo $correo): bool => str_contains((string) json_encode($correo->toMail($persona)->toArray()), 'OC-2026-0015'));
});

test('con el correo apagado para su grupo llega solo a la campana; el obligatorio llega por correo igual', function () {
    $persona = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'avisos.correo_pruebas', false);

    noAvisar($persona);
    Notification::assertNothingSent();
    expect($persona->notifications()->count())->toBe(1);

    noAvisar($persona, 'nucleo.prueba-urgente', 'fianza:3:vence');
    Notification::assertSentToTimes($persona, AvisoPorCorreo::class, 1);
});

test('con la misma clave, un reintento no repite el aviso ni el correo', function () {
    $persona = User::factory()->create();

    expect(noAvisar($persona))->toHaveCount(1)
        ->and(noAvisar($persona))->toHaveCount(0)
        ->and($persona->notifications()->count())->toBe(1);
    Notification::assertSentToTimes($persona, AvisoPorCorreo::class, 1);

    // Si el job del correo se repite, no manda otro.
    (new EnviarCorreoDeAviso($persona->notifications()->sole()->id))->handle();
    Notification::assertSentToTimes($persona, AvisoPorCorreo::class, 1);
});

test('el usuario de sistema y una cuenta inactiva no reciben avisos', function () {
    $sistema = User::query()->findOrFail(app(UsuarioSistema::class)->id());
    $inactiva = User::factory()->create(['es_activo' => false]);

    app(NotificacionesService::class)->avisar('nucleo.prueba-normal', [$sistema, $inactiva], 'X', null, null, 'x');

    expect(Notificacion::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('un aviso o un grupo sin declarar falla; declarar dos veces también', function () {
    $registro = app(RegistroAvisos::class);

    expect(fn () => noAvisar(User::factory()->create(), 'nucleo.no-existe'))->toThrow(InvalidArgumentException::class, 'no está registrado')
        ->and(fn () => $registro->registrar(new DefinicionAviso('nucleo.otro', 'sin_grupo')))->toThrow(InvalidArgumentException::class, 'grupo que no existe')
        ->and(fn () => $registro->registrarGrupo(new GrupoAvisos('pruebas', 'Otra vez')))->toThrow(InvalidArgumentException::class, 'ya está registrado')
        ->and(fn () => new DefinicionAviso('sin-modulo', 'pruebas'))->toThrow(InvalidArgumentException::class);
});

test('la campana del panel muestra el aviso de la persona y lo marca leído', function () {
    $persona = User::factory()->create();
    noAvisar($persona);
    test()->actingAs($persona);

    Livewire::test(DatabaseNotifications::class)
        ->assertSee('Tienes una orden por aprobar')
        ->call('markAllNotificationsAsRead');

    expect($persona->unreadNotifications()->count())->toBe(0);
});

test('«Mis preferencias» ofrece, en Avisos, el correo de cada grupo', function () {
    test()->actingAs(User::factory()->create())->get('/mis-preferencias')->assertOk()->assertSee('Pruebas: también por correo');
});

test('si la transacción que avisa se deshace, no queda aviso ni sale correo', function () {
    $persona = User::factory()->create();

    try {
        DB::transaction(function () use ($persona): void {
            noAvisar($persona);
            throw new RuntimeException('falla después de avisar');
        });
    } catch (RuntimeException) {
    }

    expect($persona->notifications()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('el correo reservado por otro intento no se manda dos veces: el job vuelve a la cola', function () {
    $persona = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'avisos.correo_pruebas', false);
    noAvisar($persona);
    $aviso = $persona->notifications()->sole();
    $aviso->forceFill(['enviar_correo' => true, 'correo_reservado_hasta' => now()->addMinute()])->save();

    (new EnviarCorreoDeAviso($aviso->id))->handle();

    Notification::assertNothingSent();
    expect($aviso->refresh()->correo_enviado_at)->toBeNull();

    // Con la reserva vencida (el intento anterior murió), otro la retoma y lo manda.
    $aviso->forceFill(['correo_reservado_hasta' => now()->subMinute()])->save();
    (new EnviarCorreoDeAviso($aviso->id))->handle();
    Notification::assertSentToTimes($persona, AvisoPorCorreo::class, 1);
    expect($aviso->refresh()->correo_reservado_hasta)->toBeNull();
});

test('dos avisos distintos del mismo hecho son dos avisos', function () {
    $persona = User::factory()->create();

    noAvisar($persona, 'nucleo.prueba-normal', 'orden:15');
    noAvisar($persona, 'nucleo.prueba-urgente', 'orden:15');

    expect($persona->notifications()->count())->toBe(2);
});

test('un correo que agota sus intentos avisa al administrador, como mucho una vez al día y solo en la campana (plan 1.10)', function () {
    test()->travelTo(CarbonImmutable::parse('2026-03-10 09:00', 'America/Lima'));
    $admin = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    [$primero] = noAvisar(User::factory()->create());
    [$segundo] = noAvisar(User::factory()->create(), unica: 'orden:16:por-aprobar');

    (new EnviarCorreoDeAviso($primero->id))->failed(null);
    (new EnviarCorreoDeAviso($segundo->id))->failed(null);

    $aviso = Notificacion::query()->where('type', AlertasDelNucleo::CORREO_NO_ENVIADO)->sole();

    expect($aviso->notifiable_id)->toBe($admin->id)
        ->and($aviso->data['title'])->toBe('Hay correos de aviso que no salieron (10/03/2026)')
        ->and($aviso->enviar_correo)->toBeFalse();

    test()->travelTo(CarbonImmutable::parse('2026-03-11 09:00', 'America/Lima'));
    (new EnviarCorreoDeAviso($primero->id))->failed(null);

    expect(Notificacion::query()->where('type', AlertasDelNucleo::CORREO_NO_ENVIADO)->count())->toBe(2);
});
