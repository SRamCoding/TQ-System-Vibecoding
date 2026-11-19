<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * La base protege parties y roles aunque se salte el servicio (regla 5; plan 1.3, D2, D4 y D8). Cada prueba
 * inserta por query builder: así lo haría un proceso automático mal escrito.
 */

function idTipoDocumento(string $codigo): int
{
    return TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => "Tipo {$codigo}"])->id;
}

/**
 * Una party jurídica válida con los cambios pedidos.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function partyValida(array $cambios = []): array
{
    $fila = [
        'tipo' => 'juridica',
        'tipo_documento_id' => idTipoDocumento('6'),
        'numero_documento' => '20123456786',
        'razon_social' => 'Comercial Ejemplo S.A.C.',
        'created_by' => app(UsuarioSistema::class)->id(),
        ...$cambios,
    ];

    // La clave de persona (plan 1.3b, 5b) es la del propio documento, salvo que la prueba pida otra. El nombre
    // normalizado (plan 2.20a, N1) lo llenaría el modelo; aquí se escribe a mano porque se inserta sin él.
    return ['persona_tipo_id' => $fila['tipo_documento_id'], 'persona_numero' => $fila['numero_documento'], 'nombre_normalizado' => 'x', ...$fila];
}

/**
 * Inserta un periodo de rol, como lo haría un proceso que se salta el servicio.
 */
function insertarPeriodo(int $partyId, int $rolId, string $desde, ?string $hasta): void
{
    DB::table('party_roles')->insert([
        'party_id' => $partyId,
        'rol_party_id' => $rolId,
        'desde' => $desde,
        'hasta' => $hasta,
        'created_by' => app(UsuarioSistema::class)->id(),
    ]);
}

test('un documento no se repite, aunque se intente con otro nombre', function () {
    DB::table('parties')->insert(partyValida());

    expect(fn () => DB::table('parties')->insert(partyValida(['razon_social' => 'Otra razón social S.A.'])))
        ->toThrow(QueryException::class, 'parties_tipo_documento_id_numero_documento_unique');
});

test('el mismo número con otro tipo de documento es otra party', function () {
    DB::table('parties')->insert(partyValida(['numero_documento' => 'AB123456', 'tipo' => 'natural', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento_id' => idTipoDocumento('7')]));
    DB::table('parties')->insert(partyValida(['numero_documento' => 'AB123456', 'tipo' => 'natural', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento_id' => idTipoDocumento('4')]));

    expect(DB::table('parties')->where('numero_documento', 'AB123456')->count())->toBe(2);
});

test('la base rechaza una party imposible', function (array $cambios, string $restriccion) {
    expect(fn () => DB::table('parties')->insert(partyValida($cambios)))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'tipo desconocido' => [['tipo' => 'empresa'], 'parties_tipo_check'],
    'número con espacio al final' => [['numero_documento' => '20123456786 '], 'parties_numero_documento_check'],
    'número con guion' => [['numero_documento' => '20-123456786'], 'parties_numero_documento_check'],
    'número en minúsculas' => [['numero_documento' => 'ab123456'], 'parties_numero_documento_check'],
    'número vacío' => [['numero_documento' => ''], 'parties_numero_documento_check'],
    'razón social en blanco' => [['razon_social' => '   '], 'parties_razon_social_check'],
    'natural sin nombres' => [['tipo' => 'natural', 'apellidos' => 'Pérez'], 'parties_natural_nombres_check'],
    'natural sin apellidos' => [['tipo' => 'natural', 'nombres' => 'Ana'], 'parties_natural_nombres_check'],
    'jurídica con nombres' => [['nombres' => 'Ana'], 'parties_juridica_nombres_check'],
    'jurídica con apellidos' => [['apellidos' => 'Pérez'], 'parties_juridica_nombres_check'],
    'versión cero' => [['version' => 0], 'parties_version_check'],
]);

test('el cero inicial de un DNI se guarda y se lee igual', function () {
    $dni = Party::factory()->create(['numero_documento' => '01234567']);

    expect(DB::table('parties')->where('id', $dni->id)->value('numero_documento'))->toBe('01234567')
        ->and($dni->refresh()->numero_documento)->toBe('01234567');
});

test('dos periodos del mismo rol de una party no se cruzan', function (array $existente, array $nuevo) {
    $party = Party::factory()->juridica()->create();
    $rol = RolParty::factory()->create();
    insertarPeriodo($party->id, $rol->id, ...$existente);

    expect(fn () => insertarPeriodo($party->id, $rol->id, ...$nuevo))
        ->toThrow(QueryException::class, 'party_roles_sin_cruce');
})->with([
    'dos abiertos' => [['2026-01-01', null], ['2026-06-01', null]],
    'uno abierto antes de otro abierto' => [['2026-06-01', null], ['2026-01-01', null]],
    'hasta futuro frente a uno abierto que empieza antes de ese hasta' => [['2026-01-01', '2026-12-31'], ['2026-11-01', null]],
    'abierto frente a uno con hasta futuro' => [['2026-01-01', null], ['2027-01-01', '2027-12-31']],
    'empieza el mismo día en que el otro termina (hasta es vigente)' => [['2026-01-01', '2026-03-31'], ['2026-03-31', null]],
    'uno dentro del otro' => [['2026-01-01', '2026-12-31'], ['2026-04-01', '2026-04-30']],
    'mismas fechas' => [['2026-01-01', '2026-06-30'], ['2026-01-01', '2026-06-30']],
]);

test('periodos que no se cruzan sí conviven', function (array $existente, array $nuevo) {
    $party = Party::factory()->juridica()->create();
    $rol = RolParty::factory()->create();
    insertarPeriodo($party->id, $rol->id, ...$existente);
    insertarPeriodo($party->id, $rol->id, ...$nuevo);

    expect(PartyRol::query()->where('party_id', $party->id)->count())->toBe(2);
})->with([
    'reabrir al día siguiente del cierre' => [['2026-01-01', '2026-03-31'], ['2026-04-01', null]],
    'abierto después de un hasta futuro' => [['2026-01-01', '2026-12-31'], ['2027-01-01', null]],
    'un periodo cerrado antes de otro' => [['2026-06-01', '2026-12-31'], ['2025-01-01', '2025-12-31']],
]);

test('el cruce solo cuenta entre el mismo rol de la misma party', function () {
    $empresa = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();
    $cliente = RolParty::factory()->create();
    $proveedor = RolParty::factory()->create();

    insertarPeriodo($empresa->id, $cliente->id, '2026-01-01', null);
    insertarPeriodo($empresa->id, $proveedor->id, '2026-01-01', null);
    insertarPeriodo($otra->id, $cliente->id, '2026-01-01', null);

    expect(PartyRol::query()->count())->toBe(3);
});

test('un periodo no termina antes de empezar; puede durar un solo día', function () {
    $party = Party::factory()->juridica()->create();
    $rol = RolParty::factory()->create();
    insertarPeriodo($party->id, $rol->id, '2026-05-10', '2026-05-10');

    expect(fn () => insertarPeriodo($party->id, $rol->id, '2026-06-10', '2026-06-09'))
        ->toThrow(QueryException::class, 'party_roles_hasta_check');
});

test('una party con roles no se borra sin borrar antes sus roles', function () {
    $rol = PartyRol::factory()->create();

    expect(fn () => DB::table('parties')->where('id', $rol->party_id)->delete())
        ->toThrow(QueryException::class, 'party_roles_party_id_foreign');
});

test('los cambios de una party y de sus roles quedan en la bitácora, sin la versión', function () {
    $party = Party::factory()->juridica()->create();
    $party->forceFill(['razon_social' => 'Nuevo nombre S.A.C.', 'version' => 2])->save();
    $periodo = PartyRol::factory()->create(['party_id' => $party->id]);
    $periodo->update(['hasta' => '2026-12-31']);

    $cambio = AuditoriaCambio::query()->where('auditable_type', $party->getMorphClass())->where('auditable_id', $party->id)->latest('id')->firstOrFail();
    $cierre = AuditoriaCambio::query()->where('auditable_type', $periodo->getMorphClass())->where('auditable_id', $periodo->id)->latest('id')->firstOrFail();

    expect($cambio->evento)->toBe(EventoAuditoria::Actualizado)
        ->and($cambio->valores_nuevos)->toBe(['razon_social' => 'Nuevo nombre S.A.C.'])
        ->and($cierre->valores_anteriores)->toBe(['hasta' => null])
        ->and($cierre->valores_nuevos)->toHaveKey('hasta');
});

test('el modelo convierte el tipo de persona y las fechas del periodo', function () {
    $periodo = PartyRol::factory()->create(['desde' => '2026-02-01', 'hasta' => '2026-02-28']);

    expect($periodo->party->tipo)->toBe(TipoPersona::Natural)
        ->and($periodo->party->tipoDocumento->codigo)->toBe('1')
        ->and($periodo->desde->toDateString())->toBe('2026-02-01')
        ->and($periodo->hasta?->toDateString())->toBe('2026-02-28')
        ->and($periodo->party->roles)->toHaveCount(1);
});
