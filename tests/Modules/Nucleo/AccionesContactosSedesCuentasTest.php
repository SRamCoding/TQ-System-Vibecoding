<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ContactoRechazado;
use App\Modules\Nucleo\Excepciones\CuentaBancariaRechazada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Las acciones de contactos, sedes y cuentas bancarias, sin pantalla (plan 1.3b, paso 4): así las usará otro
 * módulo (el CRM) y la API futura. Las reglas de negocio se prueban en los servicios; aquí, el registro, los
 * permisos, la validación y que cada acción llegue a su servicio.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** @param array<string, mixed> $datos */
function ejecutarAccionParty(string $operacion, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.parties.{$operacion}", $datos, $usuario);
}

function usuarioCon(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

function bancoParaAcciones(): Party
{
    $banco = Party::factory()->juridica()->create(['razon_social' => 'Banco Ejemplo S.A.']);
    app(PartiesService::class)->asignarRol($banco->id, 'banco', CarbonImmutable::parse('2026-01-01'));

    return $banco;
}

test('el núcleo registra las 17 acciones y crea sus permisos', function () {
    $permisos = [
        'parties.gestionar-contactos' => ['agregar-contacto', 'editar-contacto', 'marcar-contacto-principal', 'cerrar-vinculo-contacto', 'trasladar-contacto', 'borrar-contacto'],
        'parties.gestionar-sedes' => ['agregar-sede', 'editar-sede', 'desactivar-sede', 'activar-sede', 'borrar-sede'],
        'parties.gestionar-cuentas-bancarias' => ['registrar-cuenta-bancaria', 'completar-cci', 'marcar-cuenta-principal', 'desactivar-cuenta-bancaria', 'activar-cuenta-bancaria', 'borrar-cuenta-bancaria'],
    ];
    $registro = app(RegistroAcciones::class);

    foreach ($permisos as $permiso => $operaciones) {
        expect(Permission::query()->where('name', $permiso)->exists())->toBeTrue();

        foreach ($operaciones as $operacion) {
            expect($registro->obtener("nucleo.parties.{$operacion}")->permiso())->toBe($permiso);
        }
    }
});

test('cada grupo exige su propio permiso: el de contactos no sirve para sedes ni para cuentas', function () {
    $party = Party::factory()->juridica()->create();
    $vendedor = usuarioCon('parties.gestionar-contactos');

    expect(fn () => ejecutarAccionParty('agregar-sede', ['party_id' => $party->id, 'nombre' => 'Almacén', 'direccion' => 'Av. 1'], $vendedor))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Agregar sede».')
        ->and(fn () => ejecutarAccionParty('registrar-cuenta-bancaria', ['party_id' => $party->id, 'banco_party_id' => $party->id, 'numero_cuenta' => '1', 'moneda_id' => 1], $vendedor))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Registrar cuenta bancaria».')
        ->and(fn () => ejecutarAccionParty('agregar-contacto', ['party_id' => $party->id, 'nombres' => 'Luis', 'apellidos' => 'Ruiz'], usuarioCon('parties.editar')))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Agregar contacto».');
});

test('el usuario de sistema y uno inactivo no ejecutan acciones de contactos', function (Closure $usuario) {
    $party = Party::factory()->juridica()->create();

    expect(fn () => ejecutarAccionParty('agregar-contacto', ['party_id' => $party->id, 'nombres' => 'Luis', 'apellidos' => 'Ruiz'], $usuario()))
        ->toThrow(AccionRechazada::class);
})->with([
    'sistema' => [fn () => User::query()->where('es_sistema', true)->sole()],
    'inactivo' => [fn () => User::factory()->inactivo()->create()->givePermissionTo('parties.gestionar-contactos')],
]);

test('agregar, marcar principal, cerrar y trasladar un contacto por sus acciones', function () {
    $usuario = usuarioCon('parties.gestionar-contactos');
    $pimentel = Party::factory()->juridica()->create();
    $gore = Party::factory()->juridica()->create();

    $luis = ejecutarAccionParty('agregar-contacto', ['party_id' => $pimentel->id, 'nombres' => 'Luis', 'apellidos' => 'Fernández', 'cargo' => 'Jefe', 'es_principal' => true, 'desde' => '2025-01-01'], $usuario);
    $carmen = ejecutarAccionParty('agregar-contacto', ['party_id' => $pimentel->id, 'nombres' => 'Carmen', 'apellidos' => 'Ruiz'], $usuario);
    ejecutarAccionParty('marcar-contacto-principal', ['contacto_id' => $carmen, 'version' => 1], $usuario);
    ejecutarAccionParty('editar-contacto', ['contacto_id' => $luis, 'nombres' => 'Luis', 'apellidos' => 'Fernández', 'cargo' => 'Gerente', 'version' => 2], $usuario);
    $nuevo = ejecutarAccionParty('trasladar-contacto', [
        'contacto_id' => $luis, 'nueva_party_id' => $gore->id, 'hasta' => '2026-07-31',
        'nombres' => 'Luis', 'apellidos' => 'Fernández', 'cargo' => 'Jefe de Abastecimiento', 'email' => 'luis@gore.pe', 'version' => 3,
    ], $usuario);

    $cerrado = PartyContacto::query()->findOrFail($luis);

    expect(PartyContacto::query()->findOrFail($carmen)->es_principal)->toBeTrue()
        ->and($cerrado->cargo)->toBe('Gerente')
        ->and($cerrado->desde)->toBeNull()
        ->and($cerrado->hasta?->toDateString())->toBe('2026-07-31')
        ->and(PartyContacto::query()->findOrFail($nuevo)->contacto_anterior_id)->toBe($luis)
        ->and(PartyContacto::query()->findOrFail($nuevo)->desde?->toDateString())->toBe('2026-08-01')
        ->and(PartyContacto::query()->findOrFail($nuevo)->created_by)->toBe($usuario->id);

    $otro = ejecutarAccionParty('agregar-contacto', ['party_id' => $gore->id, 'nombres' => 'Ana', 'apellidos' => 'Soto'], $usuario);
    ejecutarAccionParty('cerrar-vinculo-contacto', ['contacto_id' => $otro, 'hasta' => '2026-09-30', 'version' => 1], $usuario);
    ejecutarAccionParty('borrar-contacto', ['contacto_id' => $otro, 'version' => 2], $usuario);

    expect(PartyContacto::query()->find($otro))->toBeNull();
});

test('el traslado exige el cargo nuevo y la institución de destino', function (array $sinCampo, string $campo) {
    $usuario = usuarioCon('parties.gestionar-contactos');
    $contacto = PartyContacto::factory()->create();
    $datos = ['contacto_id' => $contacto->id, 'nueva_party_id' => Party::factory()->juridica()->create()->id, 'hasta' => '2026-07-31', 'nombres' => 'Luis', 'apellidos' => 'Ruiz', 'cargo' => 'Gerente', 'version' => 1];

    try {
        ejecutarAccionParty('trasladar-contacto', array_diff_key($datos, $sinCampo), $usuario);
        test()->fail('Debió rechazar la validación.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($campo);
    }
})->with([
    'sin cargo' => [['cargo' => true], 'cargo'],
    'sin destino' => [['nueva_party_id' => true], 'nueva_party_id'],
]);

test('un rechazo del servicio llega tal cual por la acción', function () {
    $contacto = PartyContacto::factory()->cerrado()->create();

    expect(fn () => ejecutarAccionParty('marcar-contacto-principal', ['contacto_id' => $contacto->id, 'version' => 1], usuarioCon('parties.gestionar-contactos')))
        ->toThrow(ContactoRechazado::class, 'ya no está vigente');
});

test('agregar, editar, desactivar, activar y borrar una sede por sus acciones', function () {
    $usuario = usuarioCon('parties.gestionar-sedes');
    $party = Party::factory()->juridica()->create();
    $contacto = PartyContacto::factory()->create(['party_id' => $party->id]);

    $id = ejecutarAccionParty('agregar-sede', ['party_id' => $party->id, 'nombre' => 'Almacén Chiclayo', 'direccion' => 'Av. 1', 'latitud' => '-6.7714', 'longitud' => '-79.8409', 'contacto_id' => $contacto->id, 'codigo_establecimiento' => '0001'], $usuario);
    ejecutarAccionParty('editar-sede', ['sede_id' => $id, 'nombre' => 'Almacén Chiclayo', 'direccion' => 'Av. 2', 'version' => 1], $usuario);
    ejecutarAccionParty('desactivar-sede', ['sede_id' => $id, 'version' => 2], $usuario);
    ejecutarAccionParty('activar-sede', ['sede_id' => $id, 'version' => 3], $usuario);
    $sede = PartySede::query()->findOrFail($id);

    expect($sede->direccion)->toBe('Av. 2')
        ->and($sede->contacto_id)->toBeNull()
        ->and($sede->latitud)->toBeNull()
        ->and($sede->es_activa)->toBeTrue()
        ->and($sede->updated_by)->toBe($usuario->id);

    ejecutarAccionParty('borrar-sede', ['sede_id' => $id, 'version' => 4], $usuario);

    expect(PartySede::query()->find($id))->toBeNull();
});

test('registrar y gestionar una cuenta por sus acciones, eligiendo la moneda del catálogo', function () {
    $usuario = usuarioCon('parties.gestionar-cuentas-bancarias');
    $titular = Party::factory()->juridica()->create();
    $usd = Moneda::query()->where('codigo', 'USD')->value('id');

    $id = ejecutarAccionParty('registrar-cuenta-bancaria', ['party_id' => $titular->id, 'banco_party_id' => bancoParaAcciones()->id, 'numero_cuenta' => '191-1234567-1-12', 'moneda_id' => $usd], $usuario);
    ejecutarAccionParty('completar-cci', ['cuenta_id' => $id, 'cci' => '00219100123456711255', 'version' => 1], $usuario);
    ejecutarAccionParty('marcar-cuenta-principal', ['cuenta_id' => $id, 'version' => 2], $usuario);
    $cuenta = PartyCuentaBancaria::query()->findOrFail($id);

    expect($cuenta->numero_cuenta)->toBe('1911234567112')
        ->and($cuenta->moneda->codigo)->toBe('USD')
        ->and($cuenta->cci)->toBe('00219100123456711255')
        ->and($cuenta->es_principal)->toBeTrue()
        ->and($cuenta->created_by)->toBe($usuario->id);

    ejecutarAccionParty('desactivar-cuenta-bancaria', ['cuenta_id' => $id, 'version' => 3], $usuario);
    ejecutarAccionParty('activar-cuenta-bancaria', ['cuenta_id' => $id, 'version' => 4], $usuario);
    ejecutarAccionParty('borrar-cuenta-bancaria', ['cuenta_id' => $id, 'version' => 5], $usuario);

    expect(PartyCuentaBancaria::query()->find($id))->toBeNull();
});

test('la cuenta de detracciones por la acción: solo en el Banco de la Nación', function () {
    $usuario = usuarioCon('parties.gestionar-cuentas-bancarias');
    $pen = Moneda::query()->where('codigo', 'PEN')->value('id');
    $nacion = Party::factory()->juridica()->create(['tipo_documento_id' => TipoDocumentoIdentidad::query()->where('codigo', Catalogo::RUC)->value('id'), 'numero_documento' => '20100030595', 'razon_social' => 'Banco de la Nación']);
    app(PartiesService::class)->asignarRol($nacion->id, 'banco', CarbonImmutable::parse('2026-01-01'));
    $titular = Party::factory()->juridica()->create();

    ejecutarAccionParty('registrar-cuenta-bancaria', ['party_id' => $titular->id, 'banco_party_id' => $nacion->id, 'numero_cuenta' => '00123456789', 'moneda_id' => $pen, 'es_detraccion' => true], $usuario);

    expect(app(PartiesService::class)->cuentaDetracciones($titular->id))->not->toBeNull()
        ->and(fn () => ejecutarAccionParty('registrar-cuenta-bancaria', ['party_id' => $titular->id, 'banco_party_id' => bancoParaAcciones()->id, 'numero_cuenta' => '555', 'moneda_id' => $pen, 'es_detraccion' => true], $usuario))
        ->toThrow(CuentaBancariaRechazada::class, 'Banco de la Nación');
});

test('la validación rechaza lo que no llega bien', function (string $operacion, array $datos, string $campo) {
    $usuario = usuarioCon('parties.gestionar-contactos', 'parties.gestionar-sedes', 'parties.gestionar-cuentas-bancarias');

    try {
        ejecutarAccionParty($operacion, $datos, $usuario);
        test()->fail('Debió rechazar la validación.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($campo);
    }
})->with([
    'contacto sin apellidos' => ['agregar-contacto', ['party_id' => 1, 'nombres' => 'Luis'], 'apellidos'],
    'contacto de una party que no existe' => ['agregar-contacto', ['party_id' => 999999, 'nombres' => 'Luis', 'apellidos' => 'Ruiz'], 'party_id'],
    'fecha mal escrita' => ['cerrar-vinculo-contacto', ['contacto_id' => 1, 'hasta' => '31/07/2026', 'version' => 1], 'hasta'],
    'sede sin dirección' => ['agregar-sede', ['party_id' => 1, 'nombre' => 'Almacén'], 'direccion'],
    'anexo de cinco caracteres' => ['agregar-sede', ['party_id' => 1, 'nombre' => 'Almacén', 'direccion' => 'Av. 1', 'codigo_establecimiento' => '00001'], 'codigo_establecimiento'],
    'cuenta sin moneda' => ['registrar-cuenta-bancaria', ['party_id' => 1, 'banco_party_id' => 1, 'numero_cuenta' => '1'], 'moneda_id'],
    'cuenta sin versión' => ['desactivar-cuenta-bancaria', ['cuenta_id' => 1], 'version'],
]);
