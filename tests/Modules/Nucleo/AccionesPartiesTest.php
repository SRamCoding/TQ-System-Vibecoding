<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Parties\ActivarParty;
use App\Modules\Nucleo\Acciones\Parties\AsignarRolParty;
use App\Modules\Nucleo\Acciones\Parties\BorrarParty;
use App\Modules\Nucleo\Acciones\Parties\CerrarRolParty;
use App\Modules\Nucleo\Acciones\Parties\DesactivarParty;
use App\Modules\Nucleo\Acciones\Parties\EditarParty;
use App\Modules\Nucleo\Acciones\Parties\EditarTributariosParty;
use App\Modules\Nucleo\Acciones\Parties\RegistrarParty;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\PartyRegistrada;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Illuminate\Validation\ValidationException;

/*
 * Plan 1.3, paso 5: las acciones de parties y roles se ejecutan sin interfaz por EjecutorAcciones, con su
 * permiso y a nombre de quien las pide. Las reglas de negocio están probadas en PartiesServiceTest.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function accionParty(string $operacion, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar("nucleo.parties.{$operacion}", $datos, $usuario);
}

function usuarioConPermisos(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

function idTipoDocumentoIdentidad(string $codigo): int
{
    return TipoDocumentoIdentidad::query()->where('codigo', $codigo)->value('id');
}

function idRolParty(string $codigo): int
{
    return RolParty::query()->where('codigo', $codigo)->value('id');
}

function empresaRegistrada(): Party
{
    $registro = app(PartiesService::class)->registrar(new DatosParty('6', '20123456786', razonSocial: 'Comercial Ejemplo S.A.C.'));

    return Party::query()->findOrFail($registro->id);
}

test('el núcleo registra las ocho acciones con su permiso', function (string $clave, string $clase, string $permiso) {
    $accion = app(RegistroAcciones::class)->obtener($clave);

    expect($accion)->toBeInstanceOf($clase)
        ->and($accion->permiso())->toBe($permiso);
})->with([
    ['nucleo.parties.registrar', RegistrarParty::class, 'parties.crear'],
    ['nucleo.parties.editar', EditarParty::class, 'parties.editar'],
    ['nucleo.parties.editar-tributarios', EditarTributariosParty::class, 'parties.editar-tributarios'],
    ['nucleo.parties.desactivar', DesactivarParty::class, 'parties.desactivar'],
    ['nucleo.parties.activar', ActivarParty::class, 'parties.desactivar'],
    ['nucleo.parties.borrar', BorrarParty::class, 'parties.borrar'],
    ['nucleo.parties.asignar-rol', AsignarRolParty::class, 'parties.asignar-roles'],
    ['nucleo.parties.cerrar-rol', CerrarRolParty::class, 'parties.asignar-roles'],
]);

test('registra una empresa a nombre de la persona, con el número como lo escribió', function () {
    $usuario = usuarioConPermisos('parties.crear');

    $registro = accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20-123-456-786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'email' => 'ventas@ejemplo.pe',
    ], $usuario);

    $party = Party::query()->findOrFail($registro->id);
    expect($registro)->toBeInstanceOf(PartyRegistrada::class)
        ->and($registro->pareja)->toBeNull()
        ->and($party->numero_documento)->toBe('20123456786')
        ->and($party->created_by)->toBe($usuario->id);
});

test('registrar un DNI devuelve la pareja RUC 10 si ya existe, y crea igual', function () {
    $usuario = usuarioConPermisos('parties.crear');
    $ruc = accionParty('registrar', ['tipo_documento_id' => idTipoDocumentoIdentidad('6'), 'numero_documento' => '10012345670', 'nombres' => 'Luis', 'apellidos' => 'Díaz'], $usuario);

    $dni = accionParty('registrar', ['tipo_documento_id' => idTipoDocumentoIdentidad('1'), 'numero_documento' => '01234567', 'nombres' => 'Luis', 'apellidos' => 'Díaz'], $usuario);

    expect($dni->pareja?->id)->toBe($ruc->id)
        ->and(Party::query()->where('persona_numero', '01234567')->count())->toBe(2);
});

test('el tipo de persona se elige solo cuando el documento no lo dice', function () {
    $registro = accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('0'),
        'numero_documento' => 'US12345',
        'tipo' => 'juridica',
        'razon_social' => 'Acme Inc.',
    ], usuarioConPermisos('parties.crear'));

    expect(Party::query()->findOrFail($registro->id)->tipo->value)->toBe('juridica');
});

test('registrar exige el tipo de documento y el número', function () {
    expect(fn () => accionParty('registrar', ['razon_social' => 'X S.A.'], usuarioConPermisos('parties.crear')))
        ->toThrow(ValidationException::class);
});

test('cada acción exige su permiso', function (string $operacion, string $otroPermiso) {
    $party = empresaRegistrada();

    expect(fn () => accionParty($operacion, ['party_id' => $party->id, 'version' => 1], usuarioConPermisos($otroPermiso)))
        ->toThrow(AccionRechazada::class);
})->with([
    'registrar con solo editar' => ['registrar', 'parties.editar'],
    'editar con solo crear' => ['editar', 'parties.crear'],
    'tributarios con solo editar' => ['editar-tributarios', 'parties.editar'],
    'desactivar con solo editar' => ['desactivar', 'parties.editar'],
    'borrar con solo desactivar' => ['borrar', 'parties.desactivar'],
    'asignar rol con solo editar' => ['asignar-rol', 'parties.editar'],
]);

test('editar manda todos los campos del formulario; los del otro tipo de persona llegan vacíos', function () {
    $party = empresaRegistrada();
    $usuario = usuarioConPermisos('parties.editar');

    accionParty('editar', [
        'party_id' => $party->id,
        'razon_social' => 'Comercial Nueva S.A.C.',
        'nombre_comercial' => 'Nueva',
        'telefono' => '074 123456',
        'version' => 1,
    ], $usuario);
    $party->refresh();

    expect($party->razon_social)->toBe('Comercial Nueva S.A.C.')
        ->and($party->nombre_comercial)->toBe('Nueva')
        ->and($party->version)->toBe(2)
        ->and($party->updated_by)->toBe($usuario->id);
});

test('editar con un campo vacío lo borra: el formulario manda lo que se ve', function () {
    $party = empresaRegistrada();
    app(PartiesService::class)->editar($party->id, ['telefono' => '074 123456'], 1);

    accionParty('editar', ['party_id' => $party->id, 'razon_social' => 'Comercial Ejemplo S.A.C.', 'version' => 2], usuarioConPermisos('parties.editar'));

    expect($party->refresh()->telefono)->toBeNull();
});

test('las marcas tributarias se cambian con su acción', function () {
    $party = empresaRegistrada();

    accionParty('editar-tributarios', [
        'party_id' => $party->id,
        'es_agente_retencion' => true,
        'es_agente_percepcion' => false,
        'es_buen_contribuyente' => '1',
        'version' => 1,
    ], usuarioConPermisos('parties.editar-tributarios'));
    $party->refresh();

    expect($party->es_agente_retencion)->toBeTrue()
        ->and($party->es_agente_percepcion)->toBeFalse()
        ->and($party->es_buen_contribuyente)->toBeTrue();
});

test('desactivar, activar y borrar', function () {
    $party = empresaRegistrada();
    $usuario = usuarioConPermisos('parties.desactivar', 'parties.borrar');

    accionParty('desactivar', ['party_id' => $party->id, 'version' => 1], $usuario);
    expect($party->refresh()->es_activo)->toBeFalse();

    accionParty('activar', ['party_id' => $party->id, 'version' => 2], $usuario);
    expect($party->refresh()->es_activo)->toBeTrue();

    accionParty('borrar', ['party_id' => $party->id, 'version' => 3], $usuario);
    expect(Party::query()->whereKey($party->id)->exists())->toBeFalse();
});

test('asignar y cerrar un rol, eligiendo el rol por su id', function () {
    $party = empresaRegistrada();
    $usuario = usuarioConPermisos('parties.asignar-roles');

    $periodo = accionParty('asignar-rol', ['party_id' => $party->id, 'rol_party_id' => idRolParty('proveedor'), 'desde' => '2026-01-01'], $usuario);
    accionParty('cerrar-rol', ['party_id' => $party->id, 'rol_party_id' => idRolParty('proveedor'), 'hasta' => '2026-06-30', 'version' => 1], $usuario);

    $cerrado = PartyRol::query()->findOrFail($periodo);
    expect($cerrado->hasta?->toDateString())->toBe('2026-06-30')
        ->and($cerrado->created_by)->toBe($usuario->id);
});

test('un rechazo del servicio llega igual a quien llama la acción', function () {
    $party = empresaRegistrada();
    $usuario = usuarioConPermisos('parties.asignar-roles');
    accionParty('asignar-rol', ['party_id' => $party->id, 'rol_party_id' => idRolParty('cliente'), 'desde' => '2026-01-01'], $usuario);

    expect(fn () => accionParty('asignar-rol', ['party_id' => $party->id, 'rol_party_id' => idRolParty('cliente'), 'desde' => '2026-02-01'], $usuario))
        ->toThrow(PartyRechazada::class, 'Ya tiene el rol Cliente en esas fechas.');
});

test('una party que no existe se rechaza en la validación', function () {
    expect(fn () => accionParty('desactivar', ['party_id' => 999999, 'version' => 1], usuarioConPermisos('parties.desactivar')))
        ->toThrow(ValidationException::class);
});

// ——— Roles iniciales: campo de lista y una sola operación ———

test('registrar con roles exige también el permiso de asignarlos, y sin él no registra nada', function () {
    $soloCrear = usuarioConPermisos('parties.crear');

    expect(fn () => accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'roles' => [idRolParty('cliente')],
    ], $soloCrear))->toThrow(AccionRechazada::class, 'No tienes permiso para «Asignar rol».')
        ->and(Party::query()->where('numero_documento', '20123456786')->exists())->toBeFalse();
});

test('sin roles, registrar solo pide el permiso de crear', function () {
    $registro = accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'roles' => [],
    ], usuarioConPermisos('parties.crear'));

    expect(PartyRol::query()->where('party_id', $registro->id)->count())->toBe(0);
});

test('registrar con roles deja la party y sus roles vigentes desde hoy, en una sola operación', function () {
    test()->travelTo('2026-10-15 23:30');
    $usuario = usuarioConPermisos('parties.crear', 'parties.asignar-roles');

    $registro = accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'roles' => [idRolParty('cliente'), (string) idRolParty('entidad')],
    ], $usuario);

    $periodos = PartyRol::query()->where('party_id', $registro->id)->with('rol')->get();
    expect($periodos->map(fn (PartyRol $p): string => $p->rol->codigo.' '.$p->desde->toDateString())->sort()->values()->all())
        ->toBe(['cliente 2026-10-15', 'entidad 2026-10-15'])
        ->and($periodos->every(fn (PartyRol $p): bool => $p->hasta === null && $p->created_by === $usuario->id))->toBeTrue();
});

test('si un rol ya no está disponible, no se registra nada y el mensaje lo dice', function () {
    $aliado = idRolParty('aliado');
    RolParty::query()->whereKey($aliado)->update(['es_activo' => false]);

    expect(fn () => accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'roles' => [idRolParty('cliente'), $aliado],
    ], usuarioConPermisos('parties.crear', 'parties.asignar-roles')))
        ->toThrow(PartyRechazada::class, 'No se registró nada: el rol Aliado ya no está disponible.')
        ->and(Party::query()->where('numero_documento', '20123456786')->exists())->toBeFalse()
        ->and(PartyRol::query()->count())->toBe(0);
});

test('la lista de roles se valida elemento por elemento', function (mixed $roles) {
    expect(fn () => accionParty('registrar', [
        'tipo_documento_id' => idTipoDocumentoIdentidad('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'roles' => is_callable($roles) ? $roles() : $roles,
    ], usuarioConPermisos('parties.crear', 'parties.asignar-roles')))->toThrow(ValidationException::class);
})->with([
    'no es lista' => ['cliente'],
    'rol que no existe' => [[999999]],
    'rol repetido' => [fn () => [idRolParty('cliente'), idRolParty('cliente')]],
    'texto en vez de id' => [['cliente']],
]);
