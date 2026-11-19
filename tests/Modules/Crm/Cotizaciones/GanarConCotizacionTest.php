<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Oportunidades\Events\OportunidadGanada;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\OportunidadRechazada;
use App\Modules\Erp\Presupuestos\Services\EnMemoria\PresupuestosEnMemoria;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Costuras\PresupuestoDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\EmbudoDePrueba;

/*
 * Escenario del v4 (parte 8, Frontera, la mitad del CRM; plan 2.22, D17.3 y D12): COT-2026-0190 v1 enlazada al
 * presupuesto PRE-2026-031 del flujo 1 (versión falsa de C8: CD 17 750.00, GG 1 775.00, utilidad 1 420.00) da
 * 20 945.00 + IGV 3 770.10 = 24 715.10; aceptada, al ganar `OportunidadGanada` lleva esa cotización, ese monto en PEN,
 * sus condiciones y el presupuesto. El contrato lo prueba el área 3.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
    EmbudoDePrueba::permisos(test()->cot);
    EmbudoDePrueba::calificada(test()->cot);
    // Una obra deja poco margen: aquí el mínimo es 5 % para emitir sin aprobación.
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDeDinero(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO, '5.00', $parametros->ficha(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO)['version']);
    // La serie ya va por la 189 este año.
    DB::table('series_documento')->insert(['documento_type' => 'cotizacion', 'serie' => 'COT', 'ambito' => '', 'periodo' => '2026', 'ultimo_numero' => 189, 'created_by' => test()->cot->ejecutivo->id]);
});

/** La fila existe de verdad (la llave lo pide); sus números los da la versión falsa de C8. */
function gcFalsa(int $oportunidadId): PresupuestosEnMemoria
{
    test()->presupuestoId = PresupuestoDePrueba::nuevo(oportunidadId: $oportunidadId)->presupuesto->id;
    $falsa = new PresupuestosEnMemoria;
    $falsa->agregar(new ResumenPresupuesto(test()->presupuestoId, 'PRE-2026-031', 1, 'Videovigilancia de plaza', 'borrador', 'Borrador', false, null, $oportunidadId, 'PEN', true,
        '10.00', '8.00', '18.00', '17750.00', '1775.00', '1420.00', '3770.10', '24715.10', '0.00', []));
    app()->instance(PresupuestosService::class, $falsa);

    return $falsa;
}

/** Emitida y aceptada, con sus condiciones. */
function gcAceptada(): mixed
{
    $cot = test()->cot;
    $formaPago = FormaPago::query()->orderBy('id')->firstOrFail();
    $c = $cot->crear(new DatosCotizacion(plazoEntregaDias: 45, formaPagoId: $formaPago->id, formaPagoDetalle: '50 % adelanto', garantiaMeses: 24));
    $c = $cot->servicio()->enlazarPresupuesto($c->id, $c->version, test()->presupuestoId, '0', $cot->ejecutivo);
    $c = $cot->servicio()->emitir($c->id, $c->version, null, $cot->ejecutivo);

    return $cot->servicio()->aceptar($c->id, $c->version, CarbonImmutable::parse('2026-11-12'), TipoRespaldo::OrdenServicio, 'OS-2026-0044', null, null, $cot->ejecutivo);
}

test('D17.3: COT-2026-0190 v1 da 20 945.00 / IGV 3 770.10 / 24 715.10, oferta el presupuesto, y al ganar el ERP recibe todo', function () {
    $falsa = gcFalsa(test()->cot->oportunidad->id);
    Event::fake([OportunidadGanada::class]);
    $c = gcAceptada();

    expect([$c->numero, $c->numero_version, $c->subtotal, $c->igv, $c->total])->toBe(['COT-2026-0190', 1, '20945.00', '3770.10', '24715.10'])
        ->and($falsa->ofertados)->toBe([test()->presupuestoId]);

    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');
    EmbudoDePrueba::mover(test()->cot, 'negociar');
    app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse('2026-11-12'), null, null, test()->cot->ejecutivo);

    $forma = FormaPago::query()->orderBy('id')->firstOrFail()->nombre;
    Event::assertDispatched(OportunidadGanada::class, fn (OportunidadGanada $e): bool => $e->oportunidadId === test()->cot->oportunidad->id
        && $e->cotizacionId === $c->id && $e->monto === '24715.10' && $e->moneda === 'PEN'
        && $e->plazo === '45 días calendario' && $e->formaPago === "{$forma} · 50 % adelanto" && $e->garantia === '24 meses' && $e->presupuestoId === test()->presupuestoId);
    expect((string) test()->cot->oportunidad->refresh()->monto_estimado)->toBe('24715.10');
});

test('P5: con cotización aceptada no se gana con otro monto', function () {
    gcFalsa(test()->cot->oportunidad->id);
    gcAceptada();
    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');
    EmbudoDePrueba::mover(test()->cot, 'negociar');

    expect(fn () => app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse('2026-11-12'), '30000.00', null, test()->cot->ejecutivo))
        ->toThrow(OportunidadRechazada::class, 'El monto final es el total de la cotización aceptada COT-2026-0190 v1 (PEN 24715.10).');
});

test('C3: el resumen de la cotización para Contratos lleva sus condiciones y líneas, sin costo ni margen', function () {
    gcFalsa(test()->cot->oportunidad->id);
    $c = gcAceptada();
    $r = test()->cot->servicio()->resumen($c->id);

    expect([$r->identificador, $r->estado, $r->moneda, $r->total, $r->esAceptada, $r->plazo, $r->garantia, $r->presupuestoId])
        ->toBe(['COT-2026-0190 v1', 'aceptada', 'PEN', '24715.10', true, '45 días calendario', '24 meses', test()->presupuestoId])
        ->and($r->lineas)->toHaveCount(1)
        ->and(array_keys($r->lineas[0]))->not->toContain('costo_unitario');
});
