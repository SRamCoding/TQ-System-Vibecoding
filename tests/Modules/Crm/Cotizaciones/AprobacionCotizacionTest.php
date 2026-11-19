<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\TransicionesService;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * Escenario del v4 (parte 8, CRM; plan 2.22, D17.1): «Se solicita aprobación de COT-2026-0184 v2 con margen 19.4 % y
 * mínimo 22 % → va a la bandeja de Gerencia Comercial; no se puede enviar al cliente sin aprobación registrada». Aquí
 * es la v1 de una cotización de prueba con las líneas del diseño; la gerencia es una unidad inventada.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->cot = CotizacionesDePrueba::nueva();
});

/** La «Gerencia Comercial» de prueba, con su jefatura ocupada, y la regla de margen que la hace firmar. */
function acGerencia(): User
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => 'Gerencia de Ventas (prueba)', 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => 'Gerente de Ventas (prueba)', 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Cotización bajo el margen mínimo', 'cotizacion', DefinicionCotizacion::APROBAR, null, null, null, ["unidad:{$unidad->id}"], CondicionRegla::MargenBajoElMinimo));

    return User::factory()->create(['puesto_id' => $puesto->id]);
}

test('D17.1: con margen 19.40 % y mínimo 22 %, no se emite; la solicitud va a quien dice la regla; con su firma, se emite', function () {
    $gerente = acGerencia();
    $c = test()->cot->delDiseno();

    expect([$c->subtotal, $c->descuento_total, $c->igv, $c->total, $c->margen_porcentaje])->toBe(['51644.00', '4326.00', '9295.92', '60939.92', '19.40'])
        ->and(fn () => test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo))
        ->toThrow(TransicionRechazada::class, 'El margen (19.4 %) está bajo el mínimo (22.0 %): solicita la aprobación antes de emitirla.');

    $c = test()->cot->servicio()->solicitarAprobacion($c->id, $c->refresh()->version, 'Cliente estratégico', test()->cot->ejecutivo);

    $ronda = RondaAprobacion::query()->sole();
    expect($c->estado->codigo)->toBe(DefinicionCotizacion::POR_APROBAR)
        ->and([$c->margen_minimo, $c->margen_objetivo])->toBe(['22.00', '25.00'])
        ->and($ronda->monto_soles)->toBe('60939.92')
        ->and(app(AprobacionesService::class)->porFirmar($gerente))->toHaveCount(1)
        ->and(fn () => test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo))->toThrow(TransicionRechazada::class);

    app(AprobacionesService::class)->firmar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $gerente);
    $c->refresh();
    expect($c->estado->codigo)->toBe(DefinicionCotizacion::APROBADA);

    $c = test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo);
    expect($c->estado->codigo)->toBe(DefinicionCotizacion::EMITIDA)
        ->and($c->total)->toBe('60939.92');
});

test('sin una regla para el margen bajo el mínimo, la solicitud no se abre y lo dice', function () {
    $c = test()->cot->delDiseno();

    expect(fn () => test()->cot->servicio()->solicitarAprobacion($c->id, $c->version, null, test()->cot->ejecutivo))
        ->toThrow(AprobacionRechazada::class, '«solo si el margen queda bajo el mínimo»')
        ->and($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR);
});

test('con el margen en el mínimo o más no se pide aprobación: se emite directo', function () {
    acGerencia();
    $c = test()->cot->libre(test()->cot->crear(), 'Cableado', '10', '100.00', '78.00');

    expect($c->margen_porcentaje)->toBe('22.00')
        ->and(fn () => test()->cot->servicio()->solicitarAprobacion($c->id, $c->version, null, test()->cot->ejecutivo))
        ->toThrow(TransicionRechazada::class, 'El margen (22.0 %) no está bajo el mínimo (22.0 %): emítela sin aprobación.');
});

test('rechazar la aprobación exige comentario y la devuelve a borrador, editable', function () {
    $gerente = acGerencia();
    $c = test()->cot->delDiseno();
    $c = test()->cot->servicio()->solicitarAprobacion($c->id, $c->version, null, test()->cot->ejecutivo);
    $motivo = (int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', AprobacionesService::FAMILIA_OBSERVAR))->where('es_activo', true)->value('id');
    $observar = fn (string $comentario) => app(AprobacionesService::class)->observar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $gerente, $motivo, $comentario);

    expect(fn () => $observar('  '))->toThrow(AprobacionRechazada::class);

    $observar('Sube el precio del NVR');

    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR)
        ->and(test()->cot->libre($c, 'Capacitación', '1', '500.00', '100.00')->lineas)->toHaveCount(5);
});

test('una aprobada no se edita; vuelve a borrador solo con motivo, y entonces se edita', function () {
    $gerente = acGerencia();
    $c = test()->cot->delDiseno();
    $c = test()->cot->servicio()->solicitarAprobacion($c->id, $c->version, null, test()->cot->ejecutivo);
    app(AprobacionesService::class)->firmar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $gerente);
    $c->refresh();

    expect(fn () => test()->cot->libre($c, 'Capacitación', '1', '500.00', '100.00'))->toThrow(CotizacionRechazada::class, '(vuelve a borrador para cambiarla)')
        ->and(fn () => app(TransicionesService::class)->transicionar($c, DefinicionCotizacion::REABRIR, $c->version, test()->cot->ejecutivo))->toThrow(TransicionRechazada::class);

    $motivo = (int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', DefinicionCotizacion::CLAVE.'.reabrir'))->where('codigo', 'cambiar_precios')->value('id');
    app(TransicionesService::class)->transicionar($c, DefinicionCotizacion::REABRIR, $c->version, test()->cot->ejecutivo, new MotivoElegido($motivo));

    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR)
        ->and(test()->cot->libre($c, 'Capacitación', '1', '500.00', '100.00')->lineas)->toHaveCount(5);
});
