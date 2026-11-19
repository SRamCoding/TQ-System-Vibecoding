<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Filament\PanelAplicaciones;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\AplicacionOculta;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Models\UsoAplicacion;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Livewire\Livewire;

/*
 * Plan 1.4p, paso 4: el panel de aplicaciones en pantalla. Tres estados (D2), la memoria de la sesión (D3), el muelle
 * con las 3 más usadas (D5), abrir en otra pestaña (D6), las propias y las ocultas (D7), el celular (D8) y sin
 * animación con «reducir» (D9). Lo que hace el navegador (cambiar de estado, buscar) se revisa en el HTML que lo arma.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(ParametrosSeeder::class);
    test()->seed(CatalogosSeeder::class);
    Filament::setCurrentPanel('app');
});

/** El HTML del muelle, de su etiqueta a su cierre. */
function htmlDelMuelle(string $html): string
{
    return (string) strstr((string) strstr($html, 'data-tq-apps="muelle"'), '</nav>', true);
}

test('está en todas las pantallas de quien inició sesión, y no en el ingreso', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/')->assertOk()->assertSee('data-tq-apps="muelle"', false)->assertSee('data-tq-apps="cuadricula"', false);

    auth()->logout();
    test()->get('/login')->assertOk()->assertDontSee('data-tq-apps', false);
});

test('al entrar, el muelle con Claude, Excel y WhatsApp, que se abren en otra pestaña', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    $muelle = htmlDelMuelle($html);

    expect($html)->toContain('estado: \'muelle\'')
        ->and(preg_match_all('/data-tq-lugar-muelle=/', $muelle))->toBe(3)
        ->and($muelle)->toContain('data-nombre="Claude"')->toContain('data-nombre="Excel"')->toContain('data-nombre="WhatsApp"')
        ->toContain('target="_blank"')->toContain('rel="noopener noreferrer"')->toContain('https://claude.ai');
});

test('abrir una aplicación cuenta su uso, pero no la sube al muelle', function () {
    $usuario = User::factory()->create();
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    $pantalla->call('abrir', 'techquk', $gmail)->call('abrir', 'techquk', $gmail);

    expect(UsoAplicacion::query()->where('aplicacion_id', $gmail)->value('usos'))->toBe(2)
        ->and(htmlDelMuelle(Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->html()))->not->toContain('data-nombre="Gmail"');
});

test('la cuadrícula: «Tus 3 principales» con sus 3 lugares, «Más aplicaciones» y «Mías»; nunca «De Techquk»', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    $principales = (string) strstr((string) strstr($html, 'data-tq-apps="principales"'), 'Más aplicaciones', true);

    expect($html)->toContain('Tus 3 principales')->toContain('Más aplicaciones')->toContain('Mías')->not->toContain('De Techquk')
        ->and(substr_count($principales, 'data-tq-lugar='))->toBe(3)
        ->and($principales)->toContain('data-tq-app="techquk-'.Aplicacion::query()->where('codigo', 'claude')->value('id').'"')
        ->and(substr_count($html, 'data-tq-app="techquk-'.Aplicacion::query()->where('codigo', 'claude')->value('id').'"'))->toBe(1)
        ->and($html)->toContain('data-tq-destino="principales"')->toContain('data-tq-valor="1"')->toContain('Poner Gmail entre tus 3 principales');
});

test('arrastrar o «Poner arriba» la deja entre tus 3 principales y en el muelle', function () {
    $usuario = User::factory()->create();
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    $pantalla->call('ponerPrincipal', 1, 'techquk', $gmail)->assertReturned(null);
    $muelle = htmlDelMuelle($pantalla->html());

    expect($muelle)->toContain('data-nombre="Gmail"')->not->toContain('data-nombre="Claude"')
        ->and(strpos($muelle, 'data-nombre="Gmail"'))->toBeLessThan(strpos($muelle, 'data-nombre="Excel"'));

    $pantalla->call('ponerPrincipal', 4, 'techquk', $gmail)->assertReturned('El campo Lugar no puede ser mayor que 3.');
    $pantalla->call('ponerPrincipal', 1, 'propia', 999999)->assertReturned('Esa aplicación no está en tu panel.');
});

test('recuerda el estado en la sesión y vuelve al muelle al iniciar sesión otra vez', function () {
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->call('guardarEstado', 'cerrado', 'cuadricula');

    expect(session(PanelAplicaciones::SESION))->toBe(['estado' => 'cerrado', 'abierto' => 'cuadricula'])
        ->and(Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->html())->toContain('estado: \'cerrado\'')->toContain('abierto: \'cuadricula\'');

    event(new Login('web', $usuario, false));

    expect(session(PanelAplicaciones::SESION))->toBeNull()
        ->and(Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->html())->toContain('estado: \'muelle\'');
});

test('no guarda un estado que no existe', function () {
    Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)
        ->call('guardarEstado', 'flotante', 'muelle')
        ->call('guardarEstado', 'cerrado', 'cerrado');

    expect(session(PanelAplicaciones::SESION))->toBeNull();
});

test('agrega una propia desde la cuadrícula; un enlace sin https vuelve con su mensaje', function () {
    $usuario = User::factory()->create();
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    $pantalla->call('agregar', 'Mi tablero', 'http://tablero.example', 'generica')->assertReturned('El enlace debe empezar con https:// (una dirección segura).');
    $pantalla->call('agregar', 'Mi tablero', 'https://tablero.example', 'generica')->assertReturned(null)->assertSee('Mi tablero');

    expect(AplicacionPropia::query()->where('user_id', $usuario->id)->value('nombre'))->toBe('Mi tablero');
});

test('una aplicación propia de una persona no la ve otra', function () {
    AplicacionPropia::factory()->create(['nombre' => 'Solo de Ana']);

    Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->assertDontSee('Solo de Ana');
});

test('cambia y quita una propia; oculta y vuelve a mostrar una de Techquk', function () {
    $usuario = User::factory()->create();
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Tablero']);
    $excel = Aplicacion::query()->where('codigo', 'excel')->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    $pantalla->call('cambiar', $propia->id, 'Tablero nuevo', 'https://tablero.example', 'excel', 1)->assertReturned(null)->assertSee('Tablero nuevo');
    $pantalla->call('quitar', $propia->id, 1)->assertReturned('Otra persona cambió la aplicación mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');
    $pantalla->call('quitar', $propia->id, 2)->assertReturned(null)->assertDontSee('Tablero nuevo');

    $pantalla->call('ocultar', $excel)->assertReturned(null);

    expect(AplicacionOculta::query()->where('user_id', $usuario->id)->count())->toBe(1)
        ->and(htmlDelMuelle($pantalla->html()))->not->toContain('data-nombre="Excel"')
        ->and($pantalla->html())->toContain('Mostrar Excel');

    $pantalla->call('mostrar', $excel)->assertReturned(null);

    expect(htmlDelMuelle($pantalla->html()))->toContain('data-nombre="Excel"');
});

test('se alinea al lugar del chat, flota encima sin pasar de 3,5 de 10 partes y en el celular ocupa la pantalla', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('--tq-apps-sobre-chat: calc(var(--tq-chat-abajo) + var(--tq-chat-tamano) + 0.75rem)')
        ->toContain('right: var(--tq-chat-derecha); bottom: var(--tq-apps-sobre-chat)')
        ->toContain('width: 30vw; min-width: 17rem; max-width: 35vw')
        ->toContain('@media (max-width: 767px)')
        ->toContain("get verMuelle() { return this.estado === 'muelle' && ! this.celular }");
});

test('anima solo si se permite el movimiento', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('@media (prefers-reduced-motion: no-preference)')
        ->toContain('html:not(.tq-sin-animaciones) .tq-apps-entra { transition:');
});

test('los íconos van incrustados (los de Microsoft, los originales); el genérico es la inicial del nombre', function () {
    $usuario = User::factory()->create();
    AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'tablero']);

    $html = Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->html();

    expect(htmlDelMuelle($html))->toContain('src="'.IconoAplicacion::Excel->imagen().'"')->toContain('src="'.IconoAplicacion::Claude->imagen().'"')
        ->and($html)->toContain('<span class="tq-apps-ico" data-icono="generica" aria-hidden="true">T</span>')
        ->not->toContain('src="http');
});

test('cada ícono está en el repositorio y es un SVG sin scripts ni enlaces', function (IconoAplicacion $icono) {
    $ruta = app_path("Modules/Nucleo/resources/aplicaciones/{$icono->value}.svg");
    $svg = (string) file_get_contents($ruta);

    expect($svg)->toStartWith('<svg')->not->toContain('<script')->not->toContain('href=')
        ->and(base64_decode(substr((string) $icono->imagen(), strlen('data:image/svg+xml;base64,'))))->toBe($svg);
})->with(array_values(array_filter(IconoAplicacion::cases(), fn (IconoAplicacion $icono): bool => ! in_array($icono, [IconoAplicacion::Generica, IconoAplicacion::Propio], true))));

test('el muelle tiene expandir arriba y aminorar abajo; en la cuadrícula «—» aminora al muelle, sin botón aparte', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    $muelle = htmlDelMuelle($html);
    $cuadricula = (string) strstr($html, 'data-tq-apps="cuadricula"');

    expect(strpos($muelle, 'aria-label="Ver todas"'))->toBeLessThan(strpos($muelle, '<a'))
        ->and(strpos($muelle, 'aria-label="Aminorar"'))->toBeGreaterThan(strrpos($muelle, '</a>'))
        ->and($cuadricula)->not->toContain('Volver al muelle')->toContain('x-on:click="aminorar()"')
        ->and($html)->toContain("aminorar() { this.poner(this.celular ? 'cerrado' : 'muelle') }");
});

test('al pasar el mouse, el ícono del muelle crece como el de las cajas, y queda quieto con el movimiento reducido (1.4v)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('.tq-apps-muelle a:is(:hover, :focus-visible) .tq-apps-ico { transform: scale(1.2); }')
        ->toContain('@media (prefers-reduced-motion: reduce) { .tq-apps-muelle a .tq-apps-ico { transition: none; transform: none !important; } }')
        ->toContain('html.tq-sin-animaciones .tq-apps-muelle a .tq-apps-ico { transition: none; transform: none !important; }');
});

test('ícono y nombre comparten el centro: el enlace es una columna centrada, sin corrimientos (1.4z)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('.tq-apps-enlace { display: flex; flex-direction: column; align-items: center;')
        ->toContain('class="tq-apps-enlace"')
        ->not->toContain('class="contents"')
        ->not->toContain('translate: 4% 0');
});

test('Gmail y Google Drive van con sus colores, no de uno solo (1.4z)', function (IconoAplicacion $icono, int $colores) {
    $svg = (string) file_get_contents(app_path("Modules/Nucleo/resources/aplicaciones/{$icono->value}.svg"));

    preg_match_all('/fill="(#[0-9a-fA-F]{3,6})"/', $svg, $encontrados);

    expect(count(array_unique(array_map('strtolower', $encontrados[1]))))->toBeGreaterThanOrEqual($colores);
})->with([
    'Gmail' => [IconoAplicacion::Gmail, 4],
    'Google Drive' => [IconoAplicacion::GoogleDrive, 3],
]);

test('si se abre algo encima (ventana, menú o búsqueda), la cuadrícula vuelve al muelle (1.4z)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain("document.addEventListener('x-modal-opened', () => this.hacerseAUnLado())")
        ->toContain(".closest?.('.fi-dropdown-trigger')) this.hacerseAUnLado()")
        ->toContain(".closest?.('.fi-global-search')) this.hacerseAUnLado()")
        ->toContain("hacerseAUnLado() { if (this.estado === 'cuadricula') this.aminorar() }");
});

test('se arrastra con Pointer Events, sin espera: empieza a los 5 px y si no se movió es un clic (1.4aa, D1)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    $muelle = htmlDelMuelle($html);

    expect($html)->toContain('UMBRAL_PX: 5,')
        ->toContain('if (Math.hypot(dx, dy) < this.UMBRAL_PX) return')
        ->toContain('a.el.setPointerCapture(a.puntero)')
        ->toContain("window.addEventListener('pointercancel', terminar)")
        ->toContain('[data-tq-arrastra] { touch-action: none;')
        ->toContain('class="tq-apps-enlace" draggable="false" x-on:click="$wire.abrir(')
        ->not->toContain('setTimeout(')->not->toContain('draggable="true"')->not->toContain('x-on:drop')->not->toContain('dataTransfer')
        ->and($muelle)->toContain('data-tq-lugar-muelle="1"')->toContain('data-tq-lugar-muelle="3"')
        ->toContain('data-tq-destino="muelle"')->toContain('data-tq-valor="2"')
        ->toContain("x-on:pointerdown=\"presionar(\$event, 'techquk', ")
        ->toContain('x-on:click="$wire.abrir(');
});

test('el clic que sigue a un arrastre no abre la app (1.4aa, D1)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('this.sinClicHasta = Date.now() + 400')
        ->toContain('x-on:click.capture="if (Date.now() < sinClicHasta) { $event.preventDefault(); $event.stopPropagation() }"');
});

test('mientras se arrastra, la otra se corre en vivo; con «reducir animaciones», sin deslizarse (1.4aa, D1)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();

    expect($html)->toContain('DURACION_MS: 150,')
        ->toContain("sinMovimiento() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones') }")
        ->toContain('duracion() { return this.sinMovimiento() ? 0 : this.DURACION_MS }')
        ->toContain('this.deslizar(d.item, a.caja.left - d.cajaItem.left, a.caja.top - d.cajaItem.top)')
        ->toContain('const error = regla.lugar ? await $wire.ponerPrincipal(regla.lugar, a.origen, a.id) : await $wire.mover(a.origen, a.id, regla.sobreId)')
        ->toContain('return this.devolver(a)')
        ->toContain('<div wire:ignore x-ignore data-tq-apps="capa"></div>');
});

test('en el muelle, soltarla en otro lugar las intercambia, con la acción de siempre (1.4aa, D1)', function () {
    $usuario = User::factory()->create();
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);
    $claude = Aplicacion::query()->where('codigo', 'claude')->value('id');

    $pantalla->call('ponerPrincipal', 3, 'techquk', $claude)->assertReturned(null);
    $muelle = htmlDelMuelle($pantalla->html());

    expect(strpos($muelle, 'data-nombre="WhatsApp"'))->toBeLessThan(strpos($muelle, 'data-nombre="Excel"'))
        ->and(strpos($muelle, 'data-nombre="Excel"'))->toBeLessThan(strpos($muelle, 'data-nombre="Claude"'));
});

test('soltar una sobre otra de su sección las intercambia; de otra sección, avisa (1.4z; 1.4aa, D1)', function () {
    $usuario = User::factory()->create();
    $id = fn (string $codigo): int => Aplicacion::query()->where('codigo', $codigo)->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    $pantalla->call('mover', 'techquk', $id('gmail'), $id('word'))->assertReturned(null);

    expect(strpos($pantalla->html(), 'data-tq-app="techquk-'.$id('gmail').'"'))->toBeLessThan(strpos($pantalla->html(), 'data-tq-app="techquk-'.$id('word').'"'))
        ->and($pantalla->html())->toContain('data-tq-destino="techquk"')
        ->toContain("if (a.zona === 'muelle' || a.zona === 'principales' || a.zona !== d.zona) return { error: this.MENSAJE_SECCION }")
        ->toContain('Cada una se mueve dentro de su sección');
});

test('una propia no se mueve entre las de Techquk: la acción la rechaza y el orden no cambia (1.4aa, D1)', function () {
    $usuario = User::factory()->create();
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Tablero']);
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);
    $antes = $pantalla->html();

    $pantalla->call('mover', 'techquk', $gmail, $propia->id)->assertReturned('Esa aplicación no está en tu panel.');

    expect(strpos($pantalla->html(), 'data-tq-app="techquk-'.$gmail.'"') < strpos($pantalla->html(), 'data-tq-app="propia-'.$propia->id.'"'))
        ->toBe(strpos($antes, 'data-tq-app="techquk-'.$gmail.'"') < strpos($antes, 'data-tq-app="propia-'.$propia->id.'"'));
});

test('un clic en el ícono del muelle la abre y cuenta su uso (1.4aa, D1)', function () {
    $usuario = User::factory()->create();
    $claude = Aplicacion::query()->where('codigo', 'claude')->value('id');

    Livewire::actingAs($usuario)->test(PanelAplicaciones::class)->call('abrir', 'techquk', $claude);

    expect(UsoAplicacion::query()->where('aplicacion_id', $claude)->value('usos'))->toBe(1);
});

test('el x-data del panel llega entero: ninguna comilla doble lo corta (pasó el 2026-10-29)', function () {
    $html = Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html();
    preg_match('/class="tq-apps"\s+x-data="([^"]*)"/', $html, $atributo);

    expect($atributo[1] ?? '')->toContain('async quitar(id, version, nombre)')->toContain('hacerseAUnLado()')->toContain('async soltarPuntero(evento, cancelado)');
});

test('en modo edición, «abrir en el escritorio» solo en las que tienen programa; el enlace lleva al programa (1.4z)', function () {
    $usuario = User::factory()->create();
    $word = Aplicacion::query()->where('codigo', 'word')->value('id');
    $pantalla = Livewire::actingAs($usuario)->test(PanelAplicaciones::class);

    expect($pantalla->html())->toContain('aria-label="Abrir Word en el escritorio"')->not->toContain('Abrir Gmail en el escritorio');

    $pantalla->call('abrirEn', 'techquk', $word, true)->assertReturned(null);

    expect($pantalla->html())->toContain('href="ms-word:"')->toContain('aria-label="Abrir Word en la web"')
        ->and($pantalla->html())->toContain('wire:ignore.self');
});

test('el muelle tiene las tijeras de recortar pantalla, fijas y solo en Windows (1.4z)', function () {
    $muelle = htmlDelMuelle(Livewire::actingAs(User::factory()->create())->test(PanelAplicaciones::class)->html());

    expect($muelle)->toContain('href="ms-screenclip:"')->toContain('x-show="windows"')
        ->toContain('Recortar pantalla (Windows + Mayús + S)')
        ->and(strpos($muelle, 'data-tq-apps="recortar"'))->toBeLessThan(strpos($muelle, 'aria-label="Aminorar"'));
});
