<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Catalogos\BorrarElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\CrearElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\EditarSensiblesElementoCatalogo;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.2b, E5–E6: una acción registrada por catálogo y operación, ejecutada sin interfaz por
 * EjecutorAcciones. Las reglas de negocio están probadas en EdicionCatalogosServiceTest.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    Permission::findOrCreate('catalogos.editar', 'web');
    Permission::findOrCreate('catalogos.editar-sensibles', 'web');
});

function ejecutarEnCatalogo(string $clave, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($clave, $datos, $usuario);
}

function editorDeCatalogos(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos === [] ? ['catalogos.editar'] : $permisos);
}

test('el núcleo registra cinco acciones por catálogo y la de campos de dinero donde los hay', function () {
    $claves = array_keys(app(RegistroAcciones::class)->todas());

    foreach (Catalogo::cases() as $catalogo) {
        $prefijo = 'nucleo.'.str_replace('_', '-', $catalogo->value);

        expect($claves)->toContain("{$prefijo}.crear", "{$prefijo}.editar", "{$prefijo}.desactivar", "{$prefijo}.activar", "{$prefijo}.borrar")
            ->and(in_array("{$prefijo}.editar-sensibles", $claves, true))->toBe($catalogo->tieneSensibles());
    }

    expect(app(RegistroAcciones::class)->todas())
        ->toHaveKey('nucleo.formas-pago.borrar', BorrarElementoCatalogo::class)
        ->toHaveKey('nucleo.tipos-documento.editar-sensibles', EditarSensiblesElementoCatalogo::class);
});

test('crear, desactivar y borrar en monedas y tipos de documento exigen el permiso de campos de dinero', function (Catalogo $catalogo) {
    $esperado = $catalogo->tieneSensibles() ? 'catalogos.editar-sensibles' : 'catalogos.editar';
    $prefijo = 'nucleo.'.str_replace('_', '-', $catalogo->value);
    $registro = app(RegistroAcciones::class);

    expect($registro->obtener("{$prefijo}.crear")->permiso())->toBe($esperado)
        ->and($registro->obtener("{$prefijo}.desactivar")->permiso())->toBe($esperado)
        ->and($registro->obtener("{$prefijo}.borrar")->permiso())->toBe($esperado)
        ->and($registro->obtener("{$prefijo}.editar")->permiso())->toBe('catalogos.editar')
        ->and($registro->obtener("{$prefijo}.activar")->permiso())->toBe('catalogos.editar');
})->with(Catalogo::cases());

test('agrega un elemento a nombre de la persona', function () {
    $ana = editorDeCatalogos();

    ejecutarEnCatalogo('nucleo.formas-pago.crear', ['codigo' => 'CRED45', 'nombre' => 'Crédito 45 días', 'dias' => '45'], $ana);

    $forma = FormaPago::query()->where('codigo', 'CRED45')->firstOrFail();
    expect($forma->dias)->toBe(45)
        ->and($forma->created_by)->toBe($ana->id);
});

test('edita, desactiva, activa y borra con la versión leída', function () {
    $ana = editorDeCatalogos();
    FormaPago::factory()->create(['codigo' => 'CRED30', 'nombre' => 'Crédito 30', 'dias' => 30]);

    ejecutarEnCatalogo('nucleo.formas-pago.editar', ['codigo' => 'CRED30', 'nombre' => 'Crédito a 30 días', 'dias' => '30', 'version' => '1'], $ana);
    ejecutarEnCatalogo('nucleo.formas-pago.desactivar', ['codigo' => 'CRED30', 'version' => '2'], $ana);
    ejecutarEnCatalogo('nucleo.formas-pago.activar', ['codigo' => 'CRED30', 'version' => '3'], $ana);

    $forma = FormaPago::query()->where('codigo', 'CRED30')->firstOrFail();
    expect($forma->nombre)->toBe('Crédito a 30 días')
        ->and($forma->es_activa)->toBeTrue()
        ->and($forma->updated_by)->toBe($ana->id);

    ejecutarEnCatalogo('nucleo.formas-pago.borrar', ['codigo' => 'CRED30', 'version' => '4'], $ana);
    expect(FormaPago::query()->where('codigo', 'CRED30')->exists())->toBeFalse();
});

test('rechaza a quien no tiene permiso, al usuario de sistema y a un inactivo', function (Closure $usuario) {
    expect(fn () => ejecutarEnCatalogo('nucleo.formas-pago.crear', ['codigo' => 'CRED45', 'nombre' => 'Crédito 45'], $usuario()))
        ->toThrow(AccionRechazada::class)
        ->and(FormaPago::query()->where('codigo', 'CRED45')->exists())->toBeFalse();
})->with([
    'sin permiso' => [fn () => User::factory()->create()],
    'sistema' => [fn () => User::query()->where('es_sistema', true)->sole()],
    'inactivo' => [fn () => User::factory()->inactivo()->create()->givePermissionTo('catalogos.editar')],
]);

test('con el permiso general no se cambian los campos de dinero', function () {
    $ana = editorDeCatalogos();
    TipoDocumento::factory()->create(['codigo' => '07', 'nombre' => 'Nota de crédito', 'signo' => -1]);

    expect(fn () => ejecutarEnCatalogo('nucleo.tipos-documento.editar-sensibles', ['codigo' => '07', 'signo' => '1', 'afecta_cuenta_corriente' => true, 'version' => '1'], $ana))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Editar campos de dinero en tipos de documento»');

    // editar no declara el signo: aunque llegue, no pasa la validación hacia el servicio.
    ejecutarEnCatalogo('nucleo.tipos-documento.editar', ['codigo' => '07', 'nombre' => 'Nota de crédito electrónica', 'es_electronico' => true, 'signo' => '1', 'version' => '1'], $ana);

    $nota = TipoDocumento::query()->where('codigo', '07')->firstOrFail();
    expect($nota->signo)->toBe(-1)
        ->and($nota->nombre)->toBe('Nota de crédito electrónica');
});

test('con el permiso de dinero se cambian el signo y la cuenta corriente', function () {
    $gerente = editorDeCatalogos('catalogos.editar', 'catalogos.editar-sensibles');
    TipoDocumento::factory()->create(['codigo' => '07', 'signo' => 1, 'afecta_cuenta_corriente' => false]);

    ejecutarEnCatalogo('nucleo.tipos-documento.editar-sensibles', ['codigo' => '07', 'signo' => '-1', 'afecta_cuenta_corriente' => true, 'version' => '1'], $gerente);

    $nota = TipoDocumento::query()->where('codigo', '07')->firstOrFail();
    expect($nota->signo)->toBe(-1)
        ->and($nota->afecta_cuenta_corriente)->toBeTrue();
});

test('valida los campos antes de llegar al servicio', function (string $clave, array $datos, string $campo) {
    expect(fn () => ejecutarEnCatalogo($clave, $datos, editorDeCatalogos('catalogos.editar', 'catalogos.editar-sensibles')))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($campo));
})->with([
    'código de moneda en minúsculas' => ['nucleo.monedas.crear', ['codigo' => 'eur', 'nombre' => 'Euro', 'simbolo' => '€', 'decimales' => '2'], 'codigo'],
    'moneda con 5 decimales' => ['nucleo.monedas.crear', ['codigo' => 'EUR', 'nombre' => 'Euro', 'simbolo' => '€', 'decimales' => '5'], 'decimales'],
    'signo 2' => ['nucleo.tipos-documento.editar-sensibles', ['codigo' => '01', 'signo' => '2', 'afecta_cuenta_corriente' => true, 'version' => '1'], 'signo'],
    'editar sin versión' => ['nucleo.formas-pago.editar', ['codigo' => 'CRED30', 'nombre' => 'Crédito'], 'version'],
    'unidad de medida que no existe' => ['nucleo.cubso.crear', ['codigo' => '43211500', 'descripcion' => 'Computadoras', 'unidad_medida_id' => '999999'], 'unidad_medida_id'],
]);

test('una regla de negocio rechazada llega como error de negocio', function () {
    $euro = Moneda::factory()->create(['codigo' => 'EUR']);
    TipoCambio::factory()->create(['moneda_id' => $euro->id]);

    expect(fn () => ejecutarEnCatalogo('nucleo.monedas.borrar', ['codigo' => 'EUR', 'version' => '1'], editorDeCatalogos('catalogos.editar', 'catalogos.editar-sensibles')))
        ->toThrow(ElementoCatalogoRechazado::class, 'no se puede borrar');
});

test('cada acción pertenece a su catálogo aunque comparta clase', function () {
    $registro = app(RegistroAcciones::class);

    expect($registro->obtener('nucleo.monedas.crear'))->toBeInstanceOf(CrearElementoCatalogo::class)
        ->and($registro->obtener('nucleo.monedas.crear')->nombre())->toBe('Agregar a monedas')
        ->and($registro->obtener('nucleo.lineas-solucion.crear')->nombre())->toBe('Agregar a líneas de solución');
});
