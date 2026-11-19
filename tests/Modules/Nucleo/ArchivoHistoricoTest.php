<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\database\seeders\AlertasSeeder;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Historico\CifradoHistorico;
use App\Modules\Nucleo\Historico\HistoricoFallido;
use App\Modules\Nucleo\Models\AlertaEmitida;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/*
 * El archivo histórico (plan 1.4y, D6): lo que sale de la base queda antes, cifrado y comprobado, en un archivo que nunca
 * se borra; si no se puede, no se borra nada. Tabla y datos de prueba inventados.
 */

beforeEach(function () {
    Queue::fake();
    Storage::fake('historico');
    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    test()->seed(AlertasSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2027-11-01 03:00', 'America/Lima'));
    Schema::create('historico_prueba', function (Blueprint $table): void {
        $table->id();
        $table->string('texto');
        $table->boolean('viejo');
    });
});

/** Un disco local que, después de escribir cada archivo, llama a un gancho de la prueba. */
final class DiscoHistoricoConGancho extends FilesystemAdapter
{
    /** @var ?Closure(string, int): void */
    public static ?Closure $alEscribir = null;

    public static int $escritos = 0;

    public function put($path, $contents, $options = [])
    {
        $resultado = parent::put($path, $contents, $options);
        self::$escritos++;

        if (self::$alEscribir !== null) {
            (self::$alEscribir)($path, self::$escritos);
        }

        return $resultado;
    }
}

function discoConGancho(?Closure $alEscribir): void
{
    DiscoHistoricoConGancho::$alEscribir = $alEscribir;
    DiscoHistoricoConGancho::$escritos = 0;
    $raiz = storage_path('framework/testing/historico-gancho-'.getmypid());
    Storage::extend('con-gancho', function ($app, array $config) {
        $adaptador = new LocalFilesystemAdapter($config['root']);

        return new DiscoHistoricoConGancho(new Filesystem($adaptador, $config), $adaptador, $config);
    });
    config(['filesystems.disks.historico_gancho' => ['driver' => 'con-gancho', 'root' => $raiz, 'throw' => true], 'nucleo.historico.disco' => 'historico_gancho']);
    Storage::forgetDisk('historico_gancho');
    Storage::disk('historico_gancho')->deleteDirectory('historico_prueba');
}

function filasHistorico(int $viejas, int $nuevas = 0): void
{
    for ($i = 1; $i <= $viejas + $nuevas; $i++) {
        DB::table('historico_prueba')->insert(['texto' => "Aviso de prueba {$i} para Rosa Inventada", 'viejo' => $i <= $viejas]);
    }
}

function archivarViejas(int $lote = ArchivoHistoricoService::LOTE): int
{
    return app(ArchivoHistoricoService::class)->archivar('historico_prueba', DB::table('historico_prueba')->where('viejo', true), $lote);
}

it('deja en el histórico lo que saca de la base, cifrado, y solo saca lo que cumple la condición', function () {
    filasHistorico(viejas: 3, nuevas: 2);

    expect(archivarViejas())->toBe(3)
        ->and(DB::table('historico_prueba')->pluck('id')->all())->toBe([4, 5]);

    $archivos = Storage::disk('historico')->allFiles();
    expect($archivos)->toHaveCount(1)
        ->and($archivos[0])->toStartWith('historico_prueba/2027-11/20271101-030000-')->toEndWith('.jsonl.gz.cifrado')
        ->and(Storage::disk('historico')->get($archivos[0]))->toStartWith('TQH2')->not->toContain('Rosa')
        ->and(app(ArchivoHistoricoService::class)->leer($archivos[0]))->toBe([
            ['id' => 1, 'texto' => 'Aviso de prueba 1 para Rosa Inventada', 'viejo' => true],
            ['id' => 2, 'texto' => 'Aviso de prueba 2 para Rosa Inventada', 'viejo' => true],
            ['id' => 3, 'texto' => 'Aviso de prueba 3 para Rosa Inventada', 'viejo' => true],
        ]);
});

it('va por lotes, un archivo por lote, y nunca sobrescribe uno anterior', function () {
    filasHistorico(viejas: 5);
    archivarViejas(lote: 2);

    expect(Storage::disk('historico')->allFiles())->toHaveCount(3)
        ->and(DB::table('historico_prueba')->count())->toBe(0);

    filasHistorico(viejas: 1);
    archivarViejas();

    expect(Storage::disk('historico')->allFiles())->toHaveCount(4);
});

it('sin la clave de cifrado no archiva ni borra nada, y avisa una vez al día', function () {
    config(['backup.backup.password' => null]);
    filasHistorico(viejas: 2);

    expect(fn () => archivarViejas())->toThrow(HistoricoFallido::class, 'BACKUP_ARCHIVE_PASSWORD');
    expect(fn () => archivarViejas())->toThrow(HistoricoFallido::class);

    expect(DB::table('historico_prueba')->count())->toBe(2)
        ->and(Storage::disk('historico')->allFiles())->toBe([])
        ->and(AlertaEmitida::query()->where('clave', AlertasDelNucleo::HISTORICO_FALLIDO.':2027-11-01:historico_prueba')->count())->toBe(1);
});

it('si el disco no se puede escribir, no borra nada', function () {
    config(['filesystems.disks.historico_roto' => ['driver' => 'local', 'root' => '/proc/no-se-puede', 'throw' => true], 'nucleo.historico.disco' => 'historico_roto']);
    filasHistorico(viejas: 2);

    expect(fn () => archivarViejas())->toThrow(RuntimeException::class)
        ->and(DB::table('historico_prueba')->count())->toBe(2);
});

it('un archivo alterado o cifrado con otra clave no se lee', function () {
    filasHistorico(viejas: 1);
    archivarViejas();
    $archivo = Storage::disk('historico')->allFiles()[0];

    config(['backup.backup.password' => 'otra-clave']);
    expect(fn () => app(ArchivoHistoricoService::class)->leer($archivo))->toThrow(HistoricoFallido::class, 'no se pudo descifrar');

    config(['backup.backup.password' => 'clave-de-prueba-del-historico']);
    $contenido = Storage::disk('historico')->get($archivo);
    Storage::disk('historico')->put($archivo, substr($contenido, 0, -1).chr(ord(substr($contenido, -1)) ^ 1));
    expect(fn () => app(ArchivoHistoricoService::class)->leer($archivo))->toThrow(HistoricoFallido::class);
});

it('TI lo lee descifrado con nucleo:leer-historico', function () {
    filasHistorico(viejas: 1);
    archivarViejas();

    $this->artisan('nucleo:leer-historico', ['archivo' => Storage::disk('historico')->allFiles()[0]])
        ->expectsOutput('{"id":1,"texto":"Aviso de prueba 1 para Rosa Inventada","viejo":true}')
        ->assertSuccessful();
});

it('si falla un lote, los anteriores quedan archivados y borrados, y el que falló sigue en la base', function () {
    discoConGancho(function (string $archivo, int $escritos): void {
        if ($escritos === 2) {
            throw new RuntimeException('Disco lleno (prueba).');
        }
    });
    filasHistorico(viejas: 5);

    expect(fn () => archivarViejas(lote: 2))->toThrow(RuntimeException::class);

    expect(DB::table('historico_prueba')->pluck('id')->all())->toBe([3, 4, 5])
        ->and(Storage::disk('historico_gancho')->allFiles())->toHaveCount(2);
});

it('una fila que deja de cumplir la condición entre la lectura y el borrado no se borra: queda una copia de más', function () {
    discoConGancho(fn () => DB::table('historico_prueba')->where('id', 2)->update(['viejo' => false]));
    filasHistorico(viejas: 3);

    expect(archivarViejas())->toBe(2)
        ->and(DB::table('historico_prueba')->pluck('id')->all())->toBe([2])
        ->and(array_column(app(ArchivoHistoricoService::class)->leer(Storage::disk('historico_gancho')->allFiles()[0]), 'id'))->toBe([1, 2, 3]);
});

it('si lo que se relee no es lo que se escribió, no borra nada', function () {
    discoConGancho(function (string $archivo): void {
        Storage::disk('historico_gancho')->getDriver()->write($archivo, app(CifradoHistorico::class)->cifrar((string) gzencode('{"id":99}'."\n")));
    });
    filasHistorico(viejas: 2);

    expect(fn () => archivarViejas())->toThrow(HistoricoFallido::class, 'no se pudo volver a leer igual')
        ->and(DB::table('historico_prueba')->count())->toBe(2);
});

it('si el borrado falla después de escribir, la fila sigue en la base y el archivo queda: una copia de más, nunca de menos', function () {
    DB::unprepared("CREATE FUNCTION historico_prueba_sin_borrar() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'borrado bloqueado (prueba)'; END $$ LANGUAGE plpgsql");
    DB::unprepared('CREATE TRIGGER historico_prueba_sin_borrar BEFORE DELETE ON historico_prueba FOR EACH ROW EXECUTE FUNCTION historico_prueba_sin_borrar()');
    filasHistorico(viejas: 2);

    expect(fn () => archivarViejas())->toThrow(QueryException::class)
        ->and(DB::table('historico_prueba')->count())->toBe(2)
        ->and(Storage::disk('historico')->allFiles())->toHaveCount(1);
});

it('un archivo cifrado con la contraseña anterior se lee dándosela', function () {
    filasHistorico(viejas: 1);
    archivarViejas();
    $archivo = Storage::disk('historico')->allFiles()[0];
    config(['backup.backup.password' => 'contrasena-nueva']);

    expect(fn () => app(ArchivoHistoricoService::class)->leer($archivo))->toThrow(HistoricoFallido::class)
        ->and(array_column(app(ArchivoHistoricoService::class)->leer($archivo, 'clave-de-prueba-del-historico'), 'id'))->toBe([1]);

    $this->artisan('nucleo:leer-historico', ['archivo' => $archivo, '--otra-clave' => true])
        ->expectsQuestion('Contraseña con que se cifró', 'clave-de-prueba-del-historico')
        ->expectsOutput('{"id":1,"texto":"Aviso de prueba 1 para Rosa Inventada","viejo":true}')
        ->assertSuccessful();
});
