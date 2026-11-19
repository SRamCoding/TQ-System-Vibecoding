<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\CambiarParametro;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // Sin ubigeos: el CSV completo no hace falta aquí.
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    // Los del núcleo son todos de dinero (plan 1.3b): la acción normal se prueba con uno que no lo es.
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'plazo', 'tipo' => TipoParametro::Entero, 'valor' => '30']);
});

function cambiarParametro(User $usuario, array $datos): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro', $datos, $usuario);
}

function editorDeParametros(): User
{
    return User::factory()->create()->givePermissionTo('parametros.editar');
}

test('el núcleo la registra y su permiso existe', function () {
    expect(app(RegistroAcciones::class)->todas())->toHaveKey('nucleo.cambiar-parametro', CambiarParametro::class)
        ->and(app(CambiarParametro::class)->permiso())->toBe('parametros.editar');
});

test('cambia el parámetro a nombre de la persona y deja su bitácora', function () {
    $ana = editorDeParametros();

    cambiarParametro($ana, ['parametro' => 'pruebas.plazo', 'valor' => '10', 'version' => 1]);

    $parametro = Parametro::query()->where('clave', 'plazo')->firstOrFail();
    $bitacora = AuditoriaCambio::query()->where('auditable_type', $parametro->getMorphClass())->where('auditable_id', $parametro->id)->latest('id')->firstOrFail();

    expect(app(ParametrosService::class)->entero('pruebas.plazo'))->toBe(10)
        ->and($parametro->updated_by)->toBe($ana->id)
        ->and($bitacora->user_id)->toBe($ana->id);
});

test('cambia la descripción junto con el valor', function () {
    cambiarParametro(editorDeParametros(), ['parametro' => 'pruebas.plazo', 'valor' => '30', 'descripcion' => 'Plazo de prueba.', 'version' => 1]);

    expect(Parametro::query()->where('clave', 'plazo')->value('descripcion'))->toBe('Plazo de prueba.');
});

test('acepta lo que escribe una persona en cada tipo', function (TipoParametro $tipo, string $inicial, string $entrada, string $guardado) {
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'valor', 'tipo' => $tipo, 'valor' => $inicial]);

    cambiarParametro(editorDeParametros(), ['parametro' => 'pruebas.valor', 'valor' => $entrada, 'version' => 1]);

    expect(Parametro::query()->where('clave', 'valor')->value('valor'))->toBe($guardado);
})->with([
    'entero' => [TipoParametro::Entero, '30', ' 45 ', '45'],
    'decimal' => [TipoParametro::Decimal, '18.00', '10.50', '10.50'],
    'booleano con sí' => [TipoParametro::Booleano, 'false', 'Sí', 'true'],
    'booleano con no' => [TipoParametro::Booleano, 'true', 'no', 'false'],
    'fecha' => [TipoParametro::Fecha, '2026-01-01', '2027-01-01', '2027-01-01'],
    'texto' => [TipoParametro::Texto, 'a', 'Techquk S.A.C.', 'Techquk S.A.C.'],
]);

test('rechaza lo que no corresponde al tipo del parámetro', function (TipoParametro $tipo, string $inicial, string $entrada) {
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'valor', 'tipo' => $tipo, 'valor' => $inicial]);

    expect(fn () => cambiarParametro(editorDeParametros(), ['parametro' => 'pruebas.valor', 'valor' => $entrada, 'version' => 1]))
        ->toThrow(ParametroRechazado::class, "se espera {$tipo->formatoEsperado()}");
})->with([
    'letras en un entero' => [TipoParametro::Entero, '30', 'treinta'],
    'decimal con coma' => [TipoParametro::Decimal, '18.00', '18,5'],
    'booleano ambiguo' => [TipoParametro::Booleano, 'true', 'tal vez'],
    'fecha inexistente' => [TipoParametro::Fecha, '2026-01-01', '2026-02-30'],
    'fecha en otro formato' => [TipoParametro::Fecha, '2026-01-01', '09/10/2026'],
]);

test('rechaza a quien no tiene permiso, al usuario de sistema y a un inactivo', function (Closure $usuario) {
    expect(fn () => cambiarParametro($usuario(), ['parametro' => 'pruebas.plazo', 'valor' => '10', 'version' => 1]))
        ->toThrow(AccionRechazada::class);
    expect(app(ParametrosService::class)->entero('pruebas.plazo'))->toBe(30);
})->with([
    'sin permiso' => [fn () => User::factory()->create()],
    'sistema' => [fn () => User::query()->where('es_sistema', true)->sole()],
    'inactivo' => [fn () => User::factory()->inactivo()->create()->givePermissionTo('parametros.editar')],
]);

test('los parámetros de dinero del núcleo no se cambian con la acción normal', function (string $parametro) {
    expect(fn () => cambiarParametro(editorDeParametros(), ['parametro' => $parametro, 'valor' => '1', 'version' => 1]))
        ->toThrow(ParametroRechazado::class, 'es un parámetro de dinero');
})->with(['tributario.igv_porcentaje', 'tipo_cambio.dias_tolerancia', 'parties.ruc_banco_detracciones']);

test('rechaza un parámetro no editable aunque se tenga el permiso', function () {
    expect(fn () => cambiarParametro(editorDeParametros(), ['parametro' => 'general.moneda_local', 'valor' => 'USD', 'version' => 1]))
        ->toThrow(ParametroRechazado::class, 'no se puede cambiar');
});

test('si otra persona lo cambió después de abrirlo, se rechaza', function () {
    cambiarParametro(editorDeParametros(), ['parametro' => 'pruebas.plazo', 'valor' => '10', 'version' => 1]);

    expect(fn () => cambiarParametro(editorDeParametros(), ['parametro' => 'pruebas.plazo', 'valor' => '12', 'version' => 1]))
        ->toThrow(VersionDesactualizada::class);
});

test('valida que vengan el parámetro, el valor y una versión válida', function (array $datos, string $campo) {
    try {
        cambiarParametro(editorDeParametros(), $datos);
        test()->fail('Debió rechazar la validación.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($campo);
    }
})->with([
    'sin valor' => [['parametro' => 'pruebas.plazo', 'version' => 1], 'valor'],
    'sin versión' => [['parametro' => 'pruebas.plazo', 'valor' => '10'], 'version'],
    'versión cero' => [['parametro' => 'pruebas.plazo', 'valor' => '10', 'version' => 0], 'version'],
    'descripción muy larga' => [['parametro' => 'pruebas.plazo', 'valor' => '10', 'descripcion' => str_repeat('x', 256), 'version' => 1], 'descripcion'],
]);
