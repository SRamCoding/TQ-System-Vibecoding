<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\BarraLateral;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4f, D8 a D10: el inicio con los módulos permitidos y la pantalla angosta. Lo que depende del ancho lo hace el
 * CSS con un solo corte (BarraLateral::CORTE_ESCRITORIO); aquí, que la página traiga las reglas y las marcas. Que el
 * navegador lo pinte bien lo revisa el desarrollador a la vista (celular, escritorio y tablet girando).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Permission::findOrCreate('ventas.ver', 'web');

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: 'ventas.ver', area: AreaModulo::Crm));
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp, descripcion: 'Citas y recordatorios'));
});

function paginaAngosta(User $usuario, string $url = '/'): string
{
    return (string) test()->actingAs($usuario)->followingRedirects()->get($url)->assertOk()->getContent();
}

test('el corte es el lg de Filament y sale de un solo lugar', function () {
    expect(BarraLateral::CORTE_ESCRITORIO)->toBe(1024)
        ->and(BarraLateral::consultaCelular())->toBe('(max-width: 1023px)')
        ->and(BarraLateral::consultaEscritorio())->toBe('(min-width: 1024px)');
});

test('en el celular se esconden la barra, su botón y sus ítems del menú; en escritorio, la casa', function () {
    $html = paginaAngosta(User::factory()->conBarraModulos()->create());

    expect($html)
        ->toMatch('/@media \(max-width: 1023px\) \{\s*\.fi-sidebar,\s*\.fi-topbar-open-sidebar-btn,\s*\.fi-sidebar-close-overlay,\s*\.tq-solo-escritorio \{ display: none !important; \}/')
        ->toMatch('/@media \(min-width: 1024px\) \{\s*\.tq-solo-celular \{ display: none !important; \}/')
        // Los dos ítems de la barra en el menú del avatar llevan la marca de solo escritorio.
        ->toMatch("/class=\"tq-solo-escritorio[^\"]*\"[^>]*mountAction\\('nucleoMostrarBarraModulos'\\)/")
        ->toMatch("/class=\"tq-solo-escritorio[^\"]*\"[^>]*mountAction\\('nucleoElegirModulos'\\)/");
});

test('la casa sale en una pantalla de módulo, con su nombre para el lector, y no en el inicio', function () {
    $usuario = User::factory()->create()->givePermissionTo('catalogos.ver');

    expect(paginaAngosta($usuario, '/configuracion'))
        ->toContain('class="tq-ir-inicio tq-solo-celular" aria-label="Ir al inicio"')
        ->and(paginaAngosta($usuario))->not->toContain('aria-label="Ir al inicio"');
});

test('el inicio muestra solo los módulos permitidos y solo las áreas que tienen alguno', function () {
    $html = paginaAngosta(User::factory()->create());

    expect($html)->toContain('data-modulo="agenda"')
        ->not->toContain('data-modulo="ventas"')
        ->toContain('data-area="erp"')
        ->not->toContain('<section class="tq-area" data-area="crm"')
        ->not->toContain('<section class="tq-area" data-area="nucleo"');
});

test('cada caja abre en otra pestaña solo en escritorio; sin JavaScript, en la misma', function () {
    $html = paginaAngosta(User::factory()->create());
    $documento = new DOMDocument;
    libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    $caja = (new DOMXPath($documento))->query('//a[@data-modulo="agenda"]')?->item(0);

    expect($caja)->toBeInstanceOf(DOMElement::class);

    /** @var DOMElement $caja */
    expect($caja->getAttribute('href'))->toBe('/agenda')
        ->and($caja->hasAttribute('target'))->toBeFalse()
        ->and($caja->getAttribute('rel'))->toBe('noopener')
        ->and($caja->getAttribute('data-tq-pestana'))->toBe('nueva-en-escritorio')
        ->and($caja->getAttribute('x-on:click'))->toContain('window.innerWidth >= 1024')
        ->toContain("window.open(\$el.href, '_blank', 'noopener')");
});
