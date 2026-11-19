<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\PadronUbigeosNoDisponible;
use App\Modules\Nucleo\Ubigeos\DistritoUbigeo;
use App\Modules\Nucleo\Ubigeos\LectorXlsx;
use App\Modules\Nucleo\Ubigeos\PadronUbigeos;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Http as HttpFacade;
use Tests\Modules\Nucleo\Fixtures\XlsxDePrueba;

/*
 * La lista de ubigeos: lectura del Excel del INEI, validación, comparación y el comando que la
 * actualiza. Ninguna prueba llama al portal real (Http::fake).
 */

const URL_UBIGEOS_PRUEBA = 'https://datos.prueba/ubigeos.xlsx';

beforeEach(function () {
    $this->csv = tempnam(sys_get_temp_dir(), 'ubigeos').'.csv';
    config(['nucleo.ubigeos.url' => URL_UBIGEOS_PRUEBA, 'nucleo.ubigeos.csv' => $this->csv, 'nucleo.ubigeos.minimo_distritos' => 2]);
});

afterEach(function () {
    @unlink($this->csv);
    @unlink(preg_replace('/\.csv$/', '.fuente.json', $this->csv));
});

function padron(): PadronUbigeos
{
    return app(PadronUbigeos::class);
}

/** @return list<list<string>> */
function distritosDePrueba(): array
{
    return [
        ['140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO'],
        ['140102', 'LAMBAYEQUE', 'CHICLAYO', 'CHONGOYAPE'],
        ['021806', 'ANCASH', 'SANTA', 'NEPEÑA'],
    ];
}

test('lee el Excel del INEI con textos compartidos y en línea, y conserva la Ñ', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, XlsxDePrueba::inei(distritosDePrueba()));

    $filas = (new LectorXlsx)->filas($ruta);
    unlink($ruta);

    expect($filas[0])->toBe(['IDDIST', 'NOMBDEP', 'NOMBPROV', 'NOMBDIST', 'NOM_CAPITAL (LEGAL)'])
        ->and($filas[3])->toBe(['021806', 'ANCASH', 'SANTA', 'NEPEÑA', 'NEPEÑA']);
});

test('rechaza una lista mal formada o sospechosa', function (array $filas, string $mensaje) {
    expect(fn () => padron()->desdeFilasInei($filas))->toThrow(PadronUbigeosNoDisponible::class, $mensaje);
})->with([
    'cambió el encabezado' => [[['CODIGO', 'DEP', 'PROV', 'DIST'], ['140101', 'A', 'B', 'C'], ['140102', 'A', 'B', 'D']], 'cambió de formato'],
    'código de 5 dígitos' => [[PadronUbigeos::ENCABEZADO_INEI, ['14010', 'A', 'B', 'C'], ['140102', 'A', 'B', 'D']], 'seis dígitos'],
    'código repetido' => [[PadronUbigeos::ENCABEZADO_INEI, ['140101', 'A', 'B', 'C'], ['140101', 'A', 'B', 'D']], 'repetido'],
    'nombre vacío' => [[PadronUbigeos::ENCABEZADO_INEI, ['140101', 'A', '', 'C'], ['140102', 'A', 'B', 'D']], 'vacío'],
    'descarga cortada' => [[PadronUbigeos::ENCABEZADO_INEI, ['140101', 'A', 'B', 'C']], 'incompleta'],
]);

test('compara nuevos, renombrados y retirados', function () {
    $antes = [
        new DistritoUbigeo('140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO'),
        new DistritoUbigeo('140103', 'LAMBAYEQUE', 'CHICLAYO', 'ETEN'),
        new DistritoUbigeo('999999', 'X', 'Y', 'RETIRADO'),
    ];
    $ahora = [
        new DistritoUbigeo('140101', 'LAMBAYEQUE', 'CHICLAYO', 'CHICLAYO'),
        new DistritoUbigeo('140103', 'LAMBAYEQUE', 'CHICLAYO', 'CIUDAD ETEN'),
        new DistritoUbigeo('140121', 'LAMBAYEQUE', 'CHICLAYO', 'NUEVO'),
    ];

    $diferencia = padron()->comparar($antes, $ahora);

    expect(array_map(fn ($d) => $d->codigo, $diferencia->nuevos))->toBe(['140121'])
        ->and($diferencia->renombrados)->toHaveCount(1)
        ->and($diferencia->renombrados[0]['ahora']->distrito)->toBe('CIUDAD ETEN')
        ->and(array_map(fn ($d) => $d->codigo, $diferencia->retirados))->toBe(['999999'])
        ->and(padron()->comparar($ahora, $ahora)->hayCambios())->toBeFalse();
});

test('guarda el CSV ordenado con su fuente y lo vuelve a leer igual', function () {
    $distritos = padron()->desdeFilasInei([PadronUbigeos::ENCABEZADO_INEI, ...distritosDePrueba()]);

    padron()->guardar($distritos, ['url' => URL_UBIGEOS_PRUEBA, 'descargado' => '2026-10-10', 'codigos' => 'INEI']);

    $fuente = json_decode((string) file_get_contents(preg_replace('/\.csv$/', '.fuente.json', $this->csv)), true);

    expect(array_map(fn ($d) => $d->codigo, padron()->leerGuardado()))->toBe(['021806', '140101', '140102'])
        ->and(padron()->leerGuardado()[0]->distrito)->toBe('NEPEÑA')
        ->and($fuente)->toBe(['url' => URL_UBIGEOS_PRUEBA, 'descargado' => '2026-10-10', 'codigos' => 'INEI', 'distritos' => 3]);
});

test('el comando muestra las diferencias y sin confirmación no cambia nada', function () {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::response(XlsxDePrueba::inei(distritosDePrueba()))]);

    $this->artisan('nucleo:actualizar-csv-ubigeos')
        ->expectsOutputToContain('Nuevos: 3')
        ->expectsOutputToContain('140101 · LAMBAYEQUE / CHICLAYO / CHICLAYO')
        ->expectsConfirmation('¿Reemplazar el CSV con la lista descargada?', 'no')
        ->expectsOutputToContain('No se cambió nada')
        ->assertSuccessful();

    expect(padron()->leerGuardado())->toBe([]);
});

test('el comando con --aplicar reemplaza el CSV y la segunda vez no encuentra cambios', function () {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::response(XlsxDePrueba::inei(distritosDePrueba()))]);

    $this->artisan('nucleo:actualizar-csv-ubigeos --aplicar')->assertSuccessful();
    $this->artisan('nucleo:actualizar-csv-ubigeos')->expectsOutputToContain('Sin cambios')->assertSuccessful();

    expect(padron()->leerGuardado())->toHaveCount(3);
    HttpFacade::assertSent(fn ($peticion) => str_contains($peticion->header('User-Agent')[0] ?? '', 'Mozilla'));
});

test('el comando falla con un mensaje claro si la descarga no responde', function () {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::response('bloqueado', 418)]);

    $this->artisan('nucleo:actualizar-csv-ubigeos --aplicar')
        ->expectsOutputToContain('HTTP 418')
        ->assertFailed();

    expect(padron()->leerGuardado())->toBe([]);
});

test('si el portal no responde a tiempo, avisa sin esperar al límite del servidor', function () {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::failedConnection()]);

    expect(fn () => padron()->descargar(URL_UBIGEOS_PRUEBA))
        ->toThrow(PadronUbigeosNoDisponible::class, 'no respondió a tiempo');
});

test('rechaza un archivo de más de 10 MB, lo anuncie o no el servidor', function (array $cabeceras, int $bytes) {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::response(str_repeat('x', $bytes), 200, $cabeceras)]);

    expect(fn () => padron()->descargar(URL_UBIGEOS_PRUEBA))
        ->toThrow(PadronUbigeosNoDisponible::class, 'demasiado grande');
})->with([
    'anunciado en la cabecera' => [['Content-Length' => (string) (PadronUbigeos::MAXIMO_BYTES + 1)], 10],
    'sin anunciar' => [[], PadronUbigeos::MAXIMO_BYTES + 1],
]);

test('el comando rechaza un archivo que no es Excel', function () {
    HttpFacade::fake([URL_UBIGEOS_PRUEBA => HttpFacade::response('<html>mantenimiento</html>')]);

    $this->artisan('nucleo:actualizar-csv-ubigeos --aplicar')
        ->expectsOutputToContain('no es un Excel')
        ->assertFailed();
});

test('el CSV del repositorio es una lista INEI válida y completa', function () {
    $padron = new PadronUbigeos(app(Http::class), new LectorXlsx, app_path('Modules/Nucleo/database/data/ubigeos.csv'), 1800);
    $distritos = $padron->leerGuardado();
    $codigos = array_map(fn (DistritoUbigeo $d): string => $d->codigo, $distritos);

    // guardar() valida; aquí se repiten las reglas sobre el archivo tal como está en git.
    expect(count($distritos))->toBeGreaterThanOrEqual(1800)
        ->and(count(array_unique($codigos)))->toBe(count($codigos))
        ->and(array_filter($codigos, fn (string $c): bool => preg_match('/^\d{6}$/', $c) !== 1))->toBe([])
        ->and(collect($distritos)->firstWhere('codigo', '140101')?->distrito)->toBe('CHICLAYO');
});
