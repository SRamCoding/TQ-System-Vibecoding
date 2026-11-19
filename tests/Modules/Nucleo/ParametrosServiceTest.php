<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Excepciones\ParametroNoDisponible;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;

beforeEach(fn () => test()->seed(ParametrosSeeder::class));

function parametros(): ParametrosService
{
    return app(ParametrosService::class);
}

test('lee cada parámetro del núcleo en su tipo', function () {
    expect(parametros()->decimal('tributario.igv_porcentaje'))->toBe('18.00')
        ->and(parametros()->monedaLocal())->toBe('PEN')
        ->and(parametros()->entero('tipo_cambio.dias_tolerancia'))->toBe(4)
        ->and(parametros()->rucBancoDetracciones())->toBe('20100030595');
});

test('el RUC inicial del Banco de la Nación tiene su dígito verificador correcto', function () {
    expect(DocumentoIdentidad::de(Catalogo::RUC, parametros()->rucBancoDetracciones())->numero)->toBe('20100030595');
});

test('lee booleanos y fechas', function () {
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'activo', 'tipo' => TipoParametro::Booleano, 'valor' => 'true']);
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'inicio', 'tipo' => TipoParametro::Fecha, 'valor' => '2027-01-01']);

    expect(parametros()->booleano('pruebas.activo'))->toBeTrue()
        ->and(parametros()->fecha('pruebas.inicio'))->toBeInstanceOf(CarbonImmutable::class)
        ->and(parametros()->fecha('pruebas.inicio')->toDateString())->toBe('2027-01-01');
});

test('un parámetro inexistente o pedido con otro tipo es un error claro', function (Closure $leer, string $mensaje) {
    expect($leer)->toThrow(ParametroNoDisponible::class, $mensaje);
})->with([
    'no existe' => [fn () => parametros()->entero('pruebas.no_existe'), 'No existe el parámetro «pruebas.no_existe»'],
    'otro tipo' => [fn () => parametros()->entero('tributario.igv_porcentaje'), 'es decimal y se pidió como entero'],
]);

test('cambia el valor, sube la versión y deja bitácora', function () {
    parametros()->cambiarDeDinero('tributario.igv_porcentaje', '10.00', versionLeida: 1);

    $parametro = Parametro::query()->where('clave', 'igv_porcentaje')->firstOrFail();
    $bitacora = AuditoriaCambio::query()->where('auditable_id', $parametro->id)->where('auditable_type', $parametro->getMorphClass())->latest('id')->firstOrFail();

    expect(parametros()->decimal('tributario.igv_porcentaje'))->toBe('10.00')
        ->and($parametro->version)->toBe(2)
        ->and($bitacora->valores_anteriores)->toBe(['valor' => '18.00'])
        ->and($bitacora->valores_nuevos)->toBe(['valor' => '10.00']);
});

test('cambia también la descripción si llega, sin espacios sobrantes', function () {
    parametros()->cambiarDeDinero('tributario.igv_porcentaje', '18.00', 1, '  IGV vigente según SUNAT.  ');

    expect(Parametro::query()->where('clave', 'igv_porcentaje')->value('descripcion'))->toBe('IGV vigente según SUNAT.');
});

test('sin descripción se conserva la actual', function () {
    $antes = Parametro::query()->where('clave', 'igv_porcentaje')->value('descripcion');

    parametros()->cambiarDeDinero('tributario.igv_porcentaje', '10.00', versionLeida: 1);

    expect(Parametro::query()->where('clave', 'igv_porcentaje')->value('descripcion'))->toBe($antes);
});

test('lo leído en la misma petición se olvida al cambiar', function () {
    expect(parametros()->decimal('tributario.igv_porcentaje'))->toBe('18.00');

    parametros()->cambiarDeDinero('tributario.igv_porcentaje', '10.00', versionLeida: 1);

    expect(parametros()->decimal('tributario.igv_porcentaje'))->toBe('10.00');
});

test('si otro lo cambió después de abrirlo, se rechaza y no pisa su valor', function () {
    parametros()->cambiarDeDinero('tributario.igv_porcentaje', '10.00', versionLeida: 1);

    expect(fn () => parametros()->cambiarDeDinero('tributario.igv_porcentaje', '12.00', versionLeida: 1))
        ->toThrow(VersionDesactualizada::class, 'Otra persona cambió el parámetro «tributario.igv_porcentaje»');
    expect(parametros()->decimal('tributario.igv_porcentaje'))->toBe('10.00');
});

test('rechaza cambios no permitidos', function (Closure $cambiar, string $excepcion, string $mensaje) {
    expect($cambiar)->toThrow($excepcion, $mensaje);
})->with([
    'moneda local no editable' => [fn () => parametros()->cambiar('general.moneda_local', 'USD', 1), ParametroRechazado::class, 'no se puede cambiar'],
    'texto en un entero' => [fn () => parametros()->cambiarDeDinero('tipo_cambio.dias_tolerancia', 'tres', 1), ParametroRechazado::class, 'se espera un número entero'],
    'float en un decimal' => [fn () => parametros()->cambiarDeDinero('tributario.igv_porcentaje', 18.5, 1), ParametroRechazado::class, 'se espera un número con punto decimal'],
    'decimal sin cero inicial' => [fn () => parametros()->cambiarDeDinero('tributario.igv_porcentaje', '.5', 1), ParametroRechazado::class, 'se espera un número con punto decimal'],
    'parámetro inexistente' => [fn () => parametros()->cambiar('pruebas.no_existe', 'x', 1), ParametroNoDisponible::class, 'No existe'],
    'descripción en blanco' => [fn () => parametros()->cambiarDeDinero('tributario.igv_porcentaje', '18.00', 1, '   '), ParametroRechazado::class, 'no puede quedar vacía'],
]);

test('los errores de parámetros son errores de negocio: la pantalla los muestra como aviso', function () {
    expect(new ReflectionClass(ParametroRechazado::class))->isSubclassOf(ErrorDeNegocio::class)->toBeTrue()
        ->and(new ReflectionClass(VersionDesactualizada::class))->isSubclassOf(ErrorDeNegocio::class)->toBeTrue();
});

test('la ficha trae lo que una pantalla necesita para editar', function () {
    $ficha = parametros()->ficha('tributario.igv_porcentaje');

    expect($ficha['tipo'])->toBe(TipoParametro::Decimal)
        ->and($ficha['valor'])->toBe('18.00')
        ->and($ficha['version'])->toBe(1)
        ->and($ficha['es_editable'])->toBeTrue();
});

test('cambiar() no toca un parámetro de dinero y cambiarDeDinero() no toca uno que no lo es (plan 1.3b)', function () {
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'plazo', 'tipo' => TipoParametro::Entero, 'valor' => '30']);

    expect(fn () => parametros()->cambiar('tributario.igv_porcentaje', '10.00', 1))->toThrow(ParametroRechazado::class, 'es un parámetro de dinero')
        ->and(fn () => parametros()->cambiarDeDinero('pruebas.plazo', 10, 1))->toThrow(ParametroRechazado::class, 'no es un parámetro de dinero')
        ->and(parametros()->decimal('tributario.igv_porcentaje'))->toBe('18.00');
});
