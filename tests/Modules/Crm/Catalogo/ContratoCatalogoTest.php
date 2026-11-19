<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\CatalogoEnBase;
use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Crm\Catalogo\Services\EnMemoria\CatalogoEnMemoria;
use App\Modules\Crm\Catalogo\Services\ResumenProducto;
use App\Modules\Nucleo\Models\UnidadMedida;

/*
 * Contrato de C4 (plan 2.60, D1 a D4): las mismas pruebas contra la versión real y la falsa, para que la falsa que usan
 * otros módulos no se aleje de la real. Productos inventados (regla 10).
 */

beforeEach(function () {
    test()->unidad = UnidadMedida::factory()->create(['codigo' => 'MTR', 'abreviatura' => 'M']);
});

/**
 * Cada versión, con su forma de cargar un producto.
 *
 * @return array{servicio: CatalogoService, agregar: Closure(string, string, ?string=, bool=, bool=, bool=): int}
 */
function ccVersion(string $cual): array
{
    $unidad = test()->unidad;

    if ($cual === 'falsa') {
        $falsa = new CatalogoEnMemoria;

        return [
            'servicio' => $falsa,
            'agregar' => fn (string $codigo, string $descripcion, ?string $corta = null, bool $serie = false, bool $servicio = false, bool $activo = true): int => $falsa->agregar($codigo, $descripcion, $corta, $unidad->id, $unidad->abreviatura, $serie, $servicio, $activo)->id,
        ];
    }

    return [
        'servicio' => app(CatalogoEnBase::class),
        'agregar' => fn (string $codigo, string $descripcion, ?string $corta = null, bool $serie = false, bool $servicio = false, bool $activo = true): int => Producto::factory()->create([
            'codigo' => $codigo, 'descripcion' => $descripcion, 'descripcion_corta' => $corta, 'unidad_medida_id' => $unidad->id,
            'requiere_serie' => $serie, 'es_servicio' => $servicio, 'es_activo' => $activo,
        ])->id,
    ];
}

/** @param list<ResumenProducto> $productos */
function ccCodigos(array $productos): array
{
    return array_map(fn (ResumenProducto $p): string => $p->codigo, $productos);
}

dataset('versiones de C4', ['real', 'falsa']);

test('el resumen trae todos los campos de C4: nombre corto, descripción completa, unidad, serie, servicio y activo', function (string $cual) {
    $v = ccVersion($cual);
    $id = $v['agregar']('CAB-UTP6', 'Cable UTP categoría 6 para exteriores, bobina de 305 m', 'Cable UTP cat. 6', serie: true);
    $p = $v['servicio']->resumen($id);

    expect([$p->id, $p->codigo, $p->nombre, $p->descripcion, $p->unidadMedidaId, $p->unidad, $p->requiereSerie, $p->esServicio, $p->esActivo])
        ->toBe([$id, 'CAB-UTP6', 'Cable UTP cat. 6', 'Cable UTP categoría 6 para exteriores, bobina de 305 m', test()->unidad->id, 'M', true, false, true]);
})->with('versiones de C4');

test('sin descripción corta, el nombre es la descripción completa', function (string $cual) {
    $v = ccVersion($cual);
    $p = $v['servicio']->resumen($v['agregar']('SRV-INST', 'Instalación de punto de red', servicio: true));

    expect([$p->nombre, $p->descripcion, $p->esServicio, $p->requiereSerie])->toBe(['Instalación de punto de red', 'Instalación de punto de red', true, false]);
})->with('versiones de C4');

test('buscar va por código o descripción, sin tildes ni mayúsculas, y solo ofrece los activos', function (string $cual) {
    $v = ccVersion($cual);
    $v['agregar']('CAM-D4M', 'Cámara IP domo 4MP');
    $v['agregar']('CAM-B8M', 'Cámara IP bullet 8MP', 'Bullet 8MP');
    $v['agregar']('CAM-OLD', 'Cámara analógica', activo: false);
    $v['agregar']('NVR-16', 'Grabador de 16 canales');
    $s = $v['servicio'];

    expect(ccCodigos($s->buscar('CAMARA')))->toBe(['CAM-B8M', 'CAM-D4M'])
        ->and(ccCodigos($s->buscar('nvr')))->toBe(['NVR-16'])
        ->and(ccCodigos($s->buscar('bullet')))->toBe(['CAM-B8M'])
        ->and(ccCodigos($s->buscar()))->toBe(['CAM-B8M', 'CAM-D4M', 'NVR-16'])
        ->and(ccCodigos($s->buscar('50%')))->toBe([]);
})->with('versiones de C4');

test('el código exacto sale primero; después, el resto por código', function (string $cual) {
    $v = ccVersion($cual);
    $v['agregar']('SW-24', 'Switch de 24 puertos para el SW-8 de respaldo');
    $v['agregar']('SW-8', 'Switch de 8 puertos');
    $v['agregar']('SW-16', 'Switch de 16 puertos');

    expect(ccCodigos($v['servicio']->buscar('sw-8')))->toBe(['SW-8', 'SW-24'])
        ->and(ccCodigos($v['servicio']->buscar('switch')))->toBe(['SW-16', 'SW-24', 'SW-8']);
})->with('versiones de C4');

test('buscar devuelve hasta veinte', function (string $cual) {
    $v = ccVersion($cual);

    foreach (range(1, 25) as $n) {
        $v['agregar'](sprintf('ITM-%02d', $n), "Ítem de prueba {$n}");
    }

    expect(ccCodigos($v['servicio']->buscar('item')))->toHaveCount(CatalogoService::LIMITE)
        ->and(ccCodigos($v['servicio']->buscar('item'))[0])->toBe('ITM-01');
})->with('versiones de C4');

test('el resumen de un descontinuado se da con su marca; el de uno que no existe se rechaza', function (string $cual) {
    $v = ccVersion($cual);
    $viejo = $v['agregar']('CAM-OLD', 'Cámara analógica', activo: false);

    expect($v['servicio']->resumen($viejo)->esActivo)->toBeFalse()
        ->and(fn () => $v['servicio']->resumen(999999))->toThrow(CatalogoRechazado::class, 'no existe');
})->with('versiones de C4');

test('en el contenedor, C4 es la versión real', function () {
    expect(app(CatalogoService::class))->toBeInstanceOf(CatalogoEnBase::class);
});

test('el código se guarda en mayúsculas y sin espacios: «cam-d4m» y «CAM-D4M» son el mismo', function (string $cual) {
    $v = ccVersion($cual);
    $id = $v['agregar'](' cam-d4m ', 'Cámara IP domo 4MP');

    expect($v['servicio']->resumen($id)->codigo)->toBe('CAM-D4M')
        ->and(fn () => $v['agregar']('CAM-D4M', 'Otra cámara'))->toThrow(Exception::class);
})->with('versiones de C4');
