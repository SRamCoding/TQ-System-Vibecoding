<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Filament\Pages\FaltaGerenteGeneral;
use App\Modules\Nucleo\Http\ExigirGerenteGeneral;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Http\Request;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * El rol `gerencia general` (plan 1.10, D2): viene con el sistema, se registra al instalar, sin él el panel no arranca
 * y nunca queda sin una persona activa que lo tenga. Aquí el bloqueo va encendido (las demás pruebas lo apagan,
 * tests/Pest.php) y cada prueba empieza con un gerente. Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.exigir_gerente_general' => true]);
    $gerente = User::factory()->create(['email' => 'gerencia@techquk.test']);
    $gerente->assignRole(rolGerencia());
});

function rolGerencia(): Role
{
    return Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web');
}

function gerenteDePrueba(): User
{
    return User::query()->where('email', 'gerencia@techquk.test')->sole();
}

/** Quien administra aquí: un rol que puede gestionar usuarios y roles. */
function adminDeGerencia(): User
{
    $rol = Role::findOrCreate('gestor-de-prueba', 'web');
    $rol->syncPermissions([Permission::findOrCreate('usuarios.gestionar'), Permission::findOrCreate('roles.gestionar')]);

    return User::factory()->create()->assignRole($rol);
}

/** Deja la base sin gerente general activo, sin pasar por el servicio (que lo impediría). */
function sinGerente(): void
{
    gerenteDePrueba()->forceFill(['es_activo' => false])->save();
}

/** @param array<string, string> $opciones */
function registrarGerente(array $opciones = []): PendingCommand
{
    return test()->artisan('seguridad:gerente-general', [
        '--documento' => '07654321', '--nombres' => 'Rosa', '--apellidos' => 'Quispe Lara', '--email' => 'rosa@techquk.test', ...$opciones,
    ])
        ->expectsQuestion('Clave (al menos 12 caracteres, con mayúscula, minúscula, número y carácter especial)', 'Una-clave-larga-1')
        ->expectsQuestion('Repite la clave', 'Una-clave-larga-1');
}

test('el rol viene con el sistema, sin permisos, y el seeder no le toca los que la grilla le dé', function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    rolGerencia()->givePermissionTo(Permission::findOrCreate('usuarios.ver'));

    test()->seed(NucleoSeeder::class);

    expect(rolGerencia()->permissions()->pluck('name')->all())->toBe(['usuarios.ver']);
});

test('al instalar se registra con su comando, a nombre del usuario de sistema; con uno activo, se niega', function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);

    test()->artisan('seguridad:gerente-general')
        ->expectsOutput('Ya hay un gerente general activo: el siguiente se lo asigna un administrador, desde «Usuarios».')
        ->assertFailed();

    sinGerente();
    registrarGerente()->expectsOutput('Gerente general registrado: rosa@techquk.test.')->assertSuccessful();

    $rosa = User::query()->where('email', 'rosa@techquk.test')->sole();

    expect($rosa->hasRole(SeguridadService::ROL_GERENCIA_GENERAL))->toBeTrue()
        ->and($rosa->created_by)->toBe(app(UsuarioSistema::class)->id())
        ->and(app(SeguridadService::class)->hayGerenteGeneralActivo())->toBeTrue();
});

test('nunca queda sin gerente general activo: primero se asigna al nuevo, después se quita al anterior', function () {
    $admin = adminDeGerencia();
    $gerente = gerenteDePrueba();
    $seguridad = app(SeguridadService::class);

    expect(fn () => $seguridad->quitarRol($gerente->id, rolGerencia()->id, $admin))->toThrow(SeguridadRechazada::class, 'sin gerente general activo')
        ->and(fn () => $seguridad->desactivarUsuario($gerente->id, $gerente->version, $admin))->toThrow(SeguridadRechazada::class, 'sin gerente general activo')
        ->and($gerente->fresh()?->hasRole(SeguridadService::ROL_GERENCIA_GENERAL))->toBeTrue();

    $nueva = User::factory()->create();
    $seguridad->asignarRol($nueva->id, rolGerencia()->id, null, $admin);
    $seguridad->quitarRol($gerente->id, rolGerencia()->id, $admin);

    expect($gerente->fresh()?->hasRole(SeguridadService::ROL_GERENCIA_GENERAL))->toBeFalse()
        ->and($seguridad->hayGerenteGeneralActivo())->toBeTrue();
});

test('no se renombra ni se da dentro de un ámbito; sus permisos sí se cambian', function () {
    $admin = adminDeGerencia();
    $seguridad = app(SeguridadService::class);
    $unidad = UnidadOrganizativa::factory()->create();

    expect(fn () => $seguridad->renombrarRol(rolGerencia()->id, 'dirección'))->toThrow(SeguridadRechazada::class, 'no se renombra')
        ->and(fn () => $seguridad->asignarRolEnAmbito(User::factory()->create()->id, rolGerencia()->id, 'unidad_organizativa', $unidad->id, null, null, null, $admin))
        ->toThrow(SeguridadRechazada::class, 'no se da dentro de un ámbito');

    $seguridad->cambiarPermisosDeRol(rolGerencia()->id, [Permission::findOrCreate('usuarios.ver')->id], null, $admin);

    expect(rolGerencia()->fresh()?->permissions()->pluck('name')->all())->toBe(['usuarios.ver']);
});

test('el bloqueo viene encendido y no depende del .env: solo las pruebas lo apagan', function () {
    expect((require base_path('config/nucleo.php'))['exigir_gerente_general'])->toBeTrue();
});

test('sin gerente general activo, toda página lleva a «Falta registrar al gerente general»; con él, esa pantalla no se abre', function () {
    $persona = User::factory()->create();

    test()->actingAs($persona)->get('/')->assertOk();
    test()->actingAs($persona)->get(FaltaGerenteGeneral::getUrl())->assertForbidden();

    sinGerente();

    test()->actingAs($persona)->get('/')->assertRedirect(FaltaGerenteGeneral::getUrl());
    test()->actingAs($persona)->get('/mis-preferencias')->assertRedirect(FaltaGerenteGeneral::getUrl());
    test()->actingAs($persona)->get(FaltaGerenteGeneral::getUrl())->assertOk()->assertSee('php artisan seguridad:gerente-general');
});

test('el bloqueo también corre en Livewire: solo pasan las peticiones de su pantalla', function () {
    sinGerente();
    test()->actingAs(User::factory()->create());
    $nombre = app('livewire.finder')->normalizeName(FaltaGerenteGeneral::class);
    $peticion = fn (array $nombres): Request => tap(
        Request::create(FaltaGerenteGeneral::getUrl(), 'POST', ['components' => array_map(fn (string $n): array => ['snapshot' => json_encode(['memo' => ['name' => $n]])], $nombres)]),
        fn (Request $r) => $r->headers->set('X-Livewire', '1'),
    );

    expect(Livewire::getPersistentMiddleware())->toContain(ExigirGerenteGeneral::class)
        ->and(fn () => app(ExigirGerenteGeneral::class)->handle($peticion(['filament.livewire.topbar']), fn () => response('ok')))->toThrow(HttpException::class)
        ->and(app(ExigirGerenteGeneral::class)->handle($peticion([$nombre]), fn () => response('ok'))->getContent())->toBe('ok');
});

test('quien debe renovar su contraseña la renueva antes de ver el bloqueo', function () {
    sinGerente();

    test()->actingAs(User::factory()->create(['debe_cambiar_clave' => true]))->get('/')->assertRedirect('/renovar-clave');
});
