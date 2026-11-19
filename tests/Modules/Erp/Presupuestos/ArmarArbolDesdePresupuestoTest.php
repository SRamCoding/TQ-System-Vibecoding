<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\ArbolDePrueba;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Confirmar la propuesta del árbol (plan 2.9b, paso 4): crea el árbol en borrador o llena el vacío, con sus partidas,
 * montos, responsables y origen, en una sola transacción; aplica los ajustes (D5) y nunca pisa nada (D3). Escenario
 * del flujo 1 del v4: 19,525.00, gastos generales en PC-04 con 1,775.00. Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

/** El flujo 1 aprobado y vigente en un proyecto nuevo; devuelve el proyecto. */
function proyectoDelFlujo1(): int
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return $proyecto;
}

function armarDesdePresupuesto(int $proyecto, ?AjustesPropuesta $ajustes = null): ArbolPartidas
{
    return app(ArbolPartidasService::class)->crearDesdePropuesta(app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto), $ajustes ?? new AjustesPropuesta, User::factory()->create());
}

/** @return array<string, string> código => presupuestado */
function montosDelArbol(ArbolPartidas $arbol): array
{
    return collect(app(ConsultaArbol::class)->filas($arbol->id))->mapWithKeys(fn ($f) => [$f->codigo => $f->presupuestado])->all();
}

test('F1: sin árbol, confirmar crea el borrador con sus partidas y montos, suma 19,525.00 y se puede aprobar', function () {
    $proyecto = proyectoDelFlujo1();

    $arbol = armarDesdePresupuesto($proyecto);

    expect($arbol->estado->codigo)->toBe(DefinicionArbolPartidas::BORRADOR)
        ->and($arbol->costo_presupuestado)->toBe('19525.00')
        ->and($arbol->presupuesto_id)->toBe(app(ConsultaArbol::class)->presupuestoAprobado($proyecto)?->id)
        ->and(montosDelArbol($arbol))->toBe([
            'PC-01' => '6430.00', 'PC-01.01' => '6430.00',
            'PC-02' => '6772.00', 'PC-02.01' => '2412.00', 'PC-02.02' => '4360.00',
            'PC-03' => '4548.00', 'PC-03.01' => '4308.00', 'PC-03.02' => '240.00',
            'PC-04' => '1775.00',
        ])
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('0.00')
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-04')->value('es_gastos_generales'))->toBeTrue();

    ArbolDePrueba::aprobar($arbol->id);

    expect($arbol->refresh()->estado->codigo)->toBe(DefinicionArbolPartidas::APROBADO);
});

test('F1: cada partida guarda de qué título o partida del presupuesto salió; todas llevan al jefe del proyecto', function () {
    $proyecto = proyectoDelFlujo1();
    $presupuestoId = app(ConsultaArbol::class)->presupuestoAprobado($proyecto)?->id;
    $jefe = app(ProyectosService::class)->resumen($proyecto)->jefeProyectoUserId;

    armarDesdePresupuesto($proyecto);
    $partidas = PartidaControl::query()->where('proyecto_id', $proyecto)->get()->keyBy('codigo');
    $titulos = TituloDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->pluck('id', 'codigo');
    $deLaPartida = fn (string $codigo): string => (string) DB::table('presupuesto_partidas')->where('id', $partidas[$codigo]->origen_partida_id)->value('codigo');

    expect([$partidas['PC-01']->origen_titulo_id, $partidas['PC-02']->origen_titulo_id, $partidas['PC-03']->origen_titulo_id])
        ->toBe([$titulos['01'], $titulos['02'], $titulos['03']])
        ->and(array_map($deLaPartida, ['PC-01.01', 'PC-02.01', 'PC-02.02', 'PC-03.01', 'PC-03.02']))->toBe(['01.01', '02.01', '02.02', '03.01', '03.02'])
        ->and([$partidas['PC-04']->origen_titulo_id, $partidas['PC-04']->origen_partida_id])->toBe([null, null])
        ->and($partidas->pluck('responsable_user_id')->unique()->values()->all())->toBe([$jefe]);
});

test('D3: un borrador vacío se llena (el mismo árbol) y toma el costo del presupuesto', function () {
    $proyecto = proyectoDelFlujo1();
    $vacio = app(ArbolPartidasService::class)->crear($proyecto, '100.00', User::factory()->create());

    $arbol = armarDesdePresupuesto($proyecto);

    expect($arbol->id)->toBe($vacio->id)
        ->and($arbol->costo_presupuestado)->toBe('19525.00')
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('0.00');
});

test('D3: si el borrador ya tiene partidas, se rechaza y no se toca nada', function () {
    $proyecto = proyectoDelFlujo1();
    $propuesta = app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto);
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '19525.00', User::factory()->create());
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-01', 'Hecha a mano', User::factory()->create()->id);

    expect(fn () => app(ArbolPartidasService::class)->crearDesdePropuesta($propuesta, new AjustesPropuesta, User::factory()->create()))
        ->toThrow(ArbolRechazado::class, 'ya tiene partidas')
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->pluck('nombre')->all())->toBe(['Hecha a mano']);
});

test('D5: renombrar y cambiar el responsable se guardan; el origen no cambia', function () {
    $proyecto = proyectoDelFlujo1();
    $otra = User::factory()->create();

    armarDesdePresupuesto($proyecto, new AjustesPropuesta(nombres: ['PC-01.01' => 'Cámaras del parque'], responsables: ['PC-02' => $otra->id]));
    $partidas = PartidaControl::query()->where('proyecto_id', $proyecto)->get()->keyBy('codigo');

    expect($partidas['PC-01.01']->nombre)->toBe('Cámaras del parque')
        ->and(DB::table('presupuesto_partidas')->where('id', $partidas['PC-01.01']->origen_partida_id)->value('descripcion'))->toBe('Instalación de cámara domo IP')
        ->and($partidas['PC-02']->responsable_user_id)->toBe($otra->id)
        ->and($partidas['PC-02.01']->responsable_user_id)->not->toBe($otra->id);
});

test('F1 quitando una partida: su monto queda por repartir y no se aprueba hasta repartirlo', function () {
    $proyecto = proyectoDelFlujo1();

    $arbol = armarDesdePresupuesto($proyecto, new AjustesPropuesta(quitar: ['PC-02.02']));

    expect(montosDelArbol($arbol))->not->toHaveKey('PC-02.02')
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('4360.00')
        ->and(fn () => ArbolDePrueba::aprobar($arbol->id))->toThrow(TransicionRechazada::class, 'El árbol no cuadra');

    $hoja = PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-02.01')->sole();
    app(ArbolPartidasService::class)->fijarPresupuestado($hoja->id, '6772.00', $hoja->version);
    ArbolDePrueba::aprobar($arbol->id);

    expect($arbol->refresh()->estado->codigo)->toBe(DefinicionArbolPartidas::APROBADO);
});

test('D5: quitar un título quita sus hijas y deja todo su monto por repartir; la de gastos generales no se quita', function () {
    $proyecto = proyectoDelFlujo1();

    expect(fn () => armarDesdePresupuesto($proyecto, new AjustesPropuesta(quitar: ['PC-04'])))
        ->toThrow(ArbolRechazado::class, 'PC-04 es la de gastos generales: no se quita en la propuesta.')
        ->and(app(ConsultaArbol::class)->arbolDe($proyecto))->toBeNull();

    $arbol = armarDesdePresupuesto($proyecto, new AjustesPropuesta(quitar: ['PC-03']));

    expect(array_keys(montosDelArbol($arbol)))->toBe(['PC-01', 'PC-01.01', 'PC-02', 'PC-02.01', 'PC-02.02', 'PC-04'])
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('4548.00');
});

test('D4: con el jefe inactivo hay que elegir responsable; eligiéndolo, se arma', function () {
    $proyecto = proyectoDelFlujo1();
    User::query()->whereKey(app(ProyectosService::class)->resumen($proyecto)->jefeProyectoUserId)->update(['es_activo' => false]);
    $elegida = User::factory()->create();

    expect(fn () => armarDesdePresupuesto($proyecto))
        ->toThrow(ArbolRechazado::class, 'Elige quién responde por las partidas: el jefe del proyecto no es un usuario activo');

    armarDesdePresupuesto($proyecto, new AjustesPropuesta(responsablePorOmision: $elegida->id));

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->pluck('responsable_user_id')->unique()->values()->all())->toBe([$elegida->id]);
});

test('si una partida falla a mitad de camino, no queda ni el árbol ni ninguna partida', function () {
    $proyecto = proyectoDelFlujo1();
    $inactiva = User::factory()->create(['es_activo' => false]);

    expect(fn () => armarDesdePresupuesto($proyecto, new AjustesPropuesta(responsables: ['PC-03.02' => $inactiva->id])))
        ->toThrow(ArbolRechazado::class, 'usuario activo')
        ->and(app(ConsultaArbol::class)->arbolDe($proyecto))->toBeNull()
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->exists())->toBeFalse();
});

test('la acción registrada arma el árbol con los ajustes como «PC-…=…» y pide el permiso de editar el árbol', function () {
    $proyecto = proyectoDelFlujo1();
    $accion = app(RegistroAcciones::class)->obtener('erp.presupuestos.armar-arbol-desde-presupuesto');
    $quien = User::factory()->create()->givePermissionTo(['erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso()]);

    expect($accion->permiso())->toBe('erp.presupuestos.arbol.editar')
        // Plan 2.11, D11: además, ver costos.
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar($accion, ['proyecto_id' => $proyecto], User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.editar')))
        ->toThrow(ArbolRechazado::class, 'pide el permiso de ver costos')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar($accion, ['proyecto_id' => $proyecto, 'quitar' => ['PC-09']], $quien))
        ->toThrow(ArbolRechazado::class, 'La partida PC-09 no está en la propuesta.');

    $arbol = app(EjecutorAcciones::class)->ejecutar($accion, ['proyecto_id' => $proyecto, 'nombres' => ['pc-01=Cámaras domo'], 'quitar' => ['PC-03.02']], $quien);

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-01')->value('nombre'))->toBe('Cámaras domo')
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('240.00');
});

test('si el árbol cambió desde que se armó la propuesta, no se confirma', function () {
    $proyecto = proyectoDelFlujo1();
    $propuesta = app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto);
    app(ArbolPartidasService::class)->crear($proyecto, '19525.00', User::factory()->create());

    expect(fn () => app(ArbolPartidasService::class)->crearDesdePropuesta($propuesta, new AjustesPropuesta, User::factory()->create()))
        ->toThrow(ArbolRechazado::class, 'El árbol cambió mientras revisabas la propuesta: vuelve a abrirla.');
});

test('si otra versión del presupuesto pasó a vigente, la propuesta vieja no se confirma', function () {
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    $armado = app(ArmadoPresupuestoService::class);
    $armado->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $propuesta = app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto);
    $v2 = $armado->nuevaVersion($p->presupuesto->id, PresupuestoDePrueba::jefatura());
    $armado->traerAlProyecto($v2->id, $proyecto, $v2->refresh()->version, PresupuestoDePrueba::jefatura());

    expect(fn () => app(ArbolPartidasService::class)->crearDesdePropuesta($propuesta, new AjustesPropuesta, User::factory()->create()))
        ->toThrow(ArbolRechazado::class, 'El presupuesto vigente del proyecto cambió mientras revisabas la propuesta')
        ->and(app(ConsultaArbol::class)->arbolDe($proyecto))->toBeNull();
});

test('quitar todas las partidas no arma nada; quitar las hijas deja al título como hoja sin monto y con su origen', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false, gg: '0');
    $p->titulo('01', 'OBRAS CIVILES');
    $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    expect(fn () => armarDesdePresupuesto($proyecto, new AjustesPropuesta(quitar: ['PC-01'])))
        ->toThrow(ArbolRechazado::class, 'Quitaste todas las partidas: no queda nada que armar.');

    $arbol = armarDesdePresupuesto($proyecto, new AjustesPropuesta(quitar: ['PC-01.01']));
    $titulo = PartidaControl::query()->where('proyecto_id', $proyecto)->sole();

    expect([$titulo->codigo, $titulo->es_hoja, $titulo->origen_titulo_id !== null])->toBe(['PC-01', true, true])
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('8500.00');
});

test('los ajustes de la acción mal escritos se rechazan con el formato que esperan', function () {
    expect(fn () => AjustesPropuesta::desdeCampos(['PC-01 Cámaras'], [], [], null))->toThrow(ArbolRechazado::class, 'Cada nombre va como «PC-01=…»')
        ->and(fn () => AjustesPropuesta::desdeCampos([], ['=12'], [], null))->toThrow(ArbolRechazado::class, 'Cada responsable va como «PC-01=…»')
        ->and(fn () => AjustesPropuesta::desdeCampos([], ['PC-01=Ana'], [], null))->toThrow(ArbolRechazado::class, 'va como el número del usuario');
});
