<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Filament\Desarrollo\BarraDesarrollo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Plan 1.4i: el modo desarrollo. D7, lo primero: fuera del equipo del desarrollador no hay forma de activarlo. Hacen
 * falta las tres llaves (APP_ENV=local, APP_DEBUG=true, TECHQUK_MODO_DESARROLLO=true); sin cualquiera de ellas, nada
 * se ve ni responde, aunque la sesión diga que está prendido.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

/** Las tres llaves puestas, como en el equipo del desarrollador. */
function conLasTresLlaves(): void
{
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
}

/** La sesión dice que el modo está prendido, sin pasar por `prender()`: así lo intentaría alguien que la manipula. */
function sesionConModoPrendido(): void
{
    session()->put('nucleo.modo_desarrollo.activo', true);
    session()->put('nucleo.modo_desarrollo.fecha', '2026-12-25T20:00');
}

dataset('sin una llave', [
    'en producción' => [fn () => app()->detectEnvironment(fn (): string => 'production')],
    'en pruebas' => [fn () => app()->detectEnvironment(fn (): string => 'testing')],
    'sin APP_DEBUG' => [fn () => config(['app.debug' => false])],
    'sin la variable' => [fn () => config(['nucleo.modo_desarrollo' => false])],
]);

test('con las tres llaves, el menú de la foto lo prende y lo apaga, y la barra sale al pie', function () {
    conLasTresLlaves();
    $usuario = User::factory()->create();

    test()->actingAs($usuario)->get('/')->assertSee('Modo desarrollo')->assertDontSee('tq-dev-titulo');

    Livewire::actingAs($usuario)->test(Topbar::class)->callAction('modoDesarrollo');
    expect(ModoDesarrollo::activo())->toBeTrue();
    test()->actingAs($usuario)->get('/')->assertSee('tq-dev-titulo', false)->assertSee('Vista previa');

    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('apagar');
    expect(ModoDesarrollo::activo())->toBeFalse();
});

test('sin una de las tres llaves no hay menú, ni barra, ni modo, aunque la sesión diga que está prendido', function (Closure $quitar) {
    conLasTresLlaves();
    $quitar();
    $usuario = User::factory()->create();
    sesionConModoPrendido();

    expect(ModoDesarrollo::disponible())->toBeFalse()
        ->and(ModoDesarrollo::activo())->toBeFalse()
        ->and(ModoDesarrollo::fechaSimulada())->toBeNull()
        ->and(ModoDesarrollo::ahoraDecorativo()->toDateString())->toBe(Calendario::hoy());

    test()->actingAs($usuario)->get('/')->assertOk()->assertDontSee('Modo desarrollo')->assertDontSee('tq-dev-titulo');
    expect(fn () => ModoDesarrollo::prender())->toThrow(NotFoundHttpException::class)
        ->and(fn () => ModoDesarrollo::simular(CarbonImmutable::now()))->toThrow(NotFoundHttpException::class)
        ->and(fn () => ModoDesarrollo::elegir('dibujo', 'x'))->toThrow(NotFoundHttpException::class);
    // Pedir el componente de la barra directamente, como por Livewire: no existe.
    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->assertStatus(404);

    // Llamar la acción oculta del menú directamente, como lo intentaría alguien: no prende nada.
    session()->forget('nucleo.modo_desarrollo');
    try {
        Livewire::actingAs($usuario)->test(Topbar::class)->callAction('modoDesarrollo');
    } catch (Throwable) {
        // Filament puede rechazarla antes; lo que importa es lo de abajo.
    }
    expect(ModoDesarrollo::activo())->toBeFalse()
        ->and(session()->has('nucleo.modo_desarrollo.activo'))->toBeFalse();
})->with('sin una llave');

test('si una llave falta a mitad de sesión, la barra ya abierta deja de responder', function () {
    conLasTresLlaves();
    $usuario = User::factory()->create();
    ModoDesarrollo::prender();
    $barra = Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->assertOk();

    config(['app.debug' => false]);

    $barra->set('fecha', '2026-12-25T20:00')->call('simular')->assertStatus(404);
    expect(ModoDesarrollo::fechaSimulada())->toBeNull();
});

test('solo «true» prende la variable: cualquier otro valor la deja apagada', function (string $valor, bool $prende) {
    putenv("TECHQUK_MODO_DESARROLLO={$valor}");
    $_ENV['TECHQUK_MODO_DESARROLLO'] = $valor;
    $_SERVER['TECHQUK_MODO_DESARROLLO'] = $valor;

    try {
        expect((require base_path('config/nucleo.php'))['modo_desarrollo'])->toBe($prende);
    } finally {
        putenv('TECHQUK_MODO_DESARROLLO');
        unset($_ENV['TECHQUK_MODO_DESARROLLO'], $_SERVER['TECHQUK_MODO_DESARROLLO']);
    }
})->with([
    'true' => ['true', true],
    'no' => ['no', false],
    'off' => ['off', false],
    '0' => ['0', false],
    '1' => ['1', false],
    'vacía' => ['', false],
]);

test('la variable puesta en producción se ignora y deja un aviso crítico, una sola vez', function () {
    Log::spy();
    conLasTresLlaves();
    app()->detectEnvironment(fn (): string => 'production');

    expect(ModoDesarrollo::disponible())->toBeFalse()
        ->and(ModoDesarrollo::disponible())->toBeFalse();
    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $mensaje): bool => str_contains($mensaje, 'TECHQUK_MODO_DESARROLLO'));
});

test('la fecha simulada cambia la hora de lo decorativo y no la del negocio', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 10:00', Calendario::ZONA));
    conLasTresLlaves();
    test()->actingAs(User::factory()->create());
    ModoDesarrollo::prender();

    ModoDesarrollo::simular(CarbonImmutable::create(2026, 12, 25, 20, 0, 0, Calendario::ZONA));

    expect(ModoDesarrollo::ahoraDecorativo()->format('Y-m-d H:i'))->toBe('2026-12-25 20:00')
        ->and(Calendario::hoy())->toBe('2026-10-23');

    ModoDesarrollo::simular(null);
    expect(ModoDesarrollo::ahoraDecorativo()->toDateString())->toBe(Calendario::hoy());
});

test('usar el modo no escribe nada en la base', function () {
    conLasTresLlaves();
    $usuario = User::factory()->create();
    $escrituras = 0;
    DB::listen(function ($consulta) use (&$escrituras): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $consulta->sql) === 1) {
            $escrituras++;
        }
    });

    Livewire::actingAs($usuario)->test(Topbar::class)->callAction('modoDesarrollo');
    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->set('fecha', '2026-12-25T20:00')->call('simular')->call('volverAHoy');
    ModoDesarrollo::elegir('dibujo', 'primavera-flor');

    expect($escrituras)->toBe(0);
});

test('con la fecha simulada, el inicio se ve como ese día: el dibujo y el logo de Navidad', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 10:00', Calendario::ZONA));
    conLasTresLlaves();
    $usuario = User::factory()->create();

    test()->actingAs($usuario)->get('/')->assertDontSee('data-ambientacion="navidad-', false)->assertDontSee('data-detalle="gorro"', false);

    ModoDesarrollo::prender();
    ModoDesarrollo::simular(CarbonImmutable::parse('2026-12-25 20:00', Calendario::ZONA));

    test()->actingAs($usuario)->get('/')
        ->assertSee('data-ambientacion="navidad-', false)
        ->assertSee('data-detalle="gorro"', false);
});

test('en modo desarrollo el gran momento no sale solo ni se marca: no se escribe nada', function () {
    // El 28 de diciembre de 2026 está pendiente el de Navidad.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-28 09:00', Calendario::ZONA));
    $usuario = User::factory()->create();
    conLasTresLlaves();
    ModoDesarrollo::prender();

    test()->actingAs($usuario)->get('/')->assertOk()->assertDontSee('data-tq-gran-momento', false);
    expect(DB::table('grandes_momentos')->count())->toBe(0)
        ->and(DB::table('momentos_vistos')->count())->toBe(0);

    // Sin el modo, ese mismo día sí sale.
    ModoDesarrollo::apagar();
    test()->actingAs($usuario)->get('/')->assertSee('data-tq-gran-momento', false);
});

test('un dibujo o un logo elegido en el sitio manda sobre la fecha, y «Quitar» vuelve a la de la fecha', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 10:00', Calendario::ZONA));
    conLasTresLlaves();
    $usuario = User::factory()->create();
    ModoDesarrollo::prender();

    // La página marca las piezas que se pueden cambiar con un clic.
    test()->actingAs($usuario)->get('/')
        ->assertSee('data-tq-pieza="dibujo"', false)
        ->assertSee('data-tq-pieza="logo"', false);
    // Solo el ícono del logo lleva la marca: el nombre TECHQUK sigue llevando al inicio.
    expect((string) test()->actingAs($usuario)->get('/')->getContent())->toMatch('/<svg\\s+data-tq-pieza="logo"/')
        ->not->toMatch('/class="tq-logo"[^>]*data-tq-pieza/');

    $barra = Livewire::actingAs($usuario)->test(BarraDesarrollo::class);
    $barra->call('abrir', 'dibujo')->assertSee('Abeja')->assertSee('Navidad')->set('opcion', 'navidad:arbol')->call('aplicar');
    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('abrir', 'logo')->set('opcion', 'fiestas_patrias')->call('aplicar');

    test()->actingAs($usuario)->get('/')
        ->assertSee('data-ambientacion="navidad-arbol"', false)
        ->assertSee('data-detalle="escudo"', false);

    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('abrir', 'dibujo')->call('quitar');
    test()->actingAs($usuario)->get('/')->assertDontSee('data-ambientacion="navidad-arbol"', false);
});

test('solo se guarda una variante que la pieza ofrece; el gran momento abre la vista previa', function () {
    conLasTresLlaves();
    $usuario = User::factory()->create();
    ModoDesarrollo::prender();

    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('abrir', 'dibujo')->set('opcion', 'inventado:nada')->call('aplicar');
    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('abrir', 'no-existe')->assertSet('pieza', null);
    expect(ModoDesarrollo::eleccion('dibujo'))->toBeNull();

    Livewire::actingAs($usuario)->test(BarraDesarrollo::class)->call('abrir', 'gran-momento')
        ->set('opcion', 'navidad:navidad-establo')->call('aplicar')
        ->assertRedirect('/vista-previa-fechas?ocasion=navidad&variante=navidad-establo');
    expect(ModoDesarrollo::eleccion('gran-momento'))->toBeNull();
});

test('sin el modo, la página no marca piezas ni cambia nada aunque la sesión tenga una elección', function () {
    $usuario = User::factory()->create();
    session()->put('nucleo.modo_desarrollo.piezas.dibujo', 'navidad:arbol');

    test()->actingAs($usuario)->get('/')
        ->assertDontSee('data-tq-pieza', false)
        ->assertDontSee('data-ambientacion="navidad-arbol"', false);
});
