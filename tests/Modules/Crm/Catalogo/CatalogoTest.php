<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\EnMemoria\CatalogoEnMemoria;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lo que solo la versión real de C4 puede revisar (plan 2.60, D1): las reglas de la tabla `productos` y del modelo.
 * Lo común a las dos versiones está en ContratoCatalogoTest.php. Productos inventados (regla 10).
 */

test('el código de un producto es único en la base', function () {
    Producto::factory()->create(['codigo' => 'CAM-D4M']);

    expect(fn () => DB::transaction(fn () => Producto::factory()->create(['codigo' => 'CAM-D4M'])))
        ->toThrow(QueryException::class, 'productos_codigo_unique');
});

test('código, descripción y descripción corta no van en blanco', function (array $datos) {
    expect(fn () => DB::transaction(fn () => Producto::factory()->create($datos)))
        ->toThrow(QueryException::class, 'productos_textos_check');
})->with([
    'código' => [['codigo' => '   ']],
    'descripción' => [['descripcion' => '  ']],
    'descripción corta vacía' => [['descripcion_corta' => ' ']],
]);

test('la base rechaza un código en minúsculas o con espacios que no pasó por el modelo (D6)', function (string $codigo) {
    expect(fn () => DB::transaction(fn () => DB::table('productos')->insert([
        'codigo' => $codigo, 'descripcion' => 'Cámara IP domo 4MP', 'unidad_medida_id' => Producto::factory()->create()->unidad_medida_id,
        'created_by' => User::factory()->create()->id,
    ])))->toThrow(QueryException::class, 'productos_codigo_mayusculas_check');
})->with(['cam-d4m', ' CAM-D4M']);

test('la versión no baja de uno', function () {
    $producto = Producto::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('productos')->where('id', $producto->id)->update(['version' => 0])))
        ->toThrow(QueryException::class, 'productos_version_check');
});

test('el código no cambia nunca: las líneas de otros módulos lo guardan', function () {
    $producto = Producto::factory()->create(['codigo' => 'CAM-D4M']);

    expect(fn () => $producto->update(['codigo' => 'CAM-D4M2']))->toThrow(LogicException::class, '«codigo» de productos no cambia')
        ->and($producto->refresh()->codigo)->toBe('CAM-D4M');
});

test('la versión en memoria también rechaza un código repetido', function () {
    $falsa = new CatalogoEnMemoria;
    $falsa->agregar('CAM-D4M', 'Cámara IP domo 4MP');

    expect(fn () => $falsa->agregar('CAM-D4M', 'Otra cámara'))->toThrow(InvalidArgumentException::class, 'ya está en el catálogo');
});
