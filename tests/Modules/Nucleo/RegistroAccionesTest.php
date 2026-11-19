<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\AccionDuplicada;
use App\Modules\Nucleo\Excepciones\AccionNoRegistrada;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\AccionDePrueba;
use Tests\Modules\Nucleo\Fixtures\OtraAccionConLaMismaClave;

beforeEach(function () {
    Permission::create(['name' => 'probar-acciones']);
    app(RegistroAcciones::class)->registrar(AccionDePrueba::class);
});

function datosValidos(array $cambios = []): array
{
    return [
        'nombre' => 'Cable',
        'cantidad' => '3',
        'monto' => '1248.00',
        'fecha_entrega' => '2026-10-20',
        'es_urgente' => '1',
        'area' => 'crm',
        ...$cambios,
    ];
}

function usuarioConPermiso(): User
{
    return User::factory()->create()->givePermissionTo('probar-acciones');
}

function ejecutarPrueba(array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('pruebas.accion', $datos, $usuario);
}

test('ejecuta con los datos convertidos a su tipo y a nombre del usuario', function () {
    $usuario = usuarioConPermiso();

    $resultado = ejecutarPrueba(datosValidos(['campo_no_declarado' => 'x']), $usuario);

    expect($resultado['actor'])->toBe($usuario->id)
        ->and($resultado['datos'])->toHaveKeys(['nombre', 'cantidad', 'monto', 'fecha_entrega', 'es_urgente', 'area', 'nota'])
        ->and($resultado['datos'])->not->toHaveKey('campo_no_declarado')
        ->and($resultado['datos']['cantidad'])->toBe(3)
        ->and($resultado['datos']['monto'])->toBe('1248.00')
        ->and($resultado['datos']['fecha_entrega'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($resultado['datos']['fecha_entrega']->toDateString())->toBe('2026-10-20')
        ->and($resultado['datos']['es_urgente'])->toBeTrue()
        ->and($resultado['datos']['area'])->toBe(AreaModulo::Crm)
        ->and($resultado['datos']['nota'])->toBeNull();
});

test('el actor fijado no queda después de ejecutar', function () {
    ejecutarPrueba(datosValidos(), usuarioConPermiso());

    expect(app(ContextoAuditoria::class)->idActor())
        ->toBe(app(UsuarioSistema::class)->id());
});

test('rechaza al usuario de sistema', function () {
    $sistema = User::query()->where('es_sistema', true)->sole()->givePermissionTo('probar-acciones');

    expect(fn () => ejecutarPrueba(datosValidos(), $sistema))
        ->toThrow(AccionRechazada::class, 'El usuario de sistema no ejecuta acciones de usuario.');
});

test('rechaza a un usuario inactivo', function () {
    $usuario = User::factory()->inactivo()->create()->givePermissionTo('probar-acciones');

    expect(fn () => ejecutarPrueba(datosValidos(), $usuario))->toThrow(AccionRechazada::class, 'dado de baja');
});

test('rechaza a quien no tiene el permiso', function () {
    expect(fn () => ejecutarPrueba(datosValidos(), User::factory()->create()))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Acción de prueba».');
});

test('rechaza datos inválidos campo por campo', function (array $cambios, string $campo) {
    try {
        ejecutarPrueba(datosValidos($cambios), usuarioConPermiso());
        $this->fail('Debió rechazar los datos.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($campo);
    }
})->with([
    'falta un obligatorio' => [['nombre' => null], 'nombre'],
    'texto muy largo' => [['nombre' => str_repeat('x', 11)], 'nombre'],
    'entero con decimales' => [['cantidad' => '1.5'], 'cantidad'],
    'regla agregada por la acción' => [['cantidad' => '0'], 'cantidad'],
    'decimal con tres decimales' => [['monto' => '1.234'], 'monto'],
    'fecha con otro formato' => [['fecha_entrega' => '20/10/2026'], 'fecha_entrega'],
    'booleano inválido' => [['es_urgente' => 'tal vez'], 'es_urgente'],
    'opción que no existe' => [['area' => 'otro'], 'area'],
]);

test('una clave no registrada es un error de programación', function () {
    expect(fn () => app(EjecutorAcciones::class)->ejecutar('pruebas.no-existe', [], usuarioConPermiso()))
        ->toThrow(AccionNoRegistrada::class);
});

test('dos clases no pueden registrar la misma clave; la misma clase dos veces sí', function () {
    app(RegistroAcciones::class)->registrar(AccionDePrueba::class);

    expect(app(RegistroAcciones::class)->todas())->toHaveKey('pruebas.accion', AccionDePrueba::class)
        ->and(fn () => app(RegistroAcciones::class)->registrar(OtraAccionConLaMismaClave::class))
        ->toThrow(AccionDuplicada::class);
});

test('se registra una instancia ya configurada y se obtiene esa misma', function () {
    $accion = new AccionDePrueba;
    app(RegistroAcciones::class)->registrar($accion);

    expect(app(RegistroAcciones::class)->obtener('pruebas.accion'))->toBe($accion)
        ->and(app(RegistroAcciones::class)->todas())->toHaveKey('pruebas.accion', AccionDePrueba::class)
        ->and(fn () => app(RegistroAcciones::class)->registrar(new OtraAccionConLaMismaClave))
        ->toThrow(AccionDuplicada::class);
});

test('rechaza a un usuario dado de baja después de cargarse', function () {
    $usuario = usuarioConPermiso();
    User::query()->whereKey($usuario->id)->update(['es_activo' => false]);

    expect($usuario->es_activo)->toBeTrue()
        ->and(fn () => ejecutarPrueba(datosValidos(), $usuario))->toThrow(AccionRechazada::class, 'dado de baja');
});
