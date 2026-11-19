<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Conflictos\Pages\ListarConflictos;
use App\Modules\Nucleo\Filament\Resources\Incompatibilidades\Pages\ListarIncompatibilidades;
use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Plan 1.4c: Conflictos y Permisos incompatibles son solo presentación. Aquí: su permiso, los estados (Pendiente es lo
 * mismo que cuenta la alerta), y que llaman a las acciones registradas con lo leído al abrir.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-03-10 10:00', 'America/Lima'));
    Filament::setCurrentPanel('app');
});

function entrarAConflictos(): User
{
    $usuario = User::factory()->create()->assignRole(Role::findOrCreate('seguridad-conflictos')->syncPermissions(['roles.gestionar']));
    test()->actingAs($usuario);

    return $usuario;
}

function parDeConflicto(string $uno = 'parties.crear', string $otro = 'parties.borrar', array $atributos = []): IncompatibilidadPermisos
{
    $ids = [Permission::findByName($uno)->id, Permission::findByName($otro)->id];

    return IncompatibilidadPermisos::factory()->create(['permiso_a_id' => min($ids), 'permiso_b_id' => max($ids), ...$atributos]);
}

/** Una persona que junta los dos permisos del par, con su cruce abierto. */
function conflictoAbierto(IncompatibilidadPermisos $par, ?User $persona = null, array $atributos = []): CruceFunciones
{
    $persona ??= User::factory()->create();
    $persona->givePermissionTo([$par->permisoA, $par->permisoB]);

    return CruceFunciones::factory()->create(['user_id' => $persona->id, 'incompatibilidad_id' => $par->id, 'desde' => Calendario::hoy(), ...$atributos]);
}

function controlDelConflicto(CruceFunciones $cruce, ?string $vence = null): ControlCruce
{
    return ControlCruce::factory()->create(['cruce_id' => $cruce->id, 'user_id' => $cruce->user_id, 'responsable_user_id' => User::factory()->create()->id, 'vigente_hasta' => $vence, 'es_actual' => true]);
}

test('las dos pantallas piden roles.gestionar', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('usuarios.ver'));
    test()->get(ListarConflictos::getUrl())->assertForbidden();
    test()->get(ListarIncompatibilidades::getUrl())->assertForbidden();

    entrarAConflictos();
    test()->get(ListarConflictos::getUrl())->assertOk();
    test()->get(ListarIncompatibilidades::getUrl())->assertOk();
});

test('cada conflicto dice su estado, y Pendiente coincide con lo que cuenta la alerta', function () {
    entrarAConflictos();
    $pendiente = conflictoAbierto(parDeConflicto());
    $controlado = conflictoAbierto(parDeConflicto('parties.editar', 'parties.desactivar'));
    controlDelConflicto($controlado, '2026-12-31');
    $vencido = conflictoAbierto(parDeConflicto('catalogos.ver', 'catalogos.editar'));
    controlDelConflicto($vencido, '2026-03-09');
    $cerrado = conflictoAbierto(parDeConflicto('organigrama.ver', 'organigrama.gestionar'), atributos: ['desde' => '2026-03-01', 'hasta' => '2026-03-09']);

    $programado = parDeConflicto('alertas.configurar', 'salud.ver');
    $persona = User::factory()->create();
    $rol = Role::findOrCreate('rol futuro')->syncPermissions(['alertas.configurar', 'salud.ver']);
    PermisoPorAmbito::factory()->create(['user_id' => $persona->id, 'rol_id' => $rol->id, 'ambito_type' => 'unidad_organizativa', 'ambito_id' => UnidadOrganizativa::factory()->create()->id, 'desde' => '2026-04-01']);
    $futuro = CruceFunciones::factory()->create(['user_id' => $persona->id, 'incompatibilidad_id' => $programado->id, 'desde' => Calendario::hoy()]);

    $pagina = Livewire::test(ListarConflictos::class)->instance();

    expect($pagina->estado($pendiente))->toBe('Pendiente')
        ->and($pagina->estado($controlado))->toBe('Controlado')
        ->and($pagina->estado($vencido))->toBe('Pendiente')
        ->and($pagina->estado($cerrado))->toBe('Cerrado')
        ->and($pagina->estado($futuro))->toBe('Sin efecto hoy');
});

test('abre con los abiertos, y cada pestaña filtra lo suyo', function () {
    entrarAConflictos();
    $pendiente = conflictoAbierto(parDeConflicto());
    $controlado = conflictoAbierto(parDeConflicto('parties.editar', 'parties.desactivar'));
    controlDelConflicto($controlado);
    $cerrado = conflictoAbierto(parDeConflicto('catalogos.ver', 'catalogos.editar'), atributos: ['desde' => '2026-03-01', 'hasta' => '2026-03-09']);

    Livewire::test(ListarConflictos::class)
        ->assertCanSeeTableRecords([$pendiente, $controlado])
        ->assertCanNotSeeTableRecords([$cerrado])
        ->assertSee('Crear fichas × Borrar fichas')
        ->set('activeTab', 'pendientes')
        ->assertCanSeeTableRecords([$pendiente])
        ->assertCanNotSeeTableRecords([$controlado])
        ->set('activeTab', 'controlados')
        ->assertCanSeeTableRecords([$controlado])
        ->assertCanNotSeeTableRecords([$pendiente])
        ->set('activeTab', 'cerrados')
        ->assertCanSeeTableRecords([$cerrado])
        ->assertCanNotSeeTableRecords([$pendiente]);
});

test('registra el control de un conflicto pendiente', function () {
    entrarAConflictos();
    $cruce = conflictoAbierto(parDeConflicto());
    $responsable = User::factory()->create();

    $pantalla = Livewire::test(ListarConflictos::class)
        ->assertSeeInOrder(['Pendientes', '1', 'Controlados', '0'])
        ->callAction(TestAction::make('nucleoSeguridadRegistrarControlCruce')->table($cruce), [
            'control' => 'Doble revisión mensual.',
            'responsable_user_id' => $responsable->id,
            'control_vigente_hasta' => '2026-12-31',
        ])
        ->assertHasNoActionErrors()
        // En la misma respuesta, no en el siguiente pedido.
        ->assertSeeInOrder(['Pendientes', '0', 'Controlados', '1']);

    expect($pantalla->instance()->estado($cruce->fresh()))->toBe('Controlado')
        ->and($cruce->fresh()->controlActual->control)->toBe('Doble revisión mensual.')
        ->and($cruce->fresh()->controlActual->vigente_hasta->toDateString())->toBe('2026-12-31');
});

test('renovar abre con el control actual y deja el anterior en la historia', function () {
    entrarAConflictos();
    $cruce = conflictoAbierto(parDeConflicto());
    $anterior = controlDelConflicto($cruce, '2026-03-20');

    Livewire::test(ListarConflictos::class)
        ->mountAction(TestAction::make('nucleoSeguridadRegistrarControlCruce')->table($cruce))
        ->assertSchemaStateSet(['control' => $anterior->control, 'responsable_user_id' => $anterior->responsable_user_id], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($anterior->fresh()->es_actual)->toBeFalse()
        ->and($cruce->controles()->count())->toBe(2);
});

test('renovar no propone a un responsable que ya no está activo', function () {
    entrarAConflictos();
    $cruce = conflictoAbierto(parDeConflicto());
    $anterior = controlDelConflicto($cruce);
    User::query()->whereKey($anterior->responsable_user_id)->update(['es_activo' => false]);

    Livewire::test(ListarConflictos::class)
        ->mountAction(TestAction::make('nucleoSeguridadRegistrarControlCruce')->table($cruce))
        ->assertSchemaStateSet(['control' => $anterior->control, 'responsable_user_id' => null], 'mountedActionSchema0');
});

test('no ofrece registrar el control de un conflicto propio ni de uno cerrado', function () {
    $actor = entrarAConflictos();
    $propio = conflictoAbierto(parDeConflicto(), $actor);
    $cerrado = conflictoAbierto(parDeConflicto('parties.editar', 'parties.desactivar'), atributos: ['desde' => '2026-03-01', 'hasta' => '2026-03-09']);

    Livewire::test(ListarConflictos::class)
        ->assertActionHidden(TestAction::make('nucleoSeguridadRegistrarControlCruce')->table($propio))
        ->set('activeTab', 'cerrados')
        ->assertActionHidden(TestAction::make('nucleoSeguridadRegistrarControlCruce')->table($cerrado));
});

test('agregar un par deja pendientes a quienes ya lo juntan, sin bloquearlos', function () {
    entrarAConflictos();
    $persona = User::factory()->create()->givePermissionTo(['parties.crear', 'parties.borrar']);
    $uno = Permission::findByName('parties.crear')->id;
    $otro = Permission::findByName('parties.borrar')->id;

    Livewire::test(ListarIncompatibilidades::class)
        ->callAction('nucleoSeguridadRegistrarIncompatibilidad', ['permiso_uno_id' => $uno, 'permiso_otro_id' => $otro, 'motivo' => 'Quien crea no borra.'])
        ->assertHasNoActionErrors()
        ->assertSee('Crear fichas');

    expect(IncompatibilidadPermisos::query()->sole()->motivo)->toBe('Quien crea no borra.')
        ->and(CruceFunciones::query()->where('user_id', $persona->id)->whereNull('hasta')->count())->toBe(1);
});

test('desactivar usa la versión leída al abrir y cierra sus conflictos', function () {
    entrarAConflictos();
    $par = parDeConflicto();
    $cruce = conflictoAbierto($par);

    Livewire::test(ListarIncompatibilidades::class)
        ->callAction(TestAction::make('nucleoSeguridadDesactivarIncompatibilidad')->table($par))
        ->assertHasNoActionErrors();

    expect($par->fresh()->es_activa)->toBeFalse()
        ->and($cruce->fresh()->hasta)->not->toBeNull();
});

test('las cantidades de las pestañas cambian en la misma respuesta al desactivar', function () {
    entrarAConflictos();
    $par = parDeConflicto();

    Livewire::test(ListarIncompatibilidades::class)
        ->assertSeeInOrder(['Activos', '1', 'Desactivados', '0'])
        ->callAction(TestAction::make('nucleoSeguridadDesactivarIncompatibilidad')->table($par))
        ->assertSeeInOrder(['Activos', '0', 'Desactivados', '1']);
});

test('no ofrece desactivar el par de un conflicto propio', function () {
    $actor = entrarAConflictos();
    $propio = parDeConflicto();
    conflictoAbierto($propio, $actor);
    $ajeno = parDeConflicto('parties.editar', 'parties.desactivar');
    conflictoAbierto($ajeno);

    Livewire::test(ListarIncompatibilidades::class)
        ->assertActionHidden(TestAction::make('nucleoSeguridadDesactivarIncompatibilidad')->table($propio))
        ->assertActionVisible(TestAction::make('nucleoSeguridadDesactivarIncompatibilidad')->table($ajeno));
});

test('reactivar registra el mismo par, y cada fila ofrece solo lo que le toca', function () {
    entrarAConflictos();
    $activo = parDeConflicto();
    $apagado = parDeConflicto('parties.editar', 'parties.desactivar', ['es_activa' => false]);

    Livewire::test(ListarIncompatibilidades::class)
        ->assertCanSeeTableRecords([$activo])
        ->assertCanNotSeeTableRecords([$apagado])
        ->assertActionHidden(TestAction::make('reactivar')->table($activo))
        ->set('activeTab', 'desactivados')
        ->assertActionHidden(TestAction::make('nucleoSeguridadDesactivarIncompatibilidad')->table($apagado))
        ->callAction(TestAction::make('reactivar')->table($apagado), ['motivo' => 'Vuelve a revisarse.'])
        ->assertHasNoActionErrors();

    expect($apagado->fresh()->es_activa)->toBeTrue()
        ->and($apagado->fresh()->motivo)->toBe('Vuelve a revisarse.');
});
