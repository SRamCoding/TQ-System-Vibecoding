<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EdicionCatalogosService;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * Plan 2.20a, N2 y N3: los catálogos que pide Cuentas y el modo cerrado. «Rubros» son las 21 secciones de la CIIU
 * Rev. 4: no se crean ni se borran desde la pantalla; solo se cambia su nombre.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

test('la siembra trae los seis orígenes y «Otro» (plan 2.0, D5), los tres niveles y las 21 secciones de la CIIU', function () {
    expect(OrigenComercial::query()->orderBy('id')->pluck('nombre')->all())
        ->toBe(['Convocatoria pública', 'Convocatoria privada', 'Referencia', 'Feria', 'Sitio web', 'Visita en frío', 'Otro'])
        ->and(NivelGobierno::query()->orderBy('id')->pluck('codigo')->all())->toBe(['LOCAL', 'REGIONAL', 'NACIONAL'])
        ->and(Rubro::query()->orderBy('codigo')->pluck('codigo')->all())->toBe(range('A', 'U'))
        ->and(Rubro::query()->where('codigo', 'Q')->value('nombre'))->toBe('Salud y asistencia social')
        ->and(Catalogo::Rubros->codigosDelSistema())->toBe(range('A', 'U'));
});

test('sembrar otra vez no pisa un nombre cambiado en la pantalla', function () {
    $servicio = app(EdicionCatalogosService::class);
    $servicio->editar(Catalogo::Rubros, 'Q', ['nombre' => 'Salud'], 1);

    test()->seed(NucleoSeeder::class);

    expect(Rubro::query()->where('codigo', 'Q')->value('nombre'))->toBe('Salud')
        ->and(Rubro::query()->count())->toBe(21);
});

test('solo Rubros es cerrado', function () {
    $cerrados = array_values(array_filter(Catalogo::cases(), fn (Catalogo $c): bool => $c->esCerrado()));

    expect($cerrados)->toBe([Catalogo::Rubros]);
});

test('en un catálogo cerrado no se crea, no se borra ni se desactiva; el nombre sí se cambia', function () {
    $servicio = app(EdicionCatalogosService::class);

    expect(fn () => $servicio->crear(Catalogo::Rubros, 'V', ['nombre' => 'Inventada']))->toThrow(ElementoCatalogoRechazado::class, 'lista oficial cerrada')
        ->and(fn () => $servicio->borrar(Catalogo::Rubros, 'U', 1))->toThrow(ElementoCatalogoRechazado::class, 'lista oficial cerrada')
        ->and(fn () => $servicio->desactivar(Catalogo::Rubros, 'U', 1))->toThrow(ElementoCatalogoRechazado::class);

    $servicio->editar(Catalogo::Rubros, 'P', ['nombre' => 'Educación'], 1);

    expect(Rubro::query()->where('codigo', 'P')->value('nombre'))->toBe('Educación')
        ->and(Rubro::query()->count())->toBe(21);
});

test('un catálogo abierto del mismo grupo sí agrega y borra', function () {
    $servicio = app(EdicionCatalogosService::class);
    $servicio->crear(Catalogo::OrigenesComerciales, 'LINKEDIN', ['nombre' => 'LinkedIn']);

    expect(OrigenComercial::query()->where('codigo', 'LINKEDIN')->exists())->toBeTrue();

    $servicio->borrar(Catalogo::OrigenesComerciales, 'LINKEDIN', 1);

    expect(OrigenComercial::query()->where('codigo', 'LINKEDIN')->exists())->toBeFalse();
});

test('la base solo acepta letras de la A a la U como sección', function () {
    expect(fn () => Rubro::query()->insert(['codigo' => 'Z', 'nombre' => 'Inventada', 'created_by' => 1]))
        ->toThrow(QueryException::class);
});

test('la pantalla no ofrece «Agregar» en un catálogo cerrado y sí en uno abierto', function () {
    $usuario = User::factory()->create()->givePermissionTo(['catalogos.ver', 'catalogos.editar']);

    Livewire::withQueryParams([])->actingAs($usuario)->test(Catalogos::class)
        ->call('abrir', 'rubros')->assertDontSee('Agregar')
        ->call('abrir', 'origenes_comerciales')->assertSee('Agregar');
});
