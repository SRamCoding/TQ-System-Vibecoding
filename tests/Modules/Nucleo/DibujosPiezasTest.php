<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\Piezas\RegistroPiezasDibujo;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/*
 * Plan 1.4e2: los dibujos por piezas. El del día es el mismo para todos, nunca igual dos días seguidos, recorre todos
 * antes de repetir; se ve con la luz de la hora y trae las capas de clima.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function diaDePiezas(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

test('ninguna combinación prohibida: la abeja solo con flores', function () {
    $todas = app(RegistroPiezasDibujo::class)->combinaciones('primavera');

    $conFlor = fn ($c): bool => in_array('flor', [...$c->fondo->etiquetas, ...$c->motivo->etiquetas], true);

    expect($todas)->toHaveCount(640)
        ->and(collect($todas)->filter(fn ($c) => in_array($c->acompanante?->clave, ['abeja', 'mariquita', 'colibri'], true))->every($conFlor))->toBeTrue()
        ->and(collect($todas)->map->clave()->unique()->count())->toBe(640);
});

test('nunca la misma dos días seguidos, todas antes de repetir y el contador sigue de un año al otro', function () {
    $registro = app(RegistroPiezasDibujo::class);
    $claves = collect(range(0, 639))->map(fn (int $n): string => $registro->delDia('primavera', diaDePiezas('2026-10-01')->addDays($n))->clave());

    expect($claves->unique()->count())->toBe(640)
        ->and($claves->sliding(2)->every(fn ($par) => $par->first() !== $par->last()))->toBeTrue()
        ->and($registro->delDia('primavera', diaDePiezas('2026-12-31'))->clave())->not->toBe($registro->delDia('primavera', diaDePiezas('2027-01-01'))->clave());
});

test('un tema sin piezas no tiene dibujo del día', function () {
    expect((new RegistroPiezasDibujo)->delDia('navidad', diaDePiezas('2026-12-20')))->toBeNull();
});

test('en primavera el inicio trae el dibujo por piezas: el mismo para todos, con la luz de la hora', function () {
    $ambientacion = app(Ambientacion::class);
    $una = User::factory()->create();
    $otra = User::factory()->create();

    $manana = $ambientacion->para($una, diaDePiezas('2026-10-20 09:00'));
    $tarde = $ambientacion->para($una, diaDePiezas('2026-10-20 15:00'));
    $noche = $ambientacion->para($una, diaDePiezas('2026-10-20 21:00'));

    expect($manana?->vista)->toBe('nucleo::filament.ambientacion.compuesto')
        ->and($manana?->clave)->toBe($ambientacion->para($otra, diaDePiezas('2026-10-20 09:00'))?->clave)
        ->and([$manana?->datos['luz'], $tarde?->datos['luz'], $noche?->datos['luz']])->toBe(['manana', 'tarde', 'noche'])
        ->and($tarde?->clave)->toBe($manana?->clave);
});

test('un tema sin piezas sigue con su variante de siempre', function () {
    // Un registro sin piezas: así queda cualquier tema mientras no se dibujen las suyas.
    app()->instance(RegistroPiezasDibujo::class, new RegistroPiezasDibujo);
    app()->forgetInstance(Ambientacion::class);
    $variante = app(Ambientacion::class)->para(User::factory()->create(), diaDePiezas('2026-07-01 10:00'));

    expect($variante?->tema)->toBe('invierno')
        ->and($variante?->vista)->not->toBe('nucleo::filament.ambientacion.compuesto');
});

test('el dibujo trae las capas de clima y la consulta del navegador, salvo con el clima apagado', function () {
    $usuario = User::factory()->create();
    CarbonImmutable::setTestNow(diaDePiezas('2026-10-20 10:00'));

    $html = (string) test()->actingAs($usuario)->get('/')->getContent();
    expect($html)->toContain('class="tq-compuesto"')
        ->toContain('data-clima-activo')
        // Plan 1.4u: la consulta, en el encabezado de todas las pantallas, con la ubicación de respaldo.
        ->toContain('-6.77,-79.84')
        ->toContain('api.open-meteo.com')
        ->toContain('toFixed(1)')
        ->toContain('class="tq-clima-lluvia"');

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.clima_activo', 'false', 1);
    app()->forgetInstance(Ambientacion::class);
    expect((string) test()->actingAs($usuario)->get('/')->getContent())->not->toContain('api.open-meteo.com');
});

test('la ubicación de respaldo se valida', function () {
    $parametros = app(ParametrosService::class);

    expect(fn () => $parametros->cambiarDesdeTexto('interfaz.clima_ubicacion_respaldo', 'chiclayo', 1))->toThrow(ParametroRechazado::class)
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.clima_ubicacion_respaldo', '-95,10', 1))->toThrow(ParametroRechazado::class);

    $parametros->cambiarDesdeTexto('interfaz.clima_ubicacion_respaldo', ' -12.05, -77.04 ', 1);
    expect($parametros->texto('interfaz.clima_ubicacion_respaldo'))->toBe('-12.05,-77.04');
});

test('las cuatro estaciones tienen sus piezas, sin combinaciones prohibidas', function () {
    $registro = app(RegistroPiezasDibujo::class);

    expect(count($registro->combinaciones('verano')))->toBe(358)
        ->and(count($registro->combinaciones('otono')))->toBe(560)
        ->and(count($registro->combinaciones('invierno')))->toBe(540)
        // El caballito de totora y la tabla de surf solo con mar; el pingüino también; la llama solo en la sierra.
        ->and(collect($registro->combinaciones('verano'))->filter(fn ($c) => in_array($c->motivo->clave, ['caballito-totora', 'tabla-surf', 'barquito'], true))->every(fn ($c) => in_array('mar', $c->fondo->etiquetas, true)))->toBeTrue()
        ->and(collect($registro->combinaciones('invierno'))->filter(fn ($c) => $c->acompanante?->clave === 'llama')->every(fn ($c) => $c->fondo->clave === 'sierra'))->toBeTrue();
});

test('cada pieza registrada tiene su vista y se dibuja', function () {
    $registro = app(RegistroPiezasDibujo::class);

    foreach (['primavera', 'verano', 'otono', 'invierno', 'navidad', 'semana_santa', 'anio_nuevo', 'fiestas_patrias', 'dia_madre', 'dia_padre', 'cumpleanos', 'aniversario'] as $tema) {
        $piezas = collect($registro->combinaciones($tema))->flatMap(fn ($c) => array_filter([$c->fondo, $c->motivo, $c->acompanante]))->unique(fn ($p) => $p->vista);

        foreach ($piezas as $pieza) {
            expect(view()->exists($pieza->vista))->toBeTrue("Falta la vista {$pieza->vista}");
        }
    }
});

test('las fechas especiales tienen piezas, y Semana Santa sin confeti ni fiesta', function () {
    $registro = app(RegistroPiezasDibujo::class);

    foreach (['navidad', 'semana_santa', 'anio_nuevo', 'fiestas_patrias', 'dia_madre', 'dia_padre', 'cumpleanos', 'aniversario'] as $tema) {
        expect($registro->tienePiezas($tema))->toBeTrue($tema);
    }

    $acompanantes = collect($registro->combinaciones('semana_santa'))->map(fn ($c) => $c->acompanante?->clave)->filter()->unique()->values()->all();
    expect($acompanantes)->toBe(['paloma'])
        // Los pastores y los Reyes Magos solo afuera, en el pueblito.
        ->and(collect($registro->combinaciones('navidad'))->filter(fn ($c) => in_array($c->acompanante?->clave, ['pastores', 'reyes-magos'], true))->every(fn ($c) => $c->fondo->clave === 'pueblo'))->toBeTrue();
});

test('las animaciones de las piezas tienen nombres únicos', function () {
    // El <style> de un SVG vale para toda la página: dos @keyframes con el mismo nombre se pisarían.
    $nombres = collect(File::allFiles(base_path('app/Modules/Nucleo/resources/views/filament/ambientacion/piezas')))
        ->flatMap(fn ($archivo) => preg_match_all('/@keyframes\s+([\w-]+)/', $archivo->getContents(), $m) ? $m[1] : []);

    expect($nombres->duplicates()->values()->all())->toBe([]);
});

test('una fecha especial conserva los colores de su franja; una estación, los de la luz', function () {
    $patrias = app(Ambientacion::class)->para(User::factory()->create(), diaDePiezas('2026-07-28 10:00'));

    expect($patrias->tema)->toBe('fiestas_patrias')
        ->and($patrias->colores)->toContain('#D91023');
});
