<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\EmbudoDePrueba;

/*
 * Plan 2.22, D13: con una cotización aceptada, la oportunidad no se anula ni se pierde (primero se anula la
 * aceptación); y la aceptación no se anula si la oportunidad ya está ganada.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
    EmbudoDePrueba::permisos(test()->cot);
    $c = test()->cot->emitida();
    test()->aceptada = test()->cot->servicio()->aceptar($c->id, $c->version, CarbonImmutable::parse('2026-11-12'), TipoRespaldo::Correo, 'Correo del 12/11', null, null, test()->cot->ejecutivo);
});

function icMotivo(string $familia): MotivoElegido
{
    return new MotivoElegido((int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', $familia))->where('es_otro', false)->value('id'));
}

test('con una aceptada la oportunidad no se anula ni se pierde', function () {
    $o = test()->cot->oportunidad->refresh();

    expect(fn () => app(TransicionesService::class)->transicionar($o, 'anular', $o->version, test()->cot->ejecutivo, icMotivo(DefinicionOportunidad::CLAVE.'.anular')))
        ->toThrow(TransicionRechazada::class, 'la cotización '.test()->aceptada->numero.' v1 (aceptada: anula la aceptación primero)')
        ->and(fn () => app(TransicionesService::class)->transicionar($o->refresh(), 'perder', $o->version, test()->cot->ejecutivo, icMotivo(DefinicionOportunidad::CLAVE.'.perder')))
        ->toThrow(TransicionRechazada::class, 'aceptada: anula la aceptación primero');
});

test('anulada la aceptación, la oportunidad se puede perder', function () {
    $gerencia = User::factory()->create();
    $gerencia->givePermissionTo(Permission::findOrCreate(DefinicionCotizacion::CLAVE.'.anular_aceptacion'));
    $c = test()->aceptada->refresh();
    test()->cot->servicio()->anularAceptacion($c->id, $c->version, icMotivo(DefinicionCotizacion::CLAVE.'.anular_aceptacion'), null, $gerencia);

    $o = test()->cot->oportunidad->refresh();
    app(TransicionesService::class)->transicionar($o, 'perder', $o->version, test()->cot->ejecutivo, icMotivo(DefinicionOportunidad::CLAVE.'.perder'));

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('perdida');
});
