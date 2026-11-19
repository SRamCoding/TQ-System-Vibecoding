<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PresupuestadoDePartida;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\EdicionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\ArbolDePrueba;

/*
 * El árbol de partidas (plan 2.1, D5): códigos, `es_hoja`, desactivar y aprobar (cuadra, crea la PC-99 y pasa el
 * proyecto a ejecución). La concurrencia con aprobar está en tests/Concurrencia.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function apServicio(): ArbolPartidasService
{
    return app(ArbolPartidasService::class);
}

function apVersion(int $partidaId): int
{
    return PartidaControl::query()->findOrFail($partidaId)->version;
}

test('los códigos siguen el formato: PC-NN en la raíz y el del padre más .NN debajo; la PC-99 es del sistema', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', null, null]], aprobar: false);
    $r = $a->responsable->id;

    expect(fn () => apServicio()->agregarPartida($a->arbolId, null, 'PC-1', 'Mal', $r))->toThrow(ArbolRechazado::class, '«PC-01»')
        ->and(fn () => apServicio()->agregarPartida($a->arbolId, $a->partidas['PC-01'], 'PC-02.01', 'Otra rama', $r))->toThrow(ArbolRechazado::class, 'Bajo PC-01')
        ->and(fn () => apServicio()->agregarPartida($a->arbolId, null, 'PC-99', 'Mía', $r))->toThrow(ArbolRechazado::class, 'la crea el sistema')
        ->and(fn () => apServicio()->agregarPartida($a->arbolId, null, 'PC-01', 'Repetida', $r))->toThrow(ArbolRechazado::class, 'Ya hay una partida PC-01')
        ->and(fn () => apServicio()->agregarPartida($a->arbolId, null, 'PC-02', 'Sin responsable', User::factory()->create(['es_activo' => false])->id))->toThrow(ArbolRechazado::class, 'usuario activo');
});

test('a una hoja con monto no se le agregan hijos: primero se quita el monto', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '800.00', null]], aprobar: false);
    $pc01 = $a->partidas['PC-01'];

    expect(fn () => apServicio()->agregarPartida($a->arbolId, $pc01, 'PC-01.01', 'Cámaras', $a->responsable->id))->toThrow(ArbolRechazado::class, 'primero quita el monto');

    apServicio()->fijarPresupuestado($pc01, '0', apVersion($pc01));
    apServicio()->agregarPartida($a->arbolId, $pc01, 'PC-01.01', 'Cámaras', $a->responsable->id);

    expect(PartidaControl::query()->findOrFail($pc01)->es_hoja)->toBeFalse()
        ->and(fn () => apServicio()->fijarPresupuestado($pc01, '100', apVersion($pc01)))->toThrow(ArbolRechazado::class, 'no es una hoja activa');
});

test('un padre con hijos activos no se desactiva; al desactivar su último hijo vuelve a ser hoja', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', null, null], ['PC-01.01', 'Cámaras', null, 'PC-01']], aprobar: false);

    expect(fn () => apServicio()->desactivar($a->partidas['PC-01'], apVersion($a->partidas['PC-01'])))->toThrow(ArbolRechazado::class, 'partidas activas debajo');

    apServicio()->desactivar($a->partidas['PC-01.01'], apVersion($a->partidas['PC-01.01']));

    expect(PartidaControl::query()->findOrFail($a->partidas['PC-01'])->es_hoja)->toBeTrue();
});

test('una hoja con presupuestado no se desactiva, y una versión vieja de la partida se rechaza', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '500.00', null]], aprobar: false);
    $pc01 = $a->partidas['PC-01'];

    expect(fn () => apServicio()->desactivar($pc01, apVersion($pc01)))->toThrow(ArbolRechazado::class, 'primero quita el monto')
        ->and(fn () => apServicio()->fijarPresupuestado($pc01, '600', apVersion($pc01) - 1))->toThrow(VersionDesactualizada::class);
});

test('un árbol que no cuadra o sin hojas no se aprueba', function () {
    $noCuadra = ArbolDePrueba::armar([['PC-01', 'Suministro', '900.00', null]], aprobar: false, costo: '1000.00');
    $vacio = ArbolDePrueba::armar([], aprobar: false, costo: '0');

    expect(fn () => ArbolDePrueba::aprobar($noCuadra->arbolId))->toThrow(TransicionRechazada::class, 'suman S/ 900.00 y el costo presupuestado es S/ 1000.00')
        ->and(fn () => ArbolDePrueba::aprobar($vacio->arbolId))->toThrow(TransicionRechazada::class, 'ninguna partida hoja');
});

test('al aprobar: crea la PC-99 en cero con el jefe del proyecto como responsable y pasa el proyecto a ejecución', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null], ['PC-02', 'Obras civiles', '300.00', null]]);
    $pc99 = PartidaControl::query()->findOrFail($a->partidas['PC-99']);

    expect(ArbolPartidas::query()->with('estado')->findOrFail($a->arbolId)->estado->codigo)->toBe(DefinicionArbolPartidas::APROBADO)
        ->and([$pc99->es_no_presupuestado, $pc99->es_hoja, $pc99->padre_id, $pc99->responsable_user_id])->toBe([true, true, null, app(ProyectosService::class)->resumen($a->proyectoId)->jefeProyectoUserId])
        ->and(app(ProyectosService::class)->resumen($a->proyectoId)->estado)->toBe(DefinicionProyecto::EN_EJECUCION)
        ->and(PartidaControl::query()->where('proyecto_id', $a->proyectoId)->where('codigo', 'PC-99')->count())->toBe(1);
});

test('un árbol aprobado no se edita y un proyecto tiene un solo árbol por ahora', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null]]);

    expect(fn () => apServicio()->agregarPartida($a->arbolId, null, 'PC-02', 'Tarde', $a->responsable->id))->toThrow(ArbolRechazado::class, 'ya está aprobado')
        ->and(fn () => apServicio()->fijarPresupuestado($a->partidas['PC-01'], '1', apVersion($a->partidas['PC-01'])))->toThrow(ArbolRechazado::class, 'ya está aprobado')
        ->and(fn () => apServicio()->crear($a->proyectoId, '10', $a->responsable))->toThrow(ArbolRechazado::class, 'ya tiene su árbol');
});

test('el árbol de un proyecto anulado no se aprueba', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null]], aprobar: false);
    $proyecto = Proyecto::query()->findOrFail($a->proyectoId);
    $otro = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->value('id');
    app(TransicionesService::class)->transicionar($proyecto, 'anular', $proyecto->version, User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.anular'), new MotivoElegido($otro, 'Se registró dos veces'), 'Se registró dos veces');

    expect(fn () => ArbolDePrueba::aprobar($a->arbolId))->toThrow(TransicionRechazada::class, 'está anulado');
});

test('la base guarda la PC-99 como la de no presupuestados, en la raíz y sin hijos, y una por proyecto', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null]]);
    $fila = fn (array $cambios) => DB::table('partidas_control')->insert([
        'proyecto_id' => $a->proyectoId, 'arbol_alta_id' => $a->arbolId, 'codigo' => 'PC-05', 'nombre' => 'Directa',
        'responsable_user_id' => $a->responsable->id, 'created_by' => $a->responsable->id, ...$cambios,
    ]);

    expect(fn () => DB::transaction(fn () => $fila(['es_no_presupuestado' => true])))->toThrow(QueryException::class, 'partidas_control_pc99_check')
        ->and(fn () => DB::transaction(fn () => $fila(['codigo' => 'PC-99', 'es_no_presupuestado' => true])))->toThrow(QueryException::class, 'partidas_control_proyecto_id_codigo_unique')
        ->and(fn () => DB::transaction(fn () => $fila(['codigo' => 'PC-5'])))->toThrow(QueryException::class, 'partidas_control_codigo_check');
});

test('las acciones del árbol están registradas y piden su permiso', function () {
    $a = ArbolDePrueba::armar([], aprobar: false, costo: '0');
    $sin = User::factory()->create();
    $con = User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.editar');
    $datos = ['arbol_id' => $a->arbolId, 'codigo' => 'PC-01', 'nombre' => 'Suministro', 'responsable_user_id' => $con->id];

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.agregar-partida', $datos, $sin))->toThrow(AccionRechazada::class);

    $partida = app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.agregar-partida', $datos, $con);
    app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.fijar-presupuestado', ['partida_id' => $partida->id, 'monto' => '250.50', 'version' => $partida->version], $con);

    expect(apServicio()->sumaDeHojas($a->arbolId))->toBe('250.50');
});

test('crear el árbol y desactivar una partida también se ejecutan sin la interfaz, con su permiso', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', null, null], ['PC-02', 'Sobra', null, null]], aprobar: false);
    $sin = User::factory()->create();
    $con = User::factory()->create()->givePermissionTo(['erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso()]);
    $sinCostos = User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.editar');
    $desactivar = ['partida_id' => $a->partidas['PC-02'], 'version' => apVersion($a->partidas['PC-02'])];
    $otroProyecto = app(ProyectosService::class)->crear(new DatosProyecto('Otro', $con->id, CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-12-10')), $con);

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.desactivar-partida', $desactivar, $sin))->toThrow(AccionRechazada::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.crear-arbol', ['proyecto_id' => $otroProyecto->id, 'costo_presupuestado' => '100'], $sin))->toThrow(AccionRechazada::class)
        // Plan 2.11, D11: crear el árbol pide además ver costos, también sin la interfaz.
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.crear-arbol', ['proyecto_id' => $otroProyecto->id, 'costo_presupuestado' => '100'], $sinCostos))->toThrow(ArbolRechazado::class, 'pide el permiso de ver costos');

    app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.desactivar-partida', $desactivar, $con);
    $arbol = app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.crear-arbol', ['proyecto_id' => $otroProyecto->id, 'costo_presupuestado' => '100'], $con);

    expect(PartidaControl::query()->findOrFail($a->partidas['PC-02'])->es_activa)->toBeFalse()
        ->and($arbol->costo_presupuestado)->toBe('100.00')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.crear-arbol', ['proyecto_id' => $otroProyecto->id, 'costo_presupuestado' => '100'], $con))->toThrow(ArbolRechazado::class, 'ya tiene su árbol');
});

test('el presupuestado de un árbol aprobado no se cambia ni por fuera del servicio: lo rechaza el modelo', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '1000.00', null]]);
    $monto = PresupuestadoDePartida::query()->where('partida_id', $a->partidas['PC-01'])->firstOrFail();

    expect(fn () => $monto->update(['monto' => '1.00']))->toThrow(EdicionRechazada::class)
        ->and(fn () => $monto->delete())->toThrow(EdicionRechazada::class);
});

test('la base no deja el presupuestado de una partida en el árbol de otro proyecto', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', null, null]], aprobar: false);
    $b = ArbolDePrueba::armar([['PC-01', 'Otra obra', null, null]], aprobar: false);

    expect(fn () => DB::transaction(fn () => DB::table('partida_presupuestos')->insert([
        'proyecto_id' => $a->proyectoId, 'partida_id' => $a->partidas['PC-01'], 'arbol_id' => $b->arbolId, 'monto' => 10,
        'created_by' => $a->responsable->id,
    ])))->toThrow(QueryException::class, 'foreign key');
});
