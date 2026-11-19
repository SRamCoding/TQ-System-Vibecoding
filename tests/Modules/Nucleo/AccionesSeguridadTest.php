<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\CruceSinControl;
use App\Modules\Nucleo\Excepciones\OrganigramaRechazado;
use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Las acciones de seguridad, sin pantalla (plan 1.4, paso 4; condición de la 1.4c: la grilla no agrega lógica). Las
 * reglas se prueban en SeguridadServiceTest; aquí, el registro, los permisos, la validación y que cada acción llegue
 * a su servicio.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-03-10 10:00', 'America/Lima'));
});

/** @param array<string, mixed> $datos */
function accionSeguridad(string $operacion, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.seguridad.{$operacion}", $datos, $usuario);
}

function adminDeAcciones(): User
{
    $rol = Role::findOrCreate('gestor-acciones');
    $rol->syncPermissions(['usuarios.gestionar', 'roles.gestionar']);

    return User::factory()->create()->assignRole($rol);
}

test('el núcleo registra las catorce acciones de seguridad con su permiso', function () {
    $permisos = [
        'usuarios.gestionar' => ['registrar-usuario', 'cambiar-puesto-usuario', 'desactivar-usuario', 'activar-usuario', 'asignar-rol', 'quitar-rol', 'asignar-rol-en-ambito', 'quitar-rol-en-ambito'],
        'roles.gestionar' => ['crear-rol', 'renombrar-rol', 'cambiar-permisos-de-rol', 'registrar-incompatibilidad', 'desactivar-incompatibilidad', 'registrar-control-cruce'],
    ];
    $registro = app(RegistroAcciones::class);

    foreach ($permisos as $permiso => $operaciones) {
        foreach ($operaciones as $operacion) {
            expect($registro->obtener("nucleo.seguridad.{$operacion}")->permiso())->toBe($permiso);
        }

        expect(Permission::query()->where('name', $permiso)->exists())->toBeTrue();
    }
});

test('de punta a punta por acciones: rol, par incompatible, asignación con su control', function () {
    $admin = adminDeAcciones();
    $gerente = User::factory()->create();
    $persona = User::factory()->create();
    $registrar = Permission::findOrCreate('pagos.registrar');
    $aprobar = Permission::findOrCreate('pagos.aprobar');

    $rol = accionSeguridad('crear-rol', ['nombre' => 'Tesorería'], $admin);
    accionSeguridad('cambiar-permisos-de-rol', ['rol_id' => $rol, 'permisos' => [$registrar->id, $aprobar->id]], $admin);
    $par = accionSeguridad('registrar-incompatibilidad', ['permiso_uno_id' => $aprobar->id, 'permiso_otro_id' => $registrar->id, 'motivo' => 'Quien registra no aprueba.'], $admin);

    expect(fn () => accionSeguridad('asignar-rol', ['usuario_id' => $persona->id, 'rol_id' => $rol], $admin))
        ->toThrow(CruceSinControl::class)
        ->and(fn () => accionSeguridad('asignar-rol', ['usuario_id' => $persona->id, 'rol_id' => $rol, 'control' => 'Revisión semanal.'], $admin))
        ->toThrow(SeguridadRechazada::class, 'lleva su descripción y su responsable');

    accionSeguridad('asignar-rol', [
        'usuario_id' => $persona->id, 'rol_id' => $rol,
        'control' => 'Gerencia revisa cada semana.', 'responsable_user_id' => $gerente->id, 'control_vigente_hasta' => '2026-12-31',
    ], $admin);

    $cruce = CruceFunciones::query()->sole();
    $asignacion = AuditoriaCambio::query()->where('auditable_type', 'user')->where('auditable_id', $persona->id)->where('evento', 'actualizado')->latest('id')->firstOrFail();

    expect($asignacion->user_id)->toBe($admin->id)
        ->and($asignacion->valores_nuevos)->toBe(['roles' => ['Tesorería']])
        ->and(IncompatibilidadPermisos::query()->findOrFail($par)->permiso_a_id)->toBe(min($registrar->id, $aprobar->id))
        ->and($cruce->user_id)->toBe($persona->id)
        ->and($cruce->controlActual?->vigente_hasta?->toDateString())->toBe('2026-12-31');

    accionSeguridad('registrar-control-cruce', ['cruce_id' => $cruce->id, 'control' => 'Ahora cada día.', 'responsable_user_id' => $gerente->id], $admin);
    accionSeguridad('quitar-rol', ['usuario_id' => $persona->id, 'rol_id' => $rol], $admin);
    accionSeguridad('desactivar-incompatibilidad', ['incompatibilidad_id' => $par, 'version' => 1], $admin);
    accionSeguridad('renombrar-rol', ['rol_id' => $rol, 'nombre' => 'Tesorería y pagos'], $admin);

    expect($cruce->refresh()->hasta?->toDateString())->toBe('2026-03-10')
        ->and($cruce->controlActual?->control)->toBe('Ahora cada día.')
        ->and(Role::findById($rol)->name)->toBe('Tesorería y pagos');
});

test('registrar usuario, cambiarle el puesto, darle un rol en su unidad, desactivarlo y activarlo', function () {
    $admin = adminDeAcciones();
    $unidad = UnidadOrganizativa::factory()->create();
    $jefatura = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $jefatura->id]);
    $comun = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);
    User::factory()->create(['puesto_id' => $jefatura->id]);
    $persona = Party::factory()->create();

    $id = accionSeguridad('registrar-usuario', ['party_id' => $persona->id, 'email' => 'nuevo@techquk.test', 'puesto_id' => $comun->id], $admin);

    expect(fn () => accionSeguridad('cambiar-puesto-usuario', ['usuario_id' => $id, 'puesto_id' => $jefatura->id, 'version' => 1], $admin))
        ->toThrow(OrganigramaRechazado::class, 'ya tiene ocupante');

    accionSeguridad('cambiar-puesto-usuario', ['usuario_id' => $id, 'puesto_id' => null, 'version' => 1], $admin);
    $asignacion = accionSeguridad('asignar-rol-en-ambito', [
        'usuario_id' => $id, 'rol_id' => Role::findOrCreate('comprador')->id,
        'ambito_type' => 'unidad_organizativa', 'ambito_id' => $unidad->id, 'desde' => '2026-03-01', 'hasta' => '2026-12-31',
    ], $admin);
    accionSeguridad('quitar-rol-en-ambito', ['asignacion_id' => $asignacion, 'version' => 1], $admin);
    accionSeguridad('desactivar-usuario', ['usuario_id' => $id, 'version' => 2], $admin);
    accionSeguridad('activar-usuario', ['usuario_id' => $id, 'version' => 3], $admin);

    $usuario = User::query()->findOrFail($id);

    expect($usuario->puesto_id)->toBeNull()
        ->and($usuario->es_activo)->toBeTrue()
        ->and($usuario->version)->toBe(4)
        ->and(PermisoPorAmbito::query()->findOrFail($asignacion)->hasta?->toDateString())->toBe('2026-03-09');
});

test('sin el permiso se rechaza: cuentas y roles de personas piden usuarios.gestionar; los roles, roles.gestionar', function () {
    $soloRoles = User::factory()->create()->givePermissionTo('roles.gestionar');
    $soloUsuarios = User::factory()->create()->givePermissionTo('usuarios.gestionar');

    expect(fn () => accionSeguridad('registrar-usuario', ['party_id' => Party::factory()->create()->id, 'email' => 'x@techquk.test'], $soloRoles))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Registrar usuario».')
        ->and(fn () => accionSeguridad('crear-rol', ['nombre' => 'Nuevo'], $soloUsuarios))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Crear rol».')
        ->and(Role::query()->where('name', 'Nuevo')->exists())->toBeFalse();
});

test('valida los campos antes de llegar al servicio', function () {
    $admin = adminDeAcciones();

    expect(fn () => accionSeguridad('asignar-rol-en-ambito', ['usuario_id' => User::factory()->create()->id, 'rol_id' => Role::findOrCreate('comprador')->id, 'ambito_type' => 'party', 'ambito_id' => 1], $admin))
        ->toThrow(ValidationException::class)
        ->and(fn () => accionSeguridad('cambiar-permisos-de-rol', ['rol_id' => Role::findOrCreate('comprador')->id, 'permisos' => [999999]], $admin))
        ->toThrow(ValidationException::class)
        ->and(fn () => accionSeguridad('quitar-rol', ['usuario_id' => 999999, 'rol_id' => 1], $admin))
        ->toThrow(ValidationException::class);
});

test('un campo omitido falla en vez de borrar datos: los permisos de un rol y el puesto tienen que venir, aunque vacíos', function () {
    $admin = adminDeAcciones();
    $rol = Role::findOrCreate('comprador')->givePermissionTo(Permission::findOrCreate('ordenes.aprobar'));
    $ana = User::factory()->create(['puesto_id' => Puesto::factory()->create()->id]);

    expect(fn () => accionSeguridad('cambiar-permisos-de-rol', ['rol_id' => $rol->id], $admin))->toThrow(ValidationException::class)
        ->and(fn () => accionSeguridad('cambiar-puesto-usuario', ['usuario_id' => $ana->id, 'version' => 1], $admin))->toThrow(ValidationException::class)
        ->and($rol->fresh()?->hasPermissionTo('ordenes.aprobar'))->toBeTrue()
        ->and($ana->refresh()->puesto_id)->not->toBeNull();

    accionSeguridad('cambiar-permisos-de-rol', ['rol_id' => $rol->id, 'permisos' => []], $admin);

    expect($rol->fresh()?->permissions)->toHaveCount(0);
});
