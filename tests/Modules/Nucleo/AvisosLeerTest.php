<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisoLeido;
use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisoNoLeido;
use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisosLeidos;
use App\Modules\Nucleo\database\seeders\AlertasSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AvisoRechazado;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Avisos solo para leer (plan 1.4y, D1, D2, D4 y D6): cada persona marca sus avisos como leídos, uno o todos, y los
 * devuelve a no leídos; nadie toca los de otra; los leídos viejos pasan al archivo histórico. Personas inventadas.
 */

beforeEach(function () {
    Queue::fake();
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    test()->seed([ParametrosSeeder::class, AlertasSeeder::class]);
    test()->travelTo(CarbonImmutable::parse('2027-11-01 03:30', 'America/Lima'));
});

function avisoDe(User $persona, ?CarbonImmutable $leido = null): Notificacion
{
    $aviso = new Notificacion;
    $aviso->forceFill([
        'type' => 'prueba', 'notifiable_type' => $persona->getMorphClass(), 'notifiable_id' => $persona->id,
        'data' => ['title' => 'Aviso de prueba', 'body' => null], 'clave_unica' => 'prueba-'.fake()->unique()->numerify('######'),
        'enviar_correo' => false, 'read_at' => $leido,
    ])->save();

    return $aviso;
}

function accionAviso(string $clave, array $datos, User $persona): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.{$clave}", $datos, $persona);
}

it('el núcleo registra las tres acciones, sin permiso', function () {
    foreach (['nucleo.marcar-aviso-leido' => MarcarAvisoLeido::class, 'nucleo.marcar-avisos-leidos' => MarcarAvisosLeidos::class, 'nucleo.marcar-aviso-no-leido' => MarcarAvisoNoLeido::class] as $clave => $clase) {
        expect(app(RegistroAcciones::class)->todas())->toHaveKey($clave, $clase)
            ->and(app(RegistroAcciones::class)->obtener($clave)->permiso())->toBeNull();
    }
});

it('marca uno como leído sin cambiar la hora si se repite, y lo devuelve a no leído', function () {
    $persona = User::factory()->create();
    $aviso = avisoDe($persona);

    accionAviso('marcar-aviso-leido', ['aviso_id' => $aviso->id], $persona);
    $leido = $aviso->refresh()->read_at;
    test()->travel(5)->minutes();
    accionAviso('marcar-aviso-leido', ['aviso_id' => $aviso->id], $persona);

    expect($leido)->not->toBeNull()
        ->and($aviso->refresh()->read_at->equalTo($leido))->toBeTrue();

    accionAviso('marcar-aviso-no-leido', ['aviso_id' => $aviso->id], $persona);
    expect($aviso->refresh()->read_at)->toBeNull();
});

it('marca todos los suyos sin leer, y ninguno de otra persona', function () {
    $persona = User::factory()->create();
    $otra = User::factory()->create();
    avisoDe($persona);
    avisoDe($persona);
    $ajeno = avisoDe($otra);

    expect(accionAviso('marcar-avisos-leidos', [], $persona))->toBe(2)
        ->and(Notificacion::query()->where('notifiable_id', $persona->id)->whereNull('read_at')->count())->toBe(0)
        ->and($ajeno->refresh()->read_at)->toBeNull();
});

it('un aviso de otra persona se rechaza igual que uno que no existe', function () {
    $persona = User::factory()->create();
    $ajeno = avisoDe(User::factory()->create());

    foreach (['marcar-aviso-leido', 'marcar-aviso-no-leido'] as $clave) {
        expect(fn () => accionAviso($clave, ['aviso_id' => $ajeno->id], $persona))->toThrow(AvisoRechazado::class, 'Ese aviso no está entre los tuyos.')
            ->and(fn () => accionAviso($clave, ['aviso_id' => 999999], $persona))->toThrow(AvisoRechazado::class, 'Ese aviso no está entre los tuyos.');
    }

    expect($ajeno->refresh()->read_at)->toBeNull();
});

it('rechaza al usuario de sistema y a uno inactivo', function () {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(fn () => accionAviso('marcar-avisos-leidos', [], $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => accionAviso('marcar-avisos-leidos', [], User::factory()->inactivo()->create()))->toThrow(AccionRechazada::class);
});

it('la limpieza pasa al histórico solo los leídos de más del plazo; los no leídos nunca salen', function () {
    $persona = User::factory()->create();
    $viejo = avisoDe($persona, CarbonImmutable::now()->subDays(366));
    $reciente = avisoDe($persona, CarbonImmutable::now()->subDays(364));
    $sinLeer = avisoDe($persona);
    $sinLeer->forceFill(['created_at' => CarbonImmutable::now()->subYears(3)])->save();

    $this->artisan('nucleo:archivar-avisos')->expectsOutput('Avisos archivados: 1.')->assertSuccessful();

    $archivo = Storage::disk('historico')->allFiles()[0];
    expect(Notificacion::query()->pluck('id')->sort()->values()->all())->toBe([$reciente->id, $sinLeer->id])
        ->and($archivo)->toStartWith('notificaciones/2027-11/')
        ->and(array_column(app(ArchivoHistoricoService::class)->leer($archivo), 'id'))->toBe([$viejo->id]);
});

it('el plazo es un parámetro, de 30 a 3650 días', function () {
    $persona = User::factory()->create();
    avisoDe($persona, CarbonImmutable::now()->subDays(40));
    app(ParametrosService::class)->cambiarDesdeTexto('avisos.dias_leidos', '30', 1);

    $this->artisan('nucleo:archivar-avisos')->expectsOutput('Avisos archivados: 1.');

    expect(fn () => app(ParametrosService::class)->cambiarDesdeTexto('avisos.dias_leidos', '10', 2))->toThrow(ParametroRechazado::class);
});

it('si el histórico no se puede escribir, no saca ningún aviso de la base', function () {
    config(['backup.backup.password' => null]);
    avisoDe(User::factory()->create(), CarbonImmutable::now()->subDays(400));

    $this->artisan('nucleo:archivar-avisos')->assertFailed();

    expect(Notificacion::query()->count())->toBe(1);
});

it('el programador la corre cada día a las 03:30', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains((string) $evento->command, 'nucleo:archivar-avisos'));

    expect($evento->expression)->toBe('30 3 * * *')->and($evento->timezone)->toBe('America/Lima');
});
