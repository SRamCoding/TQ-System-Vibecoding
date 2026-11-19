<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\Filament\BarraModulos;
use App\Modules\Nucleo\Filament\Widgets\CajasModulos;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\EleccionDeModulos;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4f, D5 a D7: la barra lateral se arma con las cajas permitidas, se prende, se apaga y se elige desde el menú
 * del avatar sin recargar, y la siguiente carga llega coherente desde el servidor. La animación y lo que esconde el
 * CSS los revisa el desarrollador a la vista; aquí, lo que la página trae y lo que se guarda.
 */

beforeEach(function () {
    Permission::findOrCreate('ventas.ver', 'web');

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    // Registradas fuera del orden de las áreas: la barra y el inicio las ordenan igual.
    $registro->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp, icono: 'heroicon-o-shopping-cart'));
    $registro->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: 'ventas.ver', area: AreaModulo::Crm, icono: 'heroicon-o-banknotes'));
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp, icono: 'heroicon-o-calendar'));
    $registro->registrar(new CajaModulo('rrhh', 'RRHH', '/rrhh', permiso: 'rrhh.ver', area: AreaModulo::Erp));
});

function paginaDeLaBarra(User $usuario, string $url = '/'): DOMXPath
{
    $html = (string) test()->actingAs($usuario)->get($url)->assertOk()->getContent();
    $documento = new DOMDocument;
    libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($documento);
}

/**
 * Las claves de los nodos con `data-modulo` que encuentra la consulta, en orden.
 *
 * @return list<string>
 */
function clavesDeLaBarra(DOMXPath $xpath, string $consulta): array
{
    $claves = [];

    foreach ($xpath->query($consulta) ?: [] as $nodo) {
        if ($nodo instanceof DOMElement) {
            $claves[] = $nodo->getAttribute('data-modulo');
        }
    }

    return $claves;
}

function topbarDe(User $usuario): Testable
{
    Filament::setCurrentPanel('app');

    return Livewire::actingAs($usuario)->test(Topbar::class);
}

test('la barra trae todos los permitidos, agrupados por área en el orden del inicio; los ocultos marcados', function () {
    $usuario = User::factory()->conBarraModulos()->create()->givePermissionTo('ventas.ver');
    app(NavegacionService::class)->ocultar($usuario, ['agenda']);

    $xpath = paginaDeLaBarra($usuario);

    expect(clavesDeLaBarra($xpath, '//li[@data-tq-barra]'))->toBe(['ventas', 'compras', 'agenda'])
        ->and(clavesDeLaBarra($xpath, '//li[@data-tq-barra][not(contains(@class, "tq-oculto"))]'))->toBe(['ventas', 'compras'])
        ->and(clavesDeLaBarra($xpath, '//li[@data-tq-barra][contains(@class, "tq-oculto")]'))->toBe(['agenda'])
        // Nunca un módulo sin permiso, ni siquiera escondido.
        ->and(clavesDeLaBarra($xpath, '//*[@data-modulo="rrhh"]'))->toBe([])
        ->and($xpath->query('//*[@data-tq-area="crm"]//li[@data-modulo="ventas"]')?->length)->toBe(1)
        ->and($xpath->query('//*[@data-tq-area="erp"]//li[@data-modulo="agenda"]')?->length)->toBe(1);
});

test('inicio y barra concuerdan: mismos módulos, orden, ícono y nombre; el inicio = visibles de la barra + ocultos', function () {
    $usuario = User::factory()->conBarraModulos()->create()->givePermissionTo('ventas.ver');
    app(NavegacionService::class)->ocultar($usuario, ['compras']);
    test()->actingAs($usuario);

    $cajas = array_merge(...array_map(fn (array $seccion): array => $seccion['cajas'], (new CajasModulos)->secciones()));
    $navegacion = BarraModulos::navegacion($usuario);
    expect($navegacion)->not->toBeFalse();

    $items = array_merge(...array_map(fn (NavigationGroup $grupo): array => array_values($grupo->getItems()), $navegacion ? $navegacion->getNavigation() : []));

    expect(array_map(fn (NavigationItem $item): array => [$item->getLabel(), $item->getIcon(), $item->getUrl(), $item->getExtraAttributes()['data-modulo']], $items))
        ->toBe(array_map(fn (CajaModulo $caja): array => [$caja->nombre, $caja->icono, $caja->url, $caja->clave], $cajas));

    $xpath = paginaDeLaBarra($usuario);
    $visibles = clavesDeLaBarra($xpath, '//li[@data-tq-barra][not(contains(@class, "tq-oculto"))]');
    $delInicio = clavesDeLaBarra($xpath, '//a[@data-modulo]');

    expect($visibles)->toBe(array_map(fn (CajaModulo $caja): string => $caja->clave, app(NavegacionService::class)->deLaBarra($usuario)))
        ->and($delInicio)->toBe(array_values(array_filter(
            clavesDeLaBarra($xpath, '//li[@data-tq-barra]'),
            fn (string $clave): bool => in_array($clave, [...$visibles, ...app(NavegacionService::class)->ocultos($usuario)], true),
        )));
});

test('el módulo activo queda resaltado también en sus pantallas internas', function (string $url, bool $activo) {
    app()->instance('request', Request::create($url));
    $navegacion = BarraModulos::navegacion(User::factory()->create());
    $items = array_merge(...array_map(fn (NavigationGroup $grupo): array => array_values($grupo->getItems()), $navegacion ? $navegacion->getNavigation() : []));
    $compras = collect($items)->first(fn (NavigationItem $item): bool => $item->getLabel() === 'Compras');

    expect($compras)->toBeInstanceOf(NavigationItem::class)
        ->and($compras?->isActive())->toBe($activo);
})->with([
    'la pantalla del módulo' => ['/compras', true],
    'una pantalla interna' => ['/compras/ordenes/5/editar', true],
    'otra ruta que empieza con las mismas letras' => ['/comprasx', false],
    'otro módulo' => ['/agenda', false],
]);

test('apagada, la página marca <html> antes de dibujarse; prendida, no', function () {
    $marca = "<script>document.documentElement.classList.add('tq-barra-apagada')</script>";

    expect((string) test()->actingAs(User::factory()->create())->get('/')->getContent())->toContain($marca)
        ->and((string) test()->actingAs(User::factory()->conBarraModulos()->create())->get('/')->getContent())->not->toContain($marca);
});

test('Filament sigue usando las tres clases internas con que se esconde la barra apagada', function () {
    // Si una actualización de Filament las renombra, la barra apagada seguiría viéndose (interfaz.md, excepciones).
    $html = (string) test()->actingAs(User::factory()->create())->get('/')->getContent();

    expect($html)->toContain('html.tq-barra-apagada .fi-sidebar,')
        ->toMatch('/class="[^"]*\bfi-sidebar\b/')
        ->toMatch('/class="[^"]*\bfi-topbar-open-sidebar-btn\b/')
        ->toMatch('/class="[^"]*\bfi-sidebar-close-overlay\b/');
});

test('sin módulos permitidos no hay barra ni ítems en el menú', function () {
    // Sin las cajas de prueba que todos ven.
    app()->forgetInstance(RegistroModulos::class);
    $usuario = User::factory()->conBarraModulos()->create();

    expect(BarraModulos::navegacion($usuario))->toBeFalse();

    // Filament no registra un ítem del menú escondido.
    topbarDe($usuario)->assertActionDoesNotExist('nucleoMostrarBarraModulos')->assertActionDoesNotExist('nucleoElegirModulos');
});

test('apagada: «Mostrar barra de módulos» abre la elección y al aceptar la prende, sin redirigir', function () {
    $usuario = User::factory()->create()->givePermissionTo('ventas.ver');

    topbarDe($usuario)
        ->mountAction('prenderBarraModulos')
        ->assertSchemaStateSet(['ocultos' => ['ventas', 'compras', 'agenda']])
        ->fillForm(['ocultos' => ['ventas', 'agenda']])
        ->callMountedAction()
        ->assertNoRedirect()
        ->assertDispatched(BarraModulos::EVENTO, visible: true, ocultos: ['compras'])
        // El menú se redibuja con el ícono al día.
        ->assertDispatched('refresh-topbar');

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue()
        ->and(app(NavegacionService::class)->ocultos($usuario))->toBe(['compras']);

    // La siguiente carga llega coherente desde el servidor.
    expect(clavesDeLaBarra(paginaDeLaBarra($usuario), '//li[@data-tq-barra][contains(@class, "tq-oculto")]'))->toBe(['compras']);
});

test('un módulo que se permite con la ventana abierta no queda oculto al aceptar', function () {
    $usuario = User::factory()->create();
    $ventana = topbarDe($usuario)->mountAction('prenderBarraModulos')->fillForm(['ocultos' => ['compras', 'agenda']]);

    // Mientras la ventana está abierta, le dan Ventas: no se le mostró, así que no puede haberla desmarcado.
    $usuario->givePermissionTo('ventas.ver');
    $ventana->callMountedAction();

    expect(app(NavegacionService::class)->ocultos($usuario->refresh()))->toBe([])
        ->and(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue();
});

test('cancelar o cerrar la elección no guarda nada: la barra sigue apagada', function () {
    $usuario = User::factory()->create();

    topbarDe($usuario)
        ->mountAction('prenderBarraModulos')
        ->fillForm(['ocultos' => ['agenda']])
        ->call('unmountAction')
        ->assertNotDispatched(BarraModulos::EVENTO);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse()
        ->and(ModuloOculto::query()->count())->toBe(0);
});

test('desmarcar todo no se guarda y la barra no se prende', function () {
    $usuario = User::factory()->create();

    topbarDe($usuario)
        ->mountAction('prenderBarraModulos')
        ->fillForm(['ocultos' => []])
        ->callMountedAction()
        ->assertNotified('Deja al menos un módulo en la barra.')
        ->assertNotDispatched(BarraModulos::EVENTO);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse()
        ->and(ModuloOculto::query()->count())->toBe(0);
});

test('prendida: el ítem la apaga al instante, sin ventana ni redirigir', function () {
    $usuario = User::factory()->conBarraModulos()->create();

    topbarDe($usuario)
        ->callAction('nucleoMostrarBarraModulos')
        ->assertNoRedirect()
        ->assertDispatched(BarraModulos::EVENTO, visible: false, ocultos: []);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse();
});

test('la ventana sale solo la primera vez de la sesión; después se prende al instante con lo guardado', function () {
    $usuario = User::factory()->create();

    topbarDe($usuario)
        ->mountAction('prenderBarraModulos')
        ->fillForm(['ocultos' => ['compras']])
        ->callMountedAction();

    expect(app(EleccionDeModulos::class)->yaSeAcepto())->toBeTrue();

    topbarDe($usuario->refresh())->callAction('nucleoMostrarBarraModulos');
    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeFalse();

    // Segunda vez en la misma sesión: sin ventana y con los módulos ya guardados.
    topbarDe($usuario)
        ->assertActionDoesNotExist('prenderBarraModulos')
        ->callAction('nucleoMostrarBarraModulos')
        ->assertNoRedirect()
        ->assertDispatched(BarraModulos::EVENTO, visible: true, ocultos: ['agenda']);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue()
        ->and(app(NavegacionService::class)->ocultos($usuario))->toBe(['agenda']);
});

test('si se cancela la ventana, la próxima vez de la sesión vuelve a salir', function () {
    $usuario = User::factory()->create();

    topbarDe($usuario)->mountAction('prenderBarraModulos')->call('unmountAction');

    expect(app(EleccionDeModulos::class)->yaSeAcepto())->toBeFalse();

    topbarDe($usuario)->assertActionExists('prenderBarraModulos');
});

test('un rechazo (todo desmarcado) tampoco cuenta como aceptada', function () {
    $usuario = User::factory()->create();

    topbarDe($usuario)->mountAction('prenderBarraModulos')->fillForm(['ocultos' => []])->callMountedAction();

    expect(app(EleccionDeModulos::class)->yaSeAcepto())->toBeFalse();
});

test('un nuevo inicio de sesión vuelve a mostrar la ventana', function () {
    $usuario = User::factory()->create();
    app(EleccionDeModulos::class)->marcarAceptada();

    topbarDe($usuario)->assertActionDoesNotExist('prenderBarraModulos');

    // Sale sin cerrar la sesión de Laravel (la marca sigue ahí) y vuelve a entrar con su clave.
    auth()->logout();
    expect(app(EleccionDeModulos::class)->yaSeAcepto())->toBeTrue();

    Livewire::test(Login::class)
        ->fillForm(['email' => $usuario->email, 'password' => UserFactory::CLAVE])
        ->call('authenticate');

    expect(app(EleccionDeModulos::class)->yaSeAcepto())->toBeFalse();

    topbarDe($usuario)->assertActionExists('prenderBarraModulos');
});

test('«Elegir módulos…» aplica al instante y solo aparece con la barra prendida', function () {
    $usuario = User::factory()->conBarraModulos()->create();

    topbarDe($usuario)
        ->mountAction('nucleoElegirModulos')
        ->fillForm(['ocultos' => ['agenda']])
        ->callMountedAction()
        ->assertNoRedirect()
        ->assertDispatched(BarraModulos::EVENTO, visible: true, ocultos: ['compras']);

    expect(app(NavegacionService::class)->ocultos($usuario))->toBe(['compras']);

    topbarDe(User::factory()->create())->assertActionDoesNotExist('nucleoElegirModulos');
});

test('«Barra de módulos» dice el estado con un interruptor: prendido en el azul del tema, apagado en gris', function () {
    $prendida = topbarDe(User::factory()->conBarraModulos()->create())->instance()->getAction('nucleoMostrarBarraModulos');
    $apagada = topbarDe(User::factory()->create())->instance()->getAction('prenderBarraModulos');

    expect($prendida?->getLabel())->toBe('Barra de módulos')
        ->and($prendida?->getIcon())->toBe('tq-interruptor-prendido')
        ->and($prendida?->getExtraAttributes()['class'] ?? '')->toContain('tq-interruptor-prendido')
        ->and($apagada?->getIcon())->toBe('tq-interruptor-apagado')
        ->and($apagada?->getExtraAttributes()['class'] ?? '')->not->toContain('tq-interruptor-prendido');

    // El ícono propio existe y se dibuja (si no, Filament fallaría al pintar el menú).
    $html = (string) test()->actingAs(User::factory()->conBarraModulos()->create())->get('/')->assertOk()->getContent();

    expect($html)->toContain('tq-interruptor-perilla')
        ->toContain('.tq-interruptor-prendido .fi-icon { color: var(--primary-700); }')
        ->toContain('.dark .tq-interruptor-prendido .fi-icon { color: var(--primary-400); }');
});

test('la vista anima solo lo que entra, con tope de 600 ms y sin animación con «reducir movimiento»', function () {
    $html = (string) test()->actingAs(User::factory()->create())->get('/')->getContent();

    expect($html)->toContain('Math.min(60, 400 / (entran.length - 1))')
        ->toContain('[data-tq-barra].tq-entrando { animation: tq-entrar-barra 200ms ease-out both;')
        ->toMatch('/@media \(prefers-reduced-motion: reduce\) \{\s*\[data-tq-barra\]\.tq-entrando \{ animation: none; \}/');
});

test('la primerísima sesión de una persona muestra solo las cajas: la barra entra apagada (plan 1.4aa, D3)', function () {
    $nueva = User::factory()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $nueva->email, 'password' => UserFactory::CLAVE])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(DB::table('preferencias_usuario')->where('user_id', $nueva->id)->where('clave', 'navegacion.barra_modulos')->exists())->toBeFalse()
        ->and($this->get('/')->assertOk()->getContent())->toContain("document.documentElement.classList.add('tq-barra-apagada')");
});
