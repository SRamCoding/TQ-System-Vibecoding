<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Services\CatalogosService;

/*
 * La búsqueda de los selectores de catálogo en la base (plan 2.0, paso 2, D1): sin acentos ni mayúsculas, primero lo
 * que empieza con el texto, hasta el límite, el valor actual aunque esté desactivado, y los ubigeos por niveles con su
 * ruta. Datos inventados.
 */

function catalogosBuscar(): CatalogosService
{
    return app(CatalogosService::class);
}

/** Tres distritos: dos «Chiclayo» de provincias distintas y uno de otro departamento. */
function ubigeosParaBuscar(): array
{
    return [
        'chiclayo' => Ubigeo::factory()->create(['codigo' => '140101', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHICLAYO']),
        'jose' => Ubigeo::factory()->create(['codigo' => '140105', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'JOSÉ LEONARDO ORTIZ']),
        'ferrenafe' => Ubigeo::factory()->create(['codigo' => '140201', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'FERREÑAFE', 'distrito' => 'FERREÑAFE']),
        'otro' => Ubigeo::factory()->create(['codigo' => '150101', 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'LIMA']),
        'apagado' => Ubigeo::factory()->create(['codigo' => '150102', 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'ANCÓN', 'es_activo' => false]),
    ];
}

test('busca sin acentos ni mayúsculas, primero lo que empieza con el texto, y solo activos', function () {
    $credito = FormaPago::factory()->create(['codigo' => 'CREDITO_30', 'nombre' => 'Crédito a 30 días']);
    $contado = FormaPago::factory()->create(['codigo' => 'CONTADO', 'nombre' => 'Contado contra crédito']);
    FormaPago::factory()->create(['codigo' => 'CREDITO_90', 'nombre' => 'Crédito a 90 días', 'es_activa' => false]);

    expect(array_keys(catalogosBuscar()->buscar(Catalogo::FormasPago, 'CREDITO')))->toBe([$credito->id, $contado->id])
        ->and(catalogosBuscar()->buscar(Catalogo::FormasPago, 'credito a'))->toBe([$credito->id => 'Crédito a 30 días'])
        ->and(catalogosBuscar()->buscar(Catalogo::FormasPago, 'cont'))->toBe([$contado->id => 'Contado contra crédito']);
});

test('sin texto trae los primeros por código, hasta el límite; un valor nuevo aparece al buscarlo', function () {
    foreach (range(1, 5) as $n) {
        FormaPago::factory()->create(['codigo' => "F{$n}", 'nombre' => "Forma {$n}"]);
    }

    expect(catalogosBuscar()->buscar(Catalogo::FormasPago, '', 3))->toHaveCount(3)
        ->and(catalogosBuscar()->buscar(Catalogo::FormasPago, 'nueva'))->toBe([]);

    $nueva = FormaPago::factory()->create(['codigo' => 'NUEVA', 'nombre' => 'Nueva forma']);

    expect(catalogosBuscar()->buscar(Catalogo::FormasPago, 'nueva'))->toBe([$nueva->id => 'Nueva forma'])
        ->and(catalogosBuscar()->cuantosActivos(Catalogo::FormasPago))->toBe(6);
});

test('los comodines del texto se buscan tal cual', function () {
    $con = FormaPago::factory()->create(['codigo' => 'P100', 'nombre' => 'Pago 100% adelantado']);
    FormaPago::factory()->create(['codigo' => 'P50', 'nombre' => 'Pago 50 adelantado']);

    expect(array_keys(catalogosBuscar()->buscar(Catalogo::FormasPago, '%')))->toBe([$con->id]);
});

test('la etiqueta del valor actual sale aunque esté desactivado, y nula si no existe', function () {
    $apagada = FormaPago::factory()->create(['codigo' => 'VIEJA', 'nombre' => 'Letra a 120 días', 'es_activa' => false]);

    expect(catalogosBuscar()->etiquetaDe(Catalogo::FormasPago, $apagada->id))->toBe('Letra a 120 días (desactivado)')
        ->and(catalogosBuscar()->etiquetaDe(Catalogo::FormasPago, 999999))->toBeNull();
});

test('ubigeos: al escribir busca solo distritos, con su ruta', function () {
    $d = ubigeosParaBuscar();

    expect(catalogosBuscar()->buscar(Catalogo::Ubigeos, 'chiclayo'))->toBe([$d['chiclayo']->id => 'CHICLAYO — CHICLAYO, LAMBAYEQUE'])
        ->and(catalogosBuscar()->buscar(Catalogo::Ubigeos, 'ferrenafe'))->toBe([$d['ferrenafe']->id => 'FERREÑAFE — FERREÑAFE, LAMBAYEQUE'])
        ->and(catalogosBuscar()->buscar(Catalogo::Ubigeos, 'ancon'))->toBe([])
        ->and(catalogosBuscar()->etiquetaDe(Catalogo::Ubigeos, $d['apagado']->id))->toBe('ANCÓN — LIMA, LIMA (desactivado)');
});

test('ubigeos por niveles: departamentos, sus provincias y sus distritos, solo activos', function () {
    $d = ubigeosParaBuscar();

    expect(catalogosBuscar()->departamentos())->toBe(['14' => 'LAMBAYEQUE', '15' => 'LIMA'])
        ->and(catalogosBuscar()->provincias('14'))->toBe(['1401' => 'CHICLAYO', '1402' => 'FERREÑAFE'])
        ->and(catalogosBuscar()->distritos('1401'))->toBe([$d['chiclayo']->id => 'CHICLAYO — CHICLAYO, LAMBAYEQUE', $d['jose']->id => 'JOSÉ LEONARDO ORTIZ — CHICLAYO, LAMBAYEQUE'])
        ->and(catalogosBuscar()->distritos('1501'))->toBe([$d['otro']->id => 'LIMA — LIMA, LIMA']);
});
