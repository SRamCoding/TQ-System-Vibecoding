<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\ClimaDelEntorno;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Navegacion\FaseLunar;
use App\Modules\Nucleo\Navegacion\LuzDelDia;
use App\Modules\Nucleo\Navegacion\Oklch;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;

/*
 * Plan 1.4ac: la luz de la barra y el tinte del fondo por la hora, la temperatura y el cielo, con los números de los
 * simuladores que aprobó el desarrollador (claro y oscuro). Personas inventadas.
 */

// --- Las fases y el calor ---

it('la fase del día sale de la hora, como en el simulador', function (string $hora, string $fase) {
    expect(LuzDelDia::fase(CarbonImmutable::parse("2026-11-16 {$hora}", 'America/Lima')))->toBe($fase);
})->with([
    ['05:29', LuzDelDia::NOCHE], ['05:30', LuzDelDia::AMANECER], ['06:59', LuzDelDia::AMANECER], ['07:00', LuzDelDia::DIA],
    ['17:29', LuzDelDia::DIA], ['17:30', LuzDelDia::ATARDECER], ['19:29', LuzDelDia::ATARDECER], ['19:30', LuzDelDia::NOCHE],
]);

it('el calor va de 10 °C a 34 °C; el clima del navegador se resume en tres cielos', function () {
    expect(LuzDelDia::calor(10))->toBe(0.0)->and(LuzDelDia::calor(22))->toBe(0.5)->and(LuzDelDia::calor(34))->toBe(1.0)
        ->and(LuzDelDia::calor(-3))->toBe(0.0)->and(LuzDelDia::calor(40))->toBe(1.0)
        ->and(LuzDelDia::cielo('despejado'))->toBe(LuzDelDia::DESPEJADO)->and(LuzDelDia::cielo(null))->toBe(LuzDelDia::DESPEJADO)
        ->and(LuzDelDia::cielo('niebla'))->toBe(LuzDelDia::NUBES)->and(LuzDelDia::cielo('tormenta'))->toBe(LuzDelDia::LLUVIA)
        // Los grados de la temperatura forzada en el modo desarrollo dan los extremos y el medio.
        ->and(array_map(LuzDelDia::calor(...), ClimaDelEntorno::GRADOS_FORZADOS))->toBe(['frio' => 0.0, 'templado' => 0.5, 'calor' => 1.0]);
});

// --- Las escenas del simulador ---

it('mediodía caluroso en claro: fondo arena, sol dorado e intenso junto al buscador', function () {
    expect(LuzDelDia::escena(LuzDelDia::DIA, 1.0, LuzDelDia::DESPEJADO, 'claro'))->toBe([
        'fondo' => [250, 246, 236], 'barra' => [252, 249, 243], 'luz' => [255, 210, 80], 'intensidad' => 0.75,
        'centro' => 'calc(100% - 220px)', 'estrellas' => 0,
    ]);
});

it('amanecer frío en claro: fondo azulado, luz naranja baja al 70 %', function () {
    expect(LuzDelDia::escena(LuzDelDia::AMANECER, 0.0, LuzDelDia::DESPEJADO, 'claro'))->toBe([
        'fondo' => [240, 244, 248], 'barra' => [245, 248, 250], 'luz' => [255, 170, 110], 'intensidad' => 0.45,
        'centro' => 'calc(70% + 110px)', 'estrellas' => 0,
    ]);
});

it('templado y sin clima: el fondo tibio del medio', function () {
    expect(LuzDelDia::escena(LuzDelDia::DIA, LuzDelDia::CALOR_SIN_CLIMA, LuzDelDia::DESPEJADO, 'claro'))
        ->fondo->toBe([248, 247, 242])->luz->toBe([255, 223, 115])->intensidad->toBe(0.55);
});

it('atardecer nublado: coral agrisado a la mitad, pegado al borde', function () {
    expect(LuzDelDia::escena(LuzDelDia::ATARDECER, 0.5, LuzDelDia::NUBES, 'claro'))
        ->luz->toBe([231, 193, 188])->intensidad->toBe(0.3)->centro->toBe('calc(100% - 150px)');
});

it('con lluvia casi no hay astro y, en claro, la barra se agrisa', function () {
    $escena = LuzDelDia::escena(LuzDelDia::DIA, 0.5, LuzDelDia::LLUVIA, 'claro');

    expect($escena['intensidad'])->toBe(0.1925)->and($escena['luz'])->toBe([208, 208, 200])->and($escena['barra'])->toBe([241, 243, 245]);
});

it('de noche en claro: fondo más frío, luna plateada y 7 estrellas con cielo despejado', function () {
    expect(LuzDelDia::escena(LuzDelDia::NOCHE, 0.5, LuzDelDia::DESPEJADO, 'claro'))->toBe([
        'fondo' => [241, 242, 245], 'barra' => [233, 236, 244], 'luz' => [200, 212, 255], 'intensidad' => 0.55,
        'centro' => 'calc(100% - 220px)', 'estrellas' => 7,
    ])->and(LuzDelDia::escena(LuzDelDia::NOCHE, 0.5, LuzDelDia::NUBES, 'claro'))->intensidad->toBe(0.18)->estrellas->toBe(0);
});

it('en oscuro: el sol a 0,55, más dorado; de noche, azul noche, luna 0,42 y 9 estrellas', function () {
    expect(LuzDelDia::escena(LuzDelDia::DIA, 1.0, LuzDelDia::DESPEJADO, 'oscuro'))->toBe([
        'fondo' => [47, 42, 36], 'barra' => [57, 56, 55], 'luz' => [255, 200, 70], 'intensidad' => 0.4125,
        'centro' => 'calc(100% - 220px)', 'estrellas' => 0,
    ])->and(LuzDelDia::escena(LuzDelDia::NOCHE, 0.0, LuzDelDia::DESPEJADO, 'oscuro'))->toBe([
        'fondo' => [32, 36, 47], 'barra' => [45, 50, 63], 'luz' => [200, 212, 255], 'intensidad' => 0.42,
        'centro' => 'calc(100% - 220px)', 'estrellas' => 9,
    ]);
});

// --- La luna (D4) ---

it('la fase de la luna sale de la fecha: llena, nueva, creciente y menguante', function () {
    $llena = FaseLunar::de(CarbonImmutable::parse('2026-11-24 15:00', 'UTC'));
    $nueva = FaseLunar::de(CarbonImmutable::parse('2026-11-09 07:00', 'UTC'));
    $creciente = FaseLunar::de(CarbonImmutable::parse('2026-11-14 12:00', 'UTC'));
    $menguante = FaseLunar::de(CarbonImmutable::parse('2026-12-02 12:00', 'UTC'));

    expect($llena->iluminada)->toBeGreaterThan(0.98)->and($llena->esLlena())->toBeTrue()
        ->and($nueva->iluminada)->toBeLessThan(0.02)
        ->and($creciente->creciente)->toBeTrue()->and($creciente->iluminada)->toBeBetween(0.1, 0.6)
        ->and($menguante->creciente)->toBeFalse()->and($menguante->iluminada)->toBeBetween(0.4, 0.95);
});

it('la luna nueva casi no alumbra; la llena, entera; fuera de la llena, una sombra deja el borde iluminado', function () {
    $nueva = FaseLunar::de(CarbonImmutable::parse('2026-11-09 07:00', 'UTC'));
    $llena = FaseLunar::de(CarbonImmutable::parse('2026-11-24 15:00', 'UTC'));

    expect(LuzDelDia::escena(LuzDelDia::NOCHE, 0.5, LuzDelDia::DESPEJADO, 'claro', $nueva)['intensidad'])->toBeLessThan(0.1)
        ->and(LuzDelDia::escena(LuzDelDia::NOCHE, 0.5, LuzDelDia::DESPEJADO, 'claro', $llena)['intensidad'])->toBeGreaterThan(0.54)
        ->and(LuzDelDia::css(LuzDelDia::NOCHE, $nueva))->toContain('radial-gradient(circle 75px at calc(100% - 220px')
        ->and(LuzDelDia::css(LuzDelDia::NOCHE, $llena))->not->toContain('circle 75px');
});

// --- El contraste (paso 4) ---

it('el texto principal se lee en todos los extremos y el gris secundario no queda peor que en la barra de hoy', function () {
    $luminancia = function (array $c): float {
        $canal = fn (int $v): float => ($v /= 255) <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $canal($c[0]) + 0.7152 * $canal($c[1]) + 0.0722 * $canal($c[2]);
    };
    // Texto de Filament: el 950 y el 500 en claro; blanco y el 400 en oscuro.
    $texto = ['claro' => [Oklch::luminancia(0.141, 0.005, 285.8), Oklch::luminancia(0.552, 0.016, 285.9)], 'oscuro' => [1.0, Oklch::luminancia(0.705, 0.015, 286.1)]];

    foreach (['claro', 'oscuro'] as $tema) {
        foreach ([LuzDelDia::NOCHE, LuzDelDia::AMANECER, LuzDelDia::DIA, LuzDelDia::ATARDECER] as $fase) {
            foreach ([0.0, 1.0] as $calor) {
                foreach ([LuzDelDia::DESPEJADO, LuzDelDia::LLUVIA] as $cielo) {
                    $escena = LuzDelDia::escena($fase, $calor, $cielo, $tema);

                    foreach (['fondo', 'barra'] as $parte) {
                        $donde = "{$tema} {$fase} {$calor} {$cielo} {$parte}";
                        expect(Oklch::contraste($texto[$tema][0], $luminancia($escena[$parte])))->toBeGreaterThan(7.0, $donde)
                            // La barra perla de hoy deja el gris en 4,02:1; el tinte aprobado no baja de ahí.
                            ->and(Oklch::contraste($texto[$tema][1], $luminancia($escena[$parte])))->toBeGreaterThanOrEqual(4.0, $donde);
                    }
                }
            }
        }
    }
});

// --- La página ---

function paginaConLuz(string $momento, ?User $usuario = null): string
{
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse($momento, 'America/Lima'));

    return (string) test()->actingAs($usuario ?? User::factory()->create())->get('/')->assertOk()->getContent();
}

it('de día, todas las pantallas traen el tinte por temperatura y el halo junto al buscador', function () {
    $pagina = paginaConLuz('2026-11-16 11:00');

    expect($pagina)
        ->toContain('html { --tq-calor: 0.5; --tq-f1: clamp(0, var(--tq-calor) * 2, 1);')
        ->toContain('color-mix(in srgb, color-mix(in srgb, rgb(240 244 248), rgb(248 247 242) calc(var(--tq-f1) * 100%)), rgb(250 246 236) calc(var(--tq-f2) * 100%))')
        ->toContain('color-mix(in srgb, color-mix(in srgb, rgb(34 39 48), rgb(40 41 44) calc(var(--tq-f1) * 100%)), rgb(47 42 36) calc(var(--tq-f2) * 100%))')
        ->toContain('html .fi-body { background-color: var(--tq-luz-fondo); }')
        ->toContain('radial-gradient(circle 155.56px at calc(100% - 220px) 0,')
        ->toContain('--tq-astro: color-mix(in srgb, rgb(255 236 150), rgb(255 210 80) calc(var(--tq-calor) * 100%))')
        ->not->toContain('--tq-luz-estrellas: radial-gradient')
        // El script del clima pone el calor con los grados.
        ->toContain("html.style.setProperty('--tq-calor'");
});

it('de noche: luna, estrellas con cielo despejado y fondo más frío', function () {
    expect(paginaConLuz('2026-11-24 22:00'))
        ->toContain('--tq-astro: rgb(200 212 255)')
        ->toContain('--tq-luz-estrellas: radial-gradient(circle 2px at 8% 12%')
        ->toContain('color-mix(in srgb, var(--tq-luz-fondo-base), rgb(236 239 247) 60%)');
});

it('con la apariencia según el día apagada, la barra y el fondo como siempre', function () {
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);

    expect(LuzDelDia::para($usuario))->toBeNull()
        ->and(paginaConLuz('2026-11-16 11:00', $usuario))->not->toContain('--tq-luz-fondo')->not->toContain('--tq-calor: 0.5');
});
