<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\CambiarPreferencia;
use App\Modules\Nucleo\Acciones\RestablecerPreferencias;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\PreferenciaUsuario;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Preferencias\TipoPreferencia;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\FuenteListaDePrueba;

/*
 * Plan 1.4g, D2, D3 y D7: el registro de preferencias, su servicio y las dos acciones generales, sin interfaz. Las
 * preferencias de prueba se declaran aquí; las de los módulos, en sus providers.
 */

beforeEach(function () {
    Permission::findOrCreate('ventas.ver', 'web');
    FuenteListaDePrueba::$elegidos = [];

    app(RegistroPreferencias::class)->declarar(
        DefinicionPreferencia::siNo(GrupoPreferencia::Apariencia, 'prueba_si_no', 'Sí o no', 'Una de prueba.', false),
        DefinicionPreferencia::opcion(GrupoPreferencia::Apariencia, 'prueba_opcion', 'Opción', 'Una de prueba.', 'a', ['a' => 'A', 'b' => 'B']),
        DefinicionPreferencia::numero(GrupoPreferencia::Navegacion, 'prueba_numero', 'Número', 'Una de prueba.', 5, 1, 10),
        DefinicionPreferencia::opcion(GrupoPreferencia::Navegacion, 'prueba_permitida', 'Según permisos', 'Una de prueba.', 'inicio',
            fn (User $usuario): array => ['inicio' => 'Inicio', ...($usuario->can('ventas.ver') ? ['ventas' => 'Ventas'] : [])]),
        DefinicionPreferencia::lista(GrupoPreferencia::Navegacion, 'prueba_lista', 'Lista', 'Una de prueba.', FuenteListaDePrueba::class),
    );
});

function preferenciasDePrueba(): PreferenciasUsuarioService
{
    return app(PreferenciasUsuarioService::class);
}

function cambiarPreferenciaPorAccion(User $usuario, string $clave, string $valor): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-preferencia', ['clave' => $clave, 'valor' => $valor], $usuario);
}

test('cada preferencia registrada tiene un defecto válido', function () {
    $usuario = User::factory()->create();

    foreach (app(RegistroPreferencias::class)->todas() as $clave => $definicion) {
        if ($definicion->tipo === TipoPreferencia::Lista) {
            expect($definicion->fuente)->not->toBeNull("{$clave} es una lista sin fuente");

            continue;
        }

        expect($definicion->validar($definicion->defecto, $usuario))->toBe($definicion->defecto, "{$clave} no tiene un defecto válido");
    }
});

test('sin fila vale el defecto, en su tipo', function () {
    $usuario = User::factory()->create();

    expect(preferenciasDePrueba()->valor($usuario, 'apariencia.prueba_si_no'))->toBeFalse()
        ->and(preferenciasDePrueba()->valor($usuario, 'apariencia.prueba_opcion'))->toBe('a')
        ->and(preferenciasDePrueba()->valor($usuario, 'navegacion.prueba_numero'))->toBe(5);
});

test('se guarda como texto y se lee en su tipo; volver al defecto borra la fila', function () {
    $usuario = User::factory()->create();

    preferenciasDePrueba()->cambiar($usuario, 'apariencia.prueba_si_no', true);
    preferenciasDePrueba()->cambiar($usuario, 'navegacion.prueba_numero', '7');

    expect(PreferenciaUsuario::query()->where('user_id', $usuario->id)->orderBy('clave')->pluck('valor', 'clave')->all())
        ->toBe(['apariencia.prueba_si_no' => 'true', 'navegacion.prueba_numero' => '7'])
        ->and(preferenciasDePrueba()->valor($usuario, 'apariencia.prueba_si_no'))->toBeTrue()
        ->and(preferenciasDePrueba()->valor($usuario, 'navegacion.prueba_numero'))->toBe(7);

    preferenciasDePrueba()->cambiar($usuario, 'navegacion.prueba_numero', 5);

    expect(PreferenciaUsuario::query()->where('user_id', $usuario->id)->pluck('clave')->all())->toBe(['apariencia.prueba_si_no']);
});

test('una clave no registrada se rechaza', function () {
    expect(fn () => preferenciasDePrueba()->cambiar(User::factory()->create(), 'apariencia.no_existe', 'true'))
        ->toThrow(PreferenciaRechazada::class, 'Esa preferencia no existe.');
});

test('un valor que no cumple su tipo, sus límites o sus opciones se rechaza; nunca un decimal', function (string $clave, mixed $valor) {
    expect(fn () => preferenciasDePrueba()->cambiar(User::factory()->create(), $clave, $valor))->toThrow(PreferenciaRechazada::class)
        ->and(PreferenciaUsuario::query()->count())->toBe(0);
})->with([
    'sí o no con otra palabra' => ['apariencia.prueba_si_no', 'quizá'],
    'opción que no existe' => ['apariencia.prueba_opcion', 'c'],
    'número fuera de límites' => ['navegacion.prueba_numero', 11],
    'número con decimales' => ['navegacion.prueba_numero', '1.5'],
    'número float' => ['navegacion.prueba_numero', 1.5],
    'opción sin el permiso' => ['navegacion.prueba_permitida', 'ventas'],
]);

test('un valor guardado que ya no vale cuenta como el defecto, sin romper nada', function () {
    $usuario = User::factory()->create()->givePermissionTo('ventas.ver');
    preferenciasDePrueba()->cambiar($usuario, 'navegacion.prueba_permitida', 'ventas');
    PreferenciaUsuario::query()->create(['user_id' => $usuario->id, 'clave' => 'apariencia.prueba_opcion', 'valor' => 'retirada']);

    $usuario->revokePermissionTo('ventas.ver');
    $usuario->refresh();

    $nuevo = app(PreferenciasUsuarioService::class);

    expect($nuevo->valor($usuario, 'navegacion.prueba_permitida'))->toBe('inicio')
        ->and($nuevo->valor($usuario, 'apariencia.prueba_opcion'))->toBe('a')
        // La fila no se borra: si el permiso vuelve, la preferencia vuelve.
        ->and(PreferenciaUsuario::query()->where('clave', 'navegacion.prueba_permitida')->value('valor'))->toBe('ventas');
});

test('una lista la entrega su módulo y no se cambia con la acción general', function () {
    $usuario = User::factory()->create();
    FuenteListaDePrueba::$elegidos[$usuario->id] = ['dos'];

    expect(preferenciasDePrueba()->valor($usuario, 'navegacion.prueba_lista'))->toBe(['dos'])
        ->and(fn () => preferenciasDePrueba()->cambiar($usuario, 'navegacion.prueba_lista', 'uno'))
        ->toThrow(PreferenciaRechazada::class, '«Lista» se cambia desde su propia opción.');
});

test('restablecer un grupo vuelve sus preferencias y sus listas al defecto, y no toca otros grupos', function () {
    $usuario = User::factory()->create();
    preferenciasDePrueba()->cambiar($usuario, 'apariencia.prueba_si_no', true);
    preferenciasDePrueba()->cambiar($usuario, 'navegacion.prueba_numero', 8);
    FuenteListaDePrueba::$elegidos[$usuario->id] = ['uno'];

    app(EjecutorAcciones::class)->ejecutar('nucleo.restablecer-preferencias', ['grupo' => 'navegacion'], $usuario);

    expect(preferenciasDePrueba()->valor($usuario, 'navegacion.prueba_numero'))->toBe(5)
        ->and(preferenciasDePrueba()->valor($usuario, 'navegacion.prueba_lista'))->toBe([])
        ->and(preferenciasDePrueba()->valor($usuario, 'apariencia.prueba_si_no'))->toBeTrue();
});

test('el núcleo registra las dos acciones generales', function () {
    expect(app(RegistroAcciones::class)->todas())
        ->toHaveKey('nucleo.cambiar-preferencia', CambiarPreferencia::class)
        ->toHaveKey('nucleo.restablecer-preferencias', RestablecerPreferencias::class);
});

test('la acción general cambia solo las preferencias de quien la ejecuta, a su nombre y sin bitácora', function () {
    $una = User::factory()->create();
    $otra = User::factory()->create();
    preferenciasDePrueba()->cambiar($otra, 'apariencia.prueba_opcion', 'b');
    $cambiosAntes = DB::table('auditoria_cambios')->count();

    cambiarPreferenciaPorAccion($una, 'apariencia.prueba_opcion', 'b');
    cambiarPreferenciaPorAccion($una, 'apariencia.prueba_si_no', 'true');
    app(EjecutorAcciones::class)->ejecutar('nucleo.restablecer-preferencias', ['grupo' => 'apariencia'], $una);
    cambiarPreferenciaPorAccion($una, 'navegacion.prueba_numero', '9');

    expect(PreferenciaUsuario::query()->where('user_id', $una->id)->sole()->created_by)->toBe($una->id)
        ->and(app(PreferenciasUsuarioService::class)->valor($otra, 'apariencia.prueba_opcion'))->toBe('b')
        ->and(PreferenciaUsuario::query()->where('user_id', $otra->id)->count())->toBe(1)
        ->and(DB::table('auditoria_cambios')->count())->toBe($cambiosAntes);
});

test('la acción general acepta solo claves registradas y valida con el registro', function () {
    $usuario = User::factory()->create();

    expect(fn () => cambiarPreferenciaPorAccion($usuario, 'apariencia.no_existe', 'true'))->toThrow(PreferenciaRechazada::class)
        ->and(fn () => cambiarPreferenciaPorAccion($usuario, 'navegacion.prueba_numero', '99'))->toThrow(PreferenciaRechazada::class)
        ->and(PreferenciaUsuario::query()->count())->toBe(0);
});

test('rechaza al usuario de sistema y a uno inactivo', function (string $clave, array $datos) {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(fn () => app(EjecutorAcciones::class)->ejecutar($clave, $datos, $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar($clave, $datos, User::factory()->inactivo()->create()))->toThrow(AccionRechazada::class);
})->with([
    'cambiar' => ['nucleo.cambiar-preferencia', ['clave' => 'apariencia.prueba_si_no', 'valor' => 'true']],
    'restablecer' => ['nucleo.restablecer-preferencias', ['grupo' => 'apariencia']],
]);

test('la base no admite la misma preferencia dos veces para la misma persona', function () {
    $usuario = User::factory()->create();
    PreferenciaUsuario::query()->create(['user_id' => $usuario->id, 'clave' => 'apariencia.prueba_si_no', 'valor' => 'true']);

    expect(fn () => PreferenciaUsuario::query()->create(['user_id' => $usuario->id, 'clave' => 'apariencia.prueba_si_no', 'valor' => 'false']))
        ->toThrow(QueryException::class);
});

test('la base no admite una clave con otro formato', function (string $clave) {
    expect(fn () => PreferenciaUsuario::query()->create(['user_id' => User::factory()->create()->id, 'clave' => $clave, 'valor' => 'x']))
        ->toThrow(QueryException::class);
})->with(['sin_grupo', 'Apariencia.tema', 'apariencia.tema-oscuro', 'apariencia.', '.tema']);

test('la base no admite un valor vacío', function (string $valor) {
    expect(fn () => PreferenciaUsuario::query()->create(['user_id' => User::factory()->create()->id, 'clave' => 'apariencia.prueba_opcion', 'valor' => $valor]))
        ->toThrow(QueryException::class);
})->with(['vacío' => [''], 'solo espacios' => ['  ']]);

test('declarar dos veces la misma preferencia es un error', function () {
    expect(fn () => app(RegistroPreferencias::class)->declarar(
        DefinicionPreferencia::siNo(GrupoPreferencia::Apariencia, 'prueba_si_no', 'Otra', 'Repetida.', true),
    ))->toThrow(LogicException::class);
});
