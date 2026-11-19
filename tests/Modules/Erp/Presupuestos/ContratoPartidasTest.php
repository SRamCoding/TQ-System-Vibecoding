<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\EnMemoria\PartidasEnMemoria;
use App\Modules\Erp\Presupuestos\Services\PartidasEnBase;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\ArbolDePrueba;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;

/*
 * Contrato de C1 (plan 2.1, D1, D6 y D7): las mismas pruebas contra la versión real y la falsa. El árbol de las dos:
 * PC-01 Suministro (padre) › PC-01.01 Cámaras 1,000.00 y PC-01.02 Cable 500.00; PC-02 Obras civiles 300.00; PC-03
 * Movilidad, desactivada; y, al aprobar, la PC-99. Lo que solo la real puede revisar está en PartidasTest.php.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

/**
 * @return array{servicio: PartidasService, proyecto: int, ids: array<string, int>, imputar: Closure(string, string): void, cerrarProyecto: Closure(): void, motivo: int}
 */
function c1Version(string $cual, bool $aprobado = true): array
{
    if ($cual === 'falsa') {
        $falsa = new PartidasEnMemoria;
        $ids = [];
        $ids['PC-01'] = $falsa->agregar(7, 'PC-01', 'Suministro', arbolAprobado: $aprobado);
        $ids['PC-01.01'] = $falsa->agregar(7, 'PC-01.01', 'Cámaras', $ids['PC-01'], '1000.00', arbolAprobado: $aprobado);
        $ids['PC-01.02'] = $falsa->agregar(7, 'PC-01.02', 'Cable', $ids['PC-01'], '500.00', arbolAprobado: $aprobado);
        $ids['PC-02'] = $falsa->agregar(7, 'PC-02', 'Obras civiles', null, '300.00', arbolAprobado: $aprobado);
        $ids['PC-03'] = $falsa->agregar(7, 'PC-03', 'Movilidad', activa: false, arbolAprobado: $aprobado);

        if ($aprobado) {
            $ids['PC-99'] = $falsa->agregar(7, 'PC-99', 'No presupuestados');
        }

        return [
            'servicio' => $falsa,
            'proyecto' => 7,
            'ids' => $ids,
            'imputar' => fn (string $codigo, string $monto) => $falsa->imputar($ids[$codigo], $monto),
            'cerrarProyecto' => fn () => $falsa->proyectoSinCostos(7),
            'motivo' => 1,
        ];
    }

    $arbol = ArbolDePrueba::armar([
        ['PC-01', 'Suministro', null, null],
        ['PC-01.01', 'Cámaras', '1000.00', 'PC-01'],
        ['PC-01.02', 'Cable', '500.00', 'PC-01'],
        ['PC-02', 'Obras civiles', '300.00', null],
        ['PC-03', 'Movilidad', null, null],
    ], aprobar: false);
    app(ArbolPartidasService::class)->desactivar($arbol->partidas['PC-03'], PartidaControl::query()->findOrFail($arbol->partidas['PC-03'])->version);
    $ids = $arbol->partidas;

    if ($aprobado) {
        ArbolDePrueba::aprobar($arbol->arbolId);
        $ids['PC-99'] = (int) PartidaControl::query()->where('proyecto_id', $arbol->proyectoId)->where('codigo', 'PC-99')->value('id');
    }

    ModeloAuditado::crearTabla();
    $motivo = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_NO_PRESUPUESTADO))->value('id');

    return [
        'servicio' => app(PartidasEnBase::class),
        'proyecto' => $arbol->proyectoId,
        'ids' => $ids,
        'imputar' => function (string $codigo, string $monto) use ($ids): void {
            $linea = ModeloAuditado::query()->create(['nombre' => 'Línea '.fake()->unique()->numerify('####')]);
            $imputaciones = app(ImputacionService::class);
            $imputaciones->destinar($linea, app(PartidasEnBase::class)->destino($ids[$codigo]), CarbonImmutable::parse('2026-11-12'), 'PEN');
            $imputaciones->mover($linea, MomentoImputacion::Comprometido, $monto);
        },
        'cerrarProyecto' => function () use ($arbol): void {
            $proyecto = Proyecto::query()->findOrFail($arbol->proyectoId);
            app(TransicionesService::class)->transicionar($proyecto, 'cerrar', $proyecto->version, User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.cerrar'));
        },
        'motivo' => $motivo,
    ];
}

dataset('versiones de C1', ['real', 'falsa']);

test('buscar da las hojas activas con su ruta, sin la PC-99 ni las desactivadas', function (string $cual) {
    $v = c1Version($cual);

    expect(array_map(fn ($p) => $p->ruta, $v['servicio']->buscar($v['proyecto'])))
        ->toBe(['PC-01 › PC-01.01 Cámaras', 'PC-01 › PC-01.02 Cable', 'PC-02 Obras civiles']);
})->with('versiones de C1');

test('buscar encuentra por el nombre de un padre y sin acentos', function (string $cual) {
    $v = c1Version($cual);

    expect(array_map(fn ($p) => $p->codigo, $v['servicio']->buscar($v['proyecto'], 'suministro')))->toBe(['PC-01.01', 'PC-01.02'])
        ->and(array_map(fn ($p) => $p->codigo, $v['servicio']->buscar($v['proyecto'], 'CAMARAS')))->toBe(['PC-01.01'])
        ->and($v['servicio']->buscar($v['proyecto'], 'nada que coincida'))->toBe([]);
})->with('versiones de C1');

test('la PC-99 se pide aparte', function (string $cual) {
    $v = c1Version($cual);
    $pc99 = $v['servicio']->noPresupuestada($v['proyecto']);

    expect($pc99?->codigo)->toBe('PC-99')
        ->and($pc99?->esNoPresupuestada)->toBeTrue()
        ->and($v['servicio']->saldo($v['ids']['PC-99'])->presupuestado)->toBe('0.00');
})->with('versiones de C1');

test('el saldo de una hoja sin costos es su presupuestado, y un padre suma sus hojas', function (string $cual) {
    $v = c1Version($cual);
    $hoja = $v['servicio']->saldo($v['ids']['PC-01.01']);
    $padre = $v['servicio']->saldo($v['ids']['PC-01']);

    expect([$hoja->presupuestado, $hoja->comprometido, $hoja->ejecutado, $hoja->pagado, $hoja->disponible])->toBe(['1000.00', '0.00', '0.00', '0.00', '1000.00'])
        ->and([$padre->presupuestado, $padre->disponible])->toBe(['1500.00', '1500.00']);
})->with('versiones de C1');

test('lo comprometido baja el disponible de la hoja y de su padre, y pasarse lo deja negativo', function (string $cual) {
    $v = c1Version($cual);
    $v['imputar']('PC-01.01', '1200.00');
    $v['imputar']('PC-01.02', '100.00');
    $hoja = $v['servicio']->saldo($v['ids']['PC-01.01']);
    $padre = $v['servicio']->saldo($v['ids']['PC-01']);

    expect([$hoja->comprometido, $hoja->disponible, $hoja->excedida()])->toBe(['1200.00', '-200.00', true])
        ->and([$padre->comprometido, $padre->disponible, $padre->excedida()])->toBe(['1300.00', '200.00', false]);
})->with('versiones de C1');

test('el destino rechaza un padre, una desactivada, la PC-99 sin motivo y una que no existe', function (string $cual) {
    $v = c1Version($cual);
    $s = $v['servicio'];

    expect(fn () => $s->destino($v['ids']['PC-01']))->toThrow(ImputacionRechazada::class, 'no es una hoja')
        ->and(fn () => $s->destino($v['ids']['PC-03']))->toThrow(ImputacionRechazada::class, 'está desactivada')
        ->and(fn () => $s->destino($v['ids']['PC-99']))->toThrow(ImputacionRechazada::class, 'lleva su motivo y un comentario')
        ->and(fn () => $s->destino($v['ids']['PC-99'], $v['motivo']))->toThrow(ImputacionRechazada::class, 'lleva su motivo y un comentario')
        ->and(fn () => $s->destino(999999))->toThrow(ImputacionRechazada::class, 'no existe')
        ->and(fn () => $s->saldo(999999))->toThrow(ImputacionRechazada::class, 'no existe');
})->with('versiones de C1');

test('el destino de una hoja o de la PC-99 con motivo y comentario dice a qué partida y proyecto va', function (string $cual) {
    $v = c1Version($cual);
    $hoja = $v['servicio']->destino($v['ids']['PC-02'])->columnas();
    $pc99 = $v['servicio']->destino($v['ids']['PC-99'], $v['motivo'], 'Taxi imprevisto a la obra')->columnas();

    expect([$hoja['partida_control_id'], $hoja['proyecto_id'], $hoja['centro_costo_id']])->toBe([$v['ids']['PC-02'], $v['proyecto'], null])
        ->and([$pc99['partida_control_id'], $pc99['motivo_id'], $pc99['comentario']])->toBe([$v['ids']['PC-99'], $v['motivo'], 'Taxi imprevisto a la obra']);
})->with('versiones de C1');

test('con el árbol en borrador no hay a dónde imputar', function (string $cual) {
    $v = c1Version($cual, aprobado: false);

    expect($v['servicio']->buscar($v['proyecto']))->toBe([])
        ->and($v['servicio']->noPresupuestada($v['proyecto']))->toBeNull()
        ->and($v['servicio']->saldo($v['ids']['PC-02'])->presupuestado)->toBe('0.00')
        ->and(fn () => $v['servicio']->destino($v['ids']['PC-02']))->toThrow(ImputacionRechazada::class, 'El árbol de este proyecto todavía no está aprobado');
})->with('versiones de C1');

test('con el proyecto cerrado, buscar no da nada y el destino se rechaza', function (string $cual) {
    $v = c1Version($cual);
    $v['cerrarProyecto']();

    expect($v['servicio']->buscar($v['proyecto']))->toBe([])
        ->and(fn () => $v['servicio']->destino($v['ids']['PC-02']))->toThrow(ImputacionRechazada::class, 'no acepta costos');
})->with('versiones de C1');

test('resumen da una partida por su id aunque esté desactivada, y nada si no existe', function (string $cual) {
    $v = c1Version($cual);
    $inactiva = $v['servicio']->resumen($v['ids']['PC-03']);
    $hoja = $v['servicio']->resumen($v['ids']['PC-01.02']);

    expect([$inactiva?->codigo, $inactiva?->esActiva])->toBe(['PC-03', false])
        ->and([$hoja?->ruta, $hoja?->esActiva])->toBe(['PC-01 › PC-01.02 Cable', true])
        ->and($v['servicio']->resumen(999999))->toBeNull();
})->with('versiones de C1');

test('el saldo con bloqueo, dentro de una transacción, da lo mismo que sin él', function (string $cual) {
    $v = c1Version($cual);
    $v['imputar']('PC-01.01', '250.00');

    $bloqueado = DB::transaction(fn () => $v['servicio']->saldo($v['ids']['PC-01.01'], bloquear: true));

    expect($bloqueado)->toEqual($v['servicio']->saldo($v['ids']['PC-01.01']))
        ->and($bloqueado->disponible)->toBe('750.00');
})->with('versiones de C1');
