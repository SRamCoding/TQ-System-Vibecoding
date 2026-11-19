<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\ExcesoDePartidaRechazado;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\SerieDocumento;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\Costuras\ArbolDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\EmbudoDePrueba;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\ComprometerLineasDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\DefinicionOrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\OrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\ServicioOrdenDePrueba;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * Concurrencia real (plan 1.6): varios procesos a la vez, cada uno con su conexión, contra techquk_concurrencia. Cubre
 * la numeración (sin huecos ni duplicados), `PartiesService::obtenerOCrear` (una sola party) y dos transiciones a la vez
 * sobre un documento (una gana, la otra falla limpia) y la última firma de una aprobación dada dos veces a la vez (plan
 * 1.7: la transición corre una sola vez), y elegir la ficha de la empresa mientras otro la desactiva (plan 1.15: nunca
 * queda elegida una inactiva), y aprobar el árbol de partidas mientras otro cambia un monto (plan 2.1, D5: nunca queda
 * aprobado un árbol que no cuadra), y dos personas creando la misma cuenta (plan 2.20, D2: una sola, la otra lee «ya
 * existe»), y crear una oportunidad mientras otro desactiva su cuenta y dos ganadas a la vez de la misma cuenta (plan 2.21,
 * D6). Las demás pruebas corren dentro de una transacción y no pueden ver una carrera.
 */

// En una copia de trabajo (worktree), su propia base: la elige tests/bootstrap.php.
define('BASE_CONCURRENCIA', getenv('TECHQUK_BASE_CONCURRENCIA') ?: 'techquk_concurrencia');

/** Apunta la conexión a la base de concurrencia; la primera vez del proceso, la crea y la reconstruye. */
function baseDeConcurrencia(): void
{
    static $preparada = false;

    $existe = DB::selectOne('SELECT 1 AS si FROM pg_database WHERE datname = ?', [BASE_CONCURRENCIA]) !== null;
    if (! $existe) {
        try {
            DB::statement('CREATE DATABASE '.BASE_CONCURRENCIA);
        } catch (QueryException $e) {
            throw new RuntimeException('La prueba de concurrencia necesita la base '.BASE_CONCURRENCIA.': créala una vez o da permiso CREATEDB al usuario de PostgreSQL.', previous: $e);
        }
    }

    test()->baseOriginal = (string) config('database.connections.pgsql.database');
    config(['database.connections.pgsql.database' => BASE_CONCURRENCIA]);
    DB::purge('pgsql');

    // La base a la que de verdad se conectó, no la de la configuración.
    if (DB::selectOne('SELECT current_database() AS base')->base !== BASE_CONCURRENCIA) {
        throw new RuntimeException('La prueba de concurrencia solo reconstruye '.BASE_CONCURRENCIA.'.');
    }

    if (! $preparada) {
        Artisan::call('migrate:fresh', ['--force' => true]);
        config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
        Artisan::call('db:seed', ['--class' => NucleoSeeder::class, '--force' => true]);
        DocumentoPrueba::crearTabla();
        $preparada = true;
    }

    DocumentoPrueba::crearMorphMap();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
    // Plan 2.2: la orden de prueba con líneas de costo (sus tablas, una sola vez en esta base).
    if (! Schema::hasTable('ordenes_prueba')) {
        OrdenDePrueba::crearTablas();
    }
    OrdenDePrueba::crearMorphMap();
    app(RegistroDocumentos::class)->efecto('erp.presupuestos.prueba.comprometer', ComprometerLineasDePrueba::class);
    app(RegistroDocumentos::class)->registrar(new DefinicionOrdenDePrueba);
    app(SincronizadorEstados::class)->sincronizar();
    Permission::findOrCreate('erp.presupuestos.orden_prueba.enviar');
}

/**
 * Una orden de prueba enviada, con una línea por partida en el orden dado (plan 2.2), y quien la aprueba: la jefatura
 * de su unidad, con la regla de monto de la orden.
 *
 * @param  list<array{0: int, 1: string}>  $lineas  [partida, monto]
 */
function ordenEnviada(UnidadOrganizativa $unidad, array $lineas, int $proyectoId): OrdenDePrueba
{
    $pide = User::factory()->create()->givePermissionTo('erp.presupuestos.orden_prueba.enviar');
    $servicio = new ServicioOrdenDePrueba;
    $orden = $servicio->crear($unidad->id, $pide);

    foreach ($lineas as [$partida, $monto]) {
        $servicio->agregarLinea($orden->id, 'Línea', $monto, 'PEN', CarbonImmutable::parse('2026-11-12'), new EleccionImputacion(TipoImputacion::Partida, $proyectoId, $partida));
    }

    $servicio->enviar($orden->id, $pide);

    return $orden->refresh();
}

/** Una unidad con su jefatura ocupada y la regla de la orden de prueba que pide su firma. */
function unidadQueAprueba(): UnidadOrganizativa
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => 'Obras '.fake()->unique()->numerify('####'), 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => "Jefe de {$unidad->nombre}", 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    User::factory()->create(['puesto_id' => $puesto->id]);

    if (! ReglaAprobacion::query()->where('documento_type', 'orden_prueba')->exists()) {
        app(AprobacionesService::class)->crearRegla(new DatosRegla('Orden de prueba: su jefatura', 'orden_prueba', 'aprobar', null, null, null, ['documento']));
    }

    return $unidad->refresh();
}

/**
 * Lanza los procesos a la vez (parten en el mismo instante) y devuelve lo que imprimió cada uno.
 *
 * @param  list<list<string>>  $argumentos
 * @return list<array<string, mixed>>
 */
function aLaVez(string $modo, array $argumentos): array
{
    return distintosALaVez(array_map(fn (array $mas): array => [$modo, $mas], $argumentos));
}

/**
 * Como aLaVez, pero cada proceso con su modo (plan 2.1: aprobar el árbol y cambiar un monto a la vez).
 *
 * @param  list<array{0: string, 1: list<string>}>  $modosYArgumentos
 * @return list<array<string, mixed>>
 */
function distintosALaVez(array $modosYArgumentos): array
{
    $partida = (string) ((int) (microtime(true) * 1_000_000) + 2_500_000);
    $procesos = array_map(function (array $par) use ($partida): Process {
        [$modo, $mas] = $par;
        $proceso = new Process([PHP_BINARY, base_path('tests/Concurrencia/proceso.php'), $partida, $modo, ...$mas], base_path(), ['DB_DATABASE' => BASE_CONCURRENCIA, 'APP_ENV' => 'testing']);
        $proceso->setTimeout(60);
        $proceso->start();

        return $proceso;
    }, $modosYArgumentos);

    return array_map(function (Process $proceso): array {
        $proceso->wait();
        $ultima = collect(explode("\n", trim($proceso->getOutput())))->last();
        $resultado = json_decode((string) $ultima, true);

        if (! is_array($resultado)) {
            throw new RuntimeException('Un proceso no respondió: '.$proceso->getOutput().$proceso->getErrorOutput());
        }

        return $resultado;
    }, $procesos);
}

beforeEach(function () {
    baseDeConcurrencia();
    DB::table('series_documento')->delete();
    // Los proyectos de pruebas anteriores siguen en esta base (no hay RefreshDatabase): su correlativo sigue desde ahí.
    $proyectos = DB::table('proyectos')->count();
    if ($proyectos > 0) {
        DB::table('series_documento')->insert(['documento_type' => 'proyecto', 'serie' => 'PRY', 'ambito' => '', 'periodo' => (string) now('America/Lima')->year, 'ultimo_numero' => $proyectos, 'created_by' => User::query()->where('es_sistema', true)->value('id'), 'created_at' => now()]);
    }
    // Igual con las cotizaciones (plan 2.22b).
    $cotizaciones = DB::table('cotizaciones')->distinct()->count('numero');
    if ($cotizaciones > 0) {
        DB::table('series_documento')->insert(['documento_type' => 'cotizacion', 'serie' => 'COT', 'ambito' => '', 'periodo' => (string) now('America/Lima')->year, 'ultimo_numero' => $cotizaciones, 'created_by' => User::query()->where('es_sistema', true)->value('id'), 'created_at' => now()]);
    }
    // Igual con las oportunidades (plan 2.21): su número OP sigue desde las que dejaron otras pruebas.
    $oportunidades = DB::table('oportunidades')->count();
    if ($oportunidades > 0) {
        DB::table('series_documento')->insert(['documento_type' => 'oportunidad', 'serie' => 'OP', 'ambito' => '', 'periodo' => (string) now('America/Lima')->year, 'ultimo_numero' => $oportunidades, 'created_by' => User::query()->where('es_sistema', true)->value('id'), 'created_at' => now()]);
    }
});

afterEach(function () {
    config(['database.connections.pgsql.database' => test()->baseOriginal]);
    DB::purge('pgsql');
});

test('ocho procesos piden número a la vez: ocho números seguidos, sin huecos ni duplicados', function () {
    $resultados = aLaVez('numero', array_fill(0, 8, []));

    $numeros = collect($resultados)->pluck('numero')->sort()->values()->all();
    $anio = CarbonImmutable::now('America/Lima')->format('Y');

    expect(collect($resultados)->every(fn (array $r): bool => $r['ok']))->toBeTrue()
        ->and($numeros)->toBe(array_map(fn (int $n): string => sprintf('REQ-%s-%04d', $anio, $n), range(1, 8)))
        ->and(SerieDocumento::query()->sole()->ultimo_numero)->toBe(8);
});

test('fuera de una transacción no da número', function () {
    expect(DB::transactionLevel())->toBe(0)
        ->and(fn () => app(NumeracionService::class)->siguiente('documento_prueba', new FormatoNumeracion('REQ'), CarbonImmutable::now()))
        ->toThrow(LogicException::class, 'dentro de la transacción')
        ->and(SerieDocumento::query()->count())->toBe(0);
});

test('seis procesos registran a la vez la misma empresa: una sola party, la crea uno solo', function () {
    Party::query()->where('numero_documento', '20123456786')->delete();

    $resultados = aLaVez('party', array_fill(0, 6, []));

    expect(collect($resultados)->every(fn (array $r): bool => $r['ok']))->toBeTrue()
        ->and(collect($resultados)->pluck('id')->unique()->count())->toBe(1)
        ->and(collect($resultados)->where('creada', true)->count())->toBe(1)
        ->and(Party::query()->where('numero_documento', '20123456786')->count())->toBe(1);
});

test('dos transiciones a la vez sobre el mismo documento: una gana y la otra falla limpia', function () {
    foreach (['nucleo.prueba.enviar', 'nucleo.prueba.pedir_aprobacion'] as $permiso) {
        Permission::findOrCreate($permiso);
    }
    $persona = User::factory()->create()->givePermissionTo(['nucleo.prueba.enviar', 'nucleo.prueba.pedir_aprobacion']);
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido', 'monto' => '100.00']), $persona);

    $resultados = aLaVez('transicion', [[(string) $documento->id, 'enviar', $persona->email], [(string) $documento->id, 'pedir_aprobacion', $persona->email]]);

    $ganadores = collect($resultados)->where('ok', true);
    expect($ganadores)->toHaveCount(1)
        ->and(collect($resultados)->firstWhere('ok', false)['mensaje'])->not->toBe('')
        ->and(HistorialEstado::query()->where('documentable_id', $documento->id)->where('documentable_type', 'documento_prueba')->count())->toBe(2)
        ->and($documento->refresh()->version)->toBe(2);
});

test('la última firma de una aprobación, dada dos veces a la vez: la transición corre una sola vez', function () {
    Permission::findOrCreate('nucleo.prueba.pedir_aprobacion');
    $unidad = UnidadOrganizativa::factory()->create();
    $puesto = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    $jefe = User::factory()->create(['puesto_id' => $puesto->id]);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Su jefatura', 'documento_prueba', 'aprobar', null, null, null, ['documento']));

    $quien = User::factory()->create()->givePermissionTo('nucleo.prueba.pedir_aprobacion');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido', 'monto' => '100.00', 'unidad_id' => $unidad->id]), $quien)->refresh();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', $documento->version, $quien);

    $resultados = aLaVez('firmar', [[(string) $documento->id, $jefe->email], [(string) $documento->id, $jefe->email]]);

    $aprobaciones = HistorialEstado::query()
        ->where('documentable_type', 'documento_prueba')
        ->where('documentable_id', $documento->id)
        ->whereHas('transicion', fn ($query) => $query->where('codigo', 'aprobar'))
        ->count();

    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and(collect($resultados)->firstWhere('ok', false)['error'])->toBe(AprobacionRechazada::class)
        ->and($aprobaciones)->toBe(1)
        ->and(FirmaAprobacion::query()->whereHas('ronda', fn ($query) => $query->where('aprobable_id', $documento->id))->where('estado', 'aprobada')->count())->toBe(1);
});

test('elegir la ficha de la empresa mientras otro la desactiva: gana uno y nunca queda elegida una inactiva', function () {
    $party = Party::factory()->juridica()->create();

    $resultados = aLaVez('ficha', [['elegir', (string) $party->id], ['desactivar', (string) $party->id]]);

    $elegida = app(ParametrosService::class)->textoReciente(EmpresaService::PARAMETRO) === $party->numero_documento;
    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and($elegida && ! $party->refresh()->es_activo)->toBeFalse();
});

test('aprobar el árbol mientras otro cambia un monto: gana uno y nunca queda aprobado un árbol que no cuadra', function () {
    $arbol = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null], ['PC-02', 'Cable', '500.00', null]], aprobar: false);
    $jefatura = User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.aprobar');

    $resultados = distintosALaVez([
        ['aprobar-arbol', [(string) $arbol->arbolId, $jefatura->email]],
        ['fijar-monto', [(string) $arbol->partidas['PC-02'], '600.00']],
    ]);

    $aprobado = ArbolPartidas::query()->with('estado')->findOrFail($arbol->arbolId)->estado->codigo === DefinicionArbolPartidas::APROBADO;
    $suma = app(ArbolPartidasService::class)->sumaDeHojas($arbol->arbolId);

    // Si ganó aprobar, el monto no cambió y el árbol cuadra; si ganó el monto, no cuadra y quedó en borrador.
    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and($suma)->toBe($aprobado ? '1500.00' : '1600.00');
});

/**
 * Plan 2.20, D2: lanza dos creaciones de la cuenta del mismo RUC a la vez y devuelve lo que dijo cada una.
 *
 * @return list<array<string, mixed>>
 */
function dosCreandoLaCuenta(string $ruc): array
{
    $ejecutivo = User::factory()->create()->givePermissionTo('crm.cuentas.atender');
    $origen = OrigenComercial::query()->where('es_activo', true)->firstOrFail();

    return aLaVez('crear-cuenta', array_fill(0, 2, [$ruc, $ejecutivo->email, (string) $origen->id]));
}

test('dos personas crean a la vez la cuenta de una ficha ya registrada: una sola cuenta, y la otra lee «ya existe»', function () {
    $ruc = '2050000001'.DocumentoIdentidad::digitoVerificadorRuc('2050000001');
    $ficha = Party::factory()->juridica()->create(['numero_documento' => $ruc]);

    $resultados = dosCreandoLaCuenta($ruc);

    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and(collect($resultados)->firstWhere('ok', false)['mensaje'])->toStartWith('Esta cuenta ya existe')
        ->and(Cuenta::query()->where('party_id', $ficha->id)->count())->toBe(1);
});

test('dos personas crean a la vez la cuenta de un RUC sin ficha: una ficha, una cuenta, y la otra lee «ya existe»', function () {
    $ruc = '2050000002'.DocumentoIdentidad::digitoVerificadorRuc('2050000002');

    $resultados = dosCreandoLaCuenta($ruc);

    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and(collect($resultados)->firstWhere('ok', false)['mensaje'])->toStartWith('Esta cuenta ya existe')
        ->and(Party::query()->where('numero_documento', $ruc)->count())->toBe(1)
        ->and(Cuenta::query()->where('party_id', Party::query()->where('numero_documento', $ruc)->value('id'))->count())->toBe(1);
});

test('dos aprobaciones a la vez sobre la misma partida: gana una y la otra no la excede sin motivo', function () {
    $arbol = ArbolDePrueba::armar([['PC-01', 'Cable', '1000.00', null]]);
    $unidad = unidadQueAprueba();
    $pc01 = $arbol->partidas['PC-01'];
    $a = ordenEnviada($unidad, [[$pc01, '600.00']], $arbol->proyectoId);
    $b = ordenEnviada($unidad, [[$pc01, '600.00']], $arbol->proyectoId);
    $jefe = User::query()->where('puesto_id', $unidad->jefe_puesto_id)->sole();

    $resultados = aLaVez('aprobar-orden', [[(string) $a->id, $jefe->email], [(string) $b->id, $jefe->email]]);

    expect(collect($resultados)->where('ok', true))->toHaveCount(1)
        ->and(collect($resultados)->firstWhere('ok', false)['error'] ?? null)->toBe(ExcesoDePartidaRechazado::class)
        ->and(app(PartidasService::class)->saldo($pc01)->comprometido)->toBe('600.00');
});

test('dos órdenes con las mismas dos partidas en orden inverso, aprobadas a la vez: las dos terminan (una espera a la otra)', function () {
    $arbol = ArbolDePrueba::armar([['PC-01', 'Cable', '1000.00', null], ['PC-02', 'Cámaras', '1000.00', null]]);
    $unidad = unidadQueAprueba();
    [$pc01, $pc02] = [$arbol->partidas['PC-01'], $arbol->partidas['PC-02']];
    $a = ordenEnviada($unidad, [[$pc01, '100.00'], [$pc02, '100.00']], $arbol->proyectoId);
    $b = ordenEnviada($unidad, [[$pc02, '100.00'], [$pc01, '100.00']], $arbol->proyectoId);
    $jefe = User::query()->where('puesto_id', $unidad->jefe_puesto_id)->sole();

    $resultados = aLaVez('aprobar-orden', [[(string) $a->id, $jefe->email], [(string) $b->id, $jefe->email]]);

    expect(collect($resultados)->where('ok', true))->toHaveCount(2)
        ->and(app(PartidasService::class)->saldo($pc01)->comprometido)->toBe('200.00')
        ->and(app(PartidasService::class)->saldo($pc02)->comprometido)->toBe('200.00');
});

/** Plan 2.21, D6: una cuenta, quien vende y quien gerencia, con los permisos de Oportunidades. */
function cuentaParaOportunidades(): array
{
    $ejecutivo = User::factory()->create()->givePermissionTo('crm.cuentas.atender');
    $gerencia = User::factory()->create()->givePermissionTo([
        'crm.oportunidades.crear', 'crm.oportunidades.editar', 'crm.cuentas.desactivar',
        ...array_map(fn (string $c): string => "crm.oportunidades.oportunidad.{$c}", ['calificar', 'enviar_propuesta', 'negociar', 'ganar']),
    ]);
    $cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $ejecutivo->id]);

    return [$cuenta, $gerencia];
}

test('crear una oportunidad mientras otro desactiva su cuenta: nunca queda una abierta en una cuenta inactiva', function () {
    [$cuenta, $gerencia] = cuentaParaOportunidades();
    $moneda = (string) DB::table('monedas')->where('codigo', 'PEN')->value('id');
    $linea = (string) (LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id);
    $motivo = (string) Motivo::query()->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', 'crm.cuentas.desactivar'))->where('es_otro', false)->value('id');

    $resultados = distintosALaVez([
        ['desactivar-cuenta', [(string) $cuenta->id, $gerencia->email, $motivo]],
        ...array_fill(0, 3, ['crear-oportunidad', [(string) $cuenta->id, $gerencia->email, $moneda, $linea]]),
    ]);
    $inactiva = ! $cuenta->refresh()->es_activa;
    $abiertas = Oportunidad::query()->abiertas()->where('cuenta_id', $cuenta->id)->count();

    expect($inactiva && $abiertas > 0)->toBeFalse()
        ->and(collect($resultados)->where('ok', true)->count())->toBeGreaterThanOrEqual(1)
        ->and($abiertas)->toBe(collect($resultados)->where('ok', true)->whereNotNull('oportunidad')->count());
});

test('dos ganadas a la vez de la misma cuenta: las dos terminan, sin trabarse, y la cuenta queda cliente', function () {
    [$cuenta, $gerencia] = cuentaParaOportunidades();
    // Prueba el bloqueo de la cuenta al ganar, no lo que exige la cotización (plan 2.22): eso, en su módulo.
    OportunidadesDePrueba::sinExigirCotizacion();
    $decisor = PartyContacto::factory()->create(['party_id' => $cuenta->party_id]);
    $oportunidades = array_map(function () use ($cuenta, $gerencia, $decisor): Oportunidad {
        $oportunidad = OportunidadesDePrueba::crear($cuenta->id, ['necesidad_confirmada' => true, 'presupuesto_confirmado' => true, 'decisor_contacto_id' => $decisor->id]);
        foreach (['calificar', 'enviar_propuesta', 'negociar'] as $paso) {
            app(TransicionesService::class)->transicionar($oportunidad, $paso, (int) $oportunidad->fresh()?->version, $gerencia);
        }

        return $oportunidad->refresh();
    }, [1, 2]);

    $resultados = aLaVez('ganar', array_map(fn (Oportunidad $o): array => [(string) $o->id, $gerencia->email], $oportunidades));

    expect(collect($resultados)->where('ok', true))->toHaveCount(2)
        ->and($cuenta->refresh()->cliente_desde)->not->toBeNull()
        ->and(DB::table('cuenta_marcas_cliente')->where('cuenta_id', $cuenta->id)->count())->toBe(2);
});

/**
 * Plan 2.22b: una cotización emitida, de una oportunidad en Propuesta enviada, y quien la cierra (perder o anular) con su
 * motivo.
 *
 * @return array{0: Cotizacion, 1: CotizacionesDePrueba, 2: string}
 */
function cotizacionPorAceptar(string $codigo): array
{
    $cot = CotizacionesDePrueba::nueva();
    EmbudoDePrueba::permisos($cot);
    $c = $cot->emitida();
    EmbudoDePrueba::calificada($cot);
    EmbudoDePrueba::mover($cot, 'enviar_propuesta');
    $motivo = (string) Motivo::query()->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', DefinicionOportunidad::CLAVE.".{$codigo}"))->where('es_otro', false)->value('id');

    return [$c, $cot, $motivo];
}

foreach (['perder', 'anular'] as $codigo) {
    test("aceptar una cotización mientras otro hace «{$codigo}» con su oportunidad: nunca queda cerrada con una aceptada", function () use ($codigo) {
        [$c, $cot, $motivo] = cotizacionPorAceptar($codigo);

        $resultados = distintosALaVez([
            ['aceptar-cotizacion', [(string) $c->id, $cot->ejecutivo->email]],
            ['cerrar-oportunidad', [(string) $cot->oportunidad->id, $codigo, $cot->ejecutivo->email, $motivo]],
        ]);
        $cerrada = in_array($cot->oportunidad->refresh()->estado->codigo, ['perdida', 'anulada'], true);
        $aceptada = Cotizacion::query()->where('oportunidad_id', $cot->oportunidad->id)->where('es_aceptada', true)->exists();

        expect($cerrada && $aceptada)->toBeFalse()
            ->and(collect($resultados)->where('ok', true))->toHaveCount(1)
            ->and($cerrada || $aceptada)->toBeTrue();
    });
}
