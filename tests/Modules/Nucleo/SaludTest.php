<?php

declare(strict_types=1);

use App\Modules\Nucleo\Console\LatidoProgramador;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Salud\ComprobacionProgramador;
use App\Modules\Nucleo\Salud\ComprobacionSalud;
use App\Modules\Nucleo\Salud\EstadoSalud;
use App\Modules\Nucleo\Salud\RegistroSalud;
use App\Modules\Nucleo\Salud\ResultadoComprobacion;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Salud del sistema (plan 1.12): el registro de corridas (D1), los recuadros (D2) y el latido del programador (D7).
 */

beforeEach(function () {
    // Plan 1.12: lo que sale de la base o de la cuarentena pasa antes al archivo histórico, cifrado.
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    test()->seed(ParametrosSeeder::class);
});

it('anota una corrida correcta con lo que devuelve la tarea', function () {
    $resultado = app(SaludService::class)->registrar('nucleo.prueba', fn (): array => ['movidos' => 3]);

    $corrida = app(SaludService::class)->ultima('nucleo.prueba');

    expect($resultado)->toBe(['movidos' => 3])
        ->and($corrida->resultado)->toBe(ResultadoEjecucion::Correcta)
        ->and($corrida->datos)->toBe(['movidos' => 3])
        ->and($corrida->terminada_at)->not->toBeNull();
});

it('una corrida que falla queda fallida solo con la clase del error, y el error sigue su camino', function () {
    expect(fn () => app(SaludService::class)->registrar('nucleo.prueba', fn () => throw new RuntimeException('Sueldo de Ana Pérez: 5000')))
        ->toThrow(RuntimeException::class);

    $corrida = app(SaludService::class)->ultima('nucleo.prueba');

    expect($corrida->resultado)->toBe(ResultadoEjecucion::Fallida)
        ->and($corrida->datos)->toBe(['error' => RuntimeException::class])
        ->and(json_encode($corrida->getAttributes()))->not->toContain('Pérez');
});

it('la base exige una tarea con nombre válido, un resultado conocido y la hora de término cuando terminó', function (array $cambios, string $restriccion) {
    // Las horas se arman aquí, con la zona de la aplicación: el dataset se evalúa antes de que arranque Laravel.
    $cambios = array_map(fn (mixed $valor): mixed => is_int($valor) ? now()->addMinutes($valor) : $valor, $cambios);
    $fila = ['tarea' => 'nucleo.prueba', 'iniciada_at' => now(), 'created_by' => app(UsuarioSistema::class)->id(), ...$cambios];

    expect(fn () => DB::transaction(fn () => DB::table('salud_ejecuciones')->insert($fila)))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'tarea con espacios' => [['tarea' => 'Mi tarea'], 'salud_ejecuciones_tarea_check'],
    'resultado desconocido' => [['resultado' => 'quizas'], 'salud_ejecuciones_resultado_check'],
    'correcta sin término' => [['resultado' => 'correcta'], 'salud_ejecuciones_terminada_check'],
    'en curso con término' => [['terminada_at' => 0], 'salud_ejecuciones_terminada_check'],
    'término antes del inicio' => [['resultado' => 'correcta', 'terminada_at' => -60], 'salud_ejecuciones_terminada_check'],
    'datos que no son un objeto' => [['datos' => '[1, 2]'], 'salud_ejecuciones_datos_check'],
]);

it('el latido se anota y, sin URL de Healthchecks, no llama a nadie', function () {
    Http::fake();
    config(['nucleo.salud.latido_url' => '']);

    $this->artisan('nucleo:latido')->assertSuccessful();

    Http::assertNothingSent();
    expect(app(SaludService::class)->ultima(LatidoProgramador::TAREA)->datos)->toBe(['enviado' => false]);
});

it('con URL de Healthchecks avisa ahí, y si Healthchecks no responde el latido igual se anota', function () {
    config(['nucleo.salud.latido_url' => 'https://latido.prueba/abc']);
    Http::fake(['latido.prueba/*' => Http::sequence()->push('OK')->push('', 500)]);

    $this->artisan('nucleo:latido')->assertSuccessful();
    expect(app(SaludService::class)->ultima(LatidoProgramador::TAREA)->datos)->toBe(['enviado' => true]);

    $this->artisan('nucleo:latido')->assertSuccessful();
    expect(app(SaludService::class)->ultima(LatidoProgramador::TAREA)->datos)->toBe(['enviado' => false])
        ->and(SaludEjecucion::query()->where('tarea', LatidoProgramador::TAREA)->count())->toBe(2);
});

it('el latido guarda solo la última semana de latidos', function () {
    Http::fake();
    $this->travelTo(CarbonImmutable::parse('2026-03-01 08:00', 'America/Lima'));
    $this->artisan('nucleo:latido');
    $this->travelTo(CarbonImmutable::parse('2026-03-09 08:00', 'America/Lima'));
    $this->artisan('nucleo:latido');

    expect(SaludEjecucion::query()->where('tarea', LatidoProgramador::TAREA)->count())->toBe(1);
});

it('el programador está bien con un latido reciente y falla si pasan más de 15 minutos sin él', function () {
    Http::fake();
    $comprobacion = app(ComprobacionProgramador::class);

    expect($comprobacion->revisar()->estado)->toBe(EstadoSalud::SinDatos);

    $this->travelTo(CarbonImmutable::parse('2026-03-10 08:00', 'America/Lima'));
    $this->artisan('nucleo:latido');
    $this->travelTo(CarbonImmutable::parse('2026-03-10 08:14', 'America/Lima'));
    expect($comprobacion->revisar()->estado)->toBe(EstadoSalud::Bien);

    $this->travelTo(CarbonImmutable::parse('2026-03-10 08:16', 'America/Lima'));
    expect($comprobacion->revisar()->estado)->toBe(EstadoSalud::Falla)
        ->and($comprobacion->revisar()->detalle['Último latido'])->toBe('10/03/2026 08:00');
});

it('el programador corre el latido cada 5 minutos', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'nucleo:latido'));

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->expression)->toBe('*/5 * * * *');
});

it('el núcleo registra su recuadro y no admite dos con la misma clave', function () {
    $registro = app(RegistroSalud::class);

    expect($registro->comprobaciones())->toHaveKey('nucleo.programador')
        ->and(fn () => $registro->registrar(ComprobacionProgramador::class))->toThrow(InvalidArgumentException::class, 'ya está registrada');

    $otra = new class implements ComprobacionSalud
    {
        public function clave(): string
        {
            return 'prueba.otra';
        }

        public function titulo(): string
        {
            return 'Otra';
        }

        public function icono(): string
        {
            return 'heroicon-o-beaker';
        }

        public function revisar(): ResultadoComprobacion
        {
            return new ResultadoComprobacion(EstadoSalud::Bien, 'Bien.');
        }
    };
    app()->instance($otra::class, $otra);
    $registro->registrar($otra::class);

    expect(array_keys($registro->comprobaciones()))->toBe(['nucleo.programador', 'nucleo.respaldos', 'nucleo.huerfanos-adjuntos', 'nucleo.cola', 'nucleo.ia', 'prueba.otra']);
});

it('si el histórico falla, el latido se anota igual: la limpieza no lo detiene', function () {
    Http::fake();
    config(['backup.backup.password' => null]);
    $this->travelTo(CarbonImmutable::parse('2026-03-01 08:00', 'America/Lima'));
    $this->artisan('nucleo:latido');
    $this->travelTo(CarbonImmutable::parse('2026-03-09 08:00', 'America/Lima'));

    $this->artisan('nucleo:latido')->assertSuccessful();

    expect(SaludEjecucion::query()->where('tarea', LatidoProgramador::TAREA)->count())->toBe(2);
});

it('el latido cierra como fallida una corrida que sigue en curso después de 6 horas; una reciente la deja', function () {
    $colgada = SaludEjecucion::query()->create(['tarea' => 'nucleo.respaldo', 'iniciada_at' => now()->subHours(7)]);
    $reciente = SaludEjecucion::query()->create(['tarea' => 'nucleo.respaldo', 'iniciada_at' => now()->subHour()]);

    $this->artisan('nucleo:latido')->assertSuccessful();

    expect($colgada->refresh()->resultado)->toBe(ResultadoEjecucion::Fallida)
        ->and($colgada->datos)->toBe(['error' => 'interrumpida'])
        ->and($colgada->terminada_at)->not->toBeNull()
        ->and($reciente->refresh()->resultado)->toBe(ResultadoEjecucion::EnCurso);
});
