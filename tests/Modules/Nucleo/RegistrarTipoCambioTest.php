<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\RegistrarTipoCambio;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\CatalogoNoEncontrado;
use App\Modules\Nucleo\Excepciones\TipoCambioInvalido;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function registrarTc(User $usuario, array $cambios = []): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.registrar-tipo-cambio', [
        'moneda' => 'USD', 'fecha' => '2026-10-09', 'compra' => '3.7480', 'venta' => '3.7560', ...$cambios,
    ], $usuario);
}

function tesorero(): User
{
    return User::factory()->create()->givePermissionTo('tipos_cambio.registrar');
}

test('el núcleo la registra y su permiso existe', function () {
    expect(app(RegistroAcciones::class)->todas())->toHaveKey('nucleo.registrar-tipo-cambio', RegistrarTipoCambio::class)
        ->and(app(RegistrarTipoCambio::class)->permiso())->toBe('tipos_cambio.registrar');
});

test('registra el TC manual del día a nombre de la persona', function () {
    $luis = tesorero();

    $resultado = registrarTc($luis, ['moneda' => 'usd']);

    $fila = TipoCambio::query()->sole();
    expect($resultado->venta)->toBe('3.7560')
        ->and($fila->origen)->toBe(OrigenTipoCambio::Manual)
        ->and($fila->created_by)->toBe($luis->id)
        ->and(app(TipoCambioService::class)->del('USD', CarbonImmutable::parse('2026-10-09'))->compra)->toBe('3.7480');
});

test('corregir el del día lo reemplaza y firma quien corrigió', function () {
    registrarTc(tesorero());
    $ana = tesorero();

    registrarTc($ana, ['venta' => '3.7600']);

    expect(TipoCambio::query()->sole()->venta)->toBe('3.7600')
        ->and(TipoCambio::query()->sole()->updated_by)->toBe($ana->id);
});

test('rechaza a quien no tiene permiso', function () {
    expect(fn () => registrarTc(User::factory()->create()))->toThrow(AccionRechazada::class, 'No tienes permiso');
});

test('rechaza las reglas del negocio', function (array $cambios, string $excepcion) {
    expect(fn () => registrarTc(tesorero(), $cambios))->toThrow($excepcion);
})->with([
    'moneda local' => [['moneda' => 'PEN'], TipoCambioInvalido::class],
    'compra en cero' => [['compra' => '0'], TipoCambioInvalido::class],
    'moneda que no existe' => [['moneda' => 'EUR'], CatalogoNoEncontrado::class],
]);

test('valida la forma de los datos con mensajes en español', function (array $cambios, string $campo, string $mensaje) {
    try {
        registrarTc(tesorero(), $cambios);
        test()->fail('Debió rechazar la validación.');
    } catch (ValidationException $e) {
        expect($e->errors()[$campo][0])->toBe($mensaje);
    }
})->with([
    'cinco decimales' => [['compra' => '3.74801'], 'compra', 'El campo Compra tiene más decimales de los permitidos (0-4).'],
    'con coma' => [['venta' => '3,756'], 'venta', 'El campo Venta debe ser un número.'],
    'sin fecha' => [['fecha' => null], 'fecha', 'El campo Fecha es obligatorio.'],
    'moneda larga' => [['moneda' => 'DOLAR'], 'moneda', 'El campo Moneda no puede tener más de 3 caracteres.'],
]);
