<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EdicionCatalogosService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Plan 1.3, D3: los catálogos de identidad (tipos de documento de identidad y roles de party) se cargan con
 * los datos de SUNAT y los roles iniciales, y lo que usa el código del sistema no se desactiva, no se borra
 * ni cambia sus campos fijos, por servicio ni por pantalla.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function edicionIdentidad(): EdicionCatalogosService
{
    return app(EdicionCatalogosService::class);
}

function versionDe(Catalogo $catalogo, string $codigo): int
{
    $modelo = $catalogo->modelo();

    return (int) $modelo::query()->where('codigo', $codigo)->value('version');
}

test('se cargan los tipos de documento del catálogo 06 de SUNAT y los roles iniciales', function () {
    expect(TipoDocumentoIdentidad::query()->orderBy('codigo')->pluck('longitud', 'codigo')->all())
        ->toBe(['0' => null, '1' => 8, '4' => null, '6' => 11, '7' => null])
        ->and(RolParty::query()->orderBy('codigo')->pluck('codigo')->all())
        ->toBe(['aliado', 'banco', 'cliente', 'colaborador', 'competidor', 'entidad', 'proveedor']);
});

test('lo que usa el sistema no se desactiva ni se borra', function (Catalogo $catalogo, string $codigo, string $operacion) {
    expect(fn () => edicionIdentidad()->{$operacion}($catalogo, $codigo, versionDe($catalogo, $codigo)))
        ->toThrow(ElementoCatalogoRechazado::class, "«{$codigo}» de {$catalogo->nombre()} lo usa el sistema");

    $modelo = $catalogo->modelo();
    expect($modelo::query()->where('codigo', $codigo)->value('es_activo'))->toBeTrue();
})->with([
    'DNI' => [Catalogo::TiposDocumentoIdentidad, '1'],
    'RUC' => [Catalogo::TiposDocumentoIdentidad, '6'],
    'cliente' => [Catalogo::RolesParty, 'cliente'],
    'proveedor' => [Catalogo::RolesParty, 'proveedor'],
    'entidad' => [Catalogo::RolesParty, 'entidad'],
    'colaborador' => [Catalogo::RolesParty, 'colaborador'],
    'banco' => [Catalogo::RolesParty, 'banco'],
])->with(['desactivar', 'borrar']);

test('lo que el sistema no usa sí se desactiva y se borra', function (Catalogo $catalogo, string $codigo) {
    edicionIdentidad()->desactivar($catalogo, $codigo, 1);
    edicionIdentidad()->borrar($catalogo, $codigo, 2);

    $modelo = $catalogo->modelo();
    expect($modelo::query()->where('codigo', $codigo)->exists())->toBeFalse();
})->with([
    'pasaporte' => [Catalogo::TiposDocumentoIdentidad, '7'],
    'aliado' => [Catalogo::RolesParty, 'aliado'],
]);

test('la longitud de DNI y RUC no cambia; su nombre sí', function (string $codigo) {
    expect(fn () => edicionIdentidad()->editar(Catalogo::TiposDocumentoIdentidad, $codigo, ['longitud' => 9], 1))
        ->toThrow(ElementoCatalogoRechazado::class, "El campo «longitud» de «{$codigo}» en tipos de documento de identidad lo fija el sistema");

    // Mandar la misma longitud no es cambiarla: así guarda la pantalla, que la envía sin poder tocarla.
    $longitud = TipoDocumentoIdentidad::query()->where('codigo', $codigo)->value('longitud');
    edicionIdentidad()->editar(Catalogo::TiposDocumentoIdentidad, $codigo, ['nombre' => 'Documento renombrado', 'longitud' => $longitud], 1);

    $tipo = TipoDocumentoIdentidad::query()->where('codigo', $codigo)->firstOrFail();
    expect($tipo->nombre)->toBe('Documento renombrado')
        ->and($tipo->longitud)->toBe($longitud);
})->with(['1', '6']);

test('la longitud de los demás tipos de documento sí se edita', function () {
    edicionIdentidad()->editar(Catalogo::TiposDocumentoIdentidad, '4', ['longitud' => 9], 1);

    expect(TipoDocumentoIdentidad::query()->where('codigo', '4')->value('longitud'))->toBe(9);
});

test('la moneda local, los documentos y los roles del sistema cuentan como del sistema; los demás no', function () {
    expect(edicionIdentidad()->esDelSistema(Catalogo::Monedas, 'PEN'))->toBeTrue()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::Monedas, 'USD'))->toBeFalse()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::TiposDocumentoIdentidad, '6'))->toBeTrue()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::TiposDocumentoIdentidad, '4'))->toBeFalse()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::RolesParty, 'banco'))->toBeTrue()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::RolesParty, 'aliado'))->toBeFalse()
        ->and(edicionIdentidad()->esDelSistema(Catalogo::FormasPago, 'CONTADO'))->toBeFalse();
});

test('la pantalla no ofrece desactivar ni borrar lo que usa el sistema', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'catalogos.editar', 'catalogos.editar-sensibles']));
    Filament::setCurrentPanel('app');
    $ruc = TipoDocumentoIdentidad::query()->where('codigo', '6')->firstOrFail();
    $pasaporte = TipoDocumentoIdentidad::query()->where('codigo', '7')->firstOrFail();
    $cliente = RolParty::query()->where('codigo', 'cliente')->firstOrFail();
    $aliado = RolParty::query()->where('codigo', 'aliado')->firstOrFail();
    $sol = Moneda::query()->where('codigo', 'PEN')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'tipos_documento_identidad'])->test(Catalogos::class)
        ->assertSee('Carné de extranjería')
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoIdentidadDesactivar')->table($ruc))
        ->assertActionHidden(TestAction::make('nucleoTiposDocumentoIdentidadBorrar')->table($ruc))
        ->assertActionVisible(TestAction::make('nucleoTiposDocumentoIdentidadDesactivar')->table($pasaporte))
        ->assertActionVisible(TestAction::make('nucleoTiposDocumentoIdentidadBorrar')->table($pasaporte));

    Livewire::withQueryParams(['catalogo' => 'roles_party'])->test(Catalogos::class)
        ->assertSee('Entidad pública')
        ->assertActionHidden(TestAction::make('nucleoRolesPartyDesactivar')->table($cliente))
        ->assertActionHidden(TestAction::make('nucleoRolesPartyBorrar')->table($cliente))
        ->assertActionVisible(TestAction::make('nucleoRolesPartyDesactivar')->table($aliado));

    Livewire::withQueryParams(['catalogo' => 'monedas'])->test(Catalogos::class)
        ->assertActionHidden(TestAction::make('nucleoMonedasDesactivar')->table($sol))
        ->assertActionHidden(TestAction::make('nucleoMonedasBorrar')->table($sol));
});

test('al editar el RUC, la longitud se ve sin poder cambiarse y el nombre se guarda', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'catalogos.editar']));
    Filament::setCurrentPanel('app');
    $ruc = TipoDocumentoIdentidad::query()->where('codigo', '6')->firstOrFail();
    $carne = TipoDocumentoIdentidad::query()->where('codigo', '4')->firstOrFail();

    Livewire::withQueryParams(['catalogo' => 'tipos_documento_identidad'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoTiposDocumentoIdentidadEditar')->table($ruc))
        ->assertSchemaStateSet(['nombre' => 'RUC', 'longitud' => 11, 'version' => 1])
        ->assertFormFieldDisabled('longitud')
        ->fillForm(['nombre' => 'Registro Único de Contribuyentes'])
        ->callMountedAction()
        ->assertNotified('Guardado.');

    Livewire::withQueryParams(['catalogo' => 'tipos_documento_identidad'])->test(Catalogos::class)
        ->mountAction(TestAction::make('nucleoTiposDocumentoIdentidadEditar')->table($carne))
        ->assertFormFieldEnabled('longitud');

    expect($ruc->refresh()->nombre)->toBe('Registro Único de Contribuyentes')
        ->and($ruc->longitud)->toBe(11);
});
