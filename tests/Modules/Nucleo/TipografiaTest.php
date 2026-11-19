<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;

/*
 * Plan 1.4ab: la escala de letra del prototipo de Proyectos en todas las pantallas y la línea fina bajo la barra
 * superior. Plan 1.4ae: el nombre del logo vuelve a Audiowide, a la altura del ícono achatado. Cómo se ve lo revisa el desarrollador en pantalla; aquí, lo que la
 * página trae.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function inicioDeTipografia(): string
{
    return (string) test()->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();
}

test('todas las pantallas traen la escala de letra y la línea fina bajo la barra superior', function () {
    expect(inicioDeTipografia())
        ->toContain('html .fi-header-heading { font-size: 1.5rem;')
        ->toContain('html .fi-sidebar-group-label {')
        ->toContain('font-variant-numeric: tabular-nums;')
        ->toContain('html .fi-topbar { box-shadow: 0 0.5px 0 0');
});

test('el nombre del logo va en Audiowide, servida desde public/fonts, con su propio alto, más chico que el ícono achatado (planes 1.4ae y 1.4af)', function () {
    expect(inicioDeTipografia())
        ->toContain("@font-face { font-family: 'Audiowide';")
        ->toContain("font-family: 'Audiowide', ui-sans-serif, sans-serif;")
        ->toContain('--tq-logo-alto: 1.5rem; --tq-logo-nombre-alto: 1rem;')
        ->toContain('font-size: calc(var(--tq-logo-nombre-alto) / 0.7002);')
        ->toContain('width: calc(var(--tq-logo-alto) * 122 / 119 / 0.85);')
        ->toContain('preserveAspectRatio="none"')
        ->toContain('<span class="tq-logo-nombre"><span>TechQuk</span></span>');

    expect(public_path('fonts/audiowide/audiowide-latin.woff2'))->toBeFile()
        ->and(public_path('fonts/audiowide/OFL.txt'))->toBeFile();
});
