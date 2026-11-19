<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Enums\OrigenLinea;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Tests\Costuras\PresupuestoDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * Versiones de la cotización (plan 2.22, D2, D5, D7 y D8): la versión nueva sale de la última que ya salió al cliente,
 * copia todo, y al emitirse reemplaza a la anterior; una sola aceptada; el presupuesto enlazado se relee en borrador y
 * se oferta al emitir (C8, de verdad).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
    // Un presupuesto de obra deja poco margen: aquí se mide contra 5 % para emitir sin aprobación.
    $ficha = app(ParametrosService::class)->ficha(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO);
    app(ParametrosService::class)->cambiarDeDinero(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO, '5.00', $ficha['version']);
});

function cvNueva(int $id): mixed
{
    return test()->cot->servicio()->nuevaVersion($id, CotizacionesDePrueba::motivoVersion(), 'Pidió más cámaras', test()->cot->ejecutivo);
}

/** Un presupuesto chico y completo de la oportunidad de prueba: costo directo 200.00, GG 10 % y utilidad 8 % (sobre el CD). */
function cvPresupuesto(string $precio = '100'): PresupuestoDePrueba
{
    $p = PresupuestoDePrueba::nuevo(oportunidadId: test()->cot->oportunidad->id);
    $p->titulo('01', 'SUMINISTRO');
    $p->partida('01', '01.01', 'Cámara', 'NIU', '2', [
        new DatosLineaApu(PresupuestoDePrueba::insumo('CAM-CV', 'Cámara de prueba', Insumo::MATERIAL, 'NIU'), cantidadNeta: '1', precio: $precio),
    ]);

    return $p;
}

test('la versión nueva copia encabezado, condiciones y líneas, con su motivo; al emitirse, la anterior queda reemplazada', function () {
    $v1 = test()->cot->emitida();
    $v2 = cvNueva($v1->id);

    expect([$v2->numero, $v2->numero_version, $v2->estado->codigo, $v2->lineas->count(), $v2->total, $v2->motivo_version_detalle])
        ->toBe([$v1->numero, 2, DefinicionCotizacion::BORRADOR, 1, $v1->total, 'Pidió más cámaras'])
        ->and($v2->fecha_emision)->toBeNull()
        ->and(fn () => cvNueva($v1->id))->toThrow(CotizacionRechazada::class, "La versión nueva sale de la última: {$v1->numero} v2.")
        ->and(fn () => cvNueva($v2->id))->toThrow(CotizacionRechazada::class, 'la versión nueva sale de una emitida, rechazada o vencida');

    $v2 = test()->cot->libre($v2, 'Cámara adicional', '2', '300.00', '150.00');
    test()->cot->servicio()->emitir($v2->id, $v2->version, null, test()->cot->ejecutivo);

    expect($v1->refresh()->estado->codigo)->toBe(DefinicionCotizacion::REEMPLAZADA)
        ->and($v2->refresh()->estado->codigo)->toBe(DefinicionCotizacion::EMITIDA);
});

test('la versión nueva lleva un motivo de su lista, y con «Otro», su detalle', function () {
    $v1 = test()->cot->emitida();

    expect(fn () => test()->cot->servicio()->nuevaVersion($v1->id, 999999, null, test()->cot->ejecutivo))->toThrow(CotizacionRechazada::class, 'Elige el motivo')
        ->and(fn () => test()->cot->servicio()->nuevaVersion($v1->id, CotizacionesDePrueba::motivo('crm.cotizaciones.nueva-version', 'otro'), ' ', test()->cot->ejecutivo))
        ->toThrow(CotizacionRechazada::class, 'Con «Otro», escribe el motivo.');
});

test('una rechazada también da versión nueva; una aceptada no', function () {
    $rechazada = test()->cot->emitida();
    app(TransicionesService::class)->transicionar($rechazada, DefinicionCotizacion::RECHAZAR, $rechazada->version, test()->cot->ejecutivo,
        new MotivoElegido(CotizacionesDePrueba::motivo(DefinicionCotizacion::CLAVE.'.rechazar', 'plazo')));
    $aceptada = test()->cot->emitida();
    test()->cot->servicio()->aceptar($aceptada->id, $aceptada->version, CarbonImmutable::parse('2026-11-12'), TipoRespaldo::Contrato, 'CT-1', null, null, test()->cot->ejecutivo);

    expect(cvNueva($rechazada->id)->numero_version)->toBe(2)
        ->and(fn () => cvNueva($aceptada->id))->toThrow(CotizacionRechazada::class, 'está aceptada');
});

test('C8 (P4): el presupuesto enlazado da una línea con precio CD + GG + utilidad y costo CD + GG; se relee en borrador', function () {
    $p = cvPresupuesto();
    $c = test()->cot->crear();
    $c = test()->cot->servicio()->enlazarPresupuesto($c->id, $c->version, $p->presupuesto->id, '0', test()->cot->ejecutivo);
    $linea = $c->lineas->sole();

    // CD 200.00, GG 20.00, utilidad 16.00.
    expect([$linea->origen, $linea->precio_unitario, $linea->costo_unitario, $c->subtotal, $c->margen_porcentaje, $c->presupuesto_id])
        ->toBe([OrigenLinea::Presupuesto, '236.0000', '220.0000', '236.00', '6.78', $p->presupuesto->id]);

    $partida = $p->presupuesto->partidas()->sole();
    app(ArmadoPresupuestoService::class)->editarPartida($partida->id, 'Cámara', $partida->unidad_medida_id, '3', null, $partida->version);
    $c = test()->cot->servicio()->emitir($c->id, $c->refresh()->version, null, test()->cot->ejecutivo);

    expect([$c->lineas->sole()->precio_unitario, $c->subtotal])->toBe(['354.0000', '354.00'])
        ->and(app(PresupuestosService::class)->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::OFERTADO);
});

test('P3: otros costos para la versión 2 = versión nueva del presupuesto, que se enlaza; la ofertada queda reemplazada al emitir', function () {
    $p = cvPresupuesto();
    $v1 = test()->cot->crear();
    $v1 = test()->cot->servicio()->enlazarPresupuesto($v1->id, $v1->version, $p->presupuesto->id, '0', test()->cot->ejecutivo);
    $v1 = test()->cot->servicio()->emitir($v1->id, $v1->version, null, test()->cot->ejecutivo);

    $v2 = cvNueva($v1->id);
    expect($v2->lineas->sole()->precio_unitario)->toBe('236.0000');

    // El presupuesto ofertado no se cambia: su versión 2 lleva los costos nuevos.
    $pv2 = app(ArmadoPresupuestoService::class)->nuevaVersion($p->presupuesto->id, $p->usuario);
    $partida = $pv2->partidas()->sole();
    app(ArmadoPresupuestoService::class)->editarPartida($partida->id, 'Cámara', $partida->unidad_medida_id, '4', null, $partida->version);
    $v2 = test()->cot->servicio()->enlazarPresupuesto($v2->id, $v2->version, $pv2->id, '0', test()->cot->ejecutivo);
    $v2 = test()->cot->servicio()->emitir($v2->id, $v2->version, null, test()->cot->ejecutivo);

    expect([$v2->presupuesto_id, $v2->lineas->sole()->precio_unitario])->toBe([$pv2->id, '472.0000'])
        ->and(app(PresupuestosService::class)->resumen($pv2->id)->estado)->toBe(DefinicionPresupuesto::OFERTADO)
        ->and(app(PresupuestosService::class)->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::REEMPLAZADO)
        ->and($v1->refresh()->estado->codigo)->toBe(DefinicionCotizacion::REEMPLAZADA);
});

test('el presupuesto de otra oportunidad, en otra moneda o reemplazado no se enlaza; uno incompleto no deja emitir', function () {
    $c = test()->cot->crear();
    $ajeno = PresupuestoDePrueba::nuevo();
    $incompleto = PresupuestoDePrueba::nuevo(oportunidadId: test()->cot->oportunidad->id);

    expect(fn () => test()->cot->servicio()->enlazarPresupuesto($c->id, $c->version, $ajeno->presupuesto->id, '0', test()->cot->ejecutivo))
        ->toThrow(CotizacionRechazada::class, 'es de otra oportunidad');

    $c = test()->cot->servicio()->enlazarPresupuesto($c->id, $c->version, $incompleto->presupuesto->id, '0', test()->cot->ejecutivo);
    $c = test()->cot->libre($c, 'Otra', '1', '100', '10');

    expect(fn () => test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo))->toThrow(Exception::class, 'El presupuesto está incompleto')
        ->and($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR);
});
