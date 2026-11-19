<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\ElegirModulos;
use App\Modules\Nucleo\Acciones\MostrarBarraModulos;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\PreferenciaUsuario;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\NavegacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Plan 1.4f, D4: las dos acciones de la barra de módulos, sin interfaz. Sin permiso (preferencias propias) y sin
 * versión.
 */

beforeEach(function () {
    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('notas', 'Notas', '/notas', permiso: null, area: AreaModulo::Erp));
});

function mostrarBarraModulos(User $usuario, mixed $visible): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.mostrar-barra-modulos', ['visible' => $visible], $usuario);
}

/**
 * @param  array<string, mixed>  $datos
 */
function elegirModulos(User $usuario, array $datos): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.elegir-modulos', $datos, $usuario);
}

test('el núcleo registra las dos', function () {
    expect(app(RegistroAcciones::class)->todas())
        ->toHaveKey('nucleo.mostrar-barra-modulos', MostrarBarraModulos::class)
        ->toHaveKey('nucleo.elegir-modulos', ElegirModulos::class);
});

test('prende la barra a nombre de la persona y sin bitácora', function () {
    $usuario = User::factory()->create();
    $cambiosAntes = DB::table('auditoria_cambios')->count();

    mostrarBarraModulos($usuario, true);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue()
        ->and(PreferenciaUsuario::query()->where('user_id', $usuario->id)->where('clave', 'navegacion.barra_modulos')->value('created_by'))->toBe($usuario->id)
        ->and(DB::table('auditoria_cambios')->count())->toBe($cambiosAntes);
});

test('la última escritura gana, sin versión', function () {
    $usuario = User::factory()->create();

    mostrarBarraModulos($usuario, true);
    mostrarBarraModulos($usuario, false);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse();
});

test('guarda los ocultos a nombre de la persona', function () {
    $usuario = User::factory()->create();

    elegirModulos($usuario, ['ocultos' => ['notas']]);

    $oculto = ModuloOculto::query()->where('user_id', $usuario->id)->sole();

    expect($oculto->clave_modulo)->toBe('notas')
        ->and($oculto->created_by)->toBe($usuario->id);
});

test('una lista vacía muestra todos; omitirla se rechaza', function () {
    $usuario = User::factory()->create();
    elegirModulos($usuario, ['ocultos' => ['notas']]);

    elegirModulos($usuario, ['ocultos' => []]);
    expect(ModuloOculto::query()->where('user_id', $usuario->id)->count())->toBe(0);

    expect(fn () => elegirModulos($usuario, []))->toThrow(ValidationException::class);
});

test('rechaza claves repetidas o demasiado largas', function (array $ocultos) {
    expect(fn () => elegirModulos(User::factory()->create(), ['ocultos' => $ocultos]))->toThrow(ValidationException::class);
})->with([
    'repetidas' => [['notas', 'notas']],
    'larga' => [[str_repeat('a', 41)]],
]);

test('rechaza dejar la barra vacía y un módulo ajeno, sin guardar nada', function () {
    $usuario = User::factory()->create();

    expect(fn () => elegirModulos($usuario, ['ocultos' => ['agenda', 'notas']]))->toThrow(PreferenciaRechazada::class)
        ->and(fn () => elegirModulos($usuario, ['ocultos' => ['ventas']]))->toThrow(PreferenciaRechazada::class)
        ->and(ModuloOculto::query()->count())->toBe(0);
});

test('rechaza al usuario de sistema y a uno inactivo', function (string $clave, array $datos) {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(fn () => app(EjecutorAcciones::class)->ejecutar($clave, $datos, $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar($clave, $datos, User::factory()->inactivo()->create()))->toThrow(AccionRechazada::class);
})->with([
    'mostrar' => ['nucleo.mostrar-barra-modulos', ['visible' => true]],
    'elegir' => ['nucleo.elegir-modulos', ['ocultos' => []]],
]);
