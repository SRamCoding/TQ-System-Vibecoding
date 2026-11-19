<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\ClimaDelDibujo;
use App\Modules\Nucleo\Desarrollo\Piezas\TemperaturaDelClima;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\ClimaDelEntorno;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;

/*
 * El clima del lugar de la computadora en todas las pantallas (plan 1.4u): el navegador lo consulta y lo pone en <html>;
 * aquí, lo que el servidor le da (respaldo, umbrales, noche, lo forzado) y que nada del clima vuelva al servidor.
 * Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function paginaConClima(string $ruta, string $hora = '2026-11-05 10:00'): string
{
    test()->travelTo(CarbonImmutable::parse($hora, 'America/Lima'));

    return (string) test()->actingAs(User::factory()->create())->get($ruta)->assertOk()->getContent();
}

it('todas las pantallas consultan el clima desde el navegador, con la posición a un decimal y la temperatura', function () {
    foreach (['/', '/mis-preferencias'] as $ruta) {
        expect(paginaConClima($ruta))
            ->toContain('https://api.open-meteo.com/v1/forecast?latitude=')
            ->toContain('toFixed(1)')
            ->toContain('&current=weather_code,temperature_2m')
            ->toContain('html.dataset.tqClima = clima')
            ->toContain('html.dataset.tqTemperatura = temp')
            ->toContain('localStorage.setItem(CLAVE, JSON.stringify({ c, g, t: Date.now() }))');
    }
});

it('el servidor solo da el respaldo, los umbrales y lo forzado; nunca recibe el clima', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00', 'America/Lima'));
    $datos = ClimaDelEntorno::datos(User::factory()->create());

    expect($datos)->toBe([
        'respaldo' => '-6.77,-79.84',
        'forzado' => null,
        'temperatura' => null,
        'grados' => ['frio' => 10, 'templado' => 22, 'calor' => 34],
        'calor' => 30,
        'frio' => 15,
        'minutos' => 30,
    ]);

    // El clima nunca va al servidor: ninguna ruta lo recibe.
    expect(collect(app('router')->getRoutes()->getRoutes())->map(fn ($ruta): string => $ruta->uri())->filter(fn (string $uri): bool => str_contains($uri, 'clima')))->toBeEmpty();
});

it('con el clima apagado, ninguna consulta ni datos', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.clima_activo', 'false', 1);

    expect(ClimaDelEntorno::datos(User::factory()->create()))->toBeNull()
        ->and(paginaConClima('/'))->not->toContain('api.open-meteo.com');
});

it('en el modo desarrollo, el clima y la temperatura elegidos valen para todo; un valor que no existe, no', function () {
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
    $usuario = User::factory()->create();
    $this->actingAs($usuario);
    ModoDesarrollo::prender();
    ModoDesarrollo::elegir(ClimaDelDibujo::CLAVE, 'lluvia');
    ModoDesarrollo::elegir(TemperaturaDelClima::CLAVE, 'frio');

    expect(ClimaDelEntorno::datos($usuario))->toMatchArray(['forzado' => 'lluvia', 'temperatura' => 'frio']);

    ModoDesarrollo::elegir(ClimaDelDibujo::CLAVE, 'granizo');
    expect(ClimaDelEntorno::datos($usuario))->toMatchArray(['forzado' => null, 'temperatura' => 'frio']);
});

// --- Los efectos (D2, D3) ---

it('con la estación, la perilla del sol: entera de día, más con calor, nada con nubes, niebla, lluvia o de noche', function () {
    $pagina = paginaConClima('/', '2027-02-01 10:00');

    expect($pagina)->toContain("@property --tq-sol { syntax: '<number>'; inherits: true; initial-value: 1; }")
        ->toContain('html { --tq-sol: 1; transition: --tq-sol 1.2s ease; }')
        ->toContain('html[data-tq-temperatura="calor"] { --tq-sol: 1.35; }')
        ->toContain('html:is([data-tq-noche], [data-tq-clima="nublado"], [data-tq-clima="niebla"], [data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) { --tq-sol: 0; }')
        // Los reflejos de las cajas pasan por la perilla (la barra tiene su luz propia, plan 1.4ac).
        ->toContain('rgb(255 255 255 / calc(0.95 * var(--tq-sol)))')
        ->not->toContain("document.documentElement.dataset.tqNoche = ''");

    expect(paginaConClima('/', '2027-02-01 21:00'))->toContain("<script>document.documentElement.dataset.tqNoche = ''</script>");
});

it('con lluvia, gotas quietas en la barra; con niebla, bruma; con frío, escarcha en las cajas', function () {
    expect(paginaConClima('/', '2027-05-05 10:00'))
        ->toContain('html:is([data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) .fi-topbar::after { background-image: radial-gradient(')
        ->toContain('html[data-tq-clima="llovizna"] .fi-topbar::after {')
        ->toContain('html[data-tq-clima="niebla"] .fi-topbar::after { background-image: linear-gradient(')
        ->toContain('html[data-tq-temperatura="frio"] .tq-inicio-cajas[data-tq-estacion]:not([data-tq-estacion="invierno"]) .tq-caja-hielo { display: block;');
});

it('sin la apariencia según la estación, el clima no toca la barra ni hay noche', function () {
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);
    $this->travelTo(CarbonImmutable::parse('2027-02-01 21:00', 'America/Lima'));
    $pagina = (string) $this->actingAs($usuario)->get('/')->assertOk()->getContent();

    expect($pagina)->not->toContain('@property --tq-sol')->not->toContain('.fi-topbar::after')->not->toContain('dataset.tqNoche');
});
