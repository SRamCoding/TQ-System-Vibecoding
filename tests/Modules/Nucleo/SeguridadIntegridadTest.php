<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * La base protege usuarios, organigrama, permisos por ámbito y cruces de funciones aunque se salte el servicio
 * (regla 5; plan 1.4, D4, D5, D7 y D9). Las filas que se ponen a prueba se escriben por query builder.
 */

/** La misma persona con DNI y con RUC 10 (`10` + DNI + dígito). */
function personaConDniYRuc10(): array
{
    $dni = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);
    $ruc10 = Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::RUC], ['nombre' => 'RUC', 'longitud' => 11])->id,
        'numero_documento' => '10012345670', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana',
    ]);

    return [$dni, $ruc10];
}

/**
 * @param  array<string, mixed>  $cambios
 */
function insertarUsuarioCrudo(array $cambios): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Ana', 'email' => fake()->unique()->safeEmail(), 'password' => 'x',
        'created_by' => app(UsuarioSistema::class)->id(), ...$cambios,
    ]);
}

// --- Usuarios (D4) ---

test('un usuario que no es de sistema necesita su persona', function () {
    expect(fn () => insertarUsuarioCrudo([]))->toThrow(QueryException::class, 'users_party_check');
});

test('el usuario copia la clave de persona de su ficha y la copia no se puede desviar', function () {
    [$dni] = personaConDniYRuc10();
    $usuario = User::factory()->create(['party_id' => $dni->id]);

    expect($usuario->persona_tipo_id)->toBe($dni->persona_tipo_id)
        ->and($usuario->persona_numero)->toBe('01234567')
        ->and(fn () => DB::table('users')->where('id', $usuario->id)->update(['persona_numero' => '99999999']))
        ->toThrow(QueryException::class, 'users_persona_foreign');
});

test('las tres columnas de la persona van juntas o ninguna', function () {
    [$dni] = personaConDniYRuc10();

    expect(fn () => insertarUsuarioCrudo(['party_id' => $dni->id]))->toThrow(QueryException::class, 'users_persona_completa_check');
});

test('una persona tiene una sola cuenta, aunque la segunda sea en su ficha RUC 10', function () {
    [$dni, $ruc10] = personaConDniYRuc10();
    User::factory()->create(['party_id' => $dni->id]);

    expect(fn () => User::factory()->create(['party_id' => $ruc10->id]))->toThrow(QueryException::class, 'users_persona_unique');
});

test('el usuario de sistema no tiene persona', function () {
    $sistema = User::query()->findOrFail(app(UsuarioSistema::class)->id());

    expect($sistema->party_id)->toBeNull()->and($sistema->persona_numero)->toBeNull();
});

// --- Organigrama (D5) ---

test('una unidad no depende de sí misma y su código es único', function () {
    $unidad = UnidadOrganizativa::factory()->create(['codigo' => 'GG']);

    // En su propio punto de guardado: la segunda comprobación necesita la transacción de la prueba sana.
    expect(fn () => DB::transaction(fn () => DB::table('unidades_organizativas')->where('id', $unidad->id)->update(['parent_id' => $unidad->id])))
        ->toThrow(QueryException::class, 'unidades_organizativas_parent_check')
        ->and(fn () => UnidadOrganizativa::factory()->create(['codigo' => 'GG']))
        ->toThrow(QueryException::class, 'unidades_organizativas_codigo_unique');
});

test('dos unidades hermanas no se llaman igual, tampoco en la raíz', function () {
    UnidadOrganizativa::factory()->create(['nombre' => 'Logística']);

    expect(fn () => UnidadOrganizativa::factory()->create(['nombre' => 'LOGÍSTICA']))
        ->toThrow(QueryException::class, 'unidades_organizativas_nombre_unique');
});

test('el tipo de unidad es uno de los cuatro', function () {
    $unidad = UnidadOrganizativa::factory()->create();

    expect(fn () => DB::table('unidades_organizativas')->where('id', $unidad->id)->update(['tipo' => 'division']))
        ->toThrow(QueryException::class, 'unidades_organizativas_tipo_check');
});

test('el puesto de jefatura es de la misma unidad', function () {
    $unidad = UnidadOrganizativa::factory()->create();
    $propio = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);
    $ajeno = Puesto::factory()->create();

    $unidad->update(['jefe_puesto_id' => $propio->id]);

    expect($unidad->refresh()->jefe_puesto_id)->toBe($propio->id)
        ->and(fn () => DB::table('unidades_organizativas')->where('id', $unidad->id)->update(['jefe_puesto_id' => $ajeno->id]))
        ->toThrow(QueryException::class, 'unidades_organizativas_jefe_foreign');
});

test('dos puestos de una unidad no se llaman igual', function () {
    $puesto = Puesto::factory()->create(['nombre' => 'Asistente']);

    expect(fn () => Puesto::factory()->create(['nombre' => 'asistente', 'unidad_organizativa_id' => $puesto->unidad_organizativa_id]))
        ->toThrow(QueryException::class, 'puestos_nombre_unique');
});

// --- Permisos por ámbito (D7) ---

test('el mismo rol en el mismo ámbito no se cruza en el tiempo; en otro ámbito sí puede', function () {
    $acotado = PermisoPorAmbito::factory()->create(['desde' => '2026-01-01', 'hasta' => '2026-06-30']);
    $repetir = fn (array $cambios) => PermisoPorAmbito::factory()->create([
        'user_id' => $acotado->user_id, 'rol_id' => $acotado->rol_id, 'ambito_id' => $acotado->ambito_id, ...$cambios,
    ]);

    $repetir(['desde' => '2026-07-01', 'hasta' => null]);
    PermisoPorAmbito::factory()->create(['user_id' => $acotado->user_id, 'rol_id' => $acotado->rol_id]);

    expect(fn () => $repetir(['desde' => '2026-06-30', 'hasta' => '2026-06-30']))
        ->toThrow(QueryException::class, 'permisos_por_ambito_sin_cruce');
});

test('hasta no es anterior a desde y el ámbito es de un tipo conocido', function () {
    expect(fn () => PermisoPorAmbito::factory()->create(['desde' => '2026-02-01', 'hasta' => '2026-01-31']))
        ->toThrow(QueryException::class, 'permisos_por_ambito_hasta_check')
        ->and(fn () => PermisoPorAmbito::factory()->create(['ambito_type' => 'party']))
        ->toThrow(QueryException::class, 'permisos_por_ambito_ambito_type_check');
});

// --- Incompatibilidades y cruces (D1, D9) ---

test('un par incompatible va ordenado y no se repite', function () {
    $a = Permission::findOrCreate('pagos.registrar');
    $b = Permission::findOrCreate('pagos.aprobar');
    IncompatibilidadPermisos::factory()->entre($a, $b)->create();

    expect(fn () => IncompatibilidadPermisos::factory()->entre($b, $a)->create())
        ->toThrow(QueryException::class, 'incompatibilidades_permisos_par_unique')
        ->and(fn () => IncompatibilidadPermisos::factory()->create(['permiso_a_id' => max($a->id, $b->id), 'permiso_b_id' => min($a->id, $b->id)]))
        ->toThrow(QueryException::class, 'incompatibilidades_permisos_orden_check');
});

test('una persona tiene un solo cruce abierto por par; cerrado, puede abrirse otro', function () {
    $cruce = CruceFunciones::factory()->create();
    $otro = fn () => CruceFunciones::factory()->create(['user_id' => $cruce->user_id, 'incompatibilidad_id' => $cruce->incompatibilidad_id, 'desde' => '2026-03-01']);

    expect($otro)->toThrow(QueryException::class, 'cruces_funciones_abierto_unique');

    $cruce->update(['hasta' => '2026-02-28']);

    expect($otro()->hasta)->toBeNull();
});

test('el responsable del control no es la persona del cruce', function () {
    $cruce = CruceFunciones::factory()->create();

    expect(fn () => ControlCruce::factory()->create(['cruce_id' => $cruce->id, 'responsable_user_id' => $cruce->user_id]))
        ->toThrow(QueryException::class, 'controles_cruce_responsable_check');
});

test('el control copia la persona de su cruce: no puede apuntar a otra', function () {
    $cruce = CruceFunciones::factory()->create();
    $otraPersona = User::factory()->create();

    expect(fn () => ControlCruce::factory()->create(['cruce_id' => $cruce->id, 'user_id' => $otraPersona->id]))
        ->toThrow(QueryException::class, 'controles_cruce_cruce_foreign');
});

test('un cruce tiene un solo control actual', function () {
    $control = ControlCruce::factory()->create();

    expect(fn () => ControlCruce::factory()->create(['cruce_id' => $control->cruce_id]))
        ->toThrow(QueryException::class, 'controles_cruce_actual_unique');

    $control->update(['es_actual' => false]);

    expect(ControlCruce::factory()->create(['cruce_id' => $control->cruce_id])->es_actual)->toBeTrue();
});

test('los alias nuevos se usan como ámbito', function () {
    $acotado = PermisoPorAmbito::factory()->create();

    expect($acotado->ambito)->toBeInstanceOf(UnidadOrganizativa::class)
        ->and(Role::query()->whereKey($acotado->rol_id)->exists())->toBeTrue();
});
