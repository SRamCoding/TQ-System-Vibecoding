<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Nucleo\Aprobaciones\DatoParaAprobar;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Aprobaciones\RegistroResumenesAprobacion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\EstadoFirmaAprobacion;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Filament\Pages\MisAprobaciones;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\MotivosService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * «Mis aprobaciones» (plan 2.22, P6) con la cotización del escenario D17.1 (margen 19.40 %, mínimo 22 %): la bandeja
 * genérica del núcleo, con el resumen que aporta Cotizaciones. Se firma ahí mismo con la versión leída al abrir el panel,
 * y el margen solo llega a quien tiene el permiso de «Márgenes», filtrado en el servidor.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
});

/** Una gerencia de prueba que firma las cotizaciones bajo el margen mínimo, y la cotización ya pedida. */
function maPorAprobar(string ...$permisos): array
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => 'Gerencia de Ventas (prueba)', 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => 'Gerente de Ventas (prueba)', 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Cotización bajo el margen mínimo', 'cotizacion', DefinicionCotizacion::APROBAR, null, null, null, ["unidad:{$unidad->id}"], CondicionRegla::MargenBajoElMinimo));
    $gerente = User::factory()->create(['puesto_id' => $puesto->id, 'name' => 'Gerente Ficticio']);
    $gerente->givePermissionTo($permisos);

    $c = test()->cot->delDiseno();
    $c = test()->cot->servicio()->solicitarAprobacion($c->id, $c->version, 'Cliente estratégico', test()->cot->ejecutivo);

    return [$gerente, $c];
}

/** @return list<string> */
function maEtiquetas(User $usuario): array
{
    $bandeja = app(AprobacionesService::class)->bandeja($usuario, app(RegistroResumenesAprobacion::class));

    return array_map(fn (DatoParaAprobar $d): string => "{$d->etiqueta}: {$d->valor}", $bandeja[0]->datos);
}

test('la bandeja trae la cotización con su resumen; margen y puntos contra el mínimo solo con el permiso de márgenes', function () {
    [$conMargen, $c] = maPorAprobar(GrupoSensible::Margen->permiso());

    $bandeja = app(AprobacionesService::class)->bandeja($conMargen, app(RegistroResumenesAprobacion::class));
    expect($bandeja)->toHaveCount(1)
        ->and([$bandeja[0]->documento, $bandeja[0]->montoSoles, $bandeja[0]->version])->toBe(["Cotización {$c->numero} v1", '60939.92', $c->version])
        ->and(maEtiquetas($conMargen))->toContain('Total: PEN 60,939.92', 'Margen: 19.40 %', 'Contra el mínimo: -2.60 puntos (mínimo 22.00 %)');

    // Sin el permiso: la misma fila, sin los datos de márgenes (no llegan a la pantalla).
    $conMargen->revokePermissionTo(GrupoSensible::Margen->permiso());
    $sinMargen = $conMargen->refresh();
    expect(implode(' | ', maEtiquetas($sinMargen)))->toContain('Total: PEN 60,939.92')
        ->not->toContain('Margen')->not->toContain('mínimo');
});

test('la pantalla muestra la fila y, sin el permiso de márgenes, el panel no dibuja el margen', function () {
    [$gerente, $c] = maPorAprobar();
    test()->actingAs($gerente);

    Livewire::test(MisAprobaciones::class)
        ->assertSee("Cotización {$c->numero} v1")
        ->assertSee('60,939.92')
        ->mountAction(TestAction::make('nucleoAprobacionesFirmar')->table('firma-'.FirmaAprobacion::query()->sole()->id))
        ->assertMountedActionModalSee(['Total', 'PEN 60,939.92', 'Lo pide'])
        ->assertMountedActionModalDontSee('19.40 %')
        ->assertMountedActionModalDontSee('Contra el mínimo');
});

test('«Aprobar» firma con la versión leída al abrir el panel y la cotización queda aprobada', function () {
    [$gerente, $c] = maPorAprobar(GrupoSensible::Margen->permiso());
    test()->actingAs($gerente);

    Livewire::test(MisAprobaciones::class)
        ->mountAction(TestAction::make('nucleoAprobacionesFirmar')->table('firma-'.FirmaAprobacion::query()->sole()->id))
        ->assertMountedActionModalSee(['Margen', '19.40 %', '-2.60 puntos (mínimo 22.00 %)'])
        ->assertSchemaStateSet(['version' => $c->version])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Firmaste la aprobación.')
        ->assertDontSee("Cotización {$c->numero} v1");

    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::APROBADA);
});

test('si la cotización cambió después de abrir el panel, no se firma: avisa y la ronda queda como estaba', function () {
    [$gerente, $c] = maPorAprobar();
    test()->actingAs($gerente);
    $firma = FirmaAprobacion::query()->sole();

    $pantalla = Livewire::test(MisAprobaciones::class)
        ->mountAction(TestAction::make('nucleoAprobacionesFirmar')->table("firma-{$firma->id}"));
    // Otra persona la cambia mientras el panel está abierto.
    DB::table('cotizaciones')->where('id', $c->id)->increment('version');

    $pantalla->callMountedAction()
        ->assertNotified('La cotización cambió desde que lo abriste: no se firmó. Vuelve a abrirlo para ver lo actual.');

    expect($firma->refresh()->estado)->toBe(EstadoFirmaAprobacion::Pendiente)
        ->and($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::POR_APROBAR);
});

test('sin pantalla, firmar con una versión vieja se rechaza igual; sin versión, se firma lo actual', function () {
    [$gerente, $c] = maPorAprobar();

    expect(fn () => app(AprobacionesService::class)->firmar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $gerente, $c->version - 1))
        ->toThrow(AprobacionRechazada::class, 'cambió desde que lo abriste');

    app(AprobacionesService::class)->firmar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $gerente);
    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::APROBADA);
});

test('«Observar» pide motivo y comentario, con la versión del panel, y la devuelve a borrador', function () {
    [$gerente, $c] = maPorAprobar();
    test()->actingAs($gerente);
    $motivo = array_key_first(app(MotivosService::class)->opciones(AprobacionesService::FAMILIA_OBSERVAR, null));

    Livewire::test(MisAprobaciones::class)
        ->mountAction(TestAction::make('nucleoAprobacionesObservar')->table('firma-'.FirmaAprobacion::query()->sole()->id))
        ->fillForm(['motivo_id' => $motivo, 'comentario' => 'Revisar el descuento del NVR'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Observaste el documento: vuelve a quien lo pidió.');

    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR);
});

test('quien no firma ese paso no la ve; la tarjeta de «Mien» cuenta lo que espera a cada uno', function () {
    [$gerente] = maPorAprobar();
    $otro = User::factory()->create();

    test()->actingAs($otro);
    Livewire::test(MisAprobaciones::class)->assertSee('No tienes nada por firmar');
    Livewire::actingAs($otro)->test('mien.mis-aprobaciones', ['titulo' => 'Mis aprobaciones'])->assertSee('No tienes nada por firmar.');

    Livewire::actingAs($gerente)->test('mien.mis-aprobaciones', ['titulo' => 'Mis aprobaciones'])
        ->assertSee('documento espera tu firma.')
        ->assertSee('Ver mis aprobaciones');
});
