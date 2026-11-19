<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Services\UbigeosService;
use App\Modules\Nucleo\Ubigeos\DistritoUbigeo;
use Illuminate\Support\Facades\Http;
use Tests\Modules\Nucleo\Fixtures\XlsxDePrueba;

/*
 * Actualización de ubigeos sobre la base, la que usa el botón de Configuración. Plan 1.2b, E7: los
 * agregados a mano no se apagan y lo desactivado a mano no se reactiva solo.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.url' => 'https://datos.prueba/ubigeos.xlsx', 'nucleo.ubigeos.minimo_distritos' => 1]);

    Ubigeo::factory()->create(['codigo' => '140101', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHICLAYO']);
    Ubigeo::factory()->create(['codigo' => '140103', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'ETEN']);
    Ubigeo::factory()->create(['codigo' => '999999', 'departamento' => 'X', 'provincia' => 'Y', 'distrito' => 'RETIRADO']);
    Ubigeo::factory()->create(['codigo' => '140120', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'DESACTIVADO A MANO', 'es_activo' => false]);
    // Agregados a mano: uno que el INEI no publica, uno que publica igual y uno que publica con otro nombre.
    Ubigeo::factory()->create(['codigo' => '140199', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'SOLO NUESTRO', 'origen' => OrigenUbigeo::Manual]);
    Ubigeo::factory()->create(['codigo' => '140102', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'CHONGOYAPE', 'origen' => OrigenUbigeo::Manual]);
    Ubigeo::factory()->create(['codigo' => '140104', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'LAGUNA', 'origen' => OrigenUbigeo::Manual]);
});

/** @return list<DistritoUbigeo> */
function listaVigente(): array
{
    return [
        new DistritoUbigeo('140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO'),
        new DistritoUbigeo('140102', 'LAMBAYEQUE', 'CHICLAYO', 'CHONGOYAPE'),
        new DistritoUbigeo('140103', 'LAMBAYEQUE', 'CHICLAYO', 'CIUDAD ETEN'),
        new DistritoUbigeo('140104', 'LAMBAYEQUE', 'CHICLAYO', 'LAGUNAS'),
        new DistritoUbigeo('140120', 'LAMBAYEQUE', 'CHICLAYO', 'DESACTIVADO A MANO'),
        new DistritoUbigeo('140121', 'LAMBAYEQUE', 'CHICLAYO', 'NUEVO'),
    ];
}

test('descarga la lista vigente del INEI', function () {
    Http::fake(['datos.prueba/*' => Http::response(XlsxDePrueba::inei([['140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO']]))]);

    expect(app(UbigeosService::class)->descargar())->toEqual([new DistritoUbigeo('140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO')]);
});

test('las diferencias se calculan contra toda la base, sin cambiar nada', function () {
    $diferencia = app(UbigeosService::class)->diferencias(listaVigente());

    expect(array_map(fn ($d) => $d->codigo, $diferencia->nuevos))->toBe(['140121'])
        ->and(array_map(fn ($r) => $r['ahora']->distrito, $diferencia->renombrados))->toBe(['CIUDAD ETEN', 'LAGUNAS'])
        ->and(array_map(fn ($d) => $d->codigo, $diferencia->retirados))->toBe(['999999'])
        ->and(array_map(fn ($d) => $d->codigo, $diferencia->adoptados))->toBe(['140102'])
        ->and(Ubigeo::query()->where('codigo', '140103')->value('distrito'))->toBe('ETEN');
});

test('aplicar crea, renombra, adopta y desactiva, sin borrar', function () {
    app(UbigeosService::class)->aplicar(listaVigente());

    $ubigeo = fn (string $codigo): Ubigeo => Ubigeo::query()->where('codigo', $codigo)->firstOrFail();

    expect($ubigeo('140121')->distrito)->toBe('NUEVO')
        ->and($ubigeo('140121')->origen)->toBe(OrigenUbigeo::Inei)
        ->and($ubigeo('140103')->distrito)->toBe('CIUDAD ETEN')
        ->and($ubigeo('999999')->es_activo)->toBeFalse()
        ->and(Ubigeo::query()->count())->toBe(8)
        ->and(app(UbigeosService::class)->diferencias(listaVigente())->hayCambios())->toBeFalse();
});

test('un distrito agregado a mano que el INEI no publica sigue activo', function () {
    app(UbigeosService::class)->aplicar(listaVigente());

    $manual = Ubigeo::query()->where('codigo', '140199')->firstOrFail();
    expect($manual->es_activo)->toBeTrue()
        ->and($manual->origen)->toBe(OrigenUbigeo::Manual);
});

test('lo desactivado a mano no se reactiva aunque el INEI lo publique', function () {
    app(UbigeosService::class)->aplicar(listaVigente());

    expect(Ubigeo::query()->where('codigo', '140120')->value('es_activo'))->toBeFalse();
});

test('un agregado a mano que el INEI publica pasa a ser del INEI, con su nombre', function () {
    app(UbigeosService::class)->aplicar(listaVigente());

    $igual = Ubigeo::query()->where('codigo', '140102')->firstOrFail();
    $renombrado = Ubigeo::query()->where('codigo', '140104')->firstOrFail();
    expect($igual->origen)->toBe(OrigenUbigeo::Inei)
        ->and($renombrado->origen)->toBe(OrigenUbigeo::Inei)
        ->and($renombrado->distrito)->toBe('LAGUNAS');
});
