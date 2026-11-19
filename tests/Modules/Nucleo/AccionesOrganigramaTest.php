<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\OrganigramaRechazado;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Las acciones del organigrama, sin pantalla (plan 1.4, paso 3). Las reglas se prueban en OrganigramaServiceTest;
 * aquí, el registro, el permiso, la validación y que cada acción llegue a su servicio.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** @param array<string, mixed> $datos */
function accionOrganigrama(string $operacion, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.organigrama.{$operacion}", $datos, $usuario);
}

function gestorOrganigrama(): User
{
    return User::factory()->create()->givePermissionTo('organigrama.gestionar');
}

test('el núcleo registra las nueve acciones del organigrama y crea su permiso', function () {
    $registro = app(RegistroAcciones::class);
    $operaciones = ['crear-unidad', 'editar-unidad', 'desactivar-unidad', 'activar-unidad', 'designar-jefatura', 'crear-puesto', 'editar-puesto', 'desactivar-puesto', 'activar-puesto'];

    foreach ($operaciones as $operacion) {
        expect($registro->obtener("nucleo.organigrama.{$operacion}")->permiso())->toBe('organigrama.gestionar');
    }

    expect(Permission::query()->where('name', 'organigrama.gestionar')->exists())->toBeTrue();
});

test('arma una unidad con su puesto de jefatura y su ocupante, todo por acciones', function () {
    $gestor = gestorOrganigrama();
    $gerencia = accionOrganigrama('crear-unidad', ['codigo' => 'GG', 'nombre' => 'Gerencia General', 'tipo' => 'gerencia'], $gestor);
    $logistica = accionOrganigrama('crear-unidad', ['codigo' => 'LOG', 'nombre' => 'Logística', 'tipo' => 'jefatura', 'superior_id' => $gerencia, 'orden' => 2], $gestor);
    $jefe = accionOrganigrama('crear-puesto', ['unidad_id' => $logistica, 'nombre' => 'Jefe de logística'], $gestor);
    accionOrganigrama('designar-jefatura', ['unidad_id' => $logistica, 'puesto_id' => $jefe, 'version' => 1], $gestor);
    accionOrganigrama('editar-puesto', ['puesto_id' => $jefe, 'nombre' => 'Jefe de Logística', 'version' => 1], $gestor);

    $unidad = UnidadOrganizativa::query()->findOrFail($logistica);

    expect($unidad->tipo)->toBe(TipoUnidadOrganizativa::Jefatura)
        ->and($unidad->parent_id)->toBe($gerencia)
        ->and($unidad->orden)->toBe(2)
        ->and($unidad->jefe_puesto_id)->toBe($jefe)
        ->and($unidad->created_by)->toBe($gestor->id)
        ->and(Puesto::query()->findOrFail($jefe)->nombre)->toBe('Jefe de Logística');

    accionOrganigrama('designar-jefatura', ['unidad_id' => $logistica, 'puesto_id' => null, 'version' => 2], $gestor);
    accionOrganigrama('editar-unidad', ['unidad_id' => $logistica, 'codigo' => 'LOG', 'nombre' => 'Logística y almacén', 'tipo' => 'jefatura', 'superior_id' => null, 'orden' => 0, 'version' => 3], $gestor);
    accionOrganigrama('desactivar-puesto', ['puesto_id' => $jefe, 'version' => 2], $gestor);
    accionOrganigrama('activar-puesto', ['puesto_id' => $jefe, 'version' => 3], $gestor);

    expect($unidad->refresh()->jefe_puesto_id)->toBeNull()
        ->and($unidad->parent_id)->toBeNull()
        ->and($unidad->nombre)->toBe('Logística y almacén')
        ->and(Puesto::query()->findOrFail($jefe)->es_activo)->toBeTrue();

    accionOrganigrama('desactivar-puesto', ['puesto_id' => $jefe, 'version' => 4], $gestor);
    accionOrganigrama('desactivar-unidad', ['unidad_id' => $logistica, 'version' => 4], $gestor);
    accionOrganigrama('activar-unidad', ['unidad_id' => $logistica, 'version' => 5], $gestor);

    expect($unidad->refresh()->es_activa)->toBeTrue();
});

test('sin el permiso se rechaza y no crea nada', function () {
    expect(fn () => accionOrganigrama('crear-unidad', ['codigo' => 'GG', 'nombre' => 'Gerencia', 'tipo' => 'gerencia'], User::factory()->create()))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Crear unidad».')
        ->and(UnidadOrganizativa::query()->exists())->toBeFalse();
});

test('valida los campos antes de llegar al servicio', function () {
    expect(fn () => accionOrganigrama('crear-unidad', ['codigo' => 'GG', 'nombre' => 'Gerencia', 'tipo' => 'division'], gestorOrganigrama()))
        ->toThrow(ValidationException::class)
        ->and(fn () => accionOrganigrama('crear-puesto', ['unidad_id' => 999999, 'nombre' => 'Asistente'], gestorOrganigrama()))
        ->toThrow(ValidationException::class);
});

test('las reglas del servicio llegan como error de negocio', function () {
    $gestor = gestorOrganigrama();
    $a = accionOrganigrama('crear-unidad', ['codigo' => 'A', 'nombre' => 'A', 'tipo' => 'area'], $gestor);
    $b = accionOrganigrama('crear-unidad', ['codigo' => 'B', 'nombre' => 'B', 'tipo' => 'area', 'superior_id' => $a], $gestor);

    expect(fn () => accionOrganigrama('editar-unidad', ['unidad_id' => $a, 'codigo' => 'A', 'nombre' => 'A', 'tipo' => 'area', 'superior_id' => $b, 'orden' => 0, 'version' => 1], $gestor))
        ->toThrow(OrganigramaRechazado::class, '«A» no puede depender de «B»');
});

test('editar una unidad sin decir de quién depende ni su orden falla: no la mueve a la raíz', function () {
    $gestor = gestorOrganigrama();
    $a = accionOrganigrama('crear-unidad', ['codigo' => 'A', 'nombre' => 'A', 'tipo' => 'area'], $gestor);
    $b = accionOrganigrama('crear-unidad', ['codigo' => 'B', 'nombre' => 'B', 'tipo' => 'area', 'superior_id' => $a, 'orden' => 3], $gestor);

    expect(fn () => accionOrganigrama('editar-unidad', ['unidad_id' => $b, 'codigo' => 'B', 'nombre' => 'B2', 'tipo' => 'area', 'version' => 1], $gestor))
        ->toThrow(ValidationException::class)
        ->and(fn () => accionOrganigrama('designar-jefatura', ['unidad_id' => $b, 'version' => 1], $gestor))
        ->toThrow(ValidationException::class)
        ->and(UnidadOrganizativa::query()->findOrFail($b)->parent_id)->toBe($a);
});
