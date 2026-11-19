<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Excepciones\ParametroDuplicado;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;

/*
 * Cada módulo declara sus parámetros en código; el seeder crea los que faltan y nunca pisa un valor
 * que alguien cambió (aprobado en el plan 1.2).
 */

function definicionDias(int $valor = 30, string $descripcion = 'Días de prueba.'): DefinicionParametro
{
    return new DefinicionParametro('pruebas', 'dias', TipoParametro::Entero, $valor, $descripcion);
}

function sembrarParametros(): void
{
    app(ParametrosSeeder::class)->run(app(RegistroParametros::class));
}

test('el núcleo declara sus parámetros con su tipo y valor inicial', function () {
    $todas = app(RegistroParametros::class)->todas();

    expect($todas['tributario.igv_porcentaje']->valorInicial)->toBe('18.00')
        ->and($todas['tributario.igv_porcentaje']->tipo)->toBe(TipoParametro::Decimal)
        ->and($todas['general.moneda_local']->valorInicial)->toBe('PEN')
        ->and($todas['general.moneda_local']->esEditable)->toBeFalse()
        ->and($todas['tipo_cambio.dias_tolerancia']->valorInicial)->toBe('4')
        ->and($todas['parties.ruc_banco_detracciones']->valorInicial)->toBe('20100030595')
        ->and($todas['parties.ruc_banco_detracciones']->tipo)->toBe(TipoParametro::Texto);
});

test('declarar dos veces lo mismo no falla; dos definiciones distintas sí', function () {
    $registro = new RegistroParametros;
    $registro->declarar(definicionDias(), definicionDias());

    expect(fn () => $registro->declarar(definicionDias(45)))->toThrow(ParametroDuplicado::class);
});

test('grupo y clave van en snake_case y el valor inicial debe ser de su tipo', function () {
    expect(fn () => new DefinicionParametro('Pruebas', 'dias', TipoParametro::Entero, 30, 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DefinicionParametro('pruebas', 'dias', TipoParametro::Entero, '30', 'x'))->toThrow(LogicException::class);
});

test('el seeder crea los parámetros declarados', function () {
    app(RegistroParametros::class)->declarar(definicionDias());

    sembrarParametros();

    $creado = Parametro::query()->where('grupo', 'pruebas')->where('clave', 'dias')->firstOrFail();
    expect($creado->valor)->toBe('30')
        ->and($creado->tipo)->toBe(TipoParametro::Entero)
        ->and(Parametro::query()->where('clave', 'igv_porcentaje')->value('valor'))->toBe('18.00');
});

test('volver a sembrar no pisa el valor ni la descripción editados en la pantalla', function () {
    $registro = app(RegistroParametros::class);
    $registro->declarar(definicionDias());
    sembrarParametros();
    Parametro::query()->where('clave', 'dias')->firstOrFail()->update(['valor' => '45', 'descripcion' => 'Como lo explica Administración.']);

    // La siguiente versión del módulo cambia la descripción y sube el valor inicial (plan 1.2b, E9).
    $nuevo = new RegistroParametros;
    $nuevo->declarar(definicionDias(60, 'Días de validez, mejor explicado.'));
    app(ParametrosSeeder::class)->run($nuevo);

    $parametro = Parametro::query()->where('clave', 'dias')->firstOrFail();
    expect($parametro->valor)->toBe('45')
        ->and($parametro->descripcion)->toBe('Como lo explica Administración.')
        ->and(Parametro::query()->where('clave', 'dias')->count())->toBe(1);
});

test('si el tipo declarado ya no coincide con la base, el seeder se detiene', function () {
    app(RegistroParametros::class)->declarar(definicionDias());
    sembrarParametros();

    $otro = new RegistroParametros;
    $otro->declarar(new DefinicionParametro('pruebas', 'dias', TipoParametro::Decimal, '30.5', 'x'));

    expect(fn () => app(ParametrosSeeder::class)->run($otro))->toThrow(ParametroDuplicado::class, 'exige una migración');
});
