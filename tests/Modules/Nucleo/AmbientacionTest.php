<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\Piezas\RegistroPiezasDibujo;
use App\Modules\Nucleo\Saludo\RegistroAmbientaciones;
use App\Modules\Nucleo\Saludo\VarianteAmbientacion;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

/*
 * Plan 1.4b, paso 6: qué ambientación toca (D5, D8), que cambia cada año, que se apaga, y que la página la dibuja: el
 * saludo en vez de «Inicio», la franja y el ícono. Cómo se ve lo revisa el desarrollador en pantalla.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function enLima(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

function personaAmbientada(): User
{
    return User::factory()->create(['party_id' => Party::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Soto'])->id]);
}

test('cada año toca otra variante, la misma para todos', function () {
    $registro = app(RegistroAmbientaciones::class);
    $cinco = collect(range(2026, 2030))->map(fn (int $anio): string => (string) $registro->delAnio('primavera', $anio)?->clave);

    expect($cinco->unique()->count())->toBe(5)
        ->and($registro->delAnio('primavera', 2031)?->clave)->toBe($cinco[0])
        ->and($registro->delAnio('un-tema-sin-dibujos', 2026))->toBeNull();
});

test('en primavera y sin fecha especial toca una variante de primavera', function () {
    expect(app(Ambientacion::class)->para(personaAmbientada(), enLima('2026-10-20 09:00'))?->tema)->toBe('primavera');
});

test('la fecha especial gana a la temporada', function () {
    $persona = personaAmbientada()->forceFill(['cumpleanos_dia' => 20, 'cumpleanos_mes' => 10]);

    expect(app(Ambientacion::class)->para($persona, enLima('2026-10-20 09:00'))?->tema)->toBe('cumpleanos')
        ->and(app(Ambientacion::class)->para(personaAmbientada(), enLima('2026-12-20 09:00'))?->tema)->toBe('navidad');
});

test('Año Nuevo y verano no cambian de variante al pasar de diciembre a enero', function () {
    // La variante por año: la de un tema sin piezas (con piezas, el dibujo cambia cada día, plan 1.4e2).
    app()->instance(RegistroPiezasDibujo::class, new RegistroPiezasDibujo);
    app()->forgetInstance(Ambientacion::class);
    $registro = app(RegistroAmbientaciones::class);
    foreach (['a', 'b', 'c'] as $clave) {
        $registro->registrar(new VarianteAmbientacion('anio_nuevo', $clave, $clave, ['#F6D365'], 'nucleo::filament.ambientacion.primavera-flor'));
    }
    $persona = personaAmbientada();

    expect(app(Ambientacion::class)->para($persona, enLima('2026-12-27 09:00'))?->clave)
        ->toBe(app(Ambientacion::class)->para($persona, enLima('2027-01-02 09:00'))?->clave);
});

test('se apaga para toda la empresa o para la persona', function () {
    $persona = personaAmbientada();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'apariencia.ambientacion', false);

    expect(app(Ambientacion::class)->para($persona, enLima('2026-10-20 09:00')))->toBeNull()
        ->and(app(Ambientacion::class)->para(personaAmbientada(), enLima('2026-10-20 09:00')))->not->toBeNull();

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.ambientacion_activa', 'no', 1);
    app()->forgetScopedInstances();

    expect(app(Ambientacion::class)->para(personaAmbientada(), enLima('2026-10-20 09:00')))->toBeNull();
});

test('cada fecha especial y cada temporada tiene cinco variantes, y cada una se dibuja', function () {
    $temas = [...array_map(fn (Ocasion $o): string => $o->value, Ocasion::cases()), ...array_map(fn (Temporada $t): string => $t->value, Temporada::cases())];

    foreach ($temas as $tema) {
        $variantes = app(RegistroAmbientaciones::class)->de($tema);

        expect($variantes)->toHaveCount(5)
            ->and(array_unique(array_map(fn (VarianteAmbientacion $v): string => $v->clave, $variantes)))->toHaveCount(5);

        foreach (app(RegistroAmbientaciones::class)->de($tema) as $variante) {
            $html = view('nucleo::filament.ambientacion.dibujo', ['variante' => $variante, 'animado' => true, 'tamano' => 56])->render();

            expect($html)->toContain('<svg', 'tq-corre', "data-ambientacion=\"{$tema}-{$variante->clave}\"");
        }
    }
});

test('el inicio saluda en vez de decir «Inicio» y dibuja la franja, el ícono y la animación', function () {
    test()->travelTo(enLima('2026-10-20 09:00'));
    test()->actingAs(personaAmbientada());

    $respuesta = test()->get('/')->assertOk();

    expect(preg_match('#<title>\\s*Inicio#', (string) $respuesta->getContent()))->toBe(1);

    $respuesta->assertSee('class="tq-saludo"', false)
        ->assertSee('Ana')
        ->assertSee('data-franja="primavera-', false)
        ->assertSee('data-ambientacion="primavera-', false)
        ->assertSee('prefers-reduced-motion: reduce', false)
        ->assertSee('x-on:mouseenter', false);
});

test('con la ambientación apagada, el saludo queda y los dibujos no', function () {
    test()->travelTo(enLima('2026-10-20 09:00'));
    $persona = personaAmbientada();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'apariencia.ambientacion', false);
    test()->actingAs($persona);

    test()->get('/')->assertOk()
        ->assertSee('Ana')
        ->assertDontSee('data-franja=', false)
        ->assertDontSee('data-ambientacion=', false);
});

test('si el saludo o la ambientación fallan, la pantalla sale igual, sin adornos', function () {
    Parametro::query()->where('grupo', 'interfaz')->delete();
    app()->forgetScopedInstances();
    test()->actingAs(personaAmbientada());

    test()->get('/')->assertOk()
        ->assertDontSee('data-franja=', false)
        ->assertDontSee('data-ambientacion=', false);
});
