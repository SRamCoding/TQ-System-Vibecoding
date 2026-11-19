<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\NodoPropuesto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Tests\Costuras\ArbolDePrueba;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * La propuesta del árbol desde el presupuesto aprobado (plan 2.9b, paso 3): un padre por título y una hoja por
 * partida con su parcial (D1), la hoja de gastos generales con la raíz libre siguiente (D2), el origen de cada una
 * (D6), el jefe del proyecto como responsable por omisión (D4), y la comprobación única de «el árbol se puede llenar»
 * en sus tres casos (D3). Números del flujo 1 del v4: 17,750.00 + 1,775.00 = 19,525.00, gastos generales en PC-04.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function consultaArbol(): ConsultaArbol
{
    return app(ConsultaArbol::class);
}

/**
 * El flujo 1 aprobado y vigente en un proyecto nuevo.
 *
 * @return array{p: PresupuestoDePrueba, proyecto: int}
 */
function flujo1EnProyecto(): array
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return ['p' => $p, 'proyecto' => $proyecto];
}

test('F1: propone un padre por título, una hoja por partida con su parcial y PC-04 de gastos generales; suma 19,525.00', function () {
    ['p' => $p, 'proyecto' => $proyecto] = flujo1EnProyecto();

    $propuesta = consultaArbol()->propuestaDesdePresupuesto($proyecto);

    expect(array_map(fn (NodoPropuesto $n): array => [$n->codigo, $n->nombre, $n->padreCodigo, $n->esHoja, $n->monto], $propuesta->nodos))->toBe([
        ['PC-01', 'CÁMARAS', null, false, null],
        ['PC-01.01', 'Instalación de cámara domo IP', 'PC-01', true, '6430.00'],
        ['PC-02', 'CABLEADO Y POSTES', null, false, null],
        ['PC-02.01', 'Tendido de cable UTP', 'PC-02', true, '2412.00'],
        ['PC-02.02', 'Instalación de poste metálico', 'PC-02', true, '4360.00'],
        ['PC-03', 'CENTRO DE MONITOREO', null, false, null],
        ['PC-03.01', 'NVR con almacenamiento', 'PC-03', true, '4308.00'],
        ['PC-03.02', 'Pruebas y puesta en marcha', 'PC-03', true, '240.00'],
        ['PC-04', 'Gastos generales', null, true, '1775.00'],
    ])
        ->and($propuesta->totalHojas())->toBe('19525.00')
        ->and($propuesta->costoPresupuestado)->toBe('19525.00')
        ->and([$propuesta->arbolId, $propuesta->presupuestoId])->toBe([null, $p->presupuesto->id])
        ->and($propuesta->nodos[8]->esGastosGenerales)->toBeTrue();
});

test('cada partida propuesta dice de qué título o partida salió; la de gastos generales, del presupuesto', function () {
    ['p' => $p, 'proyecto' => $proyecto] = flujo1EnProyecto();
    $titulo01 = TituloDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->where('codigo', '01')->value('id');

    $nodos = collect(consultaArbol()->propuestaDesdePresupuesto($proyecto)->nodos)->keyBy('codigo');

    expect([$nodos['PC-01']->origenTituloId, $nodos['PC-01']->origenPartidaId, $nodos['PC-01']->origen])->toBe([$titulo01, null, 'título 01'])
        ->and([$nodos['PC-02.01']->origenPartidaId, $nodos['PC-02.01']->origen])->toBe([$p->partidas['02.01'], '02.01 · Tendido de cable UTP'])
        ->and([$nodos['PC-04']->origenTituloId, $nodos['PC-04']->origenPartidaId, $nodos['PC-04']->origen])->toBe([null, null, 'gastos generales del presupuesto']);
});

test('sin gastos generales no se propone la hoja, y el árbol suma el costo directo', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false, gg: '0');
    $p->titulo('01', 'OBRAS CIVILES');
    $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    $propuesta = consultaArbol()->propuestaDesdePresupuesto($proyecto);

    expect(array_map(fn (NodoPropuesto $n): string => $n->codigo, $propuesta->nodos))->toBe(['PC-01', 'PC-01.01'])
        ->and([$propuesta->totalHojas(), $propuesta->costoPresupuestado])->toBe(['8500.00', '8500.00']);
});

test('D3: sin árbol se propone para crearlo; con un borrador vacío, para llenarlo; con partidas, se rechaza y dice qué hacer', function () {
    ['proyecto' => $proyecto] = flujo1EnProyecto();

    expect(consultaArbol()->arbolParaLlenar($proyecto))->toBeNull()
        ->and(consultaArbol()->sePuedeLlenar($proyecto))->toBeTrue();

    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '19525.00', User::factory()->create());

    expect(consultaArbol()->arbolParaLlenar($proyecto)?->id)->toBe($arbol->id)
        ->and(consultaArbol()->propuestaDesdePresupuesto($proyecto)->arbolId)->toBe($arbol->id);

    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-01', 'Cámaras', User::factory()->create()->id);
    $codigo = app(ProyectosService::class)->resumen($proyecto)->codigo;

    expect(consultaArbol()->sePuedeLlenar($proyecto))->toBeFalse()
        ->and(fn () => consultaArbol()->propuestaDesdePresupuesto($proyecto))
        ->toThrow(ArbolRechazado::class, "El árbol de {$codigo} (v1, en borrador) ya tiene partidas: sigue armándolo en su pestaña, o desactiva las que sobren.");
});

test('un árbol aprobado no se vuelve a armar', function () {
    $aprobado = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);

    expect(fn () => consultaArbol()->arbolParaLlenar($aprobado->proyectoId))->toThrow(ArbolRechazado::class, 'ya está aprobado (v1): no se vuelve a armar');
});

test('sin presupuesto aprobado, la propuesta dice qué hacer', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $codigo = app(ProyectosService::class)->resumen($proyecto)->codigo;

    expect(fn () => consultaArbol()->propuestaDesdePresupuesto($proyecto))
        ->toThrow(ArbolRechazado::class, "{$codigo} no tiene un presupuesto aprobado: tráelo al proyecto desde su ficha en Presupuestos, o arma el árbol a mano.");
});

test('D4: el responsable por omisión es el jefe del proyecto; si está inactivo, nadie (se elige antes de confirmar)', function () {
    ['proyecto' => $proyecto] = flujo1EnProyecto();
    $jefe = app(ProyectosService::class)->resumen($proyecto)->jefeProyectoUserId;

    expect(consultaArbol()->propuestaDesdePresupuesto($proyecto)->responsableUserId)->toBe($jefe);

    User::query()->whereKey($jefe)->update(['es_activo' => false]);

    expect(consultaArbol()->responsablePorOmision($proyecto))->toBeNull()
        ->and(consultaArbol()->propuestaDesdePresupuesto($proyecto)->responsableUserId)->toBeNull();
});

test('proponer no crea nada', function () {
    ['proyecto' => $proyecto] = flujo1EnProyecto();

    consultaArbol()->propuestaDesdePresupuesto($proyecto);

    expect(consultaArbol()->arbolDe($proyecto))->toBeNull()
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->exists())->toBeFalse();
});

test('un proyecto que no acepta costos no se arma desde el presupuesto', function () {
    ['proyecto' => $id] = flujo1EnProyecto();
    $proyecto = Proyecto::query()->findOrFail($id);
    $otro = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->value('id');
    app(TransicionesService::class)->transicionar($proyecto, 'anular', $proyecto->version, User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.anular'), new MotivoElegido($otro, 'Se registró dos veces'), 'Se registró dos veces');

    expect(fn () => consultaArbol()->propuestaDesdePresupuesto($id))->toThrow(ArbolRechazado::class, 'está anulado: no se le arma un árbol.');
});

/** Un presupuesto sin APU con un título de código dado, aprobado en un proyecto; devuelve el proyecto. */
function conTitulo(string $codigo): int
{
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    $p->titulo($codigo, 'TÍTULO RARO');
    $p->partida($codigo, "{$codigo}.01", 'Partida inventada', 'GLB', '1', precioUnitario: '100');
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return $proyecto;
}

test('un título 99 chocaría con la PC-99, y un 98 deja sin raíz a los gastos generales: los dos se explican', function () {
    expect(fn () => consultaArbol()->propuestaDesdePresupuesto(conTitulo('99')))->toThrow(ArbolRechazado::class, 'El título 99 del presupuesto chocaría con la PC-99')
        ->and(fn () => consultaArbol()->propuestaDesdePresupuesto(conTitulo('98')))->toThrow(ArbolRechazado::class, 'no queda uno para los gastos generales');
});
