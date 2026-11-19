<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\DetalleDeOtroRechazado;
use App\Modules\Nucleo\Filament\Resources\Motivos\LoMasEscritoEnOtro;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosRedSocial;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\RedesSocialesService;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * «Otro» en los catálogos (plan 2.0, paso 6, D3 y D5): orígenes comerciales y redes sociales lo traen, es del sistema y
 * va al final; quien lo elige escribe cuál es; y «Lo más escrito en Otro» lo junta para agregar lo que se repite.
 * Datos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function otraRed(): int
{
    return (int) RedSocial::query()->where('codigo', 'otra')->value('id');
}

function conOtraRed(string $detalle): int
{
    return app(RedesSocialesService::class)->agregar(Party::factory()->create()->id, null, new DatosRedSocial(otraRed(), 'https://red.ejemplo.pe/'.uniqid(), detalle: $detalle));
}

test('orígenes y redes traen su «Otro», del sistema y al final del selector', function () {
    $catalogos = app(CatalogosService::class);
    $origenes = array_values($catalogos->buscar(Catalogo::OrigenesComerciales, ''));

    expect(Catalogo::OrigenesComerciales->codigosDelSistema())->toBe(['OTRO'])
        ->and(Catalogo::RedesSociales->codigosDelSistema())->toBe(['otra'])
        ->and(Catalogo::Monedas->codigoOtro())->toBeNull()
        ->and(end($origenes))->toBe('Otro')
        ->and($catalogos->esOtro(Catalogo::RedesSociales, otraRed()))->toBeTrue()
        ->and($catalogos->esOtro(Catalogo::RedesSociales, RedSocial::query()->where('codigo', 'linkedin')->value('id')))->toBeFalse();
});

test('el detalle va solo con «Otro» y con su mínimo', function () {
    $catalogos = app(CatalogosService::class);
    $linkedin = (int) RedSocial::query()->where('codigo', 'linkedin')->value('id');

    expect($catalogos->detalleDeOtro(Catalogo::RedesSociales, otraRed(), '  Mastodon del colegio ', 'la red'))->toBe('Mastodon del colegio')
        ->and($catalogos->detalleDeOtro(Catalogo::RedesSociales, $linkedin, '  ', 'la red'))->toBeNull()
        ->and(fn () => $catalogos->detalleDeOtro(Catalogo::RedesSociales, otraRed(), 'Mast', 'la red'))->toThrow(DetalleDeOtroRechazado::class, 'Elegiste «Otro» en la red: escribe cuál es')
        ->and(fn () => $catalogos->detalleDeOtro(Catalogo::RedesSociales, $linkedin, 'Mastodon del colegio', 'la red'))->toThrow(DetalleDeOtroRechazado::class, 'va solo con «Otro»');
});

test('una red «Otra red» guarda cuál es y se muestra con ese nombre; sin detalle se rechaza', function () {
    $party = Party::factory()->create();
    $id = app(RedesSocialesService::class)->agregar($party->id, null, new DatosRedSocial(otraRed(), 'https://social.ejemplo.pe/@techquk', detalle: 'Mastodon del colegio'));

    expect(collect(app(RedesSocialesService::class)->redesQueVe($party->id, null, null))->firstWhere('id', $id)->red)->toBe('Mastodon del colegio')
        ->and(fn () => app(RedesSocialesService::class)->agregar($party->id, null, new DatosRedSocial(otraRed(), 'https://otra.ejemplo.pe/x')))->toThrow(DetalleDeOtroRechazado::class);
});

test('«Lo más escrito en Otro» junta sin acentos ni mayúsculas, de lo más repetido a lo menos', function () {
    conOtraRed('Mastodon del colegio');
    conOtraRed('mastodón del colegio');
    conOtraRed('Bluesky institucional');

    $filas = array_values(LoMasEscritoEnOtro::filas('catalogo:redes_sociales'));

    expect(array_map(fn (array $f): array => [$f['veces'], $f['catalogo']], $filas))->toBe([[2, 'redes_sociales'], [1, 'redes_sociales']])
        ->and(LoMasEscritoEnOtro::listas())->toContain('Catálogo: Redes sociales');
});

test('«Agregar a la lista» crea la red con el nombre escrito, por su acción', function () {
    conOtraRed('Bluesky institucional');
    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'catalogos.editar']));

    Livewire::test(LoMasEscritoEnOtro::class)
        ->filterTable('lista', 'catalogo:redes_sociales')
        ->assertSee('Bluesky institucional')
        ->callTableAction('agregarRedesSociales', array_key_first(LoMasEscritoEnOtro::filas('catalogo:redes_sociales')), ['icono' => 'enlace', 'dominios' => null])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Agregado a la lista.');

    expect(RedSocial::query()->where('nombre', 'Bluesky institucional')->value('codigo'))->toBe('bluesky_instituciona');
});

test('por el servicio de redes: «Otra red» con detalle corto y una red de la lista con detalle se rechazan', function () {
    $party = Party::factory()->create();
    $linkedin = (int) RedSocial::query()->where('codigo', 'linkedin')->value('id');

    expect(fn () => app(RedesSocialesService::class)->agregar($party->id, null, new DatosRedSocial(otraRed(), 'https://otra.ejemplo.pe/x', detalle: 'Mast')))->toThrow(DetalleDeOtroRechazado::class, 'escribe cuál es')
        ->and(fn () => app(RedesSocialesService::class)->agregar($party->id, null, new DatosRedSocial($linkedin, 'https://linkedin.com/company/x', detalle: 'Mastodon del colegio')))->toThrow(DetalleDeOtroRechazado::class, 'va solo con «Otro»');
});

test('la base no guarda un detalle en blanco', function () {
    $id = conOtraRed('Mastodon del colegio');

    expect(fn () => PartyRedSocial::query()->whereKey($id)->update(['detalle' => '   ']))->toThrow(QueryException::class, 'party_redes_sociales_detalle_check');
});

test('agregar a un catálogo sin permiso se rechaza también sin la pantalla', function () {
    expect(fn () => app(EjecutorAcciones::class)->ejecutar(SelectorCatalogo::claveAgregar(Catalogo::OrigenesComerciales), ['codigo' => 'RADIO', 'nombre' => 'Radio'], User::factory()->create()))
        ->toThrow(AccionRechazada::class);
});
