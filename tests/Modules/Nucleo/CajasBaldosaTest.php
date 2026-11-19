<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;

/*
 * Plan 1.4ad (imagen del desarrollador, 2026-11-16): el ícono de cada caja del inicio va en una baldosa con el color de
 * su área, con brillo y sombra, y el ícono en blanco; Proyectos estrena su ícono de lista con cohete. Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

test('todas las cajas llevan el ícono en una baldosa del color de su área, también con las estaciones', function () {
    $pagina = (string) test()->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

    expect($pagina)
        ->toContain('.tq-caja-modulo .tq-caja-modulo-marco { display: flex;')
        ->toContain('linear-gradient(150deg, color-mix(in oklab, var(--tq-area-color) 55%, white) 0%, var(--tq-area-color) 62%')
        ->toContain('.tq-caja-modulo .tq-caja-modulo-marco .tq-caja-modulo-icono { width: 1.75rem !important; height: 1.75rem !important; color: white !important;')
        ->toContain('<span class="tq-caja-modulo-marco">')
        // La baldosa va después de los estilos de la estación, para ganarles.
        ->and(strpos($pagina, 'Plan 1.4ad'))->toBeGreaterThan(strpos($pagina, 'Oscuro fosforescente'));
});

test('Proyectos usa su ícono propio de lista con cohete', function () {
    expect(ProyectosServiceProvider::ICONO)->toBe('tq-proyecto-cohete')
        ->and(svg('tq-proyecto-cohete')->toHtml())->toContain('M22 2c-2.6.1-4.9 1.3-6.4 3.4');
});
