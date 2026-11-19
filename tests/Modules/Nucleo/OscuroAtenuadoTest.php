<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Navegacion\Oklch;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Illuminate\Support\Str;

/*
 * El oscuro atenuado (plan 1.4t): en modo oscuro, gris carbón en vez de casi negro, en todas las pantallas y aunque la
 * apariencia según la estación esté apagada. Los grises suben juntos y el texto gris sigue leyéndose. Personas
 * inventadas.
 */

/** Luminancia de un `oklch(L C H)`. */
function luminanciaOklch(string $color): float
{
    preg_match('/^oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)$/', $color, $partes);

    return Oklch::luminancia((float) $partes[1], (float) $partes[2], (float) $partes[3]);
}

function grisesOscurosEnCss(): string
{
    $variables = '';

    foreach (ColoresMarca::grisesModoOscuro() as $tono => $gris) {
        $variables .= "--gray-{$tono}: {$gris}; ";
    }

    return 'html.dark { '.$variables.'}';
}

it('las pantallas y el ingreso traen los grises oscuros, también sin la apariencia según la estación', function () {
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);

    expect((string) $this->get('/login')->assertOk()->getContent())->toContain(grisesOscurosEnCss())
        ->and((string) $this->actingAs($usuario)->get('/')->assertOk()->getContent())->toContain(grisesOscurosEnCss());
});

it('los grises suben juntos: del fondo a los bordes, cada uno más claro que el anterior y que el de Filament', function () {
    // Los de Filament (zinc), los que había antes.
    $antes = [950 => 0.141, 900 => 0.21, 800 => 0.274, 700 => 0.37, 600 => 0.442];
    $anterior = 0.0;

    foreach (ColoresMarca::grisesModoOscuro() as $tono => $gris) {
        $claridad = (float) Str::between($gris, 'oklch(', ' ');

        expect($claridad)->toBeGreaterThan($anterior)->toBeGreaterThan($antes[$tono]);
        $anterior = $claridad;
    }

    expect(array_keys(ColoresMarca::grisesModoOscuro()))->toBe([950, 900, 800, 700, 600]);
});

it('el texto gris claro de Filament se lee (AA) sobre el fondo y las tarjetas, y la barra superior es más clara que el fondo', function () {
    $texto = luminanciaOklch('oklch(0.705 0.015 286.067)');
    $grises = ColoresMarca::grisesModoOscuro();

    expect(Oklch::contraste($texto, luminanciaOklch($grises[950])))->toBeGreaterThanOrEqual(4.5)
        ->and(Oklch::contraste($texto, luminanciaOklch($grises[900])))->toBeGreaterThanOrEqual(4.5)
        ->and(luminanciaOklch(ColoresMarca::barraSuperiorOscura()))->toBeGreaterThan(luminanciaOklch($grises[950]));
});
