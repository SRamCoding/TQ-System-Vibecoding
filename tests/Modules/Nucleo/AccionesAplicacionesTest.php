<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Aplicaciones\AgregarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\CambiarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\ElegirDondeAbrirAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\MostrarAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\MoverAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\OcultarAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\PonerAplicacionPrincipal;
use App\Modules\Nucleo\Acciones\Aplicaciones\QuitarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\RegistrarUsoAplicacion;
use App\Modules\Nucleo\Aplicaciones\AplicacionDelPanel;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\GrupoCatalogo;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AplicacionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\AplicacionOculta;
use App\Modules\Nucleo\Models\AplicacionPrincipal;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Models\UsoAplicacion;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\AplicacionesService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4p, paso 3: las acciones del panel de aplicaciones, sin interfaz. Las de cada persona no piden permiso (es su
 * propio panel); las de administración son las del catálogo `aplicaciones` (Configuración → Catálogos).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(ParametrosSeeder::class);
    test()->seed(CatalogosSeeder::class);
});

/**
 * @param  array<string, mixed>  $datos
 */
function accionPanel(string $clave, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.{$clave}", $datos, $usuario);
}

/**
 * @return list<string>
 */
function nombresEnElPanel(User $usuario): array
{
    $cuadricula = app(AplicacionesService::class)->deLaCuadricula($usuario);

    return array_map(fn (AplicacionDelPanel $aplicacion): string => $aplicacion->nombre, [...$cuadricula['techquk'], ...$cuadricula['propias']]);
}

test('el núcleo registra las nueve acciones de cada persona, sin permiso', function () {
    $acciones = [
        'nucleo.agregar-mi-aplicacion' => AgregarMiAplicacion::class,
        'nucleo.cambiar-mi-aplicacion' => CambiarMiAplicacion::class,
        'nucleo.quitar-mi-aplicacion' => QuitarMiAplicacion::class,
        'nucleo.ocultar-aplicacion' => OcultarAplicacion::class,
        'nucleo.mostrar-aplicacion' => MostrarAplicacion::class,
        'nucleo.registrar-uso-aplicacion' => RegistrarUsoAplicacion::class,
        'nucleo.poner-aplicacion-principal' => PonerAplicacionPrincipal::class,
        'nucleo.mover-aplicacion' => MoverAplicacion::class,
        'nucleo.elegir-donde-abrir-aplicacion' => ElegirDondeAbrirAplicacion::class,
    ];

    foreach ($acciones as $clave => $clase) {
        expect(app(RegistroAcciones::class)->todas())->toHaveKey($clave, $clase)
            ->and(app(RegistroAcciones::class)->obtener($clave)->permiso())->toBeNull();
    }
});

test('agrega, cambia y quita una propia a nombre de la persona; el enlace se guarda limpio', function () {
    $usuario = User::factory()->create();

    $id = accionPanel('agregar-mi-aplicacion', ['nombre' => ' Mi tablero ', 'enlace' => 'https://Tablero.Example/inicio', 'icono' => 'generica'], $usuario);
    $propia = AplicacionPropia::query()->findOrFail($id);

    expect($propia)->nombre->toBe('Mi tablero')->enlace->toBe('https://tablero.example/inicio')->created_by->toBe($usuario->id);

    accionPanel('cambiar-mi-aplicacion', ['aplicacion_id' => $id, 'nombre' => 'Mi correo', 'enlace' => 'https://outlook.office.com/mail', 'icono' => 'outlook', 'version' => 1], $usuario);

    expect($propia->refresh())->nombre->toBe('Mi correo')->icono->toBe(IconoAplicacion::Outlook)->version->toBe(2)
        ->and(fn () => accionPanel('quitar-mi-aplicacion', ['aplicacion_id' => $id, 'version' => 1], $usuario))->toThrow(VersionDesactualizada::class);

    accionPanel('quitar-mi-aplicacion', ['aplicacion_id' => $id, 'version' => 2], $usuario);

    expect(AplicacionPropia::query()->find($id))->toBeNull();
});

test('rechaza un enlace sin https, uno que no es dirección web y un nombre repetido o en blanco', function (array $datos, string $mensaje) {
    $usuario = User::factory()->create();
    AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Tablero']);

    expect(fn () => accionPanel('agregar-mi-aplicacion', ['nombre' => 'Otra', 'enlace' => 'https://otra.example', 'icono' => 'generica', ...$datos], $usuario))
        ->toThrow(AplicacionRechazada::class, $mensaje);
})->with([
    'http' => [['enlace' => 'http://otra.example'], 'https://'],
    'no es dirección' => [['enlace' => 'no es un enlace'], 'no es una dirección web'],
    'nombre repetido' => [['nombre' => 'tablero'], 'Ya tienes una aplicación con ese nombre'],
]);

test('un nombre en blanco no pasa la validación', function () {
    expect(fn () => accionPanel('agregar-mi-aplicacion', ['nombre' => '   ', 'enlace' => 'https://otra.example', 'icono' => 'generica'], User::factory()->create()))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('nombre'));
});

test('la propia de otra persona no se cambia, no se quita y no se delata', function () {
    $ajena = AplicacionPropia::factory()->create();
    $usuario = User::factory()->create();

    expect(fn () => accionPanel('cambiar-mi-aplicacion', ['aplicacion_id' => $ajena->id, 'nombre' => 'Mía', 'enlace' => 'https://mia.example', 'icono' => 'generica', 'version' => 1], $usuario))
        ->toThrow(AplicacionRechazada::class, 'no está en tu panel')
        ->and(fn () => accionPanel('quitar-mi-aplicacion', ['aplicacion_id' => $ajena->id, 'version' => 1], $usuario))->toThrow(AplicacionRechazada::class, 'no está en tu panel')
        ->and($ajena->refresh()->nombre)->not->toBe('Mía');
});

test('oculta y vuelve a mostrar una de Techquk solo para esa persona; repetir no hace nada', function () {
    $usuario = User::factory()->create();
    $word = Aplicacion::query()->where('codigo', 'word')->value('id');

    accionPanel('ocultar-aplicacion', ['aplicacion_id' => $word], $usuario);
    accionPanel('ocultar-aplicacion', ['aplicacion_id' => $word], $usuario);

    expect(nombresEnElPanel($usuario))->toBe(['Claude', 'Excel', 'WhatsApp', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw'])
        ->and(nombresEnElPanel(User::factory()->create()))->toBe(['Claude', 'Excel', 'WhatsApp', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw'])
        ->and(AplicacionOculta::query()->where('user_id', $usuario->id)->sole()->created_by)->toBe($usuario->id);

    accionPanel('mostrar-aplicacion', ['aplicacion_id' => $word], $usuario);
    accionPanel('mostrar-aplicacion', ['aplicacion_id' => $word], $usuario);

    expect(nombresEnElPanel($usuario))->toBe(['Claude', 'Excel', 'WhatsApp', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw']);
});

test('no se oculta una desactivada ni una que no existe', function () {
    $usuario = User::factory()->create();
    $excel = Aplicacion::query()->where('codigo', 'excel')->sole();
    $excel->update(['es_activo' => false]);

    expect(fn () => accionPanel('ocultar-aplicacion', ['aplicacion_id' => $excel->id], $usuario))->toThrow(AplicacionRechazada::class)
        ->and(fn () => accionPanel('ocultar-aplicacion', ['aplicacion_id' => 999999], $usuario))->toThrow(ValidationException::class);
});

test('abrir desde el panel suma un uso a nombre de la persona', function () {
    $usuario = User::factory()->create();
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id]);
    $excel = Aplicacion::query()->where('codigo', 'excel')->value('id');

    accionPanel('registrar-uso-aplicacion', ['origen' => 'techquk', 'aplicacion_id' => $excel], $usuario);
    accionPanel('registrar-uso-aplicacion', ['origen' => 'techquk', 'aplicacion_id' => $excel], $usuario);
    accionPanel('registrar-uso-aplicacion', ['origen' => 'propia', 'aplicacion_id' => $propia->id], $usuario);

    $uso = UsoAplicacion::query()->where('aplicacion_id', $excel)->sole();

    expect($uso)->usos->toBe(2)->user_id->toBe($usuario->id)->created_by->toBe($usuario->id)->updated_by->toBe($usuario->id)
        ->and(UsoAplicacion::query()->where('aplicacion_propia_id', $propia->id)->value('usos'))->toBe(1)
        ->and(fn () => accionPanel('registrar-uso-aplicacion', ['origen' => 'otra', 'aplicacion_id' => $excel], $usuario))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('origen'));
});

test('pone una entre sus 3 principales a nombre de la persona; el lugar va del 1 al 3', function () {
    $usuario = User::factory()->create();
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->value('id');

    accionPanel('poner-aplicacion-principal', ['posicion' => 2, 'origen' => 'techquk', 'aplicacion_id' => $gmail], $usuario);

    expect(array_map(fn (AplicacionDelPanel $aplicacion): string => $aplicacion->nombre, app(AplicacionesService::class)->delMuelle($usuario)))->toBe(['Claude', 'Gmail', 'WhatsApp'])
        ->and(AplicacionPrincipal::query()->where('user_id', $usuario->id)->where('posicion', 2)->sole()->created_by)->toBe($usuario->id);

    foreach ([0, 4] as $lugar) {
        expect(fn () => accionPanel('poner-aplicacion-principal', ['posicion' => $lugar, 'origen' => 'techquk', 'aplicacion_id' => $gmail], $usuario))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('posicion'));
    }
});

test('una propia puede tener su programa de escritorio, solo de la lista cerrada (1.4z)', function () {
    $usuario = User::factory()->create();

    accionPanel('agregar-mi-aplicacion', ['nombre' => 'Reuniones', 'enlace' => 'https://zoom.us', 'icono' => 'generica', 'escritorio' => 'zoom'], $usuario);

    expect(AplicacionPropia::query()->where('user_id', $usuario->id)->sole()->escritorio)->toBe(ProgramaEscritorio::Zoom)
        ->and(fn () => accionPanel('agregar-mi-aplicacion', ['nombre' => 'Otra', 'enlace' => 'https://otra.example', 'icono' => 'generica', 'escritorio' => 'javascript:alert(1)'], $usuario))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('escritorio'));
});

test('rechaza al usuario de sistema y a uno inactivo', function (string $clave, array $datos) {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(fn () => accionPanel($clave, $datos, $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => accionPanel($clave, $datos, User::factory()->inactivo()->create()))->toThrow(AccionRechazada::class);
})->with([
    'agregar' => ['agregar-mi-aplicacion', ['nombre' => 'X', 'enlace' => 'https://x.example', 'icono' => 'generica']],
    'ocultar' => ['ocultar-aplicacion', ['aplicacion_id' => 1]],
    'abrir' => ['registrar-uso-aplicacion', ['origen' => 'techquk', 'aplicacion_id' => 1]],
    'poner principal' => ['poner-aplicacion-principal', ['posicion' => 1, 'origen' => 'techquk', 'aplicacion_id' => 1]],
]);

test('administración agrega una para todos desde el catálogo, solo con https', function () {
    Permission::findOrCreate('catalogos.editar', 'web');
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');

    expect(Catalogo::Aplicaciones->grupo())->toBe(GrupoCatalogo::DocumentosYArchivos)
        ->and(fn () => accionPanel('aplicaciones.crear', ['codigo' => 'intranet', 'nombre' => 'Intranet', 'enlace' => 'http://intranet.example', 'icono' => 'generica', 'orden' => 4], $editor))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('enlace'));

    accionPanel('aplicaciones.crear', ['codigo' => 'intranet', 'nombre' => 'Intranet', 'enlace' => 'https://intranet.example', 'icono' => 'generica', 'orden' => 0], $editor);

    expect(nombresEnElPanel(User::factory()->create()))->toBe(['Intranet', ...['Claude', 'Excel', 'WhatsApp', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw']])
        ->and(fn () => accionPanel('aplicaciones.crear', ['codigo' => 'otra', 'nombre' => 'Otra', 'enlace' => 'https://otra.example', 'icono' => 'generica', 'orden' => 1], User::factory()->create()))
        ->toThrow(AccionRechazada::class);
});
