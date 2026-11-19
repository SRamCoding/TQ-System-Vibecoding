<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\LineasDeCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\HistorialDeCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * La ficha de una versión (D19): encabezado, totales y margen (solo con su permiso, D15), condiciones, las líneas con sus
 * acciones solo en borrador y las transiciones en paneles con la versión leída al abrirlos.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
    test()->cot->ejecutivo->givePermissionTo(Permission::findOrCreate(CotizacionesServiceProvider::PERMISO_VER));
    test()->actingAs(test()->cot->ejecutivo);
});

test('el encabezado dice número, versión, cliente y estado; el margen y el costo solo con sus permisos', function () {
    $c = test()->cot->delDiseno();

    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->assertSee("{$c->numero} v1")
        ->assertSee('Borrador · PEN')
        ->assertSee('PEN 60,939.92')
        ->assertDontSee('19.40 %')
        ->assertSee('El costo y el margen se ven con el permiso');

    test()->cot->ejecutivo->givePermissionTo([GrupoSensible::Margen->permiso(), GrupoSensible::Costos->permiso()]);
    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->assertSee('19.40 %')
        ->assertSee('objetivo 25.00 % · mínimo 22.00 % · requiere aprobación');
});

test('bajo el mínimo se ofrece «Solicitar aprobación» y no «Emitir»; sobre el mínimo, al revés', function () {
    $bajo = test()->cot->delDiseno();
    $sobre = test()->cot->libre(test()->cot->crear(), 'Cableado', '10', '100.00', '60.00');

    Livewire::test(VerCotizacion::class, ['record' => $bajo->id])
        ->assertActionVisible('solicitarAprobacion')
        ->assertActionHidden('emitir')
        ->assertActionHidden('aceptar');
    Livewire::test(VerCotizacion::class, ['record' => $sobre->id])
        ->assertActionHidden('solicitarAprobacion')
        ->assertActionVisible('emitir');
});

test('«Emitir» lleva la versión leída al abrir: si otra persona la cambió, avisa y no emite', function () {
    $c = test()->cot->libre(test()->cot->crear(), 'Cableado', '10', '100.00', '60.00');

    $ficha = Livewire::test(VerCotizacion::class, ['record' => $c->id])->mountAction('emitir');
    DB::table('cotizaciones')->where('id', $c->id)->increment('version');
    $ficha->callMountedAction();
    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::BORRADOR);

    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->callAction('emitir')
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');
    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::EMITIDA);
});

test('emitida: «Aceptar» pide fecha y respaldo y la deja aceptada; «Nueva versión» abre la versión creada', function () {
    $c = test()->cot->emitida();

    Livewire::test(VerCotizacion::class, ['record' => $c->id])
        ->assertActionHidden('emitir')
        ->callAction('aceptar', ['respaldo_tipo' => 'orden_compra', 'respaldo_referencia' => 'OC-123'])
        ->assertHasNoActionErrors();
    expect($c->refresh()->estado->codigo)->toBe(DefinicionCotizacion::ACEPTADA)
        ->and($c->respaldo_referencia)->toBe('OC-123');

    $otra = test()->cot->emitida();
    Livewire::test(VerCotizacion::class, ['record' => $otra->id])
        ->assertActionVisible('nuevaVersion')
        ->callAction(TestAction::make('nuevaVersion'), ['motivo_id' => CotizacionesDePrueba::motivoVersion()])
        ->assertHasNoActionErrors()
        ->assertRedirect(VerCotizacion::getUrl(['record' => Cotizacion::query()->where('numero', $otra->numero)->where('numero_version', 2)->sole()->id]));
});

test('líneas: en borrador se agregan, editan y quitan; el costo y el margen solo con sus permisos', function () {
    $c = test()->cot->libre(test()->cot->crear(), 'Cableado', '10', '100.00', '60.00');
    $linea = LineaDeCotizacion::query()->sole();

    Livewire::test(LineasDeCotizacion::class, ['cotizacionId' => $c->id])
        ->assertSee('Cableado')
        ->assertTableColumnHidden('costo_unitario')
        ->assertTableColumnHidden('margen_porcentaje')
        ->callTableAction('libre', data: ['descripcion' => 'Canaleta', 'unidad_medida_id' => CotizacionesDePrueba::unidad(), 'cantidad' => '5', 'precio_unitario' => '20.00', 'costo_unitario' => '12.00'])
        ->assertHasNoTableActionErrors()
        ->assertSee('Canaleta')
        // Sin «Costos», editar no muestra el costo: se conserva el que tiene.
        ->mountTableAction('editarLinea', $linea)
        ->assertTableActionDataSet(['cantidad' => '10.000', 'precio_unitario' => '100.0000'])
        ->assertTableActionDataSet(fn (array $data): bool => ! array_key_exists('costo_unitario', $data))
        ->setTableActionData(['cantidad' => '12'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($linea->refresh()->cantidad)->toBe('12.000')
        ->and($linea->costo_unitario)->toBe('60.0000');

    Livewire::test(LineasDeCotizacion::class, ['cotizacionId' => $c->id])
        ->callTableAction('quitarLinea', $linea)
        ->assertHasNoTableActionErrors();
    expect(LineaDeCotizacion::query()->pluck('descripcion')->all())->toBe(['Canaleta']);

    test()->cot->ejecutivo->givePermissionTo([GrupoSensible::Margen->permiso(), GrupoSensible::Costos->permiso()]);
    Livewire::test(LineasDeCotizacion::class, ['cotizacionId' => $c->id])
        ->assertTableColumnVisible('costo_unitario')
        ->assertTableColumnVisible('margen_porcentaje');
});

test('emitida, las líneas ya no se tocan; el historial de la versión se ve', function () {
    $c = test()->cot->emitida();

    Livewire::test(LineasDeCotizacion::class, ['cotizacionId' => $c->id])
        ->assertTableActionHidden('libre')
        ->assertTableActionHidden('delCatalogo')
        ->assertTableActionHidden('editarLinea', LineaDeCotizacion::query()->sole());

    Livewire::test(HistorialDeCotizacion::class, ['registroId' => $c->id])->assertSee('Cableado estructurado');
});
