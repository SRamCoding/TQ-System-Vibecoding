<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\Alertas;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Filament\Pages\RolesPermisos;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Plan 1.4c: la pantalla de roles y permisos solo presenta. Aquí: su permiso, la grilla, el panel de un rol, el control
 * que pide cuando un cambio abre un cruce, y lo que no ofrece porque el servicio lo rechazaría.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarConRoles(): User
{
    $usuario = User::factory()->create()->assignRole(Role::findOrCreate('seguridad-pantalla')->syncPermissions(['roles.gestionar']));
    test()->actingAs($usuario);

    return $usuario;
}

/** @return array<string, list<int>> los permisos del formulario del panel, en el grupo que les toca */
function marcarEnPanel(string ...$permisos): array
{
    $grupos = PermisosLegibles::agrupar(Permission::query()->pluck('name', 'id')->all());
    $ids = Permission::query()->whereIn('name', $permisos)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    $datos = [];

    foreach ($grupos as $i => $grupo) {
        $datos["grupo_{$i}"] = array_values(array_intersect(array_keys($grupo['permisos']), $ids));
    }

    return $datos;
}

test('pide roles.gestionar', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('usuarios.ver'));
    test()->get(RolesPermisos::getUrl())->assertForbidden();

    entrarConRoles();
    test()->get(RolesPermisos::getUrl())->assertOk()->assertSee('Quién puede qué');
});

test('la grilla marca lo que tiene cada rol, con los datos sensibles primero y nombres legibles', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Compras de prueba')->syncPermissions(['parties.ver', GrupoSensible::Costos->permiso()]);
    $costos = Permission::findByName(GrupoSensible::Costos->permiso())->id;
    $editar = Permission::findByName('parties.editar')->id;

    $pantalla = Livewire::test(RolesPermisos::class)
        ->assertSeeHtml('data-tq-tiene="'.$rol->id.':'.$costos.'"')
        ->assertDontSeeHtml('data-tq-tiene="'.$rol->id.':'.$editar.'"')
        ->assertSee('Compras de prueba')
        ->assertSee('Ver costos')
        ->assertDontSee('campos.ver-costos');

    expect(mb_strpos($pantalla->html(), 'Datos sensibles'))->toBeLessThan(mb_strpos($pantalla->html(), 'Personas y empresas'));
});

test('dice cuántas personas tiene cada rol, también las de un rol por unidad', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol con gente');
    User::factory()->create()->assignRole($rol);
    PermisoPorAmbito::factory()->create(['user_id' => User::factory()->create()->id, 'rol_id' => $rol->id, 'ambito_type' => 'unidad_organizativa', 'ambito_id' => UnidadOrganizativa::factory()->create()->id]);
    PermisoPorAmbito::factory()->create(['user_id' => User::factory()->create()->id, 'rol_id' => $rol->id, 'ambito_type' => 'unidad_organizativa', 'ambito_id' => UnidadOrganizativa::factory()->create()->id, 'desde' => '2020-01-01', 'hasta' => '2020-12-31']);

    expect(app(SeguridadService::class)->titularesPorRol()[$rol->id])->toHaveCount(2);

    Livewire::test(RolesPermisos::class)->assertSee('0 permisos · 2 personas');
});

test('crear un rol abre su panel, sin permisos', function () {
    entrarConRoles();

    $pantalla = Livewire::test(RolesPermisos::class)
        ->callAction('nucleoSeguridadCrearRol', ['nombre' => 'Logística de prueba'])
        ->assertHasNoActionErrors();

    $rol = Role::findByName('Logística de prueba');
    $pantalla->assertSet('rol', (string) $rol->id)->assertSeeHtml('data-rol-abierto="'.$rol->id.'"');
});

test('el panel guarda los permisos del rol en un solo cambio', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol por editar')->syncPermissions(['parties.ver']);

    Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->set('data', marcarEnPanel('parties.ver', 'parties.editar', GrupoSensible::Margen->permiso()))
        ->call('guardar')
        ->assertNotified('Guardado.');

    expect($rol->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['campos.ver-margen', 'parties.editar', 'parties.ver']);
});

test('un rol con ?rol= de otra cosa no tumba la pantalla', function () {
    entrarConRoles();

    Livewire::test(RolesPermisos::class, ['rol' => 'abc'])->assertSet('rol', null);
    Livewire::test(RolesPermisos::class, ['rol' => '999999'])->assertSet('rol', null);
});

test('si el cambio abre un cruce, el panel pide el control ahí mismo y lo guarda con el cambio', function () {
    entrarConRoles();
    $responsable = User::factory()->create(['name' => 'Responsable Inventado']);
    $persona = User::factory()->create(['name' => 'Persona Inventada']);
    $rol = Role::findOrCreate('Rol con cruce')->syncPermissions(['parties.crear']);
    $persona->assignRole($rol);
    $par = IncompatibilidadPermisos::factory()->create([
        'permiso_a_id' => min(Permission::findByName('parties.crear')->id, Permission::findByName('parties.borrar')->id),
        'permiso_b_id' => max(Permission::findByName('parties.crear')->id, Permission::findByName('parties.borrar')->id),
    ]);

    $pantalla = Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->set('data', marcarEnPanel('parties.crear', 'parties.borrar'))
        ->call('guardar')
        ->assertNotified('Falta el control compensatorio.')
        ->assertSee('Persona Inventada')
        ->assertSee('Crear fichas')
        ->assertSee('Borrar fichas');

    expect($rol->fresh()->permissions)->toHaveCount(1)
        ->and(CruceFunciones::query()->count())->toBe(0);

    $pantalla
        ->set('data.control', 'Revisión semanal de las fichas borradas.')
        ->set('data.responsable_user_id', $responsable->id)
        ->set('data.control_vigente_hasta', '2099-12-31')
        ->call('guardar')
        ->assertNotified('Guardado.')
        ->assertSet('cruces', []);

    $cruce = CruceFunciones::query()->where('incompatibilidad_id', $par->id)->sole();
    expect($rol->fresh()->permissions)->toHaveCount(2)
        ->and($cruce->user_id)->toBe($persona->id)
        ->and($cruce->controlActual->responsable_user_id)->toBe($responsable->id)
        ->and($cruce->controlActual->vigente_hasta->toDateString())->toBe('2099-12-31');
});

test('frenado por un cruce, quitar el permiso deja guardar sin control', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol que se arrepiente')->syncPermissions(['parties.crear']);
    User::factory()->create()->assignRole($rol);
    IncompatibilidadPermisos::factory()->create([
        'permiso_a_id' => min(Permission::findByName('parties.crear')->id, Permission::findByName('parties.borrar')->id),
        'permiso_b_id' => max(Permission::findByName('parties.crear')->id, Permission::findByName('parties.borrar')->id),
    ]);

    Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->set('data', marcarEnPanel('parties.crear', 'parties.borrar'))
        ->call('guardar')
        ->assertNotified('Falta el control compensatorio.')
        ->set('data', marcarEnPanel('parties.crear'))
        ->call('guardar')
        ->assertNotified('Guardado.');

    expect(CruceFunciones::query()->count())->toBe(0);
});

test('un control escrito para un cruce no cubre otro que la pantalla no mostró', function () {
    entrarConRoles();
    $responsable = User::factory()->create();
    $rol = Role::findOrCreate('Rol que cambia de cruce')->syncPermissions(['parties.crear']);
    User::factory()->create()->assignRole($rol);
    $crear = Permission::findByName('parties.crear')->id;

    foreach (['parties.borrar', 'parties.editar'] as $otro) {
        $id = Permission::findByName($otro)->id;
        IncompatibilidadPermisos::factory()->create(['permiso_a_id' => min($crear, $id), 'permiso_b_id' => max($crear, $id)]);
    }

    $pantalla = Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->set('data', marcarEnPanel('parties.crear', 'parties.borrar'))
        ->call('guardar')
        ->assertSee('Borrar fichas');

    $pantalla->set('data', [...marcarEnPanel('parties.crear', 'parties.editar'), 'control' => 'Escrito para el otro cruce.', 'responsable_user_id' => $responsable->id])
        ->call('guardar')
        ->assertNotified('Falta el control compensatorio.');

    expect(CruceFunciones::query()->count())->toBe(0)
        ->and($pantalla->get('cruces')[0]['permisos'])->toContain('Editar fichas')
        ->and($rol->fresh()->permissions)->toHaveCount(1);

    $pantalla->call('guardar')->assertNotified('Guardado.');
    expect(CruceFunciones::query()->count())->toBe(1);
});

test('quien se arrepiente con el control a medio escribir puede guardar', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol con control a medias')->syncPermissions(['parties.crear']);
    User::factory()->create()->assignRole($rol);
    $ids = [Permission::findByName('parties.crear')->id, Permission::findByName('parties.borrar')->id];
    IncompatibilidadPermisos::factory()->create(['permiso_a_id' => min($ids), 'permiso_b_id' => max($ids)]);

    Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->set('data', marcarEnPanel('parties.crear', 'parties.borrar'))
        ->call('guardar')
        ->set('data', [...marcarEnPanel('parties.crear'), 'control' => 'Texto sin responsable.'])
        ->call('guardar')
        ->assertNotified('Guardado.');

    expect($rol->fresh()->permissions)->toHaveCount(1);
});

test('el navegador no puede cambiar lo leído al abrir', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol con lectura fija');

    Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])->set('leidos', [1]);
})->throws(CannotUpdateLockedPropertyException::class);

test('las cajas que abren su propio panel no se las lleva el asistente', function () {
    entrarConRoles();
    Livewire::test(RolesPermisos::class)->assertSeeHtml('data-tq-abre-aqui');

    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'alertas.configurar']));
    Livewire::test(Catalogos::class)->assertSeeHtml('data-tq-abre-aqui');
    Livewire::test(Alertas::class)->assertSeeHtml('data-tq-abre-aqui');
});

test('si otra persona cambió el rol mientras estaba abierto, no lo pisa', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Rol disputado')->syncPermissions(['parties.ver']);
    $pantalla = Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id]);

    DB::table('role_has_permissions')->insert(['role_id' => $rol->id, 'permission_id' => Permission::findByName('parties.editar')->id]);

    $pantalla->set('data', marcarEnPanel('parties.ver'))
        ->call('guardar')
        ->assertNotified('Alguien más cambió este rol mientras lo tenías abierto. Revisa sus permisos de ahora y vuelve a guardar.');

    expect(DB::table('role_has_permissions')->where('role_id', $rol->id)->count())->toBe(2);
});

test('administrador se ve sin poder editarse ni renombrarse', function () {
    entrarConRoles();
    $administrador = Role::findByName(SeguridadService::ROL_ADMINISTRADOR);

    Livewire::test(RolesPermisos::class, ['rol' => (string) $administrador->id])
        ->assertSeeHtml('data-tq-rol-bloqueado')
        ->assertSee('vienen con el sistema')
        ->assertActionHidden('renombrar')
        ->assertDontSee('Guardar');
});

test('gerencia general cambia sus permisos, pero no su nombre', function () {
    entrarConRoles();
    $gerencia = Role::findByName(SeguridadService::ROL_GERENCIA_GENERAL);

    Livewire::test(RolesPermisos::class, ['rol' => (string) $gerencia->id])
        ->assertDontSeeHtml('data-tq-rol-bloqueado')
        ->assertActionHidden('renombrar')
        ->assertSee('Guardar');
});

test('quien tiene un rol no edita sus permisos, y la pantalla lo dice', function () {
    $actor = entrarConRoles();

    Livewire::test(RolesPermisos::class, ['rol' => (string) $actor->roles->first()->id])
        ->assertSee('nadie cambia los permisos de un rol suyo')
        ->assertDontSee('Guardar');
});

test('renombra un rol desde su panel', function () {
    entrarConRoles();
    $rol = Role::findOrCreate('Nombre viejo');

    Livewire::test(RolesPermisos::class, ['rol' => (string) $rol->id])
        ->callAction('renombrar', ['nombre' => 'Nombre nuevo'])
        ->assertHasNoActionErrors()
        ->assertSee('Nombre nuevo');

    expect($rol->fresh()->name)->toBe('Nombre nuevo');
});

test('un permiso sin nombre en la lista sale legible y en un grupo propio', function () {
    expect(PermisosLegibles::etiqueta('compras.aprobar-orden'))->toBe('Aprobar orden')
        ->and(PermisosLegibles::grupo('compras.aprobar-orden'))->toBe('Compras')
        ->and(PermisosLegibles::etiqueta(GrupoSensible::DatosBancarios->permiso()))->toBe('Ver datos bancarios')
        ->and(PermisosLegibles::grupo('nucleo.motivos.editar'))->toBe('Documentos');
});
