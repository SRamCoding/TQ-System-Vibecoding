<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Console\LatidoProgramador;
use App\Modules\Nucleo\database\seeders\AlertasSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Respaldos\ArchivoRespaldo;
use App\Modules\Nucleo\Respaldos\Respaldador;
use App\Modules\Nucleo\Respaldos\RespaldadorSpatie;
use App\Modules\Nucleo\Respaldos\RespaldoFallido;
use App\Modules\Nucleo\Respaldos\RespaldosService;
use App\Modules\Nucleo\Salud\ComprobacionRespaldos;
use App\Modules\Nucleo\Salud\EstadoSalud;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SaludService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Respaldos (plan 1.12, D3). Ninguna prueba escribe un respaldo: el respaldador es falso. Lo que se prueba es cuándo
 * toca, qué queda anotado, los avisos y cómo se ve en la página de salud (nunca verde con destino local).
 */

/** Un respaldador falso: cuenta las llamadas y puede fallar. */
final class RespaldadorDePrueba implements Respaldador
{
    public int $respaldos = 0;

    public int $limpiezas = 0;

    public bool $falla = false;

    public function __construct(public string $destino = 'respaldos') {}

    public function disco(): string
    {
        return $this->destino;
    }

    public function respaldar(): ArchivoRespaldo
    {
        if ($this->falla) {
            throw RespaldoFallido::alRespaldar();
        }

        $this->respaldos++;

        return new ArchivoRespaldo('2026-03-10-02-00-00.zip', 5_242_880);
    }

    public function limpiar(): void
    {
        $this->limpiezas++;
    }
}

function respaldador(string $destino = 'respaldos'): RespaldadorDePrueba
{
    $falso = new RespaldadorDePrueba($destino);
    app()->instance(Respaldador::class, $falso);

    return $falso;
}

function respaldoA(string $fecha): void
{
    test()->travelTo(CarbonImmutable::parse($fecha, 'America/Lima'));
}

beforeEach(function () {
    // Plan 1.12: lo que sale de la base o de la cuarentena pasa antes al archivo histórico, cifrado.
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    Queue::fake();
    test()->seed([ParametrosSeeder::class, AlertasSeeder::class]);
    respaldoA('2026-03-10 02:00');
});

it('respalda la primera vez, anota el archivo y su tamaño, y limpia los viejos porque el destino es local', function () {
    $falso = respaldador();

    $this->artisan('nucleo:respaldar')->assertSuccessful();

    $corrida = app(RespaldosService::class)->ultimoCorrecto();
    expect($falso->respaldos)->toBe(1)
        ->and($falso->limpiezas)->toBe(1)
        ->and($corrida->datos)->toEqual(['archivo' => '2026-03-10-02-00-00.zip', 'bytes' => 5_242_880, 'disco' => 'respaldos']);
});

it('con frecuencia semanal no vuelve a respaldar hasta la semana siguiente, salvo que se fuerce', function () {
    $falso = respaldador();
    $this->artisan('nucleo:respaldar');

    respaldoA('2026-03-16 02:00');
    $this->artisan('nucleo:respaldar')->expectsOutputToContain('Todavía no toca')->assertSuccessful();
    expect($falso->respaldos)->toBe(1);

    $this->artisan('nucleo:respaldar --forzar')->assertSuccessful();
    expect($falso->respaldos)->toBe(2);

    respaldoA('2026-03-23 02:03');
    $this->artisan('nucleo:respaldar');
    expect($falso->respaldos)->toBe(3);
});

it('con frecuencia diaria respalda cada noche', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('respaldos.frecuencia', 'Diaria', 1);
    $falso = respaldador();

    $this->artisan('nucleo:respaldar');
    respaldoA('2026-03-11 01:58');
    $this->artisan('nucleo:respaldar');

    expect($falso->respaldos)->toBe(2);
});

it('la frecuencia solo admite diaria o semanal', function () {
    expect(fn () => app(ParametrosService::class)->cambiarDesdeTexto('respaldos.frecuencia', 'mensual', 1))
        ->toThrow(ParametroRechazado::class);
});

it('con destino en S3 no limpia: el job solo escribe y la retención es del bucket', function () {
    $falso = respaldador('respaldos_s3');

    $this->artisan('nucleo:respaldar')->assertSuccessful();

    expect($falso->respaldos)->toBe(1)->and($falso->limpiezas)->toBe(0);
});

it('si el respaldo falla, la corrida queda fallida, avisa una vez al día y el comando termina con error', function () {
    $falso = respaldador();
    $falso->falla = true;

    $this->artisan('nucleo:respaldar')->assertFailed();
    $this->artisan('nucleo:respaldar')->assertFailed();

    expect(app(RespaldosService::class)->ultimaCorrida()->resultado)->toBe(ResultadoEjecucion::Fallida)
        ->and(AlertaEmitida::query()->where('clave', AlertasDelNucleo::RESPALDO_FALLIDO.':2026-03-10')->count())->toBe(1)
        ->and(app(ComprobacionRespaldos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);
});

it('sin clave de cifrado no respalda: el respaldo saldría sin cifrar', function () {
    config(['backup.backup.password' => null]);
    Artisan::spy();

    expect(fn () => app(RespaldadorSpatie::class)->respaldar())->toThrow(RespaldoFallido::class, 'BACKUP_ARCHIVE_PASSWORD');
    Artisan::shouldNotHaveReceived('call');
});

it('la librería apunta al disco de respaldos, cifrada y sin sus propios avisos', function () {
    expect(app(RespaldadorSpatie::class)->disco())->toBe('respaldos')
        ->and(config('backup.backup.encryption'))->toBe('default')
        ->and(config('backup.backup.source.databases'))->toBe(['pgsql'])
        ->and(collect(config('backup.notifications.notifications'))->flatten()->all())->toBe([])
        ->and(config('filesystems.disks.respaldos.driver'))->toBe('local')
        ->and(config('filesystems.disks.respaldos_s3.driver'))->toBe('s3');
});

it('con destino local, la página de salud nunca lo muestra en verde', function () {
    respaldador();
    $this->artisan('nucleo:respaldar');

    $resultado = app(ComprobacionRespaldos::class)->revisar();

    expect($resultado->estado)->toBe(EstadoSalud::Aviso)
        ->and($resultado->resumen)->toBe(ComprobacionRespaldos::SOLO_LOCAL)
        ->and($resultado->detalle['Último respaldo'])->toBe('10/03/2026 02:00 · 5.0 MB');
});

it('con destino externo y un respaldo reciente, verde; sin respaldo o vencido, falla', function () {
    config(['filesystems.disks.respaldos_s3.driver' => 's3']);
    respaldador('respaldos_s3');

    expect(app(ComprobacionRespaldos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);

    $this->artisan('nucleo:respaldar');
    expect(app(ComprobacionRespaldos::class)->revisar()->estado)->toBe(EstadoSalud::Bien);

    respaldoA('2026-03-18 03:00');
    expect(app(ComprobacionRespaldos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);
});

it('la revisión diaria avisa si el último respaldo es más viejo que la frecuencia más un día', function () {
    respaldador();
    $this->artisan('nucleo:respaldar');

    respaldoA('2026-03-18 01:00');
    $this->artisan('nucleo:revisar-alertas');
    expect(AlertaEmitida::query()->where('clave', 'like', AlertasDelNucleo::RESPALDO_VENCIDO.':%')->count())->toBe(0);

    respaldoA('2026-03-18 07:00');
    $this->artisan('nucleo:revisar-alertas');
    $this->artisan('nucleo:revisar-alertas');
    expect(AlertaEmitida::query()->where('clave', AlertasDelNucleo::RESPALDO_VENCIDO.':2026-03-18')->count())->toBe(1);
});

it('muestra la última prueba de restauración y quién la registró, y no admite una fecha futura', function () {
    respaldador();
    expect(app(ComprobacionRespaldos::class)->revisar()->detalle['Prueba de restauración'])->toBe('sin probar');

    $this->actingAs(User::factory()->create(['name' => 'Rosa Inventada']));
    app(ParametrosService::class)->cambiarDesdeTexto('respaldos.ultima_prueba_restauracion', '2026-03-09', 1);

    expect(app(ComprobacionRespaldos::class)->revisar()->detalle['Prueba de restauración'])->toBe('09/03/2026 · registrada por Rosa Inventada')
        ->and(fn () => app(ParametrosService::class)->cambiarDesdeTexto('respaldos.ultima_prueba_restauracion', '2026-03-11', 2))
        ->toThrow(ParametroRechazado::class);
});

it('el registro de corridas guarda una semana de latidos y salud.dias_historial de lo demás; una en curso de hace días se cierra y se archiva', function () {
    Http::fake();
    $viejo = fn (string $tarea, int $dias, ResultadoEjecucion $resultado = ResultadoEjecucion::Correcta) => SaludEjecucion::query()->create([
        'tarea' => $tarea,
        'iniciada_at' => now()->subDays($dias),
        'terminada_at' => $resultado === ResultadoEjecucion::EnCurso ? null : now()->subDays($dias),
        'resultado' => $resultado,
    ]);
    $viejo(LatidoProgramador::TAREA, 8);
    $viejo(LatidoProgramador::TAREA, 6);
    $viejo(RespaldosService::TAREA, 300);
    $viejo(RespaldosService::TAREA, 401);
    $viejo('nucleo.huerfanos', 500, ResultadoEjecucion::EnCurso);

    $this->artisan('nucleo:latido');

    expect(SaludEjecucion::query()->where('tarea', LatidoProgramador::TAREA)->count())->toBe(2)
        ->and(SaludEjecucion::query()->where('tarea', RespaldosService::TAREA)->count())->toBe(1)
        // Colgada (en curso por más de 6 horas): el latido la cierra como fallida y sale como las demás.
        ->and(SaludEjecucion::query()->where('tarea', 'nucleo.huerfanos')->count())->toBe(0)
        ->and(app(SaludService::class)->ultima(RespaldosService::TAREA)->iniciada_at->toDateString())->toBe('2025-05-14')
        // Lo que salió de la base quedó en el archivo histórico (plan 1.4y, D6).
        ->and(collect(Storage::disk('historico')->allFiles())->flatMap(fn (string $archivo): array => app(ArchivoHistoricoService::class)->leer($archivo))->count())->toBe(3);
});

it('un respaldo a mano no arranca mientras otro está en marcha', function () {
    $candado = Cache::lock('nucleo:respaldar', 60);
    $candado->get();

    $this->artisan('nucleo:respaldar --forzar')->expectsOutputToContain('en marcha')->assertFailed();

    $candado->release();
    expect(SaludEjecucion::query()->where('tarea', RespaldosService::TAREA)->exists())->toBeFalse();
});

it('el disco respaldos_s3 se arma con el paquete de S3, sin llamar a S3 (plan 1.12b)', function () {
    config(['filesystems.disks.respaldos_s3' => array_merge(config('filesystems.disks.respaldos_s3'), [
        'key' => 'clave-de-prueba', 'secret' => 'secreto-de-prueba', 'region' => 'us-east-1', 'bucket' => 'bucket-de-prueba',
    ])]);

    expect(Storage::disk('respaldos_s3'))->toBeInstanceOf(AwsS3V3Adapter::class);
});
