<?php

declare(strict_types=1);

use App\Modules\Nucleo\Filament\AccionFilament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Tests\Modules\Nucleo\Fixtures\AccionDePrueba;

/*
 * AccionFilament::campo (plan 2.20): el control de un campo de una acción para una página que la llama sin ser su
 * ventana, el mismo que dibuja el formulario de la acción.
 */

test('da el control común del campo, con su etiqueta, su obligatoriedad y, en un catálogo, su buscador', function () {
    $catalogo = AccionFilament::campo(new AccionDePrueba, 'unidad_medida_id');
    $texto = AccionFilament::campo(new AccionDePrueba, 'nombre');

    expect($catalogo)->toBeInstanceOf(Select::class)
        ->and($catalogo->getLabel())->toBe('Unidad de medida')
        ->and($catalogo->isSearchable())->toBeTrue()
        ->and($catalogo->isRequired())->toBeFalse()
        ->and($texto)->toBeInstanceOf(TextInput::class)
        ->and($texto->isRequired())->toBeTrue();
});

test('un campo que la acción no tiene es un error de programación', function () {
    expect(fn () => AccionFilament::campo(new AccionDePrueba, 'no_existe'))->toThrow(LogicException::class, 'no tiene el campo «no_existe»');
});
