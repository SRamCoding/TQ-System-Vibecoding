<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\EmbudoDePrueba;

/*
 * Escenario del v4 (parte 8, CRM; plan 2.22, D17.2 y D11): «se arrastra de calificada a propuesta enviada sin
 * cotización emitida → no permite avanzar; muestra el requisito faltante». Y ganar exige una aceptada. Los dos se
 * apagan con su parámetro, sin código.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
    EmbudoDePrueba::permisos(test()->cot);
    EmbudoDePrueba::calificada(test()->cot);
});

test('D17.2: sin cotización emitida no se envía la propuesta y lo dice; con una emitida, pasa', function () {
    test()->cot->libre(test()->cot->crear(), 'Cableado', '1', '100', '50');

    expect(fn () => EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta'))->toThrow(TransicionRechazada::class, 'Para avanzar falta: Una cotización emitida.')
        ->and(app(RequisitosDeEtapa::class)->faltantes(test()->cot->oportunidad->id, 'propuesta_enviada'))->toBe(['Una cotización emitida']);

    test()->cot->emitida();
    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('propuesta_enviada');
});

test('una emitida que después venció o rechazaron sigue contando para la propuesta', function () {
    test()->cot->emitida();
    test()->travelTo(CarbonImmutable::parse('2026-12-01 10:00', 'America/Lima'));
    test()->artisan('crm:vencer-cotizaciones');
    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('propuesta_enviada');
});

test('sin cotización aceptada no se gana; con una aceptada, sí', function () {
    $c = test()->cot->emitida();
    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');
    EmbudoDePrueba::mover(test()->cot, 'negociar');
    $ganar = fn () => app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse('2026-11-12'), null, null, test()->cot->ejecutivo);

    expect($ganar)->toThrow(Exception::class);
    expect(fn () => app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse('2026-11-12'), '118.00', null, test()->cot->ejecutivo))
        ->toThrow(TransicionRechazada::class, 'Una cotización aceptada, con su documento de respaldo');

    test()->cot->servicio()->aceptar($c->id, $c->refresh()->version, CarbonImmutable::parse('2026-11-12'), TipoRespaldo::OrdenCompra, 'OC-1', null, null, test()->cot->ejecutivo);
    $ganar();

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('ganada');
});

test('con los parámetros apagados no se exige ninguna de las dos', function () {
    $parametros = app(ParametrosService::class);
    foreach ([CotizacionesServiceProvider::PARAMETRO_EXIGIR_EMITIDA, CotizacionesServiceProvider::PARAMETRO_EXIGIR_ACEPTADA] as $nombre) {
        $parametros->cambiar($nombre, false, $parametros->ficha($nombre)['version']);
    }

    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');
    EmbudoDePrueba::mover(test()->cot, 'negociar');
    app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse('2026-11-12'), '5000.00', null, test()->cot->ejecutivo);

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('ganada');
});
