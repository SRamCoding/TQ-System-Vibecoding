<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\SedeRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Parties\DatosSede;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SedesPartyService;

/*
 * Sedes de una party (plan 1.3b, D3 y D4): lugares operativos, sin la dirección fiscal ni la de una obra. Nombre y
 * anexo SUNAT no se repiten entre las activas; el contacto es un vínculo vigente de la misma party.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function sedes(): SedesPartyService
{
    return app(SedesPartyService::class);
}

function datosSede(string $nombre = 'Almacén Chiclayo', string $direccion = 'Av. Principal 123', mixed ...$otros): DatosSede
{
    return new DatosSede($nombre, $direccion, ...$otros);
}

function versionSede(int $id): int
{
    return (int) PartySede::query()->whereKey($id)->value('version');
}

test('agrega una sede con su anexo, coordenadas, ubigeo y contacto', function () {
    $party = Party::factory()->juridica()->create();
    $contacto = PartyContacto::factory()->create(['party_id' => $party->id]);
    $ubigeo = Ubigeo::factory()->create();

    $id = sedes()->agregar($party->id, datosSede(ubigeoId: $ubigeo->id, latitud: '-6,7714', longitud: '-79.8409', contactoId: $contacto->id, codigoEstablecimiento: '0001'));
    $sede = PartySede::query()->findOrFail($id);

    expect($sede->nombre_comparable)->toBe('almacen chiclayo')
        ->and($sede->latitud)->toBe('-6.7714000')
        ->and($sede->longitud)->toBe('-79.8409000')
        ->and($sede->contacto_id)->toBe($contacto->id)
        ->and($sede->codigo_establecimiento)->toBe('0001')
        ->and($sede->es_activa)->toBeTrue();
});

test('se rechaza una sede incompleta o con datos imposibles', function (Closure $datos, string $mensaje) {
    $party = Party::factory()->juridica()->create();

    expect(fn () => sedes()->agregar($party->id, $datos()))->toThrow(SedeRechazada::class, $mensaje);
})->with([
    'sin nombre' => [fn () => datosSede(' '), 'nombre y dirección'],
    'sin dirección' => [fn () => datosSede(direccion: ''), 'nombre y dirección'],
    'anexo de tres dígitos' => [fn () => datosSede(codigoEstablecimiento: '001'), '4 dígitos'],
    'solo latitud' => [fn () => datosSede(latitud: '-6.7714'), 'latitud y longitud juntas'],
    'latitud fuera de rango' => [fn () => datosSede(latitud: '-90.0000001', longitud: '0'), 'latitud es un número entre -90 y 90'],
    'longitud con letras' => [fn () => datosSede(latitud: '0', longitud: '79O'), 'longitud es un número'],
    'más de 7 decimales' => [fn () => datosSede(latitud: '-6.77140001', longitud: '0'), '7 decimales'],
]);

test('los bordes del rango de coordenadas se aceptan', function () {
    $id = sedes()->agregar(Party::factory()->juridica()->create()->id, datosSede(latitud: '-90', longitud: '180'));

    expect(PartySede::query()->findOrFail($id)->longitud)->toBe('180.0000000');
});

test('el contacto debe ser un vínculo vigente de la misma party', function () {
    $party = Party::factory()->juridica()->create();
    $deOtra = PartyContacto::factory()->create();
    $cerrado = PartyContacto::factory()->cerrado()->create(['party_id' => $party->id]);

    expect(fn () => sedes()->agregar($party->id, datosSede(contactoId: $deOtra->id)))->toThrow(SedeRechazada::class, 'contacto vigente')
        ->and(fn () => sedes()->agregar($party->id, datosSede(contactoId: $cerrado->id)))->toThrow(SedeRechazada::class, 'contacto vigente');
});

test('un ubigeo desactivado no se elige, pero el que ya tenía se respeta al editar', function () {
    $party = Party::factory()->juridica()->create();
    $ubigeo = Ubigeo::factory()->create();
    $id = sedes()->agregar($party->id, datosSede(ubigeoId: $ubigeo->id));
    $ubigeo->update(['es_activo' => false]);

    sedes()->editar($id, datosSede(direccion: 'Calle Nueva 456', ubigeoId: $ubigeo->id), 1);

    expect(PartySede::query()->findOrFail($id)->direccion)->toBe('Calle Nueva 456')
        ->and(fn () => sedes()->agregar($party->id, datosSede('Otra', ubigeoId: $ubigeo->id)))->toThrow(SedeRechazada::class, 'ubigeo');
});

test('el nombre no se repite entre las activas sin contar mayúsculas ni acentos', function () {
    $party = Party::factory()->juridica()->create();
    sedes()->agregar($party->id, datosSede('Almacén Ñaña'));

    expect(fn () => sedes()->agregar($party->id, datosSede('ALMACEN NANA')))
        ->toThrow(SedeRechazada::class, 'Ya hay una sede activa llamada «ALMACEN NANA»');
});

test('una sede desactivada libera su nombre y su anexo; reactivarla con ellos tomados se rechaza', function () {
    $party = Party::factory()->juridica()->create();
    $vieja = sedes()->agregar($party->id, datosSede(codigoEstablecimiento: '0002'));
    sedes()->desactivar($vieja, 1);
    $nueva = sedes()->agregar($party->id, datosSede('almacen chiclayo', codigoEstablecimiento: '0003'));

    expect(fn () => sedes()->activar($vieja, 2))->toThrow(SedeRechazada::class, 'Ya hay una sede activa llamada');

    sedes()->editar($nueva, datosSede('Almacén Lambayeque', codigoEstablecimiento: '0002'), 1);

    expect(fn () => sedes()->activar($vieja, 2))->toThrow(SedeRechazada::class, 'código de establecimiento 0002');

    sedes()->editar($nueva, datosSede('Almacén Lambayeque', codigoEstablecimiento: '0003'), 2);
    sedes()->activar($vieja, 2);

    expect(PartySede::query()->findOrFail($vieja)->es_activa)->toBeTrue();
});

test('desactivar o activar dos veces se avisa, y la versión se exige', function () {
    $id = sedes()->agregar(Party::factory()->juridica()->create()->id, datosSede());

    expect(fn () => sedes()->activar($id, 1))->toThrow(SedeRechazada::class, 'ya está activa')
        ->and(fn () => sedes()->desactivar($id, 9))->toThrow(VersionDesactualizada::class, 'la sede «Almacén Chiclayo»');
});

test('editar sin cambios no sube la versión; con cambios, sí', function () {
    $id = sedes()->agregar(Party::factory()->juridica()->create()->id, datosSede(latitud: '-6.7714', longitud: '-79.8409'));

    sedes()->editar($id, datosSede(latitud: '-6.7714000', longitud: '-79.8409000'), 1);

    expect(versionSede($id))->toBe(1);

    sedes()->editar($id, datosSede('Comisaría'), 1);

    expect(versionSede($id))->toBe(2)
        ->and(PartySede::query()->findOrFail($id)->nombre_comparable)->toBe('comisaria')
        ->and(PartySede::query()->findOrFail($id)->latitud)->toBeNull();
});

test('se borra una sede y sedesActivas solo da las activas', function () {
    $party = Party::factory()->juridica()->create();
    $borrada = sedes()->agregar($party->id, datosSede('Temporal'));
    $inactiva = sedes()->agregar($party->id, datosSede('Antigua'));
    sedes()->desactivar($inactiva, 1);
    sedes()->agregar($party->id, datosSede(codigoEstablecimiento: '0001'));

    sedes()->borrar($borrada, 1);

    expect(PartySede::query()->find($borrada))->toBeNull()
        ->and(array_map(fn ($s): string => $s->etiqueta, app(PartiesService::class)->sedesActivas($party->id)))->toBe(['Almacén Chiclayo · Av. Principal 123'])
        ->and(app(PartiesService::class)->sedesActivas($party->id)[0]->codigoEstablecimiento)->toBe('0001');
});

test('una party desactivada no recibe sedes nuevas', function () {
    expect(fn () => sedes()->agregar(Party::factory()->juridica()->inactiva()->create()->id, datosSede()))
        ->toThrow(SedeRechazada::class, 'está desactivada');
});
