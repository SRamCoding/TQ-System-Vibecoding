<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\LogoTechquk;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Livewire\Livewire;

/*
 * Plan 1.4b, D9: el logo de Techquk junto al nombre, su animación de construcción y sus pocas fechas propias. Cómo se
 * ve lo revisa el desarrollador en pantalla; aquí, qué se dibuja y cuándo.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function horaDeLima(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

test('los escombros tapan el espacio entre las piezas, en tres tandas, y son siempre los mismos', function () {
    $escombros = LogoTechquk::escombros();
    $tandas = array_count_values(array_column($escombros, 'zona'));

    ksort($tandas);

    expect(array_keys($tandas))->toBe([0, 1, 2])
        ->and(min($tandas))->toBeGreaterThan(5)
        ->and(LogoTechquk::escombros())->toBe($escombros);

    foreach ($escombros as $e) {
        expect($e['x'])->toBeGreaterThanOrEqual(0)->toBeLessThan(122)
            ->and($e['y'])->toBeGreaterThanOrEqual(0)->toBeLessThan(119)
            ->and($e['dy'])->toBeGreaterThan(0);
    }
});

test('solo cinco fechas tienen logo propio', function () {
    $conLogo = array_values(array_filter(Ocasion::cases(), fn (Ocasion $o): bool => LogoTechquk::tieneLogoPropio($o)));

    expect($conLogo)->toBe([Ocasion::AnioNuevo, Ocasion::SemanaSanta, Ocasion::FiestasPatrias, Ocasion::Navidad, Ocasion::Aniversario])
        ->and(LogoTechquk::tieneLogoPropio(Ocasion::Cumpleanos))->toBeFalse()
        ->and(LogoTechquk::tieneLogoPropio(null))->toBeFalse();
});

test('cada fecha cambia lo que acordamos: colores, luz o su detalle', function () {
    $normal = LogoTechquk::paleta(null);

    expect($normal)->toBe(['izquierda' => '#00DAFF', 'derecha' => '#7C6EFF', 'abajo' => '#FFFFFF', 'intensa' => false, 'detalle' => null])
        ->and(LogoTechquk::paleta(Ocasion::Aniversario))->toBe([...$normal, 'intensa' => true])
        ->and(LogoTechquk::paleta(Ocasion::Navidad))->toMatchArray(['izquierda' => '#D7263D', 'abajo' => '#FFFFFF', 'derecha' => '#D7263D', 'detalle' => 'gorro'])
        ->and(LogoTechquk::paleta(Ocasion::FiestasPatrias))->toMatchArray(['izquierda' => '#D91023', 'derecha' => '#FFFFFF', 'abajo' => '#D91023', 'detalle' => 'escudo'])
        ->and(LogoTechquk::paleta(Ocasion::SemanaSanta)['detalle'])->toBe('biblia')
        ->and(LogoTechquk::paleta(Ocasion::AnioNuevo)['detalle'])->toBe('estrella')
        ->and(LogoTechquk::paleta(Ocasion::Cumpleanos))->toBe($normal);
});

test('cada pieza se ilumina con su color (la blanca, con un blanco que se distinga del fondo claro), y el detalle de cada fecha se dibuja', function () {
    foreach ([null, Ocasion::Navidad, Ocasion::FiestasPatrias, Ocasion::SemanaSanta, Ocasion::AnioNuevo, Ocasion::Aniversario] as $ocasion) {
        $paleta = LogoTechquk::paleta($ocasion);
        $html = view('nucleo::filament.logo', ['paleta' => $paleta, 'escombros' => LogoTechquk::escombros()])->render();

        $luz = fn (string $color): string => $color === '#FFFFFF' ? 'var(--tq-luz-blanca)' : $color;

        expect($html)->toContain("stop-color: {$luz($paleta['izquierda'])}", "stop-color: {$luz($paleta['derecha'])}", "--brillo: {$luz($paleta['abajo'])}");

        if ($paleta['detalle'] !== null) {
            expect($html)->toContain("data-detalle=\"{$paleta['detalle']}\"");
        }
    }
});

test('el logo del día sale del calendario, también sin sesión, y no mira el cumpleaños', function () {
    $ambientacion = app(Ambientacion::class);
    $persona = User::factory()->create()->forceFill(['cumpleanos_dia' => 20, 'cumpleanos_mes' => 10]);

    expect($ambientacion->ocasionDelLogo(null, horaDeLima('2026-12-20 10:00')))->toBe(Ocasion::Navidad)
        ->and($ambientacion->ocasionDelLogo(null, horaDeLima('2026-07-25 10:00')))->toBe(Ocasion::FiestasPatrias)
        ->and($ambientacion->ocasionDelLogo($persona, horaDeLima('2026-10-20 10:00')))->toBeNull()
        ->and($ambientacion->ocasionDelLogo(null, horaDeLima('2026-05-08 10:00')))->toBeNull();
});

test('con la ambientación apagada, el logo es el de siempre', function () {
    $persona = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'apariencia.ambientacion', false);

    expect(app(Ambientacion::class)->ocasionDelLogo($persona, horaDeLima('2026-12-20 10:00')))->toBeNull();

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.ambientacion_activa', 'no', 1);
    app()->forgetScopedInstances();

    expect(app(Ambientacion::class)->ocasionDelLogo(null, horaDeLima('2026-12-20 10:00')))->toBeNull();
});

test('la barra superior muestra el logo con el nombre, sin el ícono de fecha que había al lado', function () {
    test()->travelTo(horaDeLima('2026-10-20 10:00'));
    test()->actingAs(User::factory()->create());

    test()->get('/')->assertOk()
        ->assertSee('class="tq-logo"', false)
        ->assertSee('data-logo="normal"', false)
        ->assertSee('tq-lg-esc', false)
        ->assertDontSee('tq-icono-fecha', false);
});

test('en el aniversario el logo brilla más, también en la pantalla de ingreso', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-10-20', 1);
    test()->travelTo(horaDeLima('2026-10-20 10:00'));

    test()->get('/login')->assertOk()->assertSee('data-logo="intenso"', false);
});

// --- Agradecimiento del aniversario (D10) ---

test('en el aniversario, la barra superior agradece a Dios por los años, y solo entonces', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-10-20', 1);
    test()->actingAs(User::factory()->create());

    test()->travelTo(horaDeLima('2026-10-20 10:00'));
    $texto = app(Ambientacion::class)->agradecimiento(null, horaDeLima('2026-10-20 10:00'));

    expect($texto)->toContain('14 años')
        ->and(mb_strlen((string) $texto))->toBeLessThanOrEqual(60);

    test()->get('/')->assertOk()->assertSee('data-agradecimiento', false)->assertSee((string) $texto);

    test()->travelTo(horaDeLima('2026-10-22 10:00'));
    test()->get('/')->assertOk()->assertDontSee('data-agradecimiento', false);
});

test('cada año sale otro agradecimiento, el mismo para todos', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-10-20', 1);
    $ambientacion = app(Ambientacion::class);
    $anios = collect([2026, 2027, 2028])->map(fn (int $anio): ?string => $ambientacion->agradecimiento(null, horaDeLima("{$anio}-10-20 10:00")));

    expect($anios->filter()->count())->toBe(3)
        ->and($anios->map(fn (?string $t): string => (string) preg_replace('/\d+/', '#', (string) $t))->unique()->count())->toBe(3)
        ->and($ambientacion->agradecimiento(User::factory()->create(), horaDeLima('2026-10-20 10:00')))->toBe($anios[0]);
});

test('sin fecha de fundación o con la ambientación apagada, no hay agradecimiento', function () {
    expect(app(Ambientacion::class)->agradecimiento(null, horaDeLima('2026-10-20 10:00')))->toBeNull();

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-10-20', 1);
    $persona = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'apariencia.ambientacion', false);

    expect(app(Ambientacion::class)->agradecimiento($persona, horaDeLima('2026-10-20 10:00')))->toBeNull();
});

test('los colores de marca son los del logo, y el azul noche exacto es el tono de los botones', function () {
    $paleta = ColoresMarca::paletaPrincipal();

    expect(Color::convertToHex($paleta[600]))->toBe('#01006f')
        ->and(array_keys($paleta))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and(LogoTechquk::paleta(null))->toMatchArray(['izquierda' => ColoresMarca::TURQUESA, 'derecha' => ColoresMarca::MORADO]);
});

test('en modo oscuro los botones usan un azul más luminoso; en modo claro, el azul noche', function () {
    test()->get('/login')->assertOk()
        ->assertSee('html.dark { --primary-600: '.ColoresMarca::principalModoOscuro().'; }', false);

    expect(Color::convertToHex(ColoresMarca::paletaPrincipal()[600]))->toBe('#01006f');
});

// --- Cuándo se anima (plan del logo, D1) ---

test('la primera pantalla tras el ingreso anima el logo, una sola vez; las siguientes lo dejan quieto', function () {
    User::factory()->create(['email' => 'logo@techquk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'logo@techquk.test', 'password' => UserFactory::CLAVE])
        ->call('authenticate');

    expect(session(LogoTechquk::MARCA_INGRESO))->toBeTrue();

    $primera = test()->get('/')->assertOk()->getContent();
    expect(substr_count((string) $primera, 'class="tq-logo"'))->toBe(substr_count((string) $primera, 'data-animar="1"'));
    test()->assertStringContainsString('data-animar="1"', (string) $primera);

    test()->get('/')->assertOk()->assertSee('class="tq-logo"', false)->assertDontSee('data-animar="1"', false);
    expect(session()->has(LogoTechquk::MARCA_INGRESO))->toBeFalse();
});

test('fuera de la primera pantalla, el logo se anima solo si el navegador dice que se actualizó la página', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/')->assertOk()
        ->assertDontSee('data-animar="1"', false)
        ->assertSee("performance.getEntriesByType('navigation')[0]?.type === 'reload'", false)
        ->assertSee('x-init="if (alCargar()) correr()"', false)
        // El selector de modo de Filament avisa `theme-changed` al cargar cada página: solo cuenta si el tema cambió.
        ->assertSee('x-on:theme-changed.window="if ($event.detail !== tema) { tema = $event.detail; correr() }"', false)
        ->assertSee("localStorage.getItem('theme')", false)
        ->assertSee('x-on:mouseenter="correr()"', false);
});

// --- La espera del ingreso (plan del logo, D2, D3 y D5) ---

test('en el ingreso el logo espera: late a ratos, con el mouse muestra su cuadrícula y forcejea, y nunca se construye', function () {
    $ingreso = test()->get('/login')->assertOk()
        ->assertSee('class="tq-logo tq-espera"', false)
        ->assertSee("Alpine.data('tqLogoEspera'", false)
        ->assertSee('.tq-espera:hover .tq-lg-esc { opacity: 1', false)
        ->getContent();

    // Ni al cargar, ni al cambiar de modo, ni con el mouse corre la construcción: solo late o forcejea.
    $logo = (string) strstr((string) $ingreso, 'class="tq-logo tq-espera"');
    expect(strstr($logo, '<svg', true))->not->toContain('correr()')->not->toContain('theme-changed')
        ->toContain('x-data="tqLogoEspera"')->toContain('x-on:mouseenter="entrar()"');
});

test('la espera es tenue solo si se permite el movimiento; si no, el logo normal y quieto', function () {
    test()->get('/login')->assertOk()
        ->assertSee('@media (prefers-reduced-motion: no-preference) {', false)
        ->assertSee('html:not(.tq-sin-animaciones) .tq-espera .tq-lg-pieza', false);
});

test('adentro el logo no espera', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/')->assertOk()->assertSee('class="tq-logo"', false)->assertDontSee('class="tq-logo tq-espera"', false);
});
