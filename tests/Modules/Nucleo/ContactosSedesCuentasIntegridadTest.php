<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * La base protege contactos, sedes y cuentas bancarias aunque se salte el servicio (regla 5; plan 1.3b, D1-D5).
 * Las filas que se ponen a prueba se insertan por query builder: así lo haría un proceso automático mal escrito.
 */

/**
 * @param  array<string, mixed>  $cambios
 */
function insertarContactoCrudo(int $partyId, array $cambios = []): int
{
    return DB::table('party_contactos')->insertGetId([
        'party_id' => $partyId,
        'nombres' => 'Luis',
        'apellidos' => 'Fernández Ruiz',
        'created_by' => app(UsuarioSistema::class)->id(),
        ...$cambios,
    ]);
}

/**
 * @param  array<string, mixed>  $cambios
 */
function insertarSedeCruda(int $partyId, array $cambios = []): int
{
    return DB::table('party_sedes')->insertGetId([
        'party_id' => $partyId,
        'nombre' => 'Almacén Chiclayo',
        'nombre_comparable' => 'almacen chiclayo',
        'direccion' => 'Av. Principal 123',
        'created_by' => app(UsuarioSistema::class)->id(),
        ...$cambios,
    ]);
}

/**
 * Copia la clave de persona del titular, como lo hace el modelo, salvo que la prueba pida otra.
 *
 * @param  array<string, mixed>  $cambios
 */
function insertarCuentaCruda(Party $titular, int $bancoId, array $cambios = []): int
{
    return DB::table('party_cuentas_bancarias')->insertGetId([
        'party_id' => $titular->id,
        'persona_tipo_id' => $titular->persona_tipo_id,
        'persona_numero' => $titular->persona_numero,
        'banco_party_id' => $bancoId,
        'numero_cuenta' => '1911234567012',
        'moneda_id' => idMonedaPrueba('PEN'),
        'created_by' => app(UsuarioSistema::class)->id(),
        ...$cambios,
    ]);
}

function idMonedaPrueba(string $codigo): int
{
    return Moneda::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'simbolo' => $codigo, 'decimales' => 2])->id;
}

/** La misma persona con DNI y con RUC 10 (`10` + DNI + dígito), con el cero inicial (plan 1.3, D9). */
function parejaDniRuc10(): array
{
    $dni = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);
    $ruc10 = Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::RUC], ['nombre' => 'RUC', 'longitud' => 11])->id,
        'numero_documento' => '10012345670', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana',
    ]);

    return [$dni, $ruc10];
}

// --- Clave de persona (5b) ---

test('la clave de persona de un RUC 10 es el DNI que lleva dentro; la de los demás, su propio documento', function () {
    [$dni, $ruc10] = parejaDniRuc10();
    $ruc20 = Party::factory()->juridica()->create();

    expect($ruc10->persona_tipo_id)->toBe($dni->tipo_documento_id)
        ->and($ruc10->persona_numero)->toBe('01234567')
        ->and($dni->persona_tipo_id)->toBe($dni->tipo_documento_id)
        ->and($dni->persona_numero)->toBe('01234567')
        ->and($ruc20->persona_tipo_id)->toBe($ruc20->tipo_documento_id)
        ->and($ruc20->persona_numero)->toBe($ruc20->numero_documento)
        ->and($ruc10->tipo)->toBe(TipoPersona::Natural);
});

test('la base no acepta una clave de persona que no salga del documento', function () {
    $ruc20 = Party::factory()->juridica()->create();

    expect(fn () => DB::table('parties')->where('id', $ruc20->id)->update(['persona_numero' => '01234567']))
        ->toThrow(QueryException::class, 'parties_clave_persona_check');
});

test('un DNI y un carné de extranjería con el mismo número no comparten clave', function () {
    $dni = Party::factory()->create(['numero_documento' => '12345678']);
    $ce = Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => '4'], ['nombre' => 'Carné de extranjería'])->id,
        'numero_documento' => '12345678',
    ]);
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda($dni, $banco->id);

    expect(fn () => insertarCuentaCruda($ce, $banco->id))->toThrow(QueryException::class, 'party_cuentas_bancarias_numero_persona_excl');
});

// --- Contactos (D1, D2) ---

test('una party tiene un solo contacto principal; otra party tiene el suyo', function () {
    $party = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();
    insertarContactoCrudo($party->id, ['es_principal' => true]);
    insertarContactoCrudo($otra->id, ['es_principal' => true]);

    expect(fn () => insertarContactoCrudo($party->id, ['es_principal' => true, 'nombres' => 'Carmen']))
        ->toThrow(QueryException::class, 'party_contactos_un_principal_unique');
});

test('la base rechaza un contacto imposible', function (array $cambios, string $restriccion) {
    $party = Party::factory()->juridica()->create();

    expect(fn () => insertarContactoCrudo($party->id, $cambios))->toThrow(QueryException::class, $restriccion);
})->with([
    'principal con el vínculo cerrado' => [['es_principal' => true, 'hasta' => '2026-07-31'], 'party_contactos_principal_vigente_check'],
    'termina antes de empezar' => [['desde' => '2026-08-01', 'hasta' => '2026-07-31'], 'party_contactos_hasta_check'],
    'nombres en blanco' => [['nombres' => '  '], 'party_contactos_nombres_check'],
    'apellidos en blanco' => [['apellidos' => ''], 'party_contactos_nombres_check'],
]);

test('las fechas del vínculo son opcionales y puede durar un solo día', function () {
    $party = Party::factory()->juridica()->create();
    insertarContactoCrudo($party->id, ['hasta' => '2026-07-31']);
    insertarContactoCrudo($party->id, ['desde' => '2026-07-31', 'hasta' => '2026-07-31']);
    insertarContactoCrudo($party->id, ['desde' => '2026-01-01']);

    expect(PartyContacto::query()->where('party_id', $party->id)->count())->toBe(3);
});

test('un vínculo cerrado continúa en un solo lugar', function () {
    $pimentel = Party::factory()->juridica()->create();
    $gore = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();
    $cerrado = insertarContactoCrudo($pimentel->id, ['hasta' => '2026-07-31']);
    insertarContactoCrudo($gore->id, ['contacto_anterior_id' => $cerrado]);

    expect(fn () => insertarContactoCrudo($otra->id, ['contacto_anterior_id' => $cerrado]))
        ->toThrow(QueryException::class, 'party_contactos_contacto_anterior_id_unique');
});

test('un vínculo no continúa en sí mismo', function () {
    $contacto = insertarContactoCrudo(Party::factory()->juridica()->create()->id);

    expect(fn () => DB::table('party_contactos')->where('id', $contacto)->update(['contacto_anterior_id' => $contacto]))
        ->toThrow(QueryException::class, 'party_contactos_anterior_check');
});

test('el vínculo anterior y el siguiente se leen desde el modelo', function () {
    $cerrado = PartyContacto::factory()->cerrado()->create();
    $nuevo = PartyContacto::factory()->create(['contacto_anterior_id' => $cerrado->id, 'desde' => '2026-08-01']);

    expect($nuevo->anterior?->id)->toBe($cerrado->id)
        ->and($cerrado->siguiente?->id)->toBe($nuevo->id)
        ->and($nuevo->desde?->toDateString())->toBe('2026-08-01')
        ->and($cerrado->hasta?->toDateString())->toBe('2026-07-31');
});

// --- Sedes (D3, D4) ---

test('una sede no apunta al contacto de otra party', function () {
    $pimentel = Party::factory()->juridica()->create();
    $gore = Party::factory()->juridica()->create();
    $deGore = insertarContactoCrudo($gore->id);
    $dePimentel = insertarContactoCrudo($pimentel->id);

    insertarSedeCruda($pimentel->id, ['contacto_id' => $dePimentel]);

    expect(fn () => insertarSedeCruda($pimentel->id, ['nombre' => 'Comisaría', 'nombre_comparable' => 'comisaria', 'contacto_id' => $deGore]))
        ->toThrow(QueryException::class, 'party_sedes_contacto_foreign');
});

test('el nombre de una sede no se repite entre las activas, sin mayúsculas ni acentos', function () {
    $party = Party::factory()->juridica()->create();
    insertarSedeCruda($party->id);

    expect(fn () => insertarSedeCruda($party->id, ['nombre' => 'ALMACEN chiclayo']))
        ->toThrow(QueryException::class, 'party_sedes_nombre_activa_unique');
});

test('una sede desactivada no bloquea su nombre, y otra party puede usarlo', function () {
    $party = Party::factory()->juridica()->create();
    insertarSedeCruda($party->id, ['es_activa' => false]);
    insertarSedeCruda($party->id);
    insertarSedeCruda(Party::factory()->juridica()->create()->id);

    expect(DB::table('party_sedes')->where('nombre_comparable', 'almacen chiclayo')->count())->toBe(3);
});

test('la base rechaza una sede imposible', function (array $cambios, string $restriccion) {
    $party = Party::factory()->juridica()->create();

    expect(fn () => insertarSedeCruda($party->id, $cambios))->toThrow(QueryException::class, $restriccion);
})->with([
    'nombre comparable que no coincide' => [['nombre_comparable' => 'otro nombre'], 'party_sedes_nombre_comparable_check'],
    'comparable con acentos' => [['nombre_comparable' => 'almacén chiclayo'], 'party_sedes_nombre_comparable_check'],
    'nombre en blanco' => [['nombre' => ' ', 'nombre_comparable' => ' '], 'party_sedes_nombre_check'],
    'dirección en blanco' => [['direccion' => ''], 'party_sedes_nombre_check'],
    'solo latitud' => [['latitud' => '-6.7714000'], 'party_sedes_coordenadas_check'],
    'latitud fuera de rango' => [['latitud' => '-91', 'longitud' => '-79.8409000'], 'party_sedes_coordenadas_check'],
    'longitud fuera de rango' => [['latitud' => '-6.7714000', 'longitud' => '181'], 'party_sedes_coordenadas_check'],
    'anexo con letras' => [['codigo_establecimiento' => '00A1'], 'party_sedes_codigo_establecimiento_check'],
    'anexo de tres dígitos' => [['codigo_establecimiento' => '001'], 'party_sedes_codigo_establecimiento_check'],
]);

test('el anexo SUNAT no se repite entre las sedes activas de una party', function () {
    $party = Party::factory()->juridica()->create();
    insertarSedeCruda($party->id, ['codigo_establecimiento' => '0001']);

    expect(fn () => insertarSedeCruda($party->id, ['nombre' => 'Comisaría', 'nombre_comparable' => 'comisaria', 'codigo_establecimiento' => '0001']))
        ->toThrow(QueryException::class, 'party_sedes_codigo_establecimiento_unique');
});

test('el modelo calcula el nombre comparable y guarda las coordenadas como texto', function () {
    $sede = PartySede::factory()->create(['nombre' => 'Almacén Ñandú', 'latitud' => '-6.7714', 'longitud' => '-79.8409']);
    $sede->refresh();

    expect($sede->nombre_comparable)->toBe('almacen nandu')
        ->and($sede->latitud)->toBe('-6.7714000')
        ->and($sede->longitud)->toBe('-79.8409000');

    $sede->update(['nombre' => 'Comisaría Pimentel']);

    expect($sede->refresh()->nombre_comparable)->toBe('comisaria pimentel');
});

test('el nombre comparable quita ñ, ü y tildes también en mayúsculas, y su CHECK lo acepta', function (string $nombre, string $comparable) {
    // Lo calcula la base al guardar (`PartySede::comparable`): si PHP y la base difirieran, el CHECK lo rechazaría.
    $sede = PartySede::factory()->create(['nombre' => $nombre]);

    expect($sede->refresh()->nombre_comparable)->toBe($comparable)
        ->and(PartySede::comparable($nombre))->toBe($comparable);
})->with([
    'eñe minúscula y mayúscula' => ['Almacén Ñaña', 'almacen nana'],
    'tilde en mayúscula' => ['PIÚRA', 'piura'],
    'diéresis' => ['Pingüino Güemes', 'pinguino guemes'],
    'diéresis en mayúscula' => ['AGÜERO', 'aguero'],
    'todas las vocales con tilde' => ['ÁÉÍÓÚ áéíóú', 'aeiou aeiou'],
]);

test('dos sedes que solo se distinguen por ñ, ü o tildes en mayúscula chocan', function (string $primera, string $segunda) {
    $party = Party::factory()->juridica()->create();
    PartySede::factory()->create(['party_id' => $party->id, 'nombre' => $primera]);

    expect(fn () => PartySede::factory()->create(['party_id' => $party->id, 'nombre' => $segunda]))
        ->toThrow(QueryException::class, 'party_sedes_nombre_activa_unique');
})->with([
    ['Almacén Ñaña', 'ALMACEN NANA'],
    ['Piura', 'PIÚRA'],
    ['Agüero', 'AGUERO'],
]);

// --- Cuentas bancarias (D2, D5) ---

test('la misma cuenta en el mismo banco no está en dos personas distintas', function () {
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id);

    expect(fn () => insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_numero_persona');
});

test('la misma cuenta sí está en el DNI y el RUC 10 de una persona, también desactivada', function () {
    [$dni, $ruc10] = parejaDniRuc10();
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda($dni, $banco->id, ['es_activa' => false]);
    insertarCuentaCruda($ruc10, $banco->id);

    expect(PartyCuentaBancaria::query()->where('numero_cuenta', '1911234567012')->count())->toBe(2);
});

test('una cuenta desactivada también bloquea su número para otra persona', function () {
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id, ['es_activa' => false]);

    expect(fn () => insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_numero_persona');
});

test('el mismo número en otro banco es otra cuenta', function () {
    $titular = Party::factory()->juridica()->create();
    insertarCuentaCruda($titular, Party::factory()->juridica()->create()->id);
    insertarCuentaCruda(Party::factory()->juridica()->create(), Party::factory()->juridica()->create()->id);

    expect(PartyCuentaBancaria::query()->count())->toBe(2);
});

test('una party no tiene dos veces la misma cuenta', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda($titular, $banco->id);

    expect(fn () => insertarCuentaCruda($titular, $banco->id, ['moneda_id' => idMonedaPrueba('USD')]))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_cuenta_unique');
});

test('el mismo CCI no está en dos personas distintas', function () {
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id, ['cci' => '00219100123456701255']);

    expect(fn () => insertarCuentaCruda(Party::factory()->juridica()->create(), $banco->id, ['numero_cuenta' => '1', 'cci' => '00219100123456701255']))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_cci_persona');
});

test('el mismo CCI no está dos veces en una party', function () {
    $banco = Party::factory()->juridica()->create();
    $titular = Party::factory()->juridica()->create();
    insertarCuentaCruda($titular, $banco->id, ['cci' => '00219100123456701255']);

    expect(fn () => insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '2', 'cci' => '00219100123456701255']))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_cci_unique');
});

test('la cuenta no puede llevar una clave de persona distinta de la de su titular', function () {
    $titular = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();

    expect(fn () => insertarCuentaCruda($titular, Party::factory()->juridica()->create()->id, ['persona_numero' => $otra->persona_numero]))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_titular_foreign');
});

test('la base rechaza una cuenta imposible', function (array $cambios, string $restriccion) {
    $titular = Party::factory()->juridica()->create();

    expect(fn () => insertarCuentaCruda($titular, Party::factory()->juridica()->create()->id, $cambios))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'número con guiones' => [['numero_cuenta' => '191-1234567-0-12'], 'party_cuentas_bancarias_numero_check'],
    'número con espacios' => [['numero_cuenta' => '191 1234567'], 'party_cuentas_bancarias_numero_check'],
    'CCI de 19 dígitos' => [['cci' => '0021910012345670125'], 'party_cuentas_bancarias_cci_check'],
    'CCI con letras' => [['cci' => '0021910012345670125X'], 'party_cuentas_bancarias_cci_check'],
    'principal desactivada' => [['es_principal' => true, 'es_activa' => false], 'party_cuentas_bancarias_principal_check'],
    'detracciones como principal' => [['es_principal' => true, 'es_detraccion' => true], 'party_cuentas_bancarias_principal_check'],
]);

test('una cuenta principal por moneda', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda($titular, $banco->id, ['es_principal' => true]);
    insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '2', 'es_principal' => true, 'moneda_id' => idMonedaPrueba('USD')]);

    expect(fn () => insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '3', 'es_principal' => true]))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_principal_unique');
});

test('una sola cuenta de detracciones activa por party', function () {
    $titular = Party::factory()->juridica()->create();
    $banco = Party::factory()->juridica()->create();
    insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '1', 'es_detraccion' => true, 'es_activa' => false]);
    insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '2', 'es_detraccion' => true]);

    expect(fn () => insertarCuentaCruda($titular, $banco->id, ['numero_cuenta' => '3', 'es_detraccion' => true]))
        ->toThrow(QueryException::class, 'party_cuentas_bancarias_detraccion_unique');
});

test('el modelo copia la clave de persona del titular y normaliza el número', function () {
    [, $ruc10] = parejaDniRuc10();
    $cuenta = PartyCuentaBancaria::factory()->create(['party_id' => $ruc10->id]);

    expect($cuenta->persona_numero)->toBe('01234567')
        ->and($cuenta->persona_tipo_id)->toBe($ruc10->persona_tipo_id)
        ->and(PartyCuentaBancaria::normalizarNumero('191-1234567-0-12'))->toBe('1911234567012')
        ->and(PartyCuentaBancaria::normalizarNumero(' 191 1234567.0 12 '))->toBe('1911234567012');
});

test('contactos, sedes y cuentas quedan en la bitácora, sin la versión ni los campos calculados', function () {
    $contacto = PartyContacto::factory()->create();
    $sede = PartySede::factory()->create();
    $cuenta = PartyCuentaBancaria::factory()->create();
    $contacto->update(['telefono' => '944 555 999']);

    $campos = fn (string $tipo, int $id): array => AuditoriaCambio::query()
        ->where('auditable_type', $tipo)->where('auditable_id', $id)->get()
        ->flatMap(fn (AuditoriaCambio $c): array => array_keys($c->valores_nuevos ?? []))->unique()->values()->all();

    expect($campos($contacto->getMorphClass(), $contacto->id))->toContain('telefono')->not->toContain('version')
        ->and($campos($sede->getMorphClass(), $sede->id))->toContain('nombre')->not->toContain('nombre_comparable')
        ->and($campos($cuenta->getMorphClass(), $cuenta->id))->toContain('numero_cuenta')->not->toContain('persona_numero');
});
