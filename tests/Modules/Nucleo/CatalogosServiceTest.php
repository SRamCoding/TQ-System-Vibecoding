<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\CatalogoNoEncontrado;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Services\CatalogosService;

function catalogos(): CatalogosService
{
    return app(CatalogosService::class);
}

test('da el id de un elemento activo por su código y el código por su id', function () {
    $usd = Moneda::factory()->create(['codigo' => 'USD']);

    expect(catalogos()->idPorCodigo(Catalogo::Monedas, 'USD'))->toBe($usd->id)
        ->and(catalogos()->codigoDe(Catalogo::Monedas, $usd->id))->toBe('USD');
});

test('un código desactivado o inexistente no se entrega', function () {
    FormaPago::factory()->create(['codigo' => 'CREDITO_90', 'es_activa' => false]);

    expect(fn () => catalogos()->idPorCodigo(Catalogo::FormasPago, 'CREDITO_90'))
        ->toThrow(CatalogoNoEncontrado::class, '«CREDITO_90» no está en formas de pago o está desactivado')
        ->and(fn () => catalogos()->idPorCodigo(Catalogo::Monedas, 'XXX'))->toThrow(CatalogoNoEncontrado::class);
});

test('valida que un id siga activo, con la columna de activo de cada catálogo', function () {
    $activa = Moneda::factory()->create();
    $inactiva = Moneda::factory()->create(['es_activa' => false]);
    $distrito = Ubigeo::factory()->create(['es_activo' => false]);

    expect(catalogos()->estaActivo(Catalogo::Monedas, $activa->id))->toBeTrue()
        ->and(catalogos()->estaActivo(Catalogo::Monedas, $inactiva->id))->toBeFalse()
        ->and(catalogos()->estaActivo(Catalogo::Ubigeos, $distrito->id))->toBeFalse();
});

test('las opciones de un selector traen solo activos, ordenados por código y con su etiqueta', function () {
    $chiclayo = Ubigeo::factory()->create(['codigo' => '140101', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHICLAYO']);
    $amazonas = Ubigeo::factory()->create(['codigo' => '010101', 'departamento' => 'AMAZONAS', 'provincia' => 'CHACHAPOYAS', 'distrito' => 'CHACHAPOYAS']);
    Ubigeo::factory()->create(['codigo' => '999999', 'es_activo' => false]);

    expect(catalogos()->opciones(Catalogo::Ubigeos))->toBe([
        $amazonas->id => 'CHACHAPOYAS, CHACHAPOYAS (AMAZONAS)',
        $chiclayo->id => 'CHICLAYO, CHICLAYO (LAMBAYEQUE)',
    ]);
});

test('las opciones incluyen, marcado, lo que el registro ya tiene aunque se haya desactivado', function () {
    $activa = FormaPago::factory()->create(['codigo' => 'A', 'nombre' => 'Contado']);
    $vieja = FormaPago::factory()->create(['codigo' => 'B', 'nombre' => 'Crédito viejo', 'es_activa' => false]);

    expect(catalogos()->opciones(Catalogo::FormasPago, $vieja->id))->toBe([$activa->id => 'Contado', $vieja->id => 'Crédito viejo (desactivado)'])
        ->and(catalogos()->opciones(Catalogo::FormasPago, $activa->id))->toBe([$activa->id => 'Contado'])
        ->and(catalogos()->opciones(Catalogo::FormasPago, 999999))->toBe([$activa->id => 'Contado']);
});

test('cada catálogo del enum apunta a un modelo de catálogo con su columna de activo', function (Catalogo $catalogo) {
    $modelo = $catalogo->modelo();

    expect(is_subclass_of($modelo, ElementoCatalogo::class))->toBeTrue()
        ->and((new $modelo)->getTable())->toBe($catalogo->value)
        ->and(in_array($modelo::columnaActivo(), ['es_activo', 'es_activa'], true))->toBeTrue()
        ->and(Schema::hasColumn($catalogo->value, $modelo::columnaActivo()))->toBeTrue();
})->with(Catalogo::cases());
