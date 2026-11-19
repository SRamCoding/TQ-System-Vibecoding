<?php

declare(strict_types=1);

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Modules\Nucleo\Fixtures\DefinicionVersionadaPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoVersionadoPrueba;

/*
 * Planes 2.9 y 2.22, D2: las versiones de un documento comparten número. El motor numera la versión 1; las siguientes
 * traen el número de su versión anterior, que tiene que existir.
 */

beforeEach(function () {
    DocumentoVersionadoPrueba::crearTabla();
});

function ncRegistrar(bool $comparten = true): void
{
    app(RegistroDocumentos::class)->registrar(new DefinicionVersionadaPrueba($comparten));
    app(SincronizadorEstados::class)->sincronizar();
}

/**
 * @param  array<string, mixed>  $datos
 */
function ncCrear(array $datos = []): DocumentoVersionadoPrueba
{
    return app(TransicionesService::class)->crear(new DocumentoVersionadoPrueba(['nombre' => 'Oferta', ...$datos]), User::factory()->create())->refresh();
}

test('la versión 1 toma su número del motor; la 2 y la 3 lo heredan', function () {
    ncRegistrar();
    $anio = now()->format('Y');

    $v1 = ncCrear();
    $v2 = ncCrear(['numero' => $v1->numero, 'numero_version' => 2]);
    $v3 = ncCrear(['numero' => $v1->numero, 'numero_version' => 3]);
    $otro = ncCrear();

    expect($v1->numero)->toBe("DVP-{$anio}-0001")
        ->and($v1->numero_version)->toBe(1)
        ->and($v2->numero)->toBe("DVP-{$anio}-0001")
        ->and($v3->numero)->toBe("DVP-{$anio}-0001")
        ->and($otro->numero)->toBe("DVP-{$anio}-0002")
        ->and((new DefinicionVersionadaPrueba)->identificador($v3))->toBe("DVP-{$anio}-0001 v3");
});

test('la versión 1 no trae número puesto por el servicio', function () {
    ncRegistrar();

    expect(fn () => ncCrear(['numero' => 'DVP-2026-0999', 'numero_version' => 1]))
        ->toThrow(LogicException::class, 'El documento versionado de prueba v1 toma su número al crearse: el servicio no se lo pone.');
});

test('una versión 2 sin su versión anterior no se crea, ni sin número', function () {
    ncRegistrar();
    $v1 = ncCrear();

    expect(fn () => ncCrear(['numero' => $v1->numero, 'numero_version' => 3]))
        ->toThrow(LogicException::class, 'El documento versionado de prueba v3 lleva el número de su versión anterior, y esa versión no existe.')
        ->and(fn () => ncCrear(['numero_version' => 2]))
        ->toThrow(LogicException::class, 'esa versión no existe');
});

test('la misma versión dos veces la rechaza la base', function () {
    ncRegistrar();
    $v1 = ncCrear();
    ncCrear(['numero' => $v1->numero, 'numero_version' => 2]);

    expect(fn () => ncCrear(['numero' => $v1->numero, 'numero_version' => 2]))->toThrow(UniqueConstraintViolationException::class);
});

test('sin versiones compartidas, cada documento numera aunque traiga versión', function () {
    ncRegistrar(comparten: false);
    $v1 = ncCrear(['numero_version' => 1]);
    $otro = ncCrear(['numero_version' => 2]);

    expect($otro->numero)->not->toBe($v1->numero);
});
