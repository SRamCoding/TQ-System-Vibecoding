<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Services\NavegacionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4f, D2 a D4: qué módulos ve cada persona en el inicio y en la barra, y cómo se guardan sus preferencias.
 * Cajas de prueba: «ventas» y «compras» piden permiso; «agenda» la ve todo el que entra.
 */

beforeEach(function () {
    Permission::findOrCreate('ventas.ver', 'web');
    Permission::findOrCreate('compras.ver', 'web');

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: 'ventas.ver', area: AreaModulo::Crm));
    $registro->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: 'compras.ver', area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp));
});

/**
 * @param  list<CajaModulo>  $cajas
 * @return list<string>
 */
function clavesDeNavegacion(array $cajas): array
{
    return array_values(array_map(fn (CajaModulo $caja): string => $caja->clave, $cajas));
}

test('una cuenta nueva empieza con la barra apagada y sin ocultos', function () {
    $usuario = User::factory()->create()->refresh();

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse()
        ->and(DB::table('preferencias_usuario')->where('user_id', $usuario->id)->count())->toBe(0)
        ->and(app(NavegacionService::class)->ocultos($usuario))->toBe([]);
});

test('permitidos son las cajas cuyo permiso tiene, en el orden del registro', function () {
    $usuario = User::factory()->create()->givePermissionTo('compras.ver');

    expect(clavesDeNavegacion(app(NavegacionService::class)->permitidos($usuario)))->toBe(['compras', 'agenda']);
});

test('la barra muestra los permitidos menos los ocultos', function () {
    $usuario = User::factory()->create()->givePermissionTo(['ventas.ver', 'compras.ver']);
    $navegacion = app(NavegacionService::class);

    $navegacion->ocultar($usuario, ['compras']);

    expect(clavesDeNavegacion($navegacion->deLaBarra($usuario)))->toBe(['ventas', 'agenda'])
        ->and(clavesDeNavegacion($navegacion->permitidos($usuario)))->toBe(['ventas', 'compras', 'agenda'])
        ->and($navegacion->ocultos($usuario))->toBe(['compras']);
});

test('ocultar nunca da acceso: sin permiso, el módulo no aparece aunque no esté oculto', function () {
    $usuario = User::factory()->create();

    expect(clavesDeNavegacion(app(NavegacionService::class)->deLaBarra($usuario)))->toBe(['agenda']);
});

test('guardar reemplaza toda la lista', function () {
    $usuario = User::factory()->create()->givePermissionTo(['ventas.ver', 'compras.ver']);
    $navegacion = app(NavegacionService::class);

    $navegacion->ocultar($usuario, ['ventas', 'compras']);
    $navegacion->ocultar($usuario, ['agenda']);

    expect(ModuloOculto::query()->where('user_id', $usuario->id)->pluck('clave_modulo')->all())->toBe(['agenda']);

    $navegacion->ocultar($usuario, []);

    expect(ModuloOculto::query()->where('user_id', $usuario->id)->count())->toBe(0);
});

test('rechaza ocultar un módulo que la persona no puede abrir, sin decir cuál', function () {
    $usuario = User::factory()->create();

    expect(fn () => app(NavegacionService::class)->ocultar($usuario, ['ventas']))
        ->toThrow(PreferenciaRechazada::class, 'Uno de los módulos elegidos no está disponible para ti.');

    expect(fn () => app(NavegacionService::class)->ocultar($usuario, ['no-existe']))
        ->toThrow(PreferenciaRechazada::class, 'Uno de los módulos elegidos no está disponible para ti.')
        ->and(ModuloOculto::query()->count())->toBe(0);
});

test('rechaza dejar la barra sin módulos', function () {
    $usuario = User::factory()->create()->givePermissionTo('ventas.ver');
    $navegacion = app(NavegacionService::class);
    $permitidos = clavesDeNavegacion($navegacion->permitidos($usuario));

    expect(fn () => $navegacion->ocultar($usuario, $permitidos))
        ->toThrow(PreferenciaRechazada::class, 'Deja al menos un módulo en la barra.')
        ->and(ModuloOculto::query()->count())->toBe(0);
});

test('un oculto de un módulo cuyo permiso se perdió no cuenta, y desaparece al guardar', function () {
    $usuario = User::factory()->create()->givePermissionTo(['ventas.ver', 'compras.ver']);
    $navegacion = app(NavegacionService::class);
    $navegacion->ocultar($usuario, ['ventas']);

    $usuario->revokePermissionTo('ventas.ver');
    $usuario->refresh();

    expect($navegacion->ocultos($usuario))->toBe([]);

    $navegacion->ocultar($usuario, ['compras']);
    $usuario->givePermissionTo('ventas.ver');
    $usuario->refresh();

    // Con el permiso de vuelta, el módulo vuelve a la barra.
    expect(clavesDeNavegacion($navegacion->deLaBarra($usuario)))->toBe(['ventas', 'agenda']);
});

test('una clave guardada de una caja que ya no existe se ignora', function () {
    $usuario = User::factory()->create();
    ModuloOculto::query()->create(['user_id' => $usuario->id, 'clave_modulo' => 'retirado']);

    expect(app(NavegacionService::class)->ocultos($usuario))->toBe([])
        ->and(clavesDeNavegacion(app(NavegacionService::class)->deLaBarra($usuario)))->toBe(['agenda']);
});

test('prende y apaga la barra', function () {
    $usuario = User::factory()->create();
    $navegacion = app(NavegacionService::class);

    $navegacion->mostrarBarra($usuario, true);
    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue();

    $navegacion->mostrarBarra($usuario, false);
    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse();
});

test('el interruptor y los ocultos son de cada persona: lo de otra queda intacto', function () {
    $una = User::factory()->create()->givePermissionTo(['ventas.ver', 'compras.ver']);
    $otra = User::factory()->create()->givePermissionTo(['ventas.ver', 'compras.ver']);
    $navegacion = app(NavegacionService::class);
    $navegacion->ocultar($otra, ['agenda']);

    $navegacion->mostrarBarra($una, true);
    $navegacion->ocultar($una, ['ventas']);
    $navegacion->ocultar($una, ['compras']);

    expect(app(NavegacionService::class)->barraVisible($otra))->toBeFalse()
        ->and($navegacion->ocultos($otra))->toBe(['agenda'])
        ->and($navegacion->ocultos($una))->toBe(['compras']);
});

test('la base no admite el mismo módulo oculto dos veces para la misma persona', function () {
    $usuario = User::factory()->create();
    ModuloOculto::query()->create(['user_id' => $usuario->id, 'clave_modulo' => 'agenda']);

    expect(fn () => ModuloOculto::query()->create(['user_id' => $usuario->id, 'clave_modulo' => 'agenda']))
        ->toThrow(QueryException::class);
});

test('la base no admite una clave con otro formato', function (string $clave) {
    $usuario = User::factory()->create();

    expect(fn () => ModuloOculto::query()->create(['user_id' => $usuario->id, 'clave_modulo' => $clave]))
        ->toThrow(QueryException::class);
})->with(['Mayusculas', '9cifra', 'con espacio', '']);

test('una caja con una clave de más de 40 caracteres no se registra', function () {
    expect(fn () => new CajaModulo(str_repeat('a', 41), 'Larga', '/larga', permiso: null, area: AreaModulo::Erp))
        ->toThrow(InvalidArgumentException::class);
});
