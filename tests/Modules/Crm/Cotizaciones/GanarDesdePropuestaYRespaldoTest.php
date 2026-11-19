<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Cotizaciones\Fixtures\EmbudoDePrueba;

/*
 * Plan 2.22b, lo que pasó de la 2.22a: se gana desde «Propuesta enviada» (la transición es dato; la ficha, el tablero y
 * la faja lo leen de ahí), con la aceptada que exige el requisito; y el archivo del respaldo de la aceptación se sube
 * desde la ficha y queda adjunto a la cotización (el servicio comprueba que un adjunto elegido sea suyo).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Storage::fake(AdjuntosService::DISCO);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
    EmbudoDePrueba::permisos(test()->cot);
    test()->cot->ejecutivo->givePermissionTo([
        Permission::findOrCreate(CotizacionesServiceProvider::PERMISO_VER),
        Permission::findOrCreate(OportunidadesServiceProvider::PERMISO_VER),
    ]);
    test()->actingAs(test()->cot->ejecutivo);
});

/** Un PDF de prueba en un archivo temporal, como llega sin interfaz. */
function grArchivo(string $nombre): ArchivoSubido
{
    $ruta = tempnam(sys_get_temp_dir(), 'grs');
    file_put_contents($ruta, "%PDF-1.4\n%prueba\n");

    return new ArchivoSubido($ruta, $nombre);
}

test('desde «Propuesta enviada», con la cotización aceptada, se gana; la ficha ofrece «Ganar» ahí', function () {
    EmbudoDePrueba::calificada(test()->cot);
    $c = test()->cot->libre(test()->cot->crear(), 'Cableado', '10', '100.00', '60.00');
    $c = test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo);
    EmbudoDePrueba::mover(test()->cot, 'enviar_propuesta');
    test()->cot->servicio()->aceptar($c->id, $c->refresh()->version, CarbonImmutable::parse(Calendario::hoy()), TipoRespaldo::OrdenCompra, 'OC-1', null, null, test()->cot->ejecutivo);

    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('propuesta_enviada');
    Livewire::test(VerOportunidad::class, ['record' => test()->cot->oportunidad->id])->assertActionVisible('ganar');

    app(OportunidadesService::class)->ganar(test()->cot->oportunidad->id, test()->cot->oportunidad->refresh()->version, CarbonImmutable::parse(Calendario::hoy()), null, null, test()->cot->ejecutivo);
    expect(EmbudoDePrueba::etapa(test()->cot))->toBe('ganada');
});

test('«Aceptar» en la ficha pide el archivo del respaldo (no el id); el archivo queda adjunto a la cotización', function () {
    $c = test()->cot->emitida();

    // La subida real no se prueba por la pantalla: Livewire guarda el archivo de prueba en un disco falso, fuera de las
    // carpetas de subida que acepta AdjuntosService (en producción es `livewire-tmp`). Aquí, que el campo esté.
    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->mountAction('aceptar')
        ->assertFormFieldExists('respaldo_archivo')
        ->assertFormFieldDoesNotExist('respaldo_adjunto_id');

    $c = test()->cot->servicio()->aceptar($c->id, $c->refresh()->version, CarbonImmutable::parse(Calendario::hoy()), TipoRespaldo::OrdenCompra, 'OC-2026-0099', null, null, test()->cot->ejecutivo, grArchivo('orden-de-compra.pdf'));

    // Además del PDF que se guardó al emitir (plan 2.22c).
    $adjunto = Adjunto::query()->whereKeyNot($c->adjunto_pdf_id)->sole();
    expect($c->respaldo_adjunto_id)->toBe($adjunto->id)
        ->and([$adjunto->adjuntable_type, $adjunto->adjuntable_id])->toBe(['cotizacion', $c->id])
        ->and($c->respaldo_referencia)->toBe('OC-2026-0099');
});
test('sin interfaz: un adjunto de otra cotización se rechaza, y no se puede dar archivo e id a la vez', function () {
    $otra = test()->cot->emitida();
    $ajeno = app(AdjuntosService::class)->agregar('cotizacion', $otra->id, grArchivo('ajeno.pdf'), null, test()->cot->ejecutivo);
    $c = test()->cot->emitida();
    $aceptar = fn (?ArchivoSubido $archivo, ?int $adjuntoId) => test()->cot->servicio()->aceptar($c->id, $c->refresh()->version, CarbonImmutable::parse(Calendario::hoy()), TipoRespaldo::OrdenCompra, 'OC-1', $adjuntoId, null, test()->cot->ejecutivo, $archivo);

    expect(fn () => $aceptar(null, $ajeno->id))->toThrow(CotizacionRechazada::class, 'Ese archivo no está adjunto a esta cotización.')
        ->and(fn () => $aceptar(new ArchivoSubido('x.pdf', 'x.pdf'), $ajeno->id))->toThrow(CotizacionRechazada::class, 'no los dos')
        ->and($c->refresh()->es_aceptada)->toBeFalse();

    $propio = app(AdjuntosService::class)->agregar('cotizacion', $c->id, grArchivo('oc.pdf'), null, test()->cot->ejecutivo);
    $aceptar(null, $propio->id);
    expect($c->refresh()->respaldo_adjunto_id)->toBe($propio->id);
});
