<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/*
 * Los campos editables de cada catálogo (plan 1.2b, E5–E8) son sus columnas: si una migración agrega una
 * columna y nadie la declara aquí, la pantalla no podría escribirla.
 */

test('los campos de un catálogo son exactamente sus columnas editables', function (Catalogo $catalogo) {
    $modelo = $catalogo->modelo();
    $noEditables = ['id', 'codigo', $modelo::columnaActivo(), 'version', 'origen', 'created_by', 'created_at', 'updated_by', 'updated_at', ...$catalogo->columnasDeServicio()];

    $columnas = array_values(array_diff(Schema::getColumnListing($catalogo->value), $noEditables));
    $campos = array_map(fn (Campo $campo): string => $campo->nombre, $catalogo->campos());
    sort($columnas);
    sort($campos);

    expect($campos)->toBe($columnas)
        ->and($catalogo->campoCodigo()->nombre)->toBe('codigo');
})->with(Catalogo::cases());

test('el modelo acepta el código y todos los campos del catálogo', function (Catalogo $catalogo) {
    $modelo = $catalogo->modelo();
    $campos = array_map(fn (Campo $campo): string => $campo->nombre, [$catalogo->campoCodigo(), ...$catalogo->campos()]);

    expect(array_diff($campos, (new $modelo)->getFillable()))->toBe([]);
})->with(Catalogo::cases());

test('solo monedas y tipos de documento tienen campos que mueven dinero, y son campos del catálogo', function (Catalogo $catalogo) {
    $campos = array_map(fn (Campo $campo): string => $campo->nombre, $catalogo->campos());

    expect(array_diff($catalogo->camposSensibles(), $campos))->toBe([])
        ->and($catalogo->tieneSensibles())->toBe(in_array($catalogo, [Catalogo::Monedas, Catalogo::TiposDocumento], true));
})->with(Catalogo::cases());

test('el código respeta el formato de su lista oficial', function (Catalogo $catalogo, string $valido, string $invalido) {
    $reglas = ['codigo' => $catalogo->campoCodigo()->reglasDeValidacion()];

    expect(Validator::make(['codigo' => $valido], $reglas)->passes())->toBeTrue()
        ->and(Validator::make(['codigo' => $invalido], $reglas)->passes())->toBeFalse();
})->with([
    'moneda en minúsculas' => [Catalogo::Monedas, 'EUR', 'eur'],
    'ubigeo con letras' => [Catalogo::Ubigeos, '140101', '14010A'],
    'tipo de documento largo' => [Catalogo::TiposDocumento, '07', '0701'],
    'documento de identidad en minúsculas' => [Catalogo::TiposDocumentoIdentidad, 'A', 'a'],
    'documento de identidad largo' => [Catalogo::TiposDocumentoIdentidad, '6', '0601'],
    'rol en mayúsculas' => [Catalogo::RolesParty, 'agente_aduanas', 'CLIENTE'],
    'rol con espacios' => [Catalogo::RolesParty, 'cliente', 'cliente final'],
]);

test('un campo de elemento de catálogo acepta un id existente y lo entrega como entero', function () {
    $unidad = UnidadMedida::factory()->create();
    $campo = Campo::elementoDe('unidad_medida_id', 'Unidad de medida', Catalogo::UnidadesMedida);
    $reglas = ['unidad_medida_id' => $campo->reglasDeValidacion()];

    expect($campo->tipo)->toBe(TipoCampo::ElementoCatalogo)
        ->and($campo->catalogo)->toBe(Catalogo::UnidadesMedida)
        ->and($campo->opcional()->catalogo)->toBe(Catalogo::UnidadesMedida)
        ->and(Validator::make(['unidad_medida_id' => (string) $unidad->id], $reglas)->passes())->toBeTrue()
        ->and(Validator::make(['unidad_medida_id' => $unidad->id + 1000], $reglas)->passes())->toBeFalse()
        ->and($campo->convertir((string) $unidad->id))->toBe($unidad->id);
});
