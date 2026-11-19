<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\FotoPerfilService;
use App\Modules\Nucleo\Services\HuerfanosAdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Plan 2.61, D1 y D2: la foto de referencia del proyecto, una sola, que solo cambia o quita su jefe, y que ve quien
 * puede ver proyectos. Personas y proyectos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Storage::fake(FotoPerfilService::DISCO);
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);
});

function proyectoConJefe(User $jefe): Proyecto
{
    $creado = app(ProyectosService::class)->crear(
        new DatosProyecto('Cámaras de la planta inventada', $jefe->id, CarbonImmutable::parse('2026-11-15'), CarbonImmutable::parse('2027-02-28')),
        User::factory()->create(),
    );

    return Proyecto::query()->findOrFail($creado->id);
}

/** Un PNG de verdad (1 × 1) en un archivo temporal. */
function pngDelProyecto(): string
{
    $ruta = (string) tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return $ruta;
}

function cambiarFotoDelProyecto(Proyecto $proyecto, User $quien, string $archivo): void
{
    app(EjecutorAcciones::class)->ejecutar('erp.proyectos.cambiar-foto', ['proyecto_id' => $proyecto->id, 'foto' => ['ruta' => $archivo, 'nombre' => 'obra.png']], $quien);
}

test('el jefe sube la foto, la cambia (la anterior se borra) y la quita', function () {
    $jefe = User::factory()->create();
    $proyecto = proyectoConJefe($jefe);
    $version = $proyecto->version;

    cambiarFotoDelProyecto($proyecto, $jefe, pngDelProyecto());
    $primera = (string) $proyecto->refresh()->foto_ruta;
    expect($primera)->toStartWith("fotos-proyecto/{$proyecto->id}/")->toEndWith('.png')
        ->and(Storage::disk(FotoPerfilService::DISCO)->exists($primera))->toBeTrue()
        // No invalida un formulario abierto de la ficha.
        ->and($proyecto->version)->toBe($version);

    cambiarFotoDelProyecto($proyecto, $jefe, pngDelProyecto());
    expect(Storage::disk(FotoPerfilService::DISCO)->exists($primera))->toBeFalse()
        ->and($proyecto->refresh()->foto_ruta)->not->toBe($primera);

    app(EjecutorAcciones::class)->ejecutar('erp.proyectos.quitar-foto', ['proyecto_id' => $proyecto->id], $jefe);
    expect($proyecto->refresh()->foto_ruta)->toBeNull()
        ->and(Storage::disk(FotoPerfilService::DISCO)->allFiles())->toBe([]);
});

test('nadie más que el jefe cambia o quita la foto, ni con todos los permisos de proyectos', function () {
    $jefe = User::factory()->create();
    $proyecto = proyectoConJefe($jefe);
    cambiarFotoDelProyecto($proyecto, $jefe, pngDelProyecto());
    $otro = User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, 'erp.proyectos.crear']);

    expect(fn () => cambiarFotoDelProyecto($proyecto, $otro, pngDelProyecto()))->toThrow(ProyectoRechazado::class, 'Solo el jefe del proyecto')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('erp.proyectos.quitar-foto', ['proyecto_id' => $proyecto->id], $otro))->toThrow(ProyectoRechazado::class, 'Solo el jefe del proyecto')
        ->and($proyecto->refresh()->foto_ruta)->not->toBeNull()
        ->and(Storage::disk(FotoPerfilService::DISCO)->allFiles())->toHaveCount(1);
});

test('solo acepta imágenes', function () {
    $jefe = User::factory()->create();
    $proyecto = proyectoConJefe($jefe);
    $texto = (string) tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($texto, 'no soy una imagen');

    expect(fn () => cambiarFotoDelProyecto($proyecto, $jefe, $texto))->toThrow(FotoRechazada::class)
        ->and(Storage::disk(FotoPerfilService::DISCO)->allFiles())->toBe([]);
});

test('la foto se sirve con sesión a quien ve proyectos; a los demás, no se encuentra', function () {
    $jefe = User::factory()->create()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER);
    $proyecto = proyectoConJefe($jefe);
    cambiarFotoDelProyecto($proyecto, $jefe, pngDelProyecto());
    $url = (string) FotoProyectoService::url($proyecto->refresh());

    expect($url)->toContain("/imagenes/proyecto/{$proyecto->id}?v=");

    test()->get($url)->assertRedirect();
    test()->actingAs(User::factory()->create())->get($url)->assertNotFound();
    test()->actingAs(User::factory()->create()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER))->get($url)
        ->assertOk()->assertHeader('Content-Type', 'image/png');
    test()->get('/imagenes/no-existe/'.$proyecto->id)->assertNotFound();
});

test('la limpieza de adjuntos no toca las fotos de proyectos; la base no acepta una ruta vacía', function () {
    expect(HuerfanosAdjuntosService::esDeOtroServicio('fotos-proyecto/1/x.png'))->toBeTrue();

    $proyecto = proyectoConJefe(User::factory()->create());
    expect(fn () => DB::table('proyectos')->where('id', $proyecto->id)->update(['foto_ruta' => '  ']))->toThrow(QueryException::class);
});
