<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\EdicionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El presupuesto con APU (plan 2.9): armado, estados y versiones (D6), con y sin APU (D9), lo sin detallar (D10) y el
 * ajuste por redondeo (D5). Los números del flujo 1 están en PresupuestoFlujo1Test.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function prArmado(): ArmadoPresupuestoService
{
    return app(ArmadoPresupuestoService::class);
}

function prOfertar(Presupuesto $presupuesto): void
{
    $presupuesto->refresh();
    app(TransicionesService::class)->transicionar($presupuesto, 'ofertar', $presupuesto->version, PresupuestoDePrueba::jefatura());
}

/** Un presupuesto chico y completo: un título y una partida con un insumo. */
function prChico(): PresupuestoDePrueba
{
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('01', 'SUMINISTRO');
    $p->partida('01', '01.01', 'Cámara', 'NIU', '2', [
        new DatosLineaApu(PresupuestoDePrueba::insumo('CAM-X', 'Cámara de prueba', Insumo::MATERIAL, 'NIU'), cantidadNeta: '1', precio: '100'),
    ]);

    return $p;
}

test('los códigos: títulos NN o el del padre más .NN; partidas, el del título más .NN; ninguno se repite', function () {
    $p = PresupuestoDePrueba::nuevo();
    $id = $p->presupuesto->id;
    $p->titulo('01', 'CÁMARAS');
    $nvr = PresupuestoDePrueba::unidad('NIU');

    expect(fn () => prArmado()->agregarTitulo($id, null, '1', 'Mal'))->toThrow(PresupuestoRechazado::class, '«1» no sirve')
        ->and(fn () => prArmado()->agregarTitulo($id, $p->titulos['01'], '02.01', 'Otra rama'))->toThrow(PresupuestoRechazado::class, '«01.NN»')
        ->and(fn () => prArmado()->agregarPartida($id, $p->titulos['01'], '01', 'Mal', $nvr, '1'))->toThrow(PresupuestoRechazado::class, '«01.NN»');

    $p->partida('01', '01.01', 'Cámara', 'NIU', '1');

    expect(fn () => prArmado()->agregarTitulo($id, $p->titulos['01'], '01.01', 'Choca con la partida'))->toThrow(PresupuestoRechazado::class, 'Ya hay un título o una partida 01.01')
        ->and(fn () => prArmado()->agregarPartida($id, $p->titulos['01'], '01.01', 'Repetida', $nvr, '1'))->toThrow(PresupuestoRechazado::class, 'Ya hay');
});

test('con APU el P.U. sale del análisis y no se escribe; sin APU se escribe', function () {
    $p = prChico();

    expect(fn () => prArmado()->agregarPartida($p->presupuesto->id, $p->titulos['01'], '01.02', 'Con precio', PresupuestoDePrueba::unidad('NIU'), '1', '50'))
        ->toThrow(PresupuestoRechazado::class, 'sale del análisis');

    $sin = PresupuestoDePrueba::nuevo(usaApu: false);
    $sin->titulo('01', 'SUMINISTRO');
    $sin->partida('01', '01.01', 'Cámara', 'NIU', '3', precioUnitario: '120.505');

    // El P.U. va a 2 decimales (parámetro, como S10): 120.51 × 3 = 361.53.
    expect(PartidaDePresupuesto::query()->findOrFail($sin->partidas['01.01'])->only(['costo_unitario', 'parcial']))->toBe(['costo_unitario' => '120.5100', 'parcial' => '361.53'])
        ->and($sin->fresco()->costo_directo)->toBe('361.53')
        ->and(fn () => prArmado()->agregarLineaApu($sin->partidas['01.01'], new DatosLineaApu(PresupuestoDePrueba::insumo('CAM-X', 'Cámara', Insumo::MATERIAL, 'NIU'), cantidadNeta: '1', precio: '1'), PartidaDePresupuesto::query()->findOrFail($sin->partidas['01.01'])->version))
        ->toThrow(PresupuestoRechazado::class, 'va sin APU');
});

test('el precio inicial es el de referencia; sin referencia ni sugerencia, hay que escribirlo', function () {
    $p = prChico();
    $tecnico = PresupuestoDePrueba::insumo('MO-T', 'Técnico', Insumo::MANO_OBRA, 'HUR', '18.50');
    $ayudante = PresupuestoDePrueba::insumo('MO-A', 'Ayudante', Insumo::MANO_OBRA, 'HUR');
    $partida = fn () => PartidaDePresupuesto::query()->findOrFail($p->partidas['01.01']);

    $linea = prArmado()->agregarLineaApu($partida()->id, new DatosLineaApu($tecnico, rendimiento: '4', unidadRendimiento: 'hora'), $partida()->version);

    expect([$linea->precio, $linea->cantidad, $linea->parcial])->toBe(['18.5000', '0.25000', '4.6250'])
        ->and(fn () => prArmado()->agregarLineaApu($partida()->id, new DatosLineaApu($ayudante, rendimiento: '4', unidadRendimiento: 'hora'), $partida()->version))
        ->toThrow(PresupuestoRechazado::class, 'escribe el precio')
        ->and(fn () => prArmado()->agregarLineaApu($partida()->id, new DatosLineaApu($tecnico, rendimiento: '4', unidadRendimiento: 'hora'), $partida()->version))
        ->toThrow(PresupuestoRechazado::class, 'ya está en el análisis');

    // 100 + 4.625 → P.U. 104.63 (a 2), × 2 = 209.26.
    expect($partida()->only(['costo_unitario', 'parcial']))->toBe(['costo_unitario' => '104.6300', 'parcial' => '209.26']);
});

test('editar una línea del APU recalcula la partida y los totales; con una versión vieja no pasa', function () {
    $p = prChico();
    $linea = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['01.01'])->firstOrFail();

    prArmado()->editarLineaApu($linea->id, new DatosLineaApu($linea->insumo_id, cantidadNeta: '1', desperdicioPct: '10', precio: '100'), $linea->version);

    expect(PartidaDePresupuesto::query()->findOrFail($p->partidas['01.01'])->parcial)->toBe('220.00')
        ->and($p->fresco()->costo_directo)->toBe('220.00')
        ->and(fn () => prArmado()->editarLineaApu($linea->id, new DatosLineaApu($linea->insumo_id, cantidadNeta: '2', precio: '100'), $linea->version))
        ->toThrow(VersionDesactualizada::class);

    prArmado()->quitarLineaApu($linea->id, $linea->version + 1);

    expect($p->fresco()->costo_directo)->toBe('0.00');
});

test('D5: con metrado 12.345 la lista lleva el ajuste por redondeo a la vista, con su partida', function () {
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('02', 'CABLEADO');
    $p->partida('02', '02.01', 'Cableado', 'MTR', '12.345', [
        new DatosLineaApu(PresupuestoDePrueba::insumo('CAB-Y', 'Cable', Insumo::MATERIAL, 'MTR'), cantidadNeta: '1', precio: '1.02'),
        new DatosLineaApu(PresupuestoDePrueba::insumo('MO-Y', 'Técnico', Insumo::MANO_OBRA, 'HUR'), rendimiento: '50', unidadRendimiento: 'hora', precio: '15'),
    ]);

    // P.U. 1.02 + 0.30 = 1.32; 12.345 × 1.32 = 16.30; los insumos aportan 12.59 + 3.70 = 16.29.
    $lista = app(ConsultaPresupuestos::class)->listaDeInsumos($p->presupuesto->id);

    expect($p->fresco()->costo_directo)->toBe('16.30')
        ->and(array_map(fn ($i) => [$i->codigo, $i->monto], $lista->insumos))->toBe([['MO-Y', '3.70'], ['CAB-Y', '12.59']])
        ->and($lista->ajuste)->toBe('0.01')
        ->and(array_map(fn ($a) => [$a->codigo, $a->descripcion, $a->metrado, $a->unidad, $a->monto], $lista->ajustes))->toBe([['02.01', 'Cableado', '12.345', 'm', '0.01']]);
});

test('D6: borrador → ofertado → aprobado; el ofertado no se edita', function () {
    $p = prChico();
    prOfertar($p->presupuesto);

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::OFERTADO)
        ->and(fn () => prArmado()->agregarTitulo($p->presupuesto->id, null, '02', 'Tarde'))->toThrow(PresupuestoRechazado::class, 'está ofertado: no se edita');

    // Ni por el modelo, sin el servicio.
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['01.01']);
    $partida->metrado = '5';

    expect(fn () => $partida->save())->toThrow(EdicionRechazada::class);

    $proyecto = PresupuestoDePrueba::proyecto();
    prArmado()->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    expect($p->fresco()->only(['proyecto_id', 'es_vigente']))->toBe(['proyecto_id' => $proyecto, 'es_vigente' => true])
        ->and($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::APROBADO);
});

test('D6: borrador → aprobado directo, para proyectos sin oferta; incompleto no pasa', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $vacio = PresupuestoDePrueba::nuevo();

    expect(fn () => prArmado()->traerAlProyecto($vacio->presupuesto->id, $proyecto, $vacio->fresco()->version, PresupuestoDePrueba::jefatura()))
        ->toThrow(TransicionRechazada::class, 'no tiene partidas');

    $vacio->titulo('01', 'SERVICIOS');
    $vacio->partida('01', '01.01', 'Sin análisis', 'GLB', '1');

    expect(fn () => prOfertar($vacio->presupuesto))->toThrow(TransicionRechazada::class, 'la partida 01.01 no tiene análisis de precios unitarios');

    $p = prChico();
    prArmado()->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::APROBADO);
});

test('D6: traer al proyecto pide el permiso de la transición y un proyecto que acepte costos', function () {
    $p = prChico();
    $proyecto = PresupuestoDePrueba::proyecto();

    expect(fn () => prArmado()->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, User::factory()->create()))
        ->toThrow(TransicionRechazada::class);

    $anulado = PresupuestoDePrueba::proyecto();
    DB::table('proyectos')->where('id', $anulado)->update(['estado_id' => DB::table('estados')->where('documento_type', 'proyecto')->where('codigo', 'anulado')->value('id')]);

    expect(fn () => prArmado()->traerAlProyecto($p->presupuesto->id, $anulado, $p->fresco()->version, PresupuestoDePrueba::jefatura()))
        ->toThrow(PresupuestoRechazado::class, 'no recibe presupuesto')
        ->and($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::BORRADOR);
});

test('D6: la versión nueva copia todo; al aprobarse, la anterior pasa a reemplazada', function () {
    $p = prChico();
    $proyecto = PresupuestoDePrueba::proyecto();
    prArmado()->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    $v2 = prArmado()->nuevaVersion($p->presupuesto->id, $p->usuario);

    expect([$v2->numero, $v2->numero_version, $v2->proyecto_id, $v2->es_vigente, $v2->costo_directo])->toBe([$p->presupuesto->numero, 2, $proyecto, false, '200.00'])
        ->and(PartidaDePresupuesto::query()->where('presupuesto_id', $v2->id)->count())->toBe(1)
        ->and(fn () => prArmado()->nuevaVersion($p->presupuesto->id, $p->usuario))->toThrow(PresupuestoRechazado::class, 'ya tiene la versión 2 en curso');

    // La adenda agrega una partida en la versión 2; la 1 sigue vigente hasta aprobar la 2.
    $partida = PartidaDePresupuesto::query()->where('presupuesto_id', $v2->id)->firstOrFail();
    prArmado()->editarPartida($partida->id, 'Cámara', $partida->unidad_medida_id, '3', null, $partida->version);

    expect($p->fresco()->es_vigente)->toBeTrue()
        ->and(fn () => prArmado()->traerAlProyecto($v2->id, PresupuestoDePrueba::proyecto(), $v2->refresh()->version, PresupuestoDePrueba::jefatura()))
        ->toThrow(PresupuestoRechazado::class, 'va a ese');

    prArmado()->traerAlProyecto($v2->id, $proyecto, $v2->refresh()->version, PresupuestoDePrueba::jefatura());

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::REEMPLAZADO)
        ->and($p->fresco()->es_vigente)->toBeFalse()
        ->and([$v2->refresh()->es_vigente, $v2->costo_directo])->toBe([true, '300.00'])
        ->and(app(PresupuestosService::class)->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::REEMPLAZADO);
});

test('plan 2.22, D8: la versión nueva sale también de uno ofertado; al traer la nueva al proyecto, la ofertada pasa a reemplazada', function () {
    $p = prChico();
    prOfertar($p->presupuesto);

    $v2 = prArmado()->nuevaVersion($p->presupuesto->id, $p->usuario);

    expect([$v2->numero, $v2->numero_version, $v2->estado->codigo])->toBe([$p->presupuesto->numero, 2, DefinicionPresupuesto::BORRADOR])
        ->and($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::OFERTADO)
        ->and(fn () => prArmado()->nuevaVersion($p->presupuesto->id, $p->usuario))->toThrow(PresupuestoRechazado::class, 'ya tiene la versión 2 en curso');

    prArmado()->traerAlProyecto($v2->id, PresupuestoDePrueba::proyecto(), $v2->refresh()->version, PresupuestoDePrueba::jefatura());

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::REEMPLAZADO)
        ->and($v2->refresh()->estado->codigo)->toBe(DefinicionPresupuesto::APROBADO);
});

test('plan 2.22, D8: de un borrador o un anulado no sale versión nueva', function () {
    $p = prChico();

    expect(fn () => prArmado()->nuevaVersion($p->presupuesto->id, $p->usuario))
        ->toThrow(PresupuestoRechazado::class, 'La versión nueva sale del presupuesto aprobado u ofertado');
});

test('D6: un proyecto con un presupuesto vigente no recibe otro distinto, y la base no admite dos vigentes', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $uno = prChico();
    prArmado()->traerAlProyecto($uno->presupuesto->id, $proyecto, $uno->fresco()->version, PresupuestoDePrueba::jefatura());
    $otro = prChico();

    expect(fn () => prArmado()->traerAlProyecto($otro->presupuesto->id, $proyecto, $otro->fresco()->version, PresupuestoDePrueba::jefatura()))
        ->toThrow(PresupuestoRechazado::class, "ya tiene vigente el presupuesto {$uno->presupuesto->numero}");

    expect(fn () => DB::table('presupuestos')->where('id', $otro->presupuesto->id)->update(['proyecto_id' => $proyecto, 'es_vigente' => true]))
        ->toThrow(QueryException::class, 'presupuestos_un_vigente_por_proyecto');
});

test('D6: anular, solo en borrador y con motivo', function () {
    $p = prChico();
    $motivo = new MotivoElegido((int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', 'erp.presupuestos.presupuesto.anular'))->where('es_otro', true)->value('id'), 'Se desistió de la oferta');
    $presupuesto = $p->fresco();

    expect(fn () => app(TransicionesService::class)->transicionar($presupuesto, 'anular', $presupuesto->version, PresupuestoDePrueba::jefatura()))
        ->toThrow(TransicionRechazada::class);

    app(TransicionesService::class)->transicionar($presupuesto, 'anular', $presupuesto->version, PresupuestoDePrueba::jefatura(), $motivo);

    $ofertado = prChico();
    prOfertar($ofertado->presupuesto);
    $ofertado = $ofertado->fresco();

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::ANULADO)
        ->and(fn () => app(TransicionesService::class)->transicionar($ofertado, 'anular', $ofertado->version, PresupuestoDePrueba::jefatura(), $motivo))
        ->toThrow(TransicionRechazada::class);
});

test('D9: de sin APU a con APU, cada partida recibe «Precio sin detallar» por su P.U.; de vuelta, con confirmación', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    $p->titulo('01', 'OBRAS');
    $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');
    $p->partida('01', '01.02', 'Cámaras', 'NIU', '4', precioUnitario: '250.50');

    prArmado()->cambiarUsaApu($p->presupuesto->id, true, false, $p->fresco()->version);
    $lineas = LineaApu::query()->whereIn('presupuesto_partida_id', $p->partidas)->with('insumo')->get();

    expect($p->fresco()->only(['usa_apu', 'costo_directo']))->toBe(['usa_apu' => true, 'costo_directo' => '9502.00'])
        ->and($lineas->map(fn ($l) => [$l->insumo->codigo, $l->insumo->es_sistema, $l->cantidad, $l->precio])->all())
        ->toBe([['SIN-DETALLE', true, '1.00000', '8500.0000'], ['SIN-DETALLE', true, '1.00000', '250.5000']]);

    // D10: se puede ofertar con partidas sin detallar; la lista lo muestra aparte, y el resumen dice cuánto.
    $lista = app(ConsultaPresupuestos::class)->listaDeInsumos($p->presupuesto->id);

    expect([$lista->insumos, $lista->sinDetallar, $lista->total])->toBe([[], '9502.00', '9502.00'])
        ->and(app(PresupuestosService::class)->resumen($p->presupuesto->id)->sinDetallar)->toBe('9502.00');

    // Se detalla una partida: un insumo real en su análisis, y se quita el sin detallar.
    $cam = PresupuestoDePrueba::insumo('CAM-Z', 'Cámara', Insumo::MATERIAL, 'NIU');
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['01.02']);
    prArmado()->agregarLineaApu($partida->id, new DatosLineaApu($cam, cantidadNeta: '1', precio: '260'), $partida->version);
    $sin = LineaApu::query()->where('presupuesto_partida_id', $partida->id)->whereHas('insumo', fn ($q) => $q->where('es_sistema', true))->firstOrFail();
    prArmado()->quitarLineaApu($sin->id, $sin->version);

    expect(app(PresupuestosService::class)->resumen($p->presupuesto->id)->sinDetallar)->toBe('8500.00')
        ->and($p->fresco()->costo_directo)->toBe('9540.00');

    expect(fn () => prArmado()->cambiarUsaApu($p->presupuesto->id, false, false, $p->fresco()->version))->toThrow(PresupuestoRechazado::class, 'confírmalo');

    prArmado()->cambiarUsaApu($p->presupuesto->id, false, true, $p->fresco()->version);

    expect(LineaApu::query()->whereIn('presupuesto_partida_id', $p->partidas)->count())->toBe(0)
        ->and(PartidaDePresupuesto::query()->findOrFail($p->partidas['01.02'])->costo_unitario)->toBe('260.0000')
        ->and($p->fresco()->only(['usa_apu', 'costo_directo']))->toBe(['usa_apu' => false, 'costo_directo' => '9540.00']);

    prOfertar($p->presupuesto);

    expect(fn () => prArmado()->cambiarUsaApu($p->presupuesto->id, true, false, $p->fresco()->version))->toThrow(PresupuestoRechazado::class, 'no se edita');
});

test('D10: con una partida sin detallar se puede ofertar', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    $p->titulo('01', 'OBRAS');
    $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');
    prArmado()->cambiarUsaApu($p->presupuesto->id, true, false, $p->fresco()->version);

    prOfertar($p->presupuesto);

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::OFERTADO);
});

test('D5: los decimales del P.U. quedan como foto del presupuesto: cambiar el parámetro no toca los ya creados', function () {
    $antes = PresupuestoDePrueba::nuevo(usaApu: false);
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto(ConsultaPresupuestos::PARAMETRO_DECIMALES, '3', $parametros->ficha(ConsultaPresupuestos::PARAMETRO_DECIMALES)['version']);
    $despues = PresupuestoDePrueba::nuevo(usaApu: false);

    foreach ([$antes, $despues] as $p) {
        $p->titulo('01', 'SUMINISTRO');
        $p->partida('01', '01.01', 'Cámara', 'NIU', '3', precioUnitario: '120.505');
    }

    expect([$antes->fresco()->decimales_precio_unitario, $antes->fresco()->costo_directo])->toBe([2, '361.53'])
        ->and([$despues->fresco()->decimales_precio_unitario, $despues->fresco()->costo_directo])->toBe([3, '361.52'])
        ->and(app(PresupuestosService::class)->resumen($despues->presupuesto->id)->partidas[0]->precioUnitario)->toBe('120.505');
});

test('la oportunidad de un presupuesto tiene que existir: la base lo garantiza con su llave', function () {
    $p = prChico();

    expect(fn () => DB::table('presupuestos')->where('id', $p->presupuesto->id)->update(['oportunidad_id' => 999999]))
        ->toThrow(QueryException::class, 'presupuestos_oportunidad_id_foreign');
});
