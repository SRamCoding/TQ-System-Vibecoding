<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Navegacion\AdornosDeEstacion;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\EstiloEstacion;
use App\Modules\Nucleo\Navegacion\Oklch;

/*
 * Los adornos de las cajas por estación (plan 1.4t): hojas de otoño distintas en cada caja (colores, formas, giro y
 * disposición), siempre las mismas para la misma caja, y la escarcha de invierno; y los íconos con contraste.
 */

$claves = ['mien', 'personas', 'usuarios', 'configuracion', 'compras', 'ventas', 'almacen', 'proyectos'];

it('cada caja tiene sus hojas: siempre las mismas, de 3 a 4, con colores y formas de otoño', function () use ($claves) {
    foreach ($claves as $clave) {
        $hojas = AdornosDeEstacion::hojas($clave);

        expect($hojas)->toBe(AdornosDeEstacion::hojas($clave))
            ->toStartWith('<svg class="tq-caja-hojas"')
            ->toContain('aria-hidden="true"')
            ->and(substr_count($hojas, '<g '))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(4)
            ->and(preg_match_all('/fill="oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)"/', $hojas, $colores))->toBeGreaterThanOrEqual(3);

        foreach ($colores[3] as $matiz) {
            // Rojos, naranjas, ámbar, ocres y marrones.
            expect((float) $matiz)->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(85);
        }
    }
});

it('las hojas no se ven igual en todas las cajas: cambian de color y de disposición', function () use ($claves) {
    $dibujos = array_map(AdornosDeEstacion::hojas(...), $claves);
    $colores = array_map(fn (string $hojas): string => implode(',', (preg_match_all('/fill="([^"]+)"/', $hojas, $m) ? $m[1] : [])), $dibujos);
    $lugares = array_map(fn (string $hojas): string => implode(',', (preg_match_all('/translate\((\d+ \d+)\) rotate/', $hojas, $m) ? $m[1] : [])), $dibujos);

    expect(count(array_unique($dibujos)))->toBe(count($claves))
        ->and(count(array_unique($colores)))->toBeGreaterThan(count($claves) / 2)
        ->and(count(array_unique($lugares)))->toBeGreaterThan(count($claves) / 2);
});

it('la escarcha de invierno crece desde la esquina de arriba, a veces también desde la de abajo, y no se lee', function () use ($claves) {
    $abajo = 0;

    foreach ($claves as $clave) {
        $hielo = AdornosDeEstacion::hielo($clave);

        expect($hielo)->toBe(AdornosDeEstacion::hielo($clave))
            ->toContain('tq-hielo-arriba')
            ->and(substr_count($hielo, 'aria-hidden="true"'))->toBe(substr_count($hielo, '<svg'))
            ->and(substr_count($hielo, 'tq-cristal'))->toBeGreaterThanOrEqual(2);
        $abajo += str_contains($hielo, 'tq-hielo-abajo') ? 1 : 0;
    }

    expect($abajo)->toBeGreaterThan(0)->toBeLessThan(count($claves));
});

it('los íconos de otoño e invierno se distinguen de su fondo en claro (3:1, lo que pide WCAG para lo que no es texto)', function (Temporada $estacion) {
    foreach (AreaModulo::cases() as $area) {
        $fondo = Oklch::luminancia(...EstiloEstacion::lch($estacion, 'claro', $area, 'fondo1'));

        foreach (['icono1', 'icono2'] as $parte) {
            expect(Oklch::contraste(Oklch::luminancia(...EstiloEstacion::lch($estacion, 'claro', $area, $parte)), $fondo))
                ->toBeGreaterThanOrEqual(3.0, "{$estacion->value} {$area->value} {$parte}");
        }
    }
})->with([Temporada::Otono, Temporada::Invierno]);
