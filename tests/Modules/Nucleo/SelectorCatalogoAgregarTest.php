<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\CatalogosService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Modules\Nucleo\Fixtures\FormularioConCatalogo;

/*
 * El «+» del selector de catálogo (plan 2.0, paso 4, D2): solo con el permiso de agregar a ese catálogo y nunca en uno
 * cerrado; sugiere el código desde el nombre, avisa de un parecido sin bloquear, crea por la acción registrada (en la
 * bitácora) y deja elegido lo creado. Datos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function agregarAlSelector(string $campo): mixed
{
    return Livewire::test(FormularioConCatalogo::class)->call('mountAction', 'createOption', [], ['schemaComponent' => "form.{$campo}"]);
}

test('sin permiso de editar catálogos no hay «+»; con él, sí; en un catálogo cerrado, nunca', function () {
    test()->actingAs(User::factory()->create());

    expect(SelectorCatalogo::puedeAgregar(SelectorCatalogo::claveAgregar(Catalogo::OrigenesComerciales)))->toBeFalse();

    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.editar'));

    expect(SelectorCatalogo::puedeAgregar(SelectorCatalogo::claveAgregar(Catalogo::OrigenesComerciales)))->toBeTrue()
        ->and(SelectorCatalogo::de(Catalogo::Rubros, 'rubro_id')->getCreateOptionUsing())->toBeNull()
        ->and(SelectorCatalogo::de(Catalogo::OrigenesComerciales, 'origen_id')->getCreateOptionUsing())->not->toBeNull();
});

test('el código se sugiere desde el nombre con el formato de cada catálogo; en los oficiales, no', function () {
    expect(Catalogo::OrigenesComerciales->codigoDesdeNombre('Convocatoria privada'))->toBe('CONVOCATORIA_PRIVADA')
        ->and(Catalogo::RedesSociales->codigoDesdeNombre('Mastodón Perú'))->toBe('mastodon_peru')
        ->and(Catalogo::CentrosCosto->codigoDesdeNombre('Obras civiles'))->toBe('OBRASCIVILES')
        ->and(Catalogo::FormasPago->codigoDesdeNombre('3 cuotas'))->toBe('C3_CUOTAS')
        ->and(Catalogo::FrasesSaludo->codigoDesdeNombre('Buen día, equipo'))->toBe('buen-dia-equipo')
        ->and(Catalogo::Monedas->codigoDesdeNombre('Euro'))->toBeNull()
        ->and(Catalogo::Ubigeos->codigoDesdeNombre('Chiclayo'))->toBeNull();
});

test('avisa de uno igual o parecido sin acentos ni mayúsculas, aunque esté desactivado', function () {
    // «Feria» viene sembrado.
    OrigenComercial::factory()->create(['codigo' => 'VIEJO_X', 'nombre' => 'Llamada en frío', 'es_activo' => false]);

    expect(app(CatalogosService::class)->parecidos(Catalogo::OrigenesComerciales, 'feria'))->toBe(['Feria'])
        ->and(app(CatalogosService::class)->parecidos(Catalogo::OrigenesComerciales, 'LLAMADA EN FRIO'))->toBe(['Llamada en frío (desactivado)'])
        // Parecido sobre el umbral de parties (0.70); «Ferias» frente a «Feria» (0.62) no avisa.
        ->and(SelectorCatalogo::avisoParecidos(Catalogo::OrigenesComerciales, 'Llamadas en frio'))->toBe('Ya hay uno igual o parecido: «Llamada en frío (desactivado)». Revisa la lista antes de agregar.')
        ->and(SelectorCatalogo::avisoParecidos(Catalogo::OrigenesComerciales, 'Ferias'))->toBeNull()
        ->and(SelectorCatalogo::avisoParecidos(Catalogo::OrigenesComerciales, 'Radio local'))->toBeNull();
});

test('agregar desde el selector crea por la acción, queda en la bitácora y queda elegido', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.editar'));

    $pantalla = agregarAlSelector('origen_id')
        ->set('mountedActions.0.data.nombre', 'Radio local')
        ->assertSet('mountedActions.0.data.codigo', 'RADIO_LOCAL')
        ->call('callMountedAction')
        ->assertHasNoErrors();
    $nuevo = OrigenComercial::query()->where('codigo', 'RADIO_LOCAL')->sole();

    $pantalla->assertSet('data.origen_id', $nuevo->id);

    expect(AuditoriaCambio::query()->where('auditable_id', $nuevo->id)->where('evento', 'creado')->exists())->toBeTrue();
});

test('un código repetido se rechaza con su mensaje y el panel sigue abierto', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.editar'));

    agregarAlSelector('origen_id')
        ->set('mountedActions.0.data.nombre', 'Otra feria')
        ->set('mountedActions.0.data.codigo', 'FERIA')
        ->call('callMountedAction')
        ->assertNotified('Ya existe «FERIA» en orígenes comerciales. El código no se repite.')
        ->assertSet('mountedActions.0.data.nombre', 'Otra feria')
        ->assertSet('data.origen_id', null);

    expect(OrigenComercial::query()->where('nombre', 'Otra feria')->exists())->toBeFalse();
});
