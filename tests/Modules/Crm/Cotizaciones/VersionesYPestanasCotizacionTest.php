<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Filament\CotizacionesDeCuenta;
use App\Modules\Crm\Cotizaciones\Filament\CotizacionesDeOportunidad;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\VersionesDeCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Crm\Cotizaciones\Services\ComparadorVersiones;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * D19: el comparador de dos versiones (total, margen, líneas nuevas, quitadas y cambiadas), la pestaña «Versiones» de la
 * ficha y las pestañas «Cotizaciones» de la oportunidad (también capa del despiece) y de la cuenta.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
    test()->cot->ejecutivo->givePermissionTo([
        Permission::findOrCreate(CotizacionesServiceProvider::PERMISO_VER),
        Permission::findOrCreate(OportunidadesServiceProvider::PERMISO_VER),
        Permission::findOrCreate(CuentasServiceProvider::PERMISO_VER),
    ]);
    test()->actingAs(test()->cot->ejecutivo);
});

/** v1 emitida (cableado 10 × 100.00) y v2 con 12 de cableado, una canaleta nueva y sin la mano de obra. */
function vpDosVersiones(): array
{
    $v1 = test()->cot->libre(test()->cot->crear(), 'Cableado estructurado', '10', '100.00', '60.00');
    $v1 = test()->cot->libre($v1, 'Mano de obra', '1', '500.00', '300.00');
    $v1 = test()->cot->servicio()->emitir($v1->id, $v1->version, null, test()->cot->ejecutivo);
    $v2 = test()->cot->servicio()->nuevaVersion($v1->id, CotizacionesDePrueba::motivoVersion(), 'Más puntos de red', test()->cot->ejecutivo);
    $cableado = LineaDeCotizacion::query()->where('cotizacion_id', $v2->id)->where('descripcion', 'Cableado estructurado')->sole();
    test()->cot->servicio()->editarLinea($cableado->id, $v2->refresh()->version, new DatosLinea('12', '100.00', '60.00', '0'), test()->cot->ejecutivo);
    $obra = LineaDeCotizacion::query()->where('cotizacion_id', $v2->id)->where('descripcion', 'Mano de obra')->sole();
    test()->cot->servicio()->quitarLinea($obra->id, $v2->refresh()->version, test()->cot->ejecutivo);
    $v2 = test()->cot->libre($v2->refresh(), 'Canaleta', '5', '20.00', '12.00');

    return [$v1->refresh(), $v2->refresh()];
}

test('el comparador da total y margen de cada versión y las líneas nuevas, quitadas y cambiadas', function () {
    [$v1, $v2] = vpDosVersiones();

    // El orden de los argumentos no importa: «antes» es siempre la versión menor.
    $c = app(ComparadorVersiones::class)->comparar($v2->id, $v1->id);

    expect([$c['antes']['version'], $c['despues']['version']])->toBe([1, 2])
        ->and([$c['antes']['total'], $c['despues']['total']])->toBe([(string) $v1->total, (string) $v2->total])
        ->and(array_column($c['nuevas'], 'descripcion'))->toBe(['Canaleta'])
        ->and(array_column($c['quitadas'], 'descripcion'))->toBe(['Mano de obra'])
        ->and($c['cambiadas'][0]['descripcion'])->toBe('Cableado estructurado')
        ->and($c['cambiadas'][0]['cambios'][0])->toBe(['campo' => 'Cantidad', 'antes' => '10.000', 'despues' => '12.000', 'sensible' => false]);

    $otra = test()->cot->crear();
    expect(fn () => app(ComparadorVersiones::class)->comparar($v1->id, $otra->id))
        ->toThrow(CotizacionRechazada::class, 'Solo se comparan versiones de la misma cotización.');
});

test('la pestaña «Versiones» lista la más nueva primero y «Comparar» abre el comparador; el margen, solo con su permiso', function () {
    [$v1, $v2] = vpDosVersiones();

    Livewire::test(VersionesDeCotizacion::class, ['cotizacionId' => $v2->id])
        ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
        ->assertSee('versión 2 (esta)')
        ->assertTableActionHidden('comparar', $v2)
        ->mountTableAction('comparar', $v1)
        ->assertMountedActionModalSee(['Versión 1', 'Versión 2', 'Líneas nuevas', 'Canaleta', 'Líneas quitadas', 'Mano de obra', 'Cantidad 10.000 → 12.000'])
        ->assertMountedActionModalDontSee('Margen');

    test()->cot->ejecutivo->givePermissionTo(GrupoSensible::Margen->permiso());
    Livewire::test(VerCotizacion::class, ['record' => $v2->id])
        ->assertActionVisible('compararAnterior')
        ->mountAction(TestAction::make('compararAnterior'))
        ->assertMountedActionModalSee(['Margen', "{$v1->margen_porcentaje} %"]);
    Livewire::test(VerCotizacion::class, ['record' => $v1->id])->assertActionHidden('compararAnterior');
});

test('pestaña de la oportunidad: sus versiones y «Nueva cotización de esta oportunidad» mientras esté abierta', function () {
    [$v1, $v2] = vpDosVersiones();
    $oportunidadId = test()->cot->oportunidad->id;

    Livewire::test(CotizacionesDeOportunidad::class, ['oportunidadId' => $oportunidadId])
        ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
        ->callTableAction('nuevaCotizacion')
        ->assertHasNoTableActionErrors();

    expect(Cotizacion::query()->where('oportunidad_id', $oportunidadId)->count())->toBe(3);
});

test('pestaña de la cuenta: las cotizaciones de sus oportunidades, con la oportunidad de cada una', function () {
    [$v1, $v2] = vpDosVersiones();

    Livewire::test(CotizacionesDeCuenta::class, ['cuentaId' => $v1->cuenta_id])
        ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
        ->assertSee(test()->cot->oportunidad->numero);
});
