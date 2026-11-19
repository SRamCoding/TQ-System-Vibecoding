<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * La base protege los catálogos y parámetros aunque se salte el código (regla 5, plan 1.2 D4). Cada
 * prueba inserta por query builder una fila válida con un solo dato roto y espera la restricción exacta.
 */

/**
 * Fila válida de la tabla con los cambios pedidos.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function filaValida(string $tabla, array $cambios = []): array
{
    $base = match ($tabla) {
        'monedas' => ['codigo' => 'EUR', 'nombre' => 'Euro', 'simbolo' => '€', 'decimales' => 2],
        'tipos_cambio' => ['moneda_id' => Moneda::factory()->create()->id, 'fecha' => '2026-10-09', 'compra' => '3.7500', 'venta' => '3.7600', 'origen' => 'manual'],
        'unidades_medida' => ['codigo' => 'NIU', 'nombre' => 'Unidad', 'abreviatura' => 'und', 'codigo_sunat' => 'NIU'],
        'cubso' => ['codigo' => '43211500', 'descripcion' => 'Computadoras'],
        'ubigeos' => ['codigo' => '140101', 'departamento' => 'Lambayeque', 'provincia' => 'Chiclayo', 'distrito' => 'Chiclayo', 'origen' => 'inei'],
        'tipos_documento' => ['codigo' => '01', 'nombre' => 'Factura', 'signo' => 1],
        'formas_pago' => ['codigo' => 'CRED30', 'nombre' => 'Crédito 30 días', 'dias' => 30],
        'lineas_solucion' => ['codigo' => 'REDES', 'nombre' => 'Redes'],
        'tipos_penalidad' => ['codigo' => 'MORA', 'nombre' => 'Mora'],
        'tipos_trabajo' => ['codigo' => 'DESARROLLO', 'nombre' => 'Desarrollo'],
        'tipos_documento_identidad' => ['codigo' => '6', 'nombre' => 'RUC', 'longitud' => 11],
        'roles_party' => ['codigo' => 'cliente', 'nombre' => 'Cliente'],
        'parametros' => ['grupo' => 'pruebas', 'clave' => 'dias', 'valor' => '30', 'tipo' => 'entero', 'descripcion' => 'Días de prueba.'],
    };

    return [...$base, 'created_by' => app(UsuarioSistema::class)->id(), ...$cambios];
}

test('un código de catálogo no se repite', function (string $tabla) {
    DB::table($tabla)->insert(filaValida($tabla));

    expect(fn () => DB::table($tabla)->insert(filaValida($tabla)))
        ->toThrow(QueryException::class, "{$tabla}_codigo_unique");
})->with(['monedas', 'unidades_medida', 'cubso', 'ubigeos', 'tipos_documento', 'formas_pago', 'lineas_solucion', 'tipos_penalidad', 'tipos_trabajo', 'tipos_documento_identidad', 'roles_party']);

test('una moneda tiene un solo tipo de cambio por día', function () {
    $fila = filaValida('tipos_cambio');
    DB::table('tipos_cambio')->insert($fila);

    expect(fn () => DB::table('tipos_cambio')->insert([...$fila, 'compra' => '3.8000']))
        ->toThrow(QueryException::class, 'tipos_cambio_moneda_id_fecha_unique');
});

test('un parámetro no se repite en su grupo', function () {
    DB::table('parametros')->insert(filaValida('parametros'));

    expect(fn () => DB::table('parametros')->insert(filaValida('parametros', ['valor' => '45'])))
        ->toThrow(QueryException::class, 'parametros_grupo_clave_unique');
});

test('la base rechaza un valor imposible', function (string $tabla, array $cambios, string $restriccion) {
    expect(fn () => DB::table($tabla)->insert(filaValida($tabla, $cambios)))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'TC de compra en cero' => ['tipos_cambio', ['compra' => '0'], 'tipos_cambio_compra_check'],
    'TC de venta negativo' => ['tipos_cambio', ['venta' => '-3.7600'], 'tipos_cambio_venta_check'],
    'TC de origen desconocido' => ['tipos_cambio', ['origen' => 'banco'], 'tipos_cambio_origen_check'],
    'moneda con 5 decimales' => ['monedas', ['decimales' => 5], 'monedas_decimales_check'],
    'documento con signo 2' => ['tipos_documento', ['signo' => 2], 'tipos_documento_signo_check'],
    'crédito a días negativos' => ['formas_pago', ['dias' => -30], 'formas_pago_dias_check'],
    'parámetro de tipo desconocido' => ['parametros', ['tipo' => 'numero'], 'parametros_tipo_check'],
    'entero con letras' => ['parametros', ['tipo' => 'entero', 'valor' => 'abc'], 'parametros_valor_check'],
    'decimal con coma' => ['parametros', ['tipo' => 'decimal', 'valor' => '18,5'], 'parametros_valor_check'],
    'decimal con exponente' => ['parametros', ['tipo' => 'decimal', 'valor' => '1e3'], 'parametros_valor_check'],
    'booleano como sí' => ['parametros', ['tipo' => 'booleano', 'valor' => 'sí'], 'parametros_valor_check'],
    'fecha en otro formato' => ['parametros', ['tipo' => 'fecha', 'valor' => '09/10/2026'], 'parametros_valor_check'],
    'versión cero' => ['parametros', ['version' => 0], 'parametros_version_check'],
    'ubigeo de origen desconocido' => ['ubigeos', ['origen' => 'sunat'], 'ubigeos_origen_check'],
    'documento de identidad de longitud cero' => ['tipos_documento_identidad', ['longitud' => 0], 'tipos_documento_identidad_longitud_check'],
    'documento de identidad de longitud 21' => ['tipos_documento_identidad', ['longitud' => 21], 'tipos_documento_identidad_longitud_check'],
]);

test('un elemento de catálogo no tiene versión cero', function (string $tabla) {
    expect(fn () => DB::table($tabla)->insert(filaValida($tabla, ['version' => 0])))
        ->toThrow(QueryException::class, "{$tabla}_version_check");
})->with(['monedas', 'unidades_medida', 'cubso', 'ubigeos', 'tipos_documento', 'formas_pago', 'lineas_solucion', 'tipos_penalidad', 'tipos_trabajo', 'tipos_documento_identidad', 'roles_party']);

test('un ubigeo siempre dice de dónde vino', function () {
    expect(fn () => DB::table('ubigeos')->insert(filaValida('ubigeos', ['origen' => null])))
        ->toThrow(QueryException::class, 'origen');
});

test('la base exige los datos obligatorios', function (string $tabla, string $columna) {
    expect(fn () => DB::table($tabla)->insert(filaValida($tabla, [$columna => null])))
        ->toThrow(QueryException::class, 'not-null');
})->with([
    'TC sin origen' => ['tipos_cambio', 'origen'],
    'TC sin moneda' => ['tipos_cambio', 'moneda_id'],
    'catálogo sin autor' => ['lineas_solucion', 'created_by'],
    'parámetro sin valor' => ['parametros', 'valor'],
    'parámetro sin descripción' => ['parametros', 'descripcion'],
]);

test('un elemento de catálogo en uso no se puede borrar', function () {
    $fila = filaValida('tipos_cambio');
    DB::table('tipos_cambio')->insert($fila);

    expect(fn () => DB::table('monedas')->where('id', $fila['moneda_id'])->delete())
        ->toThrow(QueryException::class, 'tipos_cambio_moneda_id_foreign');
});

test('los valores válidos de un parámetro sí entran', function (string $tipo, string $valor) {
    DB::table('parametros')->insert(filaValida('parametros', ['tipo' => $tipo, 'valor' => $valor]));

    expect(DB::table('parametros')->where('clave', 'dias')->value('valor'))->toBe($valor);
})->with([
    ['entero', '-5'], ['decimal', '18.00'], ['decimal', '0.000001'], ['booleano', 'false'],
    ['fecha', '2027-01-01'], ['texto', 'PEN'],
]);
