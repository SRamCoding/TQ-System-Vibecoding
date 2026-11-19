<?php

declare(strict_types=1);

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\SerieDocumento;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Numeracion\ReinicioNumeracion;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * M6 · Numeración (plan 1.6): formato, reinicio por periodo, ámbitos, sin huecos si algo falla, y el número que recibe
 * un documento al crearse (D2). La concurrencia con procesos de verdad, y el rechazo fuera de una transacción, están en
 * tests/Concurrencia (aquí todo corre dentro de la transacción de la prueba).
 */

beforeEach(function () {
    DocumentoPrueba::crearTabla();
});

function numerar(FormatoNumeracion $formato, string $fecha = '2026-10-26 10:00', string $ambito = ''): string
{
    return DB::transaction(fn (): string => app(NumeracionService::class)->siguiente('documento_prueba', $formato, CarbonImmutable::parse($fecha, 'America/Lima'), $ambito));
}

function registrarNumerado(?FormatoNumeracion $formato = new FormatoNumeracion('DPR')): void
{
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(numeracion: $formato));
    app(SincronizadorEstados::class)->sincronizar();
}

/**
 * @param  array<string, mixed>  $datos
 */
function documentoNumerado(array $datos = []): DocumentoPrueba
{
    return app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido', ...$datos]), User::factory()->create())->refresh();
}

test('numera seguido con prefijo, año y cuatro dígitos', function () {
    $formato = new FormatoNumeracion('REQ');

    expect([numerar($formato), numerar($formato), numerar($formato)])->toBe(['REQ-2026-0001', 'REQ-2026-0002', 'REQ-2026-0003']);
});

test('reinicia cada año, por la fecha en hora de Lima', function () {
    $formato = new FormatoNumeracion('REQ');
    numerar($formato, '2026-12-31 23:30');

    // 2027-01-01 04:30 en UTC todavía es 31 de diciembre en Lima.
    expect(DB::transaction(fn (): string => app(NumeracionService::class)->siguiente('documento_prueba', $formato, CarbonImmutable::parse('2027-01-01 04:30', 'UTC'))))->toBe('REQ-2026-0002')
        ->and(numerar($formato, '2027-01-01 08:00'))->toBe('REQ-2027-0001');
});

test('reinicia cada mes o nunca, según el formato', function () {
    $mensual = new FormatoNumeracion('AS', ReinicioNumeracion::Mensual);
    $nunca = new FormatoNumeracion('COL', ReinicioNumeracion::Nunca);

    expect([numerar($mensual, '2026-10-30'), numerar($mensual, '2026-11-02')])->toBe(['AS-2026-10-0001', 'AS-2026-11-0001'])
        ->and([numerar($nunca, '2026-12-31'), numerar($nunca, '2027-01-01')])->toBe(['COL-0001', 'COL-0002']);
});

test('cada ámbito lleva su propio contador, y su código va dentro del número para que no se repita', function () {
    $formato = new FormatoNumeracion('VAL');

    expect([numerar($formato, ambito: 'CT0001'), numerar($formato, ambito: 'CT0002'), numerar($formato, ambito: 'CT0001'), numerar($formato)])
        ->toBe(['VAL-CT0001-2026-0001', 'VAL-CT0002-2026-0001', 'VAL-CT0001-2026-0002', 'VAL-2026-0001']);
});

test('un ámbito que no es un código no se acepta', function (string $ambito) {
    expect(fn () => numerar(new FormatoNumeracion('VAL'), ambito: $ambito))->toThrow(InvalidArgumentException::class);
})->with(['con dos puntos' => 'proyecto:15', 'con guion' => 'CT-0001', 'con espacio' => 'CT 1', 'en minúsculas' => 'ct1']);

test('al pasar de 9999 crece a cinco dígitos, sin fallar', function () {
    $formato = new FormatoNumeracion('REQ');
    numerar($formato);
    SerieDocumento::query()->where('serie', 'REQ')->update(['ultimo_numero' => 9999]);

    expect(numerar($formato))->toBe('REQ-2026-10000');
});

test('si la transacción falla después de tomar el número, el número vuelve y no queda hueco', function () {
    $formato = new FormatoNumeracion('REQ');
    numerar($formato);

    expect(fn () => DB::transaction(function () use ($formato): void {
        numerar($formato);
        throw new RuntimeException('falla al guardar el documento');
    }))->toThrow(RuntimeException::class);

    expect(numerar($formato))->toBe('REQ-2026-0002');
});

test('la base rechaza una serie o un periodo mal formados y un contador en cero', function (array $fila) {
    $base = ['documento_type' => 'documento_prueba', 'serie' => 'REQ', 'ambito' => '', 'periodo' => '2026', 'ultimo_numero' => 1, 'created_by' => User::factory()->create()->id];

    expect(fn () => DB::transaction(fn () => DB::table('series_documento')->insert([...$base, ...$fila])))->toThrow(QueryException::class);
})->with([
    'serie en minúsculas' => [['serie' => 'req']],
    'periodo raro' => [['periodo' => '26']],
    'mes 13' => [['periodo' => '2026-13']],
    'contador en cero' => [['ultimo_numero' => 0]],
    'ámbito con dos puntos' => [['ambito' => 'proyecto:15']],
]);

test('un formato mal declarado falla al declararlo', function () {
    expect(fn () => new FormatoNumeracion('req'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FormatoNumeracion('REQ', digitos: 10))->toThrow(InvalidArgumentException::class);
});

test('un documento recibe su número al crearse, ya en borrador, por su fecha', function () {
    registrarNumerado();
    $primero = documentoNumerado(['fecha' => '2026-03-01']);
    $segundo = documentoNumerado(['fecha' => '2026-03-02']);
    $otroAnio = documentoNumerado(['fecha' => '2027-01-05']);

    expect([$primero->numero, $segundo->numero, $otroAnio->numero])->toBe(['DPR-2026-0001', 'DPR-2026-0002', 'DPR-2027-0001'])
        ->and((new DefinicionPrueba)->identificador($primero))->toBe('DPR-2026-0001');
});

test('dos documentos de distinto ámbito el mismo año no chocan: el número sigue siendo único', function () {
    registrarNumerado();
    $unidad = UnidadOrganizativa::factory()->create();

    expect([documentoNumerado(['fecha' => '2026-05-01'])->numero, documentoNumerado(['fecha' => '2026-05-01', 'unidad_id' => $unidad->id])->numero])
        ->toBe(['DPR-2026-0001', "DPR-U{$unidad->id}-2026-0001"]);
});

test('un tipo sin numeración no toma número y se nombra por su id', function () {
    registrarNumerado(null);
    $documento = documentoNumerado();

    expect($documento->numero)->toBeNull()
        ->and(SerieDocumento::query()->count())->toBe(0)
        ->and((new DefinicionPrueba(numeracion: null))->identificador($documento))->toBe("#{$documento->id}");
});
