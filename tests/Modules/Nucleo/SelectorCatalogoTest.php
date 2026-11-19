<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use Filament\Forms\Components\Select;
use Tests\Modules\Nucleo\Fixtures\AccionDePrueba;

/*
 * El selector de catálogo común (plan 2.0, paso 3, D1 y D4): el campo de catálogo de una acción busca en la base al
 * escribir, trae el valor actual aunque esté desactivado y, en ubigeos, va por niveles. Datos inventados.
 */

test('el campo de catálogo de una acción es el selector común y busca en la base al escribir', function () {
    $select = AccionFilament::campo(new AccionDePrueba, 'unidad_medida_id');
    $kilo = UnidadMedida::factory()->create(['codigo' => 'KGM', 'nombre' => 'Kilogramo']);

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->hasDynamicSearchResults())->toBeTrue()
        ->and($select->getSearchResults('kilo'))->toBe([$kilo->id => 'Kilogramo']);

    $metro = UnidadMedida::factory()->create(['codigo' => 'MTR', 'nombre' => 'Metro']);

    expect($select->getSearchResults('metro'))->toBe([$metro->id => 'Metro']);
});

test('al abrir trae los primeros por código y el valor actual aunque esté desactivado', function () {
    $activa = FormaPago::factory()->create(['codigo' => 'CONTADO', 'nombre' => 'Contado']);
    $apagada = FormaPago::factory()->create(['codigo' => 'LETRA', 'nombre' => 'Letra a 120 días', 'es_activa' => false]);

    expect(SelectorCatalogo::alAbrir(Catalogo::FormasPago, null))->toBe([$activa->id => 'Contado'])
        ->and(SelectorCatalogo::alAbrir(Catalogo::FormasPago, (string) $apagada->id))->toBe([$activa->id => 'Contado', $apagada->id => 'Letra a 120 días (desactivado)'])
        ->and(SelectorCatalogo::alAbrir(Catalogo::FormasPago, [$apagada->id], varios: true))->toHaveKey($apagada->id)
        ->and(SelectorCatalogo::etiqueta(Catalogo::FormasPago, $apagada->id))->toBe('Letra a 120 días (desactivado)');
});

test('ubigeos por niveles: departamentos, provincias con cómo volver, y distritos con su ruta', function () {
    $chiclayo = Ubigeo::factory()->create(['codigo' => '140101', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHICLAYO']);
    Ubigeo::factory()->create(['codigo' => '140201', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'FERREÑAFE', 'distrito' => 'FERREÑAFE']);
    Ubigeo::factory()->create(['codigo' => '150101', 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'LIMA']);

    expect(SelectorCatalogo::alAbrir(Catalogo::Ubigeos, null))->toBe(['dep:14' => 'LAMBAYEQUE ›', 'dep:15' => 'LIMA ›'])
        ->and(SelectorCatalogo::alAbrir(Catalogo::Ubigeos, 'dep:14'))->toBe([
            'dep:' => '‹ Todos los departamentos', 'dep:14' => 'LAMBAYEQUE ›', 'prov:1401' => 'CHICLAYO ›', 'prov:1402' => 'FERREÑAFE ›',
        ])
        ->and(SelectorCatalogo::alAbrir(Catalogo::Ubigeos, 'prov:1401'))->toBe([
            'dep:14' => '‹ Provincias de LAMBAYEQUE', 'prov:1401' => 'CHICLAYO, LAMBAYEQUE ›', $chiclayo->id => 'CHICLAYO — CHICLAYO, LAMBAYEQUE',
        ])
        ->and(SelectorCatalogo::alAbrir(Catalogo::Ubigeos, $chiclayo->id))->toHaveKey($chiclayo->id)
        ->and(SelectorCatalogo::alAbrir(Catalogo::Ubigeos, 'dep:'))->toBe(['dep:14' => 'LAMBAYEQUE ›', 'dep:15' => 'LIMA ›']);
});

test('en ubigeos, un departamento o una provincia elegidos no se guardan: pide un distrito', function () {
    $select = SelectorCatalogo::de(Catalogo::Ubigeos, 'ubigeo_id');

    expect($select->isLive())->toBeTrue()
        ->and($select->getValidationMessages())->toHaveKey('integer');
});
