<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Spatie\Permission\Models\Permission;

/*
 * Plan 2.20a, N5: «lo propio y lo de todos». Editar todas o solo las propias (donde soy el responsable), con la regla
 * en un solo lugar del núcleo. Se prueba con permisos de ejemplo y con `users.id` como columna de responsable.
 */

beforeEach(function () {
    Permission::findOrCreate('crm.prueba.editar', 'web');
    Permission::findOrCreate('crm.prueba.editar-propias', 'web');
});

/** @param  list<string>  $permisos */
function usuarioConPermisosDePrueba(array $permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

test('con el de todas puede sobre cualquiera; con el de propias, solo sobre lo suyo; sin ninguno, sobre nada', function (array $permisos, bool $propio, bool $ajeno) {
    $usuario = usuarioConPermisosDePrueba($permisos);
    $otro = User::factory()->create();
    $seguridad = app(SeguridadService::class);

    expect($seguridad->puedeSobrePropio($usuario, 'crm.prueba.editar', 'crm.prueba.editar-propias', $usuario->id))->toBe($propio)
        ->and($seguridad->puedeSobrePropio($usuario, 'crm.prueba.editar', 'crm.prueba.editar-propias', $otro->id))->toBe($ajeno);
})->with([
    'todas' => [['crm.prueba.editar'], true, true],
    'propias' => [['crm.prueba.editar-propias'], true, false],
    'las dos: es tener todas' => [['crm.prueba.editar', 'crm.prueba.editar-propias'], true, true],
    'ninguna' => [[], false, false],
]);

test('una cuenta desactivada no puede ni sobre lo suyo', function () {
    $usuario = User::factory()->inactivo()->create()->givePermissionTo('crm.prueba.editar-propias');

    expect(app(SeguridadService::class)->puedeSobrePropio($usuario, 'crm.prueba.editar', 'crm.prueba.editar-propias', $usuario->id))->toBeFalse();
});

test('filtrarPropios deja en la consulta lo mismo que dice puedeSobrePropio', function (array $permisos, string $quienes) {
    $usuario = usuarioConPermisosDePrueba($permisos);
    $otro = User::factory()->create();
    $ids = [$usuario->id, $otro->id];

    $visibles = app(SeguridadService::class)
        ->filtrarPropios(User::query()->whereIn('id', $ids), $usuario, 'crm.prueba.editar', 'crm.prueba.editar-propias', 'id')
        ->pluck('id')->sort()->values()->all();

    $esperados = match ($quienes) {
        'todos' => collect($ids)->sort()->values()->all(),
        'el suyo' => [$usuario->id],
        'ninguno' => [],
    };

    expect($visibles)->toBe($esperados);
})->with([
    'todas' => [['crm.prueba.editar'], 'todos'],
    'propias' => [['crm.prueba.editar-propias'], 'el suyo'],
    'las dos' => [['crm.prueba.editar', 'crm.prueba.editar-propias'], 'todos'],
    'ninguna' => [[], 'ninguno'],
]);
