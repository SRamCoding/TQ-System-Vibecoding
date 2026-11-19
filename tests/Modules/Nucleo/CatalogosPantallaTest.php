<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\GrupoCatalogo;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Plan 1.4j: Configuración → Catálogos como cajas agrupadas por tema, con su ícono y su descripción (D2 y D3); al
 * presionar una, su tabla en un panel (D1).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

/** Una persona que puede ver los catálogos. */
function quienVeCatalogos(): User
{
    return User::factory()->create()->givePermissionTo('catalogos.ver');
}

test('cada catálogo está en un solo grupo, con un ícono que existe y su descripción', function () {
    $enGrupos = array_merge(...array_map(fn (GrupoCatalogo $g): array => $g->catalogos(), GrupoCatalogo::cases()));

    expect(array_map(fn (Catalogo $c): string => $c->value, $enGrupos))->toHaveCount(count(Catalogo::cases()))
        ->and(array_unique(array_map(fn (Catalogo $c): string => $c->value, $enGrupos)))->toHaveCount(count(Catalogo::cases()));

    foreach (Catalogo::cases() as $catalogo) {
        expect($catalogo->grupo())->toBeInstanceOf(GrupoCatalogo::class)
            ->and($catalogo->descripcion())->not->toBe('')
            ->and(fn () => app(IconFactory::class)->svg($catalogo->icono()))->not->toThrow(Throwable::class);
    }
});

test('los grupos van en el orden de la pantalla', function () {
    expect(array_map(fn (GrupoCatalogo $g): string => $g->etiqueta(), GrupoCatalogo::cases()))
        ->toBe(['Comercio y pagos', 'Personas y lugares', 'Proyectos y obras', 'Documentos y archivos', 'Calendario y saludo'])
        ->and(Catalogo::Feriados->grupo())->toBe(GrupoCatalogo::CalendarioYSaludo);
});

test('al entrar, las cajas de todos los catálogos en sus grupos, con su descripción, y ninguna tabla', function () {
    $html = (string) test()->actingAs(quienVeCatalogos())->get('/configuracion/catalogos')->assertOk()->getContent();

    expect(substr_count($html, 'class="fi-section tq-caja-catalogo tq-caja-sub"'))->toBe(count(Catalogo::cases()))
        ->and(substr_count($html, 'data-tq-grupo='))->toBe(count(GrupoCatalogo::cases()))
        ->and($html)->toContain('Buscar catálogo')
        ->and($html)->toContain(e(Catalogo::Ubigeos->descripcion()))
        ->and($html)->not->toContain('data-catalogo-abierto')
        ->and($html)->not->toContain('Dólar estadounidense');

    // En el orden de los grupos.
    $posiciones = array_map(fn (GrupoCatalogo $g): int|false => strpos($html, 'data-tq-grupo="'.$g->value.'"'), GrupoCatalogo::cases());
    expect($posiciones)->toBe(array_values(array_filter($posiciones)))
        ->and($posiciones)->toEqual(collect($posiciones)->sort()->values()->all());
});

test('cada caja lleva a su catálogo; con ?catalogo= se abre su panel con la tabla y la ruta dice su nombre', function () {
    $usuario = quienVeCatalogos();

    test()->actingAs($usuario)->get('/configuracion/catalogos')
        ->assertSee('href="'.e(Catalogos::getUrl(['catalogo' => 'monedas'])).'"', false);

    test()->actingAs($usuario)->get('/configuracion/catalogos?catalogo=monedas')->assertOk()
        ->assertSee('data-catalogo-abierto="monedas"', false)
        ->assertSee('Dólar estadounidense')
        ->assertSee('Cerrar y volver a los catálogos')
        // El panel va encima: las cajas siguen detrás, atenuadas.
        ->assertSee('class="tq-catalogos-detras"', false)
        ->assertSee('class="fi-section tq-caja-catalogo tq-caja-sub"', false);

    expect(array_values(Livewire::withQueryParams(['catalogo' => 'monedas'])->actingAs($usuario)->test(Catalogos::class)->instance()->getBreadcrumbs()))
        ->toBe(['Configuración', 'Catálogos', 'Monedas']);
    // `withQueryParams` sigue valiendo para las siguientes: se vacía.
    expect(array_values(Livewire::withQueryParams([])->actingAs($usuario)->test(Catalogos::class)->instance()->getBreadcrumbs()))
        ->toBe(['Configuración', 'Catálogos', 'Listado']);
});

test('un catálogo que no existe en la URL no abre nada: se ven las cajas', function () {
    test()->actingAs(quienVeCatalogos())->get('/configuracion/catalogos?catalogo=inventado')->assertOk()
        ->assertDontSee('data-catalogo-abierto', false)
        ->assertSee('class="fi-section tq-caja-catalogo tq-caja-sub"', false);
});

test('sin permiso para ver catálogos, 403 como antes', function () {
    test()->actingAs(User::factory()->create())->get('/configuracion/catalogos')->assertForbidden();
});

test('sin un panel abierto no se consulta ningún catálogo', function () {
    $usuario = quienVeCatalogos();
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas): void {
        $consultas[] = $consulta->sql;
    });

    test()->actingAs($usuario)->get('/configuracion/catalogos')->assertOk();

    // La tabla del panel cuenta sus filas y las pide ordenadas; otras partes (el calendario laboral del saludo) leen
    // feriados por su cuenta, y eso no es la tabla. Tampoco el panel de aplicaciones de todas las pantallas (plan 1.4p),
    // que lee las suyas junto con las que la persona ocultó.
    $tablas = array_map(fn (Catalogo $c): string => (new ($c->modelo()))->getTable(), Catalogo::cases());
    $aCatalogos = array_filter($consultas, fn (string $sql): bool => ! str_contains($sql, 'aplicaciones_ocultas') && collect($tablas)->contains(
        fn (string $t): bool => preg_match('/from "'.$t.'"/', $sql) === 1 && (str_contains($sql, 'count(*)') || str_contains($sql, 'order by')),
    ));

    expect($aCatalogos)->toBe([]);

    // Y la prueba sirve: con el panel de monedas abierto, sí se consulta.
    $consultas = [];
    test()->actingAs($usuario)->get('/configuracion/catalogos?catalogo=monedas')->assertOk();
    expect(array_filter($consultas, fn (string $sql): bool => str_contains($sql, 'from "monedas"') && str_contains($sql, 'order by')))->not->toBe([]);
});

test('ningún ícono se repite: ni entre catálogos, ni con los módulos del inicio, ni con las pantallas', function () {
    // Pedido del desarrollador (2026-10-23): cada cosa con su propio dibujo.
    $catalogos = array_map(fn (Catalogo $c): string => $c->icono(), Catalogo::cases());
    $modulos = array_map(fn (CajaModulo $c): string => $c->icono, app(RegistroModulos::class)->todas());
    $pantallas = array_values(array_filter(array_map(
        fn (string $clase): ?string => is_string($icono = $clase::getNavigationIcon()) ? $icono : null,
        [...Filament::getPanel('app')->getPages(), ...Filament::getPanel('app')->getResources()],
    )));
    // Una pantalla y la caja de su módulo pueden compartirlo: son lo mismo (Personas y empresas, Mien, Configuración).
    $todos = [...$catalogos, ...array_values(array_unique([...$modulos, ...$pantallas]))];

    expect(array_unique($catalogos))->toHaveCount(count($catalogos))
        ->and(array_diff_assoc($todos, array_unique($todos)))->toBe([]);
});

test('las cajas crecen al pasar el mouse, menos en una subparte que en el inicio; quietas con animaciones reducidas', function () {
    $usuario = quienVeCatalogos();
    $catalogos = (string) test()->actingAs($usuario)->get('/configuracion/catalogos')->getContent();
    $inicio = (string) test()->actingAs($usuario)->get('/')->getContent();

    expect($catalogos)->toContain('class="fi-section tq-caja-catalogo tq-caja-sub"')
        ->toContain('.tq-caja-sub:hover, .tq-caja-sub:focus-visible { position: relative; z-index: 1; transform: scale(1.04); }')
        ->toContain('.dark .tq-caja-sub .tq-caja-sub-icono { color: #FFFFFF; }')
        ->toContain('html.tq-sin-animaciones .tq-caja-sub, html.tq-sin-animaciones .tq-caja-sub .tq-caja-sub-icono { transition: none; transform: none !important; }')
        ->and($inicio)->toContain('.tq-caja-modulo:hover, .tq-caja-modulo:focus-visible { z-index: 1; transform: scale(1.08); }')
        ->toContain('html.tq-sin-animaciones .tq-caja-modulo, html.tq-sin-animaciones .tq-caja-modulo .tq-caja-modulo-icono { transition: none; transform: none !important; }');
});

test('abrir y cerrar un catálogo no recarga la página: la tabla cambia en el sitio y la URL lo sigue', function () {
    $pagina = Livewire::withQueryParams([])->actingAs(quienVeCatalogos())->test(Catalogos::class)
        ->assertDontSeeHtml('data-catalogo-abierto');

    $pagina->call('abrir', 'monedas')
        ->assertSet('catalogo', 'monedas')
        ->assertSeeHtml('data-catalogo-abierto="monedas"')
        ->assertSee('Dólar estadounidense');

    // Otro catálogo desde una caja que asoma detrás: la tabla se rehace para él.
    $pagina->set('tableSearch', 'dolar')->call('abrir', 'formas_pago')
        ->assertSet('catalogo', 'formas_pago')
        ->assertSet('tableSearch', '')
        ->assertDontSee('Dólar estadounidense')
        ->assertSeeHtml('data-catalogo-abierto="formas_pago"');

    $pagina->call('cerrar')
        ->assertSet('catalogo', null)
        ->assertDontSeeHtml('data-catalogo-abierto');

    // Uno que no existe no hace nada.
    $pagina->call('abrir', 'inventado')->assertSet('catalogo', null);
});

test('la caja abre en el sitio con un clic normal y deja el enlace para otra pestaña; la X desliza antes de cerrar', function () {
    $html = (string) test()->actingAs(quienVeCatalogos())->get('/configuracion/catalogos?catalogo=monedas')->getContent();

    expect($html)->toContain('$wire.abrir(')
        ->toContain('$event.ctrlKey')
        ->toContain('setTimeout(() => $wire.cerrar(), quieto ? 0 : 190)')
        ->toContain('@keyframes tq-catalogo-sale');
});
