<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Organigrama\Pages\ListarUnidades;
use App\Modules\Nucleo\Filament\Resources\Organigrama\PuestosDeUnidad;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\ListarUsuarios;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\VerUsuario;
use App\Modules\Nucleo\Filament\Resources\Usuarios\RolesDeUsuario;
use App\Modules\Nucleo\Filament\Resources\Usuarios\RolesPorUnidadDeUsuario;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Plan 1.4, paso 6: las pantallas de usuarios y organigrama son solo presentación. Aquí: que abren con su permiso,
 * que llaman a las acciones registradas con la versión leída al abrir, que los selectores aceptan el valor que el
 * registro ya tiene, y que el control compensatorio se pide en el mismo formulario. Las reglas ya se prueban en los
 * servicios y las acciones. Las pestañas se prueban como componentes hijos (AGENTS.md).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-03-10 10:00', 'America/Lima'));
    Filament::setCurrentPanel('app');
});

function entrarComoGestor(): User
{
    $rol = Role::findOrCreate('gestor-pantallas');
    $rol->syncPermissions(['usuarios.ver', 'usuarios.gestionar', 'organigrama.ver', 'organigrama.gestionar', 'roles.gestionar']);
    $usuario = User::factory()->create()->assignRole($rol);
    test()->actingAs($usuario);

    return $usuario;
}

// --- Usuarios ---

test('la lista de usuarios pide usuarios.ver y no muestra la cuenta de sistema', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'));
    test()->get(ListarUsuarios::getUrl())->assertForbidden();

    $gestor = entrarComoGestor();
    $ana = User::factory()->create(['puesto_id' => Puesto::factory()->create(['nombre' => 'Asistente de compras'])->id]);

    Livewire::test(ListarUsuarios::class)
        ->assertCanSeeTableRecords([$gestor, $ana])
        ->assertCanNotSeeTableRecords(User::query()->where('es_sistema', true)->get())
        ->assertSee('Asistente de compras')
        ->assertSee('gestor-pantallas')
        ->assertSee('Nunca');
});

test('registrar usuario desde la lista lleva a su ficha', function () {
    entrarComoGestor();
    $persona = Party::factory()->create(['nombres' => 'Carmen', 'apellidos' => 'Ruiz Díaz', 'razon_social' => 'Ruiz Díaz Carmen']);

    Livewire::test(ListarUsuarios::class)
        ->callAction('nucleoSeguridadRegistrarUsuario', ['party_id' => $persona->id, 'email' => 'carmen@techquk.test'])
        ->assertHasNoActionErrors()
        ->assertRedirect(VerUsuario::getUrl(['record' => User::query()->where('email', 'carmen@techquk.test')->sole()->id]));
});

test('cambiar puesto toma la versión al abrir: si otro guardó antes, se rechaza', function () {
    entrarComoGestor();
    $ana = User::factory()->create();
    $puesto = Puesto::factory()->create();

    $pantalla = Livewire::test(ListarUsuarios::class)
        ->mountAction(TestAction::make('nucleoSeguridadCambiarPuestoUsuario')->table($ana));

    app(SeguridadService::class)->cambiarPuesto($ana->id, $puesto->id, 1, User::factory()->create());

    $pantalla->fillForm(['puesto_id' => null])->callMountedAction()
        ->assertNotified("Otra persona cambió la cuenta de {$ana->name} mientras lo editabas. Vuelve a abrirlo para ver el valor actual.");

    expect($ana->refresh()->puesto_id)->toBe($puesto->id);
});

test('el selector de puesto acepta el puesto desactivado que la cuenta ya tiene', function () {
    entrarComoGestor();
    $puesto = Puesto::factory()->create();
    $ana = User::factory()->inactivo()->create(['puesto_id' => $puesto->id]);
    $puesto->update(['es_activo' => false]);

    Livewire::test(ListarUsuarios::class)
        ->callAction(TestAction::make('nucleoSeguridadCambiarPuestoUsuario')->table($ana))
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');
});

test('desactivar solo se ofrece a una cuenta activa, y activar a una desactivada', function () {
    entrarComoGestor();
    $activa = User::factory()->create();
    $inactiva = User::factory()->inactivo()->create();

    Livewire::test(ListarUsuarios::class)
        ->assertActionVisible(TestAction::make('nucleoSeguridadDesactivarUsuario')->table($activa))
        ->assertActionHidden(TestAction::make('nucleoSeguridadActivarUsuario')->table($activa))
        ->assertActionHidden(TestAction::make('nucleoSeguridadDesactivarUsuario')->table($inactiva))
        ->callAction(TestAction::make('nucleoSeguridadDesactivarUsuario')->table($activa))
        ->assertNotified('Guardado.');

    expect($activa->refresh()->es_activo)->toBeFalse();
});

test('asignar un rol que junta permisos incompatibles pide el control en el mismo formulario', function () {
    entrarComoGestor();
    $persona = User::factory()->create()->assignRole(Role::findOrCreate('registra-pagos')->givePermissionTo(Permission::findOrCreate('pagos.registrar')));
    $aprueba = Role::findOrCreate('aprueba-pagos')->givePermissionTo(Permission::findOrCreate('pagos.aprobar'));
    app(SeguridadService::class)->registrarIncompatibilidad(Permission::findByName('pagos.registrar')->id, Permission::findByName('pagos.aprobar')->id, 'Quien registra no aprueba.');
    $responsable = User::factory()->create();

    // El formulario queda abierto con el aviso: se completa ahí mismo con el control y su responsable.
    $pestana = Livewire::test(RolesDeUsuario::class, ['usuarioId' => $persona->id])
        ->mountAction(TestAction::make('nucleoSeguridadAsignarRol')->table())
        ->fillForm(['rol_id' => $aprueba->id])
        ->callMountedAction()
        ->assertNotified("Este cambio junta permisos incompatibles ({$persona->name}: pagos.aprobar × pagos.registrar). Registra el control compensatorio y su responsable para guardarlo.");

    expect(CruceFunciones::query()->exists())->toBeFalse();

    $pestana->fillForm(['control' => 'Gerencia revisa cada semana.', 'responsable_user_id' => $responsable->id])
        ->callMountedAction()
        ->assertNotified('Guardado.')
        ->assertSee('aprueba-pagos');

    expect(CruceFunciones::query()->sole()->controlActual?->responsable_user_id)->toBe($responsable->id);

    $pestana->callAction(TestAction::make('nucleoSeguridadQuitarRol')->table($aprueba))->assertNotified('Guardado.');

    expect(CruceFunciones::query()->sole()->hasta)->not->toBeNull();
});

test('la pestaña de roles por unidad asigna y quita un rol en una unidad', function () {
    entrarComoGestor();
    $ana = User::factory()->create();
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => 'Logística']);
    $rol = Role::findOrCreate('almacenero');

    $pestana = Livewire::test(RolesPorUnidadDeUsuario::class, ['usuarioId' => $ana->id])
        ->callAction(TestAction::make('nucleoSeguridadAsignarRolEnAmbito')->table(), ['rol_id' => $rol->id, 'ambito_id' => $unidad->id, 'hasta' => '2026-12-31'])
        ->assertNotified('Guardado.')
        ->assertSee('Logística')
        ->assertSee('Vigente');

    $asignacion = PermisoPorAmbito::query()->sole();

    $pestana->callAction(TestAction::make('nucleoSeguridadQuitarRolEnAmbito')->table($asignacion))
        ->assertNotified('Guardado.')
        ->assertSee('Terminado')
        ->assertActionHidden(TestAction::make('nucleoSeguridadQuitarRolEnAmbito')->table($asignacion->refresh()));
});

test('las pestañas piden el permiso de su pantalla', function () {
    test()->actingAs(User::factory()->create());

    Livewire::test(RolesDeUsuario::class, ['usuarioId' => 1])->assertForbidden();
    Livewire::test(PuestosDeUnidad::class, ['unidadId' => 1])->assertForbidden();
});

// --- Organigrama ---

test('el organigrama se ve en árbol, con la jefatura y quién la ocupa, o vacante', function () {
    entrarComoGestor();
    $gerencia = UnidadOrganizativa::factory()->create(['nombre' => 'Gerencia General', 'orden' => 1]);
    $logistica = UnidadOrganizativa::factory()->dentroDe($gerencia)->create(['nombre' => 'Logística', 'orden' => 2]);
    $compras = UnidadOrganizativa::factory()->dentroDe($gerencia)->create(['nombre' => 'Compras', 'orden' => 1]);
    $otraRaiz = UnidadOrganizativa::factory()->create(['nombre' => 'Auditoría', 'orden' => 2]);
    $jefe = Puesto::factory()->create(['unidad_organizativa_id' => $gerencia->id, 'nombre' => 'Gerente general']);
    $gerencia->update(['jefe_puesto_id' => $jefe->id]);
    $jefeLogistica = Puesto::factory()->create(['unidad_organizativa_id' => $logistica->id, 'nombre' => 'Jefe de logística']);
    $logistica->update(['jefe_puesto_id' => $jefeLogistica->id]);
    User::factory()->create(['puesto_id' => $jefe->id, 'name' => 'Ana Gerente']);

    Livewire::test(ListarUnidades::class)
        ->assertCanSeeTableRecords([$gerencia, $compras, $logistica, $otraRaiz], inOrder: true)
        ->assertSee('— Compras')
        ->assertSee('Ana Gerente')
        ->assertSee('Vacante')
        ->assertSee('Sin definir');
});

test('crear unidad y designar su jefatura desde la pantalla', function () {
    entrarComoGestor();

    Livewire::test(ListarUnidades::class)
        ->callAction('nucleoOrganigramaCrearUnidad', ['codigo' => 'gg', 'nombre' => 'Gerencia General', 'tipo' => 'gerencia'])
        ->assertNotified('Unidad creada.');

    $unidad = UnidadOrganizativa::query()->sole();
    $puesto = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);

    Livewire::test(ListarUnidades::class)
        ->callAction(TestAction::make('nucleoOrganigramaDesignarJefatura')->table($unidad), ['puesto_id' => $puesto->id])
        ->assertNotified('Guardado.');

    expect($unidad->refresh()->codigo)->toBe('GG')
        ->and($unidad->jefe_puesto_id)->toBe($puesto->id);
});

test('la pestaña de puestos crea, renombra y marca la jefatura', function () {
    entrarComoGestor();
    $unidad = UnidadOrganizativa::factory()->create();

    $pestana = Livewire::test(PuestosDeUnidad::class, ['unidadId' => $unidad->id])
        ->callAction(TestAction::make('nucleoOrganigramaCrearPuesto')->table(), ['nombre' => 'Jefe'])
        ->assertNotified('Guardado.')
        ->assertSee('Vacante');

    $puesto = Puesto::query()->sole();
    $unidad->update(['jefe_puesto_id' => $puesto->id]);

    $pestana->callAction(TestAction::make('nucleoOrganigramaEditarPuesto')->table($puesto), ['nombre' => 'Jefe de área'])
        ->assertNotified('Guardado.')
        ->assertSee('Jefe de área')
        ->assertSee('Jefatura de la unidad');
});

// --- Panel ---

test('la persona pone su clave con «olvidé mi contraseña»', function () {
    test()->get(Filament::getPanel('app')->getRequestPasswordResetUrl())->assertOk();
});
