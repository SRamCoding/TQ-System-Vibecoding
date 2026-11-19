<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Lo que la base garantiza del origen del árbol (plan 2.9b, paso 2, D6): el presupuesto del árbol es del mismo
 * proyecto; cada partida tiene a lo sumo un origen, y la de gastos generales ninguno; el origen no cambia. Números y
 * nombres del flujo 1, inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

/** La falla esperada corre en un punto de guardado: así no aborta la transacción de la prueba. */
function sinRomperLaPrueba(Closure $tarea): Closure
{
    return fn () => DB::transaction($tarea);
}

/**
 * El flujo 1 aprobado en un proyecto, con su árbol en borrador vacío.
 *
 * @return array{p: PresupuestoDePrueba, proyecto: int, arbol: ArbolPartidas}
 */
function arbolConPresupuesto(): array
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '19525.00', User::factory()->create());
    DB::table('partida_arboles')->where('id', $arbol->id)->update(['presupuesto_id' => $p->presupuesto->id]);

    return ['p' => $p, 'proyecto' => $proyecto, 'arbol' => $arbol->refresh()];
}

/** Una partida cruda del árbol, con lo que la prueba cambie; con origen, del presupuesto del árbol. */
function partidaConOrigen(ArbolPartidas $arbol, string $codigo, array $cambios = []): PartidaControl
{
    $conOrigen = isset($cambios['origen_titulo_id']) || isset($cambios['origen_partida_id']);

    return PartidaControl::query()->create([
        'proyecto_id' => $arbol->proyecto_id, 'arbol_alta_id' => $arbol->id, 'codigo' => $codigo, 'nombre' => "Partida {$codigo}",
        'responsable_user_id' => User::factory()->create()->id, 'origen_presupuesto_id' => $conOrigen ? $arbol->presupuesto_id : null, ...$cambios,
    ]);
}

test('el árbol guarda el presupuesto de su proyecto; uno de otro proyecto no entra', function () {
    ['p' => $p, 'arbol' => $arbol] = arbolConPresupuesto();
    $otro = PresupuestoDePrueba::flujo1();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($otro->presupuesto->id, PresupuestoDePrueba::proyecto(), $otro->fresco()->version, PresupuestoDePrueba::jefatura());

    expect(ArbolPartidas::query()->findOrFail($arbol->id)->presupuesto_id)->toBe($p->presupuesto->id)
        ->and(sinRomperLaPrueba(fn () => DB::table('partida_arboles')->where('id', $arbol->id)->update(['presupuesto_id' => $otro->presupuesto->id])))
        ->toThrow(QueryException::class, 'partida_arboles_presupuesto_id_proyecto_id_foreign');
});

test('una partida sale de un título o de una partida del presupuesto, no de los dos', function () {
    ['p' => $p, 'arbol' => $arbol] = arbolConPresupuesto();
    $titulo = TituloDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->where('codigo', '01')->value('id');
    $partida = $p->partidas['01.01'];

    $deTitulo = partidaConOrigen($arbol, 'PC-01', ['origen_titulo_id' => $titulo, 'es_hoja' => false]);
    $dePartida = partidaConOrigen($arbol, 'PC-01.01', ['padre_id' => $deTitulo->id, 'origen_partida_id' => $partida]);

    expect([$deTitulo->origen_titulo_id, $dePartida->origen_partida_id])->toBe([$titulo, $partida])
        ->and(sinRomperLaPrueba(fn () => partidaConOrigen($arbol, 'PC-02', ['origen_titulo_id' => $titulo, 'origen_partida_id' => $partida])))
        ->toThrow(QueryException::class, 'partidas_control_origen_check');
});

test('la de gastos generales y la PC-99 no llevan origen del presupuesto', function () {
    ['p' => $p, 'arbol' => $arbol] = arbolConPresupuesto();

    partidaConOrigen($arbol, 'PC-04', ['es_gastos_generales' => true]);

    expect(sinRomperLaPrueba(fn () => partidaConOrigen($arbol, 'PC-05', ['es_gastos_generales' => true, 'origen_partida_id' => $p->partidas['01.01']])))
        ->toThrow(QueryException::class, 'partidas_control_origen_sistema_check')
        ->and(sinRomperLaPrueba(fn () => partidaConOrigen($arbol, 'PC-99', ['es_no_presupuestado' => true, 'origen_partida_id' => $p->partidas['01.01']])))
        ->toThrow(QueryException::class, 'partidas_control_origen_sistema_check');
});

test('una partida no sale de otro presupuesto, y el presupuesto del árbol no cambia mientras alguna salga de él', function () {
    ['p' => $p, 'arbol' => $arbol] = arbolConPresupuesto();
    $otro = PresupuestoDePrueba::flujo1();
    partidaConOrigen($arbol, 'PC-01', ['origen_partida_id' => $p->partidas['01.01']]);

    expect(sinRomperLaPrueba(fn () => partidaConOrigen($arbol, 'PC-02', ['origen_partida_id' => $otro->partidas['01.01']])))
        ->toThrow(QueryException::class, 'partidas_control_origen_partida_foreign')
        ->and(sinRomperLaPrueba(fn () => partidaConOrigen($arbol, 'PC-03', ['origen_partida_id' => $otro->partidas['01.01'], 'origen_presupuesto_id' => $otro->presupuesto->id])))
        ->toThrow(QueryException::class, 'partidas_control_origen_arbol_foreign')
        ->and(sinRomperLaPrueba(fn () => DB::table('partida_arboles')->where('id', $arbol->id)->update(['presupuesto_id' => null])))
        ->toThrow(QueryException::class, 'partidas_control_origen_arbol_foreign');
});

test('el origen es histórico: renombrar no lo toca y no se cambia', function () {
    ['p' => $p, 'arbol' => $arbol] = arbolConPresupuesto();
    $partida = partidaConOrigen($arbol, 'PC-01', ['origen_partida_id' => $p->partidas['01.01']]);

    $partida->update(['nombre' => 'Cámaras del parque']);

    expect($partida->refresh()->origen_partida_id)->toBe($p->partidas['01.01'])
        ->and(fn () => $partida->update(['origen_partida_id' => $p->partidas['02.01']]))->toThrow(LogicException::class, 'origen_partida_id');
});
