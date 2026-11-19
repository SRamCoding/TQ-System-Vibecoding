<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use App\Modules\Nucleo\Parties\ReglasFichaCompleta;
use App\Modules\Nucleo\Services\CalidadPartiesService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4m, D2 y D3: qué ficha está incompleta y por qué, y cuáles podrían estar duplicadas. Cada regla se prueba
 * sola: la ficha que la incumple aparece con su motivo y, completándola, sale.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** Una empresa con todo lo de cualquier ficha: teléfono, correo y dirección fiscal con distrito. */
function empresaCompleta(string $tipo = ''): Party
{
    $empresa = Party::factory()->juridica()->create([
        'telefono' => '074 123456', 'email' => 'contacto@ejemplo.test',
        'direccion_fiscal' => 'Av. Ejemplo 123', 'ubigeo_id' => Ubigeo::factory()->create()->id,
    ]);

    if ($tipo !== '') {
        app(PartiesService::class)->asignarRol($empresa->id, $tipo, CarbonImmutable::parse('2026-01-01'));
    }

    return $empresa;
}

/** @return list<string> las reglas que la ficha incumple */
function motivosDe(Party $ficha): array
{
    $motivos = [];

    foreach (ReglasFichaCompleta::todas() as $clave => [, $regla]) {
        if (Party::query()->whereKey($ficha->id)->where(fn ($q) => $regla($q, Calendario::hoy()))->exists()) {
            $motivos[] = $clave;
        }
    }

    return $motivos;
}

test('una empresa con contacto, teléfono y dirección no tiene nada que completar', function () {
    expect(motivosDe(empresaCompleta()))->toBe([])
        ->and(app(CalidadPartiesService::class)->cuantasIncompletas(true))->toBe(0);
});

test('sin teléfono ni correo propios ni de un contacto vigente (si tiene algún tipo que no sea colaborador)', function () {
    $ficha = empresaCompleta('aliado');
    $ficha->forceFill(['telefono' => null, 'email' => ''])->save();
    expect(motivosDe($ficha))->toBe(['sin_medio_de_contacto']);

    // Un contacto cerrado no cuenta; uno vigente con correo, sí.
    PartyContacto::factory()->create(['party_id' => $ficha->id, 'telefono' => null, 'hasta' => '2026-01-31']);
    expect(motivosDe($ficha))->toBe(['sin_medio_de_contacto']);
    PartyContacto::factory()->create(['party_id' => $ficha->id, 'telefono' => null]);
    expect(motivosDe($ficha))->toBe([]);

    // Sin ningún tipo (la ficha de una cuenta de usuario, por ejemplo), no se avisa.
    $sinTipo = empresaCompleta();
    $sinTipo->forceFill(['telefono' => null, 'email' => null])->save();
    expect(motivosDe($sinTipo))->toBe([]);
});

test('con RUC, sin dirección fiscal o sin distrito', function () {
    $ficha = empresaCompleta();
    $ficha->forceFill(['ubigeo_id' => null])->save();
    expect(motivosDe($ficha))->toBe(['sin_direccion_fiscal']);

    $ficha->forceFill(['ubigeo_id' => Ubigeo::factory()->create()->id, 'direccion_fiscal' => ''])->save();
    expect(motivosDe($ficha))->toBe(['sin_direccion_fiscal']);

    // Una persona con DNI no necesita dirección fiscal.
    expect(motivosDe(Party::factory()->create(['telefono' => '999 111 222'])))->toBe([]);
});

test('proveedor sin cuenta bancaria activa y sin contacto', function () {
    $proveedor = empresaCompleta('proveedor');
    expect(motivosDe($proveedor))->toBe(['proveedor_sin_cuenta', 'proveedor_sin_contacto']);

    PartyCuentaBancaria::factory()->create(['party_id' => $proveedor->id, 'es_activa' => false]);
    expect(motivosDe($proveedor))->toContain('proveedor_sin_cuenta');

    PartyCuentaBancaria::factory()->create(['party_id' => $proveedor->id]);
    PartyContacto::factory()->create(['party_id' => $proveedor->id]);
    expect(motivosDe($proveedor))->toBe([]);
});

test('cliente y entidad sin contacto vigente', function () {
    $cliente = empresaCompleta('cliente');
    $entidad = empresaCompleta('entidad');

    expect(motivosDe($cliente))->toBe(['cliente_sin_contacto'])
        ->and(motivosDe($entidad))->toBe(['entidad_sin_contacto']);

    PartyContacto::factory()->create(['party_id' => $cliente->id]);
    expect(motivosDe($cliente))->toBe([]);
});

test('colaborador sin teléfono ni correo propios, aunque tenga contactos', function () {
    $colaborador = Party::factory()->create();
    app(PartiesService::class)->asignarRol($colaborador->id, 'colaborador', CarbonImmutable::parse('2026-01-01'));
    PartyContacto::factory()->create(['party_id' => $colaborador->id]);

    expect(motivosDe($colaborador))->toBe(['colaborador_sin_medio']);

    $colaborador->forceFill(['email' => 'persona@ejemplo.test'])->save();
    expect(motivosDe($colaborador))->toBe([]);
});

test('el resumen cuenta por motivo, las fichas inactivas no cuentan y la consulta trae cada una una sola vez', function () {
    empresaCompleta('proveedor');
    empresaCompleta('proveedor');
    empresaCompleta('cliente');
    $inactiva = empresaCompleta('cliente');
    $inactiva->forceFill(['es_activo' => false])->save();

    $resumen = app(CalidadPartiesService::class)->resumenIncompletas(true);

    expect($resumen['proveedor_sin_cuenta'])->toBe(['texto' => 'proveedores sin cuenta bancaria', 'cantidad' => 2])
        ->and($resumen['cliente_sin_contacto']['cantidad'])->toBe(1)
        ->and(app(CalidadPartiesService::class)->cuantasIncompletas(true))->toBe(3);
});

test('posibles duplicados: el mismo nombre sin acentos ni forma societaria, y el DNI dentro de un RUC 10', function () {
    expect(PosiblesDuplicados::normalizar('Ferretería El Norte S.A.C.'))->toBe('ferreteria el norte')
        ->and(PosiblesDuplicados::normalizar('FERRETERIA  EL NORTE SAC'))->toBe('ferreteria el norte')
        ->and(PosiblesDuplicados::normalizar('Comercial Andina E.I.R.L.'))->toBe('comercial andina');

    $ids = PosiblesDuplicados::ids([
        ['id' => 1, 'nombre' => 'Ferretería El Norte S.A.C.', 'tipo_documento' => '6', 'numero' => '20111111111'],
        ['id' => 2, 'nombre' => 'FERRETERIA EL NORTE SAC', 'tipo_documento' => '6', 'numero' => '20222222222'],
        ['id' => 3, 'nombre' => 'Pérez Gómez Ana', 'tipo_documento' => '1', 'numero' => '45678912'],
        ['id' => 4, 'nombre' => 'Ana Pérez (con RUC)', 'tipo_documento' => '6', 'numero' => '10456789125'],
        ['id' => 5, 'nombre' => 'Otra Empresa S.A.', 'tipo_documento' => '6', 'numero' => '20333333333'],
    ]);

    expect($ids)->toBe([1, 2, 3, 4]);
});

test('el servicio solo mira fichas activas para los duplicados', function () {
    $una = Party::factory()->juridica()->create(['razon_social' => 'Ferretería El Norte S.A.C.']);
    $otra = Party::factory()->juridica()->create(['razon_social' => 'FERRETERIA EL NORTE SAC']);
    Party::factory()->juridica()->inactiva()->create(['razon_social' => 'Distinta S.A.C.']);
    Party::factory()->juridica()->inactiva()->create(['razon_social' => 'DISTINTA SAC']);

    expect(app(CalidadPartiesService::class)->idsPosiblesDuplicados())->toBe([$una->id, $otra->id]);
});

test('un tipo vencido o que todavía no empieza no cuenta', function () {
    $vencido = empresaCompleta('cliente');
    PartyRol::query()->where('party_id', $vencido->id)->update(['hasta' => '2026-02-01']);
    $futuro = empresaCompleta();
    app(PartiesService::class)->asignarRol($futuro->id, 'cliente', CarbonImmutable::parse('2099-01-01'));

    expect(motivosDe($vencido))->toBe([])
        ->and(motivosDe($futuro))->toBe([]);
});

test('un contacto vigente con teléfono y correo vacíos no salva; uno cerrado tampoco salva a un cliente', function () {
    $ficha = empresaCompleta('aliado');
    $ficha->forceFill(['telefono' => '', 'email' => ''])->save();
    PartyContacto::factory()->create(['party_id' => $ficha->id, 'telefono' => '', 'email' => '']);
    expect(motivosDe($ficha))->toBe(['sin_medio_de_contacto']);

    $cliente = empresaCompleta('cliente');
    PartyContacto::factory()->create(['party_id' => $cliente->id, 'hasta' => '2026-01-31']);
    expect(motivosDe($cliente))->toBe(['cliente_sin_contacto']);
});

test('sin permiso de datos bancarios, la regla de la cuenta no se cuenta ni filtra', function () {
    empresaCompleta('proveedor');

    expect(app(CalidadPartiesService::class)->resumenIncompletas(false))->not->toHaveKey('proveedor_sin_cuenta')
        ->and(app(CalidadPartiesService::class)->resumenIncompletas(true))->toHaveKey('proveedor_sin_cuenta');
});

test('últimos cambios: solo la ficha misma (no sus contactos ni cuentas) y, si se borró, sin enlace y con su nombre', function () {
    test()->actingAs(User::factory()->create());
    $ficha = empresaCompleta('proveedor');
    PartyContacto::factory()->create(['party_id' => $ficha->id]);
    PartyCuentaBancaria::factory()->create(['party_id' => $ficha->id]);

    $cambios = app(CalidadPartiesService::class)->ultimosCambios(50);
    $tipos = DB::table('auditoria_cambios')->distinct()->pluck('auditable_type')->all();

    expect($tipos)->toContain((new PartyContacto)->getMorphClass())
        ->and(collect($cambios)->pluck('ficha')->unique()->all())->each->not->toContain('@')
        ->and(collect($cambios)->every(fn (array $c): bool => is_int($c['party_id']) || $c['party_id'] === null))->toBeTrue()
        ->and(count($cambios))->toBe(DB::table('auditoria_cambios')->where('auditable_type', (new Party)->getMorphClass())->count());

    $borrable = Party::factory()->juridica()->create(['razon_social' => 'Fantasma S.A.C.']);
    app(PartiesService::class)->borrar($borrable->id, $borrable->refresh()->version);
    $ultimo = app(CalidadPartiesService::class)->ultimosCambios(1)[0];

    expect($ultimo['ficha'])->toBe('Fantasma S.A.C.')
        ->and($ultimo['party_id'])->toBeNull()
        ->and($ultimo['accion'])->toBe('Borró');
});

test('un guion u otro signo separa palabras al comparar nombres', function () {
    expect(PosiblesDuplicados::normalizar('Hermanos-López S.R.L.'))->toBe(PosiblesDuplicados::normalizar('Hermanos López SRL'));
});
