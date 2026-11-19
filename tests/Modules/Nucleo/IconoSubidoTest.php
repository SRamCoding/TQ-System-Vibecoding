<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Excepciones\AplicacionRechazada;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Filament\PanelAplicaciones;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * El ícono subido por administración (plan 1.4z, D9): sumar una aplicación con su logo sin código. PNG o WEBP, hasta 1 MB;
 * nunca SVG (prohibido en todo adjunto). Archivos de prueba creados aquí.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Storage::fake('adjuntos');
});

afterEach(function () {
    foreach ($GLOBALS['iconosTemporales'] ?? [] as $ruta) {
        @unlink($ruta);
    }
});

/** @return array{ruta: string, nombre: string} el archivo como lo recibe la acción sin interfaz */
function iconoArchivo(string $nombre, string $contenido): array
{
    $ruta = tempnam(sys_get_temp_dir(), 'ico');
    file_put_contents($ruta, $contenido);
    $GLOBALS['iconosTemporales'][] = $ruta;

    return ['ruta' => $ruta, 'nombre' => $nombre];
}

/**
 * Un PNG de verdad, de 1×1 (rojo o azul, para que dos íconos sean distintos). Sin GD: los bytes ya armados.
 *
 * @return array{ruta: string, nombre: string}
 */
function iconoPng(bool $azul = false): array
{
    return iconoArchivo('logo.png', (string) base64_decode($azul ? 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQkzsBAAFiAQURG6MhAAAAAElFTkSuQmCC' : 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGM4IScHAAK2AQU0pnWqAAAAAElFTkSuQmCC'));
}

/** @param array{ruta: string, nombre: string} $archivo */
function subirIcono(Aplicacion $aplicacion, array $archivo, User $persona): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.aplicaciones.subir-icono', ['aplicacion_id' => $aplicacion->id, 'archivo' => $archivo], $persona);
}

function administracion(): User
{
    return User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
}

it('administración sube el ícono: la aplicación pasa a «Propio» y el panel lo muestra incrustado', function () {
    $aplicacion = Aplicacion::factory()->create(['codigo' => 'intranet', 'nombre' => 'Intranet', 'icono' => IconoAplicacion::Generica, 'orden' => 0]);

    subirIcono($aplicacion, iconoPng(), administracion());

    $aplicacion->refresh();
    expect($aplicacion->icono)->toBe(IconoAplicacion::Propio)
        ->and($aplicacion->icono_adjunto_id)->not->toBeNull()
        ->and($aplicacion->version)->toBe(2)
        ->and(TipoAdjunto::query()->whereKey(Adjunto::query()->whereKey($aplicacion->icono_adjunto_id)->value('tipo_adjunto_id'))->value('codigo'))->toBe('icono_aplicacion');

    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    expect($html)->toContain('src="data:image/png;base64,')->toContain('data-icono="propio"');
});

it('subirlo otra vez reemplaza el anterior: queda uno solo vigente', function () {
    $aplicacion = Aplicacion::query()->where('codigo', 'word')->sole();
    $admin = administracion();

    subirIcono($aplicacion, iconoPng(), $admin);
    $primero = $aplicacion->refresh()->icono_adjunto_id;
    subirIcono($aplicacion, iconoPng(azul: true), $admin);

    expect($aplicacion->refresh()->icono_adjunto_id)->not->toBe($primero)
        ->and(Adjunto::query()->where('adjuntable_type', 'aplicacion')->where('adjuntable_id', $aplicacion->id)->whereNull('quitado_at')->count())->toBe(1);
});

it('solo quien edita el catálogo lo sube', function () {
    expect(fn () => subirIcono(Aplicacion::query()->where('codigo', 'word')->sole(), iconoPng(), User::factory()->create()))
        ->toThrow(AccionRechazada::class);
});

it('nunca un SVG, ni otro tipo, ni uno que dice ser PNG sin serlo', function (string $nombre, string $contenido) {
    expect(fn () => subirIcono(Aplicacion::query()->where('codigo', 'word')->sole(), iconoArchivo($nombre, $contenido), administracion()))
        ->toThrow(AdjuntoRechazado::class);

    expect(Aplicacion::query()->where('codigo', 'word')->sole()->icono)->toBe(IconoAplicacion::Word);
})->with([
    'svg' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'pdf' => ['logo.pdf', "%PDF-1.4\n%%EOF\n"],
    'png falso' => ['logo.png', 'no soy una imagen'],
]);

it('la base exige la imagen para «propio», y las propias de cada persona no lo usan', function () {
    $persona = User::factory()->create();

    expect(fn () => DB::transaction(fn () => Aplicacion::query()->where('codigo', 'word')->update(['icono' => 'propio'])))
        ->toThrow(QueryException::class, 'aplicaciones_icono_propio_check')
        ->and(fn () => DB::transaction(fn () => DB::table('aplicaciones_propias')->insert(['user_id' => $persona->id, 'nombre' => 'X', 'enlace' => 'https://x.example', 'icono' => 'propio', 'created_by' => $persona->id])))
        ->toThrow(QueryException::class, 'aplicaciones_propias_icono_check')
        ->and(array_map(fn (IconoAplicacion $icono): string => $icono->value, IconoAplicacion::paraPropias()))->not->toContain('propio');
});

it('el tipo de adjunto del ícono es del sistema: no se borra desde el catálogo', function () {
    expect(Catalogo::TiposAdjunto->esDelSistema('icono_aplicacion'))->toBeTrue();
});

it('«Propio» sin imagen se rechaza con un mensaje, en el catálogo y en las propias (no con un error de la base)', function () {
    $admin = administracion();
    $word = Aplicacion::query()->where('codigo', 'word')->sole();

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.aplicaciones.editar', ['codigo' => 'word', 'nombre' => 'Word', 'enlace' => $word->enlace, 'icono' => 'propio', 'escritorio' => 'word', 'orden' => $word->orden, 'version' => $word->version], $admin))
        ->toThrow(ElementoCatalogoRechazado::class, 'súbelo primero con «Subir ícono»')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.agregar-mi-aplicacion', ['nombre' => 'Mía', 'enlace' => 'https://mia.example', 'icono' => 'propio'], $admin))
        ->toThrow(AplicacionRechazada::class, 'solo para las aplicaciones de Techquk');
});

it('un ícono quitado (o sin su archivo) no se muestra: el panel cae a la inicial', function () {
    $aplicacion = Aplicacion::factory()->create(['codigo' => 'intranet', 'nombre' => 'Intranet', 'icono' => IconoAplicacion::Generica, 'orden' => 0]);
    subirIcono($aplicacion, iconoPng(), administracion());
    Adjunto::query()->whereKey($aplicacion->refresh()->icono_adjunto_id)->update(['quitado_at' => now(), 'quitado_por_user_id' => User::factory()->create()->id]);
    Cache::flush();

    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->not->toContain('src="data:image/png;base64,')
        ->toContain('<span class="tq-apps-ico" data-icono="generica" aria-hidden="true">I</span>');
});

it('las extensiones, el tamaño y el registro del tipo de adjunto del ícono son fijos', function () {
    expect(Catalogo::TiposAdjunto->camposFijosDelSistema())->toContain('extensiones', 'tamano_maximo_mb', 'documento_type');
});
