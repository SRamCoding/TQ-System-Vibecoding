<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\database\seeders\AlertasSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Salud\ComprobacionHuerfanos;
use App\Modules\Nucleo\Salud\EstadoSalud;
use App\Modules\Nucleo\Services\HuerfanosAdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SaludService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Huérfanos del disco de adjuntos (plan 1.12, D4): a cuarentena, nunca borrados de una vez; con un tope que detiene la
 * corrida y `--simular`. Archivos de prueba inventados en un disco falso; la hora de cada uno se pone a mano.
 */

beforeEach(function () {
    // Plan 1.12: lo que sale de la base o de la cuarentena pasa antes al archivo histórico, cifrado.
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    Queue::fake();
    Storage::fake('adjuntos');
    test()->seed([ParametrosSeeder::class, AlertasSeeder::class]);
    test()->travelTo(CarbonImmutable::parse('2026-03-15 04:00', 'America/Lima'));
});

/** Un archivo en el disco de adjuntos, modificado hace `$horas` horas. */
function huerfano(string $ruta, int $horas = 48, string $contenido = 'contenido de prueba'): string
{
    Storage::disk('adjuntos')->put($ruta, $contenido);
    touch(Storage::disk('adjuntos')->path($ruta), now()->subHours($horas)->getTimestamp());

    return $ruta;
}

/** Un archivo con su fila en `adjuntos`. */
function conFila(string $ruta): string
{
    huerfano($ruta);
    Adjunto::query()->create([
        'adjuntable_type' => 'party', 'adjuntable_id' => Party::factory()->create()->id, 'tipo_adjunto_id' => TipoAdjunto::factory()->create()->id,
        'nombre_original' => 'acta.pdf', 'ruta' => $ruta, 'mime' => 'application/pdf', 'tamano_bytes' => 19, 'hash' => hash('sha256', $ruta), 'disco' => 'adjuntos',
    ]);

    return $ruta;
}

function enCuarentena(string $fecha, string $ruta, string $contenido = 'viejo'): string
{
    $completa = HuerfanosAdjuntosService::CUARENTENA."/{$fecha}/{$ruta}";
    Storage::disk('adjuntos')->put($completa, $contenido);

    return $completa;
}

it('pasa a cuarentena el huérfano viejo, con su ruta original, y nunca toca un archivo con fila', function () {
    $sinFila = huerfano('party/2026/03/sin-fila.pdf');
    $registrado = conFila('party/2026/03/con-fila.pdf');

    $this->artisan('nucleo:revisar-huerfanos')->assertSuccessful();

    Storage::disk('adjuntos')->assertMissing($sinFila);
    Storage::disk('adjuntos')->assertExists('cuarentena/2026-03-15/'.$sinFila);
    Storage::disk('adjuntos')->assertExists($registrado);
    expect(app(SaludService::class)->ultima(HuerfanosAdjuntosService::TAREA)->datos)->toMatchArray(['encontrados' => 1, 'movidos' => 1, 'detenida' => false]);
});

it('un archivo con menos de 24 horas no se mueve: puede ser un adjunto que se está guardando', function () {
    $reciente = huerfano('party/2026/03/reciente.pdf', horas: 23);

    $this->artisan('nucleo:revisar-huerfanos');

    Storage::disk('adjuntos')->assertExists($reciente);
});

it('lo que pasó los días de cuarentena va al archivo histórico y sale del disco; lo demás se puede devolver', function () {
    $vencido = enCuarentena('2026-02-12', 'party/2026/02/a.pdf');
    $vigente = enCuarentena('2026-02-14', 'party/2026/02/b.pdf');

    $this->artisan('nucleo:revisar-huerfanos');

    Storage::disk('adjuntos')->assertMissing($vencido);
    Storage::disk('adjuntos')->assertExists($vigente);
    $guardado = Storage::disk('historico')->allFiles('adjuntos-huerfanos');
    expect($guardado)->toHaveCount(1)
        ->and(app(ArchivoHistoricoService::class)->leerArchivo($guardado[0]))->toBe('viejo');
    expect(Storage::disk('adjuntos')->directories('cuarentena'))->toBe(['cuarentena/2026-02-14'])
        ->and(app(SaludService::class)->ultima(HuerfanosAdjuntosService::TAREA)->datos)->toMatchArray(['borrados' => 1, 'bytes_borrados' => 5, 'bytes_cuarentena' => 5]);
});

it('los días de cuarentena son un parámetro', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('adjuntos.dias_cuarentena', '7', 1);
    $vencido = enCuarentena('2026-03-07', 'party/2026/03/a.pdf');

    $this->artisan('nucleo:revisar-huerfanos');

    Storage::disk('adjuntos')->assertMissing($vencido);
});

it('si los huérfanos pasan el tope, no mueve ninguno y avisa', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('adjuntos.tope_huerfanos', '2', 1);
    $rutas = [huerfano('party/2026/03/1.pdf'), huerfano('party/2026/03/2.pdf'), huerfano('party/2026/03/3.pdf')];

    $this->artisan('nucleo:revisar-huerfanos')->expectsOutputToContain('Detenida')->assertSuccessful();

    foreach ($rutas as $ruta) {
        Storage::disk('adjuntos')->assertExists($ruta);
    }
    expect(AlertaEmitida::query()->where('clave', AlertasDelNucleo::HUERFANOS_DEMASIADOS.':2026-03-15')->count())->toBe(1)
        ->and(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);
});

it('--simular lista lo que haría sin mover, borrar, avisar ni anotar la corrida', function () {
    $sinFila = huerfano('party/2026/03/sin-fila.pdf');
    $vencido = enCuarentena('2026-01-01', 'party/2026/01/a.pdf');

    $this->artisan('nucleo:revisar-huerfanos --simular')
        ->expectsOutputToContain('Pasarían a cuarentena: 1')
        ->expectsOutputToContain($sinFila)
        ->expectsOutputToContain('Se borrarían de la cuarentena: 1')
        ->assertSuccessful();

    Storage::disk('adjuntos')->assertExists($sinFila);
    Storage::disk('adjuntos')->assertExists($vencido);
    expect(app(SaludService::class)->ultima(HuerfanosAdjuntosService::TAREA))->toBeNull()
        ->and(AlertaEmitida::query()->count())->toBe(0);
});

it('pausada, no hace nada y la página lo muestra', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('adjuntos.revision_huerfanos_activa', 'no', 1);
    $sinFila = huerfano('party/2026/03/sin-fila.pdf');

    $this->artisan('nucleo:revisar-huerfanos')->expectsOutputToContain('pausada');

    Storage::disk('adjuntos')->assertExists($sinFila);
    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Aviso);
});

it('una pausa de más de 14 días pasa de amarillo a rojo, y los días son un parámetro', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('adjuntos.revision_huerfanos_activa', 'no', 1);

    test()->travelTo(CarbonImmutable::parse('2026-03-29 03:59', 'America/Lima'));
    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Aviso)
        ->and(app(ComprobacionHuerfanos::class)->revisar()->detalle)->toBe(['Pausada desde' => '15/03/2026 04:00']);

    test()->travelTo(CarbonImmutable::parse('2026-03-29 04:01', 'America/Lima'));
    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);

    app(ParametrosService::class)->cambiarDesdeTexto('adjuntos.dias_pausa_huerfanos', '30', 1);
    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Aviso);
});

it('la página muestra la última revisión, y falla si pasan más de dos semanas sin ella', function () {
    huerfano('party/2026/03/sin-fila.pdf', contenido: str_repeat('x', 2048));
    $this->artisan('nucleo:revisar-huerfanos');

    $resultado = app(ComprobacionHuerfanos::class)->revisar();
    expect($resultado->estado)->toBe(EstadoSalud::Bien)
        ->and($resultado->detalle)->toBe([
            'Última revisión' => '15/03/2026 04:00',
            'A cuarentena' => '1',
            'Borrados de la cuarentena' => '0',
            'Ocupa la cuarentena' => '2.0 KB',
        ]);

    test()->travelTo(CarbonImmutable::parse('2026-03-31 04:00', 'America/Lima'));
    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Falla);
});

it('el programador la corre cada domingo a las 04:00', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains((string) $evento->command, 'nucleo:revisar-huerfanos'));

    expect($evento->expression)->toBe('0 4 * * 0')->and($evento->timezone)->toBe('America/Lima');
});

it('nunca toca las fotos de perfil: están en el mismo disco, sin fila en adjuntos (revisión de arquitectura)', function () {
    $foto = huerfano('fotos-perfil/7/una-foto.jpg', horas: 24 * 90);
    huerfano('party/2026/03/sin-fila.pdf');

    $this->artisan('nucleo:revisar-huerfanos')->assertSuccessful();

    Storage::disk('adjuntos')->assertExists($foto);
    expect(app(SaludService::class)->ultima(HuerfanosAdjuntosService::TAREA)->datos)->toMatchArray(['encontrados' => 1, 'movidos' => 1]);
});

it('una revisión que todavía corre no se muestra como «sin pendientes»', function () {
    SaludEjecucion::query()->create(['tarea' => HuerfanosAdjuntosService::TAREA, 'iniciada_at' => now()->subMinutes(5)]);

    expect(app(ComprobacionHuerfanos::class)->revisar()->estado)->toBe(EstadoSalud::Aviso);
});

it('una corrida a mano no arranca mientras otra está en marcha, y no mueve nada', function () {
    $ruta = huerfano('party/2026/03/sin-fila.pdf');
    $candado = Cache::lock('nucleo:revisar-huerfanos', 60);
    $candado->get();

    $this->artisan('nucleo:revisar-huerfanos')->expectsOutputToContain('en marcha')->assertFailed();

    $candado->release();
    Storage::disk('adjuntos')->assertExists($ruta);
});
