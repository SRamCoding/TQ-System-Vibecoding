<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use App\Modules\Nucleo\Services\MotivosService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Filament\Forms\Components\Select;
use Illuminate\Validation\ValidationException;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * La pieza común para elegir un motivo (plan 2.21, D7.3b): `Campo::motivoDe`, dibujada por AccionFilament, en las
 * transiciones del núcleo y en las acciones que piden un motivo. Ofrece los activos de la familia con «Otro» al final y
 * el valor actual aunque esté desactivado; el detalle se pide solo con «Otro».
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
    app(SincronizadorEstados::class)->sincronizar();
    test()->seed(PermisosSeeder::class);
});

function cmFamilia(): TipoMotivo
{
    return TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();
}

function cmMotivo(string $nombre, bool $activo = true): Motivo
{
    return Motivo::query()->create([
        'tipo_motivo_id' => cmFamilia()->id, 'codigo' => str($nombre)->slug('_')->value(), 'nombre' => $nombre, 'es_activo' => $activo,
    ]);
}

function cmOtro(): Motivo
{
    return Motivo::query()->where('tipo_motivo_id', cmFamilia()->id)->where('es_otro', true)->firstOrFail();
}

test('las transiciones con motivo lo piden con la pieza común, de su propia lista', function () {
    $campo = collect(app(RegistroAcciones::class)->obtener('nucleo.prueba.anular')->campos())->firstWhere('nombre', 'motivo_id');

    expect($campo)->toBeInstanceOf(Campo::class)
        ->and($campo->tipo)->toBe(TipoCampo::Motivo)
        ->and($campo->familiaMotivos)->toBe('nucleo.prueba.anular')
        ->and($campo->campoDetalle)->toBe('motivo_detalle');
});

test('ofrece los motivos activos de la familia con «Otro» al final, y no los de otra lista', function () {
    $precio = cmMotivo('Precio');
    $plazo = cmMotivo('Plazo');
    cmMotivo('Ya no se usa', activo: false);
    $ajeno = Motivo::query()->where('tipo_motivo_id', '!=', cmFamilia()->id)->value('id');

    $opciones = app(MotivosService::class)->opciones('nucleo.prueba.anular');

    expect(array_keys($opciones))->toBe([$plazo->id, $precio->id, cmOtro()->id])
        ->and($opciones)->not->toHaveKey((int) $ajeno);
});

test('incluye el motivo que el registro ya tiene aunque esté desactivado, marcado; uno de otra lista no', function () {
    $viejo = cmMotivo('Ya no se usa', activo: false);
    $ajeno = Motivo::query()->where('tipo_motivo_id', '!=', cmFamilia()->id)->value('id');

    expect(app(MotivosService::class)->opciones('nucleo.prueba.anular', $viejo->id))->toHaveKey($viejo->id, 'Ya no se usa (desactivado)')
        ->and(app(MotivosService::class)->opciones('nucleo.prueba.anular', (int) $ajeno))->not->toHaveKey((int) $ajeno);
});

test('una lista que no existe es un error de programación', function () {
    expect(fn () => app(MotivosService::class)->opciones('no.existe'))->toThrow(LogicException::class, 'No existe la lista de motivos «no.existe».');
});

test('sabe cuál es «Otro», el que pide detalle', function () {
    expect(app(MotivosService::class)->esOtro(cmOtro()->id))->toBeTrue()
        ->and(app(MotivosService::class)->esOtro(cmMotivo('Precio')->id))->toBeFalse()
        ->and(app(MotivosService::class)->esOtro(null))->toBeFalse();
});

test('en una página, AccionFilament::campo da el mismo selector, que redibuja el formulario al cambiar', function () {
    $selector = AccionFilament::campo('nucleo.prueba.anular', 'motivo_id');

    expect($selector)->toBeInstanceOf(Select::class)
        ->and($selector->getLabel())->toBe('Motivo')
        ->and($selector->isLive())->toBeTrue();
});

test('sin interfaz, el motivo llega como entero y uno que no existe no pasa la validación', function () {
    $persona = User::factory()->create()->givePermissionTo('nucleo.prueba.anular');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido']), $persona)->refresh();
    $precio = cmMotivo('Precio');

    $fila = app(EjecutorAcciones::class)->ejecutar('nucleo.prueba.anular', ['documento_id' => $documento->id, 'version' => 1, 'motivo_id' => (string) $precio->id], $persona);

    expect($fila->motivo_id)->toBe($precio->id)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.prueba.anular', ['documento_id' => $documento->id, 'version' => 2, 'motivo_id' => 999999], $persona))
        ->toThrow(ValidationException::class);
});
