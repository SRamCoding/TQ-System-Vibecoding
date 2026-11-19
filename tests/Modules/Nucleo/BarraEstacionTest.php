<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AdornosDeEstacion;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;

/*
 * La barra superior según la estación (plan 1.4t): en otoño caen hojas cada tanto, dentro de la barra y detrás de lo que tiene, nunca con «reducir movimiento». Personas inventadas.
 */

function barraEnLaFecha(string $fecha, ?User $usuario = null): string
{
    test()->travelTo(CarbonImmutable::parse($fecha.' 10:00', 'America/Lima'));

    return (string) test()->actingAs($usuario ?? User::factory()->create())->get('/')->assertOk()->getContent();
}

it('el destello de primavera y verano ya no está: la barra lleva la luz del día (plan 1.4ac)', function () {
    foreach ([barraEnLaFecha('2026-11-05'), barraEnLaFecha('2027-02-01')] as $pagina) {
        expect($pagina)->not->toContain('at 32% 0%')->not->toContain('at 30% 0%')->not->toContain('tq-barra-hojas')
            ->toContain('html .fi-topbar { background-image: var(--tq-luz-estrellas');
    }
});

it('en otoño caen hojas cada 45 segundos dentro de la barra, detrás de su contenido y nunca con el movimiento reducido', function () {
    $otono = barraEnLaFecha('2027-05-05');

    expect($otono)->toContain('<span class="tq-barra-hojas" aria-hidden="true" data-tq-intervalo="45">')
        ->toContain('html .fi-topbar { position: relative; isolation: isolate; }')
        ->toContain('.tq-barra-hojas { position: absolute; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }')
        ->toContain("window.matchMedia('(prefers-reduced-motion: reduce)')")
        ->toContain("classList.contains('tq-sin-animaciones')")
        ->toContain('document.hidden')
        ->toContain('html.tq-sin-animaciones .tq-barra-hojas { display: none; }');

    foreach (AdornosDeEstacion::hojasQueCaen() as $hoja) {
        expect($otono)->toContain($hoja);
    }
});

it('en invierno no caen hojas, y con la apariencia según el día apagada, la barra queda como siempre', function () {
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);

    expect(barraEnLaFecha('2027-08-05'))->not->toContain('tq-barra-hojas');

    foreach ([barraEnLaFecha('2027-05-05', $usuario), barraEnLaFecha('2027-02-01', $usuario)] as $pagina) {
        expect($pagina)->not->toContain('tq-barra-hojas')->not->toContain('html .fi-topbar { background-image:');
    }
});

it('las hojas que caen son de otoño, una de cada color, y no se leen', function () {
    $hojas = AdornosDeEstacion::hojasQueCaen();

    expect($hojas)->toHaveCount(6)->and(count(array_unique($hojas)))->toBe(6);

    foreach ($hojas as $hoja) {
        expect($hoja)->toStartWith('<svg class="tq-hoja-cae"')->toContain('aria-hidden="true"');
    }
});
