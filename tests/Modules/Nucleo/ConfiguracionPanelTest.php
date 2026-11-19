<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Filament\Resources\Parametros\Pages\ListarParametros;
use App\Modules\Nucleo\Filament\Resources\Parametros\ParametroResource;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\Pages\ListarTiposCambio;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\TipoCambioResource;
use App\Modules\Nucleo\Models\Cubso;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EdicionCatalogosService;
use App\Modules\Nucleo\Services\ParametrosService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Modules\Nucleo\Fixtures\XlsxDePrueba;

/*
 * Configuración en el panel: solo presentación. Las reglas ya se prueban en las acciones; aquí, que
 * cada pantalla abre con su permiso, llama a su acción registrada y muestra los rechazos como aviso.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function conPermisos(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['catalogos.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

test('sin permiso para ver, Configuración no abre ni aparece', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/configuracion/parametros')->assertForbidden();
    test()->get('/configuracion/catalogos')->assertForbidden();
    test()->get('/')->assertDontSee('Configuración');
});

test('con permiso para ver, abre las tres pantallas', function (string $ruta, string $texto) {
    conPermisos();

    test()->get($ruta)->assertOk()->assertSee($texto);
})->with([
    // Con el buscador: la lista tiene más de una página y el orden por grupo no depende de la prueba.
    'parámetros' => ['/configuracion/parametros?search=igv', 'igv_porcentaje'],
    'tipos de cambio' => ['/configuracion/tipos-cambio', 'Aún no hay tipos de cambio'],
    // Al entrar, las cajas (plan 1.4j); la tabla se abre al elegir uno.
    'catálogos' => ['/configuracion/catalogos', 'Comercio y pagos'],
]);

test('el inicio muestra la caja de Configuración a quien puede verla', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.ver'));

    test()->get('/')->assertOk()->assertSee('data-modulo="configuracion"', false);
});

test('cambia un parámetro desde su fila, por la acción registrada', function () {
    conPermisos('parametros.editar-sensibles');
    $igv = Parametro::query()->where('clave', 'igv_porcentaje')->firstOrFail();

    Livewire::test(ListarParametros::class)
        ->callAction(TestAction::make('nucleoCambiarParametroSensible')->table($igv), ['valor' => '10.00'])
        ->assertHasNoFormErrors()
        ->assertNotified('Parámetro actualizado.');

    expect(app(ParametrosService::class)->decimal('tributario.igv_porcentaje'))->toBe('10.00');
});

test('sin permiso de edición, la acción no aparece; en un no editable tampoco', function () {
    conPermisos();
    $igv = Parametro::query()->where('clave', 'igv_porcentaje')->firstOrFail();

    Livewire::test(ListarParametros::class)->assertActionHidden(TestAction::make('nucleoCambiarParametroSensible')->table($igv));

    conPermisos('parametros.editar', 'parametros.editar-sensibles');
    $monedaLocal = Parametro::query()->where('clave', 'moneda_local')->firstOrFail();

    Livewire::test(ListarParametros::class)
        ->assertActionHidden(TestAction::make('nucleoCambiarParametro')->table($monedaLocal))
        ->assertActionHidden(TestAction::make('nucleoCambiarParametroSensible')->table($monedaLocal));
});

test('si otra persona guarda mientras el formulario está abierto, se avisa y no se pisa su valor', function () {
    conPermisos('parametros.editar-sensibles');
    $igv = Parametro::query()->where('clave', 'igv_porcentaje')->firstOrFail();

    $pantalla = Livewire::test(ListarParametros::class)
        ->mountAction(TestAction::make('nucleoCambiarParametroSensible')->table($igv));

    // Con el formulario abierto, otra persona lo cambia.
    app(ParametrosService::class)->cambiarDeDinero('tributario.igv_porcentaje', '12.00', versionLeida: 1);

    $pantalla->fillForm(['valor' => '10.00'])
        ->callMountedAction()
        ->assertNotified('Otra persona cambió el parámetro «tributario.igv_porcentaje» mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');

    expect(Parametro::query()->where('clave', 'igv_porcentaje')->value('valor'))->toBe('12.00');
});

test('registra el tipo de cambio desde el botón de arriba', function () {
    conPermisos('tipos_cambio.registrar');

    Livewire::test(ListarTiposCambio::class)
        ->callAction('nucleoRegistrarTipoCambio', ['moneda' => 'USD', 'fecha' => '2026-10-09', 'compra' => '3.7480', 'venta' => '3.7560'])
        ->assertHasNoFormErrors()
        ->assertNotified('Tipo de cambio guardado.');

    expect(TipoCambio::query()->sole()->venta)->toBe('3.7560');
});

test('un error de validación del tipo de cambio sale en español en el formulario', function () {
    conPermisos('tipos_cambio.registrar');

    Livewire::test(ListarTiposCambio::class)
        ->callAction('nucleoRegistrarTipoCambio', ['moneda' => 'USD', 'fecha' => '2026-10-09', 'compra' => '3.74801', 'venta' => '3.7560'])
        ->assertHasFormErrors(['compra']);

    expect(TipoCambio::query()->count())->toBe(0);
});

test('corregir una fila cambia solo compra y venta: moneda y fecha son las de la fila', function () {
    conPermisos('tipos_cambio.registrar');
    $tc = TipoCambio::factory()->create(['moneda_id' => Moneda::query()->where('codigo', 'USD')->value('id'), 'fecha' => '2026-10-09']);

    // Aunque llegara otra fecha, manda la de la fila.
    Livewire::test(ListarTiposCambio::class)
        ->callAction(TestAction::make('nucleoRegistrarTipoCambio')->table($tc), ['compra' => '3.7500', 'venta' => '3.9000', 'fecha' => '2026-10-19'])
        ->assertHasNoFormErrors();

    expect($tc->refresh()->venta)->toBe('3.9000')
        ->and(TipoCambio::query()->count())->toBe(1);
});

test('cada pestaña de catálogos muestra su lista', function () {
    conPermisos();

    Livewire::withQueryParams(['catalogo' => 'tipos_documento'])
        ->test(Catalogos::class)
        ->assertSee('Nota de crédito')
        ->assertDontSee('Dólar estadounidense');

    Livewire::withQueryParams(['catalogo' => 'cubso'])
        ->test(Catalogos::class)
        ->assertSee('CUBSO todavía no se carga');
});

test('en ubigeos, buscar muestra los cambios sin aplicarlos y aplicar los trae', function () {
    conPermisos('ubigeos.actualizar');
    config(['nucleo.ubigeos.url' => 'https://datos.prueba/ubigeos.xlsx', 'nucleo.ubigeos.minimo_distritos' => 1]);
    Http::fake(['datos.prueba/*' => Http::response(XlsxDePrueba::inei([['140121', 'LAMBAYEQUE', 'CHICLAYO', 'NUEVO']]))]);

    $pagina = Livewire::withQueryParams(['catalogo' => 'ubigeos'])->test(Catalogos::class);

    // Plan 1.4j: los dos botones del INEI van en la tabla del panel.
    $pagina->callAction(TestAction::make('buscarActualizacionUbigeos')->table())->assertNotified('Hay cambios en la lista del INEI');
    expect(Ubigeo::query()->where('codigo', '140121')->exists())->toBeFalse();

    $pagina->callAction(TestAction::make('nucleoActualizarUbigeos')->table())->assertNotified('Ubigeos actualizados');
    expect(Ubigeo::query()->where('codigo', '140121')->value('distrito'))->toBe('NUEVO');
});

test('los botones de ubigeos solo aparecen con su permiso y en su panel', function () {
    conPermisos();

    Livewire::withQueryParams(['catalogo' => 'ubigeos'])->test(Catalogos::class)
        ->assertActionHidden(TestAction::make('buscarActualizacionUbigeos')->table())
        ->assertActionHidden(TestAction::make('nucleoActualizarUbigeos')->table());

    conPermisos('ubigeos.actualizar');

    Livewire::withQueryParams(['catalogo' => 'monedas'])->test(Catalogos::class)
        ->assertActionDoesNotExist(TestAction::make('nucleoActualizarUbigeos')->table());
});

test('agrega un elemento desde el botón de la tabla', function () {
    conPermisos('catalogos.editar');

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->callAction(TestAction::make('nucleoFormasPagoCrear')->table(), ['codigo' => 'CREDITO_45', 'nombre' => 'Crédito a 45 días', 'dias' => '45'])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect(FormaPago::query()->where('codigo', 'CREDITO_45')->value('dias'))->toBe(45);
});

test('en CUBSO la unidad de medida se elige de las activas', function () {
    conPermisos('catalogos.editar');
    $unidad = UnidadMedida::query()->where('codigo', 'NIU')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'cubso'])->test(Catalogos::class)
        ->callAction(TestAction::make('nucleoCubsoCrear')->table(), ['codigo' => '43211500', 'descripcion' => 'Computadoras', 'unidad_medida_id' => $unidad->id])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect(Cubso::query()->where('codigo', '43211500')->value('unidad_medida_id'))->toBe($unidad->id);
});

test('editar un CUBSO conserva su unidad aunque se haya desactivado después', function () {
    conPermisos('catalogos.editar');
    $unidad = UnidadMedida::query()->where('codigo', 'NIU')->firstOrFail();
    $cubso = Cubso::factory()->create(['codigo' => '43211500', 'descripcion' => 'Computadoras', 'unidad_medida_id' => $unidad->id]);
    $unidad->update(['es_activa' => false]);

    Livewire::withQueryParams(['catalogo' => 'cubso'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoCubsoEditar')->table($cubso))
        ->fillForm(['descripcion' => 'Computadoras portátiles'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect($cubso->refresh()->descripcion)->toBe('Computadoras portátiles')
        ->and($cubso->unidad_medida_id)->toBe($unidad->id);
});

test('editar abre con los valores de la fila y guarda por la acción registrada', function () {
    conPermisos('catalogos.editar');
    $contado = FormaPago::query()->where('codigo', 'CONTADO')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoFormasPagoEditar')->table($contado))
        ->assertSchemaStateSet(['nombre' => 'Contado', 'version' => 1])
        ->fillForm(['nombre' => 'Contado (al entregar)'])
        ->callMountedAction()
        ->assertNotified('Guardado.');

    expect($contado->refresh()->nombre)->toBe('Contado (al entregar)')
        ->and($contado->version)->toBe(2);
});

test('si otra persona guarda un elemento mientras el formulario está abierto, se avisa', function () {
    conPermisos('catalogos.editar');
    $contado = FormaPago::query()->where('codigo', 'CONTADO')->firstOrFail();

    $pantalla = Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoFormasPagoEditar')->table($contado));

    app(EdicionCatalogosService::class)->editar(Catalogo::FormasPago, 'CONTADO', ['nombre' => 'Lo de Ana'], 1);

    $pantalla->fillForm(['nombre' => 'Lo de Luis'])
        ->callMountedAction()
        ->assertNotified('Otra persona cambió «CONTADO» de formas de pago mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');

    expect($contado->refresh()->nombre)->toBe('Lo de Ana');
});

test('desactivar y activar se alternan según el estado de la fila', function () {
    conPermisos('catalogos.editar');
    $contado = FormaPago::query()->where('codigo', 'CONTADO')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->assertActionHidden(TestAction::make('nucleoFormasPagoActivar')->table($contado))
        ->callAction(TestAction::make('nucleoFormasPagoDesactivar')->table($contado))
        ->assertNotified('Guardado.');

    $contado->refresh();
    expect($contado->es_activa)->toBeFalse();

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->assertActionHidden(TestAction::make('nucleoFormasPagoDesactivar')->table($contado))
        ->callAction(TestAction::make('nucleoFormasPagoActivar')->table($contado));

    expect($contado->refresh()->es_activa)->toBeTrue();
});

test('borrar lo que nunca se usó funciona; lo usado se rechaza con aviso', function () {
    conPermisos('catalogos.editar', 'catalogos.editar-sensibles');
    $dolar = Moneda::query()->where('codigo', 'USD')->firstOrFail();
    TipoCambio::factory()->create(['moneda_id' => $dolar->id]);
    $credito = FormaPago::query()->where('codigo', 'CREDITO_60')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'monedas'])->test(Catalogos::class)
        ->callAction(TestAction::make('nucleoMonedasBorrar')->table($dolar))
        ->assertNotified('«USD» de monedas ya se usó en otros registros y no se puede borrar. Desactívalo para que no se elija más.');

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->callAction(TestAction::make('nucleoFormasPagoBorrar')->table($credito))
        ->assertNotified('Guardado.');

    expect(Moneda::query()->where('codigo', 'USD')->exists())->toBeTrue()
        ->and(FormaPago::query()->where('codigo', 'CREDITO_60')->exists())->toBeFalse();
});

test('sin permiso de edición no aparece ninguna acción de catálogo', function () {
    conPermisos();
    $contado = FormaPago::query()->where('codigo', 'CONTADO')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->assertActionHidden(TestAction::make('nucleoFormasPagoCrear')->table())
        ->assertActionHidden(TestAction::make('nucleoFormasPagoEditar')->table($contado))
        ->assertActionHidden(TestAction::make('nucleoFormasPagoDesactivar')->table($contado))
        ->assertActionHidden(TestAction::make('nucleoFormasPagoBorrar')->table($contado));
});

test('los campos de dinero piden su propio permiso, también para agregar monedas y documentos', function () {
    conPermisos('catalogos.editar');
    $factura = TipoDocumento::query()->where('codigo', '01')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'tipos_documento'])->test(Catalogos::class)
        ->assertActionVisible(TestAction::make('nucleoTiposDocumentoEditar')->table($factura))
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoEditarSensibles')->table($factura))
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoCrear')->table())
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoDesactivar')->table($factura))
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoBorrar')->table($factura));

    conPermisos('catalogos.editar', 'catalogos.editar-sensibles');

    Livewire::withQueryParams(['catalogo' => 'tipos_documento'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoTiposDocumentoEditarSensibles')->table($factura))
        ->assertSchemaStateSet(['signo' => 1, 'afecta_cuenta_corriente' => true])
        ->fillForm(['afecta_cuenta_corriente' => false])
        ->callMountedAction()
        ->assertNotified('Guardado.');

    expect($factura->refresh()->afecta_cuenta_corriente)->toBeFalse();
});

test('el formulario de un parámetro abre con su descripción y la guarda', function () {
    conPermisos('parametros.editar-sensibles');
    $igv = Parametro::query()->where('clave', 'igv_porcentaje')->firstOrFail();

    Livewire::test(ListarParametros::class)
        ->mountAction(TestAction::make('nucleoCambiarParametroSensible')->table($igv))
        ->assertSchemaStateSet(['descripcion' => $igv->descripcion])
        ->fillForm(['descripcion' => 'IGV vigente según SUNAT.'])
        ->callMountedAction()
        ->assertNotified('Parámetro actualizado.');

    expect($igv->refresh()->descripcion)->toBe('IGV vigente según SUNAT.');
});

test('las pantallas de Configuración son de solo lectura: crear, editar y borrar están cerrados', function (string $recurso) {
    $fila = new ($recurso::getModel());

    expect($recurso::canCreate())->toBeFalse()
        ->and($recurso::canEdit($fila))->toBeFalse()
        ->and($recurso::canDelete($fila))->toBeFalse()
        ->and($recurso::canDeleteAny())->toBeFalse();
})->with([ParametroResource::class, TipoCambioResource::class]);

test('las pantallas del núcleo van solo al panel principal, no a otro panel', function () {
    expect(Filament::getPanel('app')->getResources())->toContain(ParametroResource::class, TipoCambioResource::class)
        ->and(Panel::make()->id('portal-clientes')->getResources())->toBe([])
        ->and(Panel::make()->id('portal-clientes')->getPages())->not->toContain(Catalogos::class);
});
