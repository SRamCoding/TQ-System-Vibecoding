<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\SeguridadSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;

/*
 * El usuario de desarrollo sale del .env (DEV_USER_*, vía config/nucleo.php) y solo existe en local. Datos
 * inventados: «Ana Pérez Soto» no es nadie.
 */

function sembrarComo(string $ambiente): void
{
    app()->instance('env', $ambiente);
    app(DatabaseSeeder::class)->setContainer(app())->__invoke();
}

test('en local crea el usuario con el nombre y el correo configurados, y su persona con DNI inventado', function () {
    config(['nucleo.usuario_desarrollo.nombres' => 'Ana', 'nucleo.usuario_desarrollo.apellidos' => 'Pérez Soto', 'nucleo.usuario_desarrollo.email' => 'Ana@Techquk.test']);

    sembrarComo('local');

    $usuario = User::query()->where('email', 'ana@techquk.test')->firstOrFail();

    expect($usuario->name)->toBe('Pérez Soto Ana')
        ->and($usuario->party?->numero_documento)->toBe('00000000')
        ->and($usuario->party?->nombres)->toBe('Ana')
        ->and($usuario->persona_numero)->toBe('00000000')
        ->and($usuario->hasPermissionTo('parties.ver'))->toBeTrue()
        // Por roles, nunca permisos directos (plan 1.4, D7 y D8).
        ->and($usuario->getDirectPermissions())->toHaveCount(0)
        ->and($usuario->getRoleNames()->sort()->values()->all())->toBe(['administrador', 'desarrollo']);
});

test('sin configuración usa dev@techquk.test', function () {
    sembrarComo('local');

    expect(User::query()->where('email', 'dev@techquk.test')->exists())->toBeTrue();
});

test('volver a sembrar no duplica, aunque haya cambiado el correo', function () {
    sembrarComo('local');
    config(['nucleo.usuario_desarrollo.email' => 'otro@techquk.test']);
    sembrarComo('local');

    // El usuario de desarrollo y el gerente general de desarrollo (plan 1.10, D2).
    expect(User::query()->where('es_sistema', false)->count())->toBe(2)
        ->and(Party::query()->where('numero_documento', '00000000')->count())->toBe(1)
        ->and(Party::query()->where('numero_documento', '00000001')->count())->toBe(1);
});

test('en local crea un gerente general inventado, que no es el usuario de desarrollo; si ya hay uno activo, no otro', function () {
    sembrarComo('local');

    $gerentes = User::role(SeguridadService::ROL_GERENCIA_GENERAL)->get();

    expect($gerentes)->toHaveCount(1)
        ->and($gerentes->sole()->email)->toBe('gerencia.dev@techquk.test')
        ->and($gerentes->sole()->hasRole(SeguridadService::ROL_ADMINISTRADOR))->toBeFalse();

    $gerentes->sole()->forceFill(['email' => 'cambiado@techquk.test'])->save();
    sembrarComo('local');

    expect(User::role(SeguridadService::ROL_GERENCIA_GENERAL)->count())->toBe(1);
});

test('en producción no crea el usuario de desarrollo ni el rol desarrollo, pero sí el administrador con sus permisos fijos', function () {
    sembrarComo('production');

    expect(User::query()->where('es_sistema', false)->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'desarrollo')->exists())->toBeFalse()
        ->and(Role::findByName('administrador')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(SeguridadSeeder::PERMISOS_ADMINISTRADOR)->sort()->values()->all());
});

test('volver a sembrar devuelve al administrador sus permisos fijos', function () {
    sembrarComo('production');
    Role::findByName('administrador')->givePermissionTo('parties.ver');

    sembrarComo('production');

    expect(Role::findByName('administrador')->hasPermissionTo('parties.ver'))->toBeFalse();
});
