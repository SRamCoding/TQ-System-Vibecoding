<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Navegacion\ColoresMarca;

/**
 * El logo de Techquk (la T de tres piezas) y su animación de construcción (plan 1.4b, D9). La forma sale del logo que
 * entregó el desarrollador, en un lienzo de 122 × 119: el cuadrado turquesa arriba a la izquierda, la pieza morada
 * arriba a la derecha y la blanca abajo.
 *
 * Al pasar el mouse, un bloque blanco se rompe en tres tandas de escombros (arriba, abajo a la izquierda, abajo a la
 * derecha), quedan las tres piezas, se colorean y cada una se ilumina desde adentro con su color. En pocas fechas cambian
 * los colores, la luz o aparece un detalle.
 */
final class LogoTechquk
{
    /** Las tres piezas, como polígonos. */
    public const array PIEZAS = [
        'izquierda' => '2,2 40,2 40,40 2,40',
        'derecha' => '80,2 120,2 120,40 42,40',
        'abajo' => '40,41 80,81 80,117 40,117',
    ];

    /** Lo que se cae al romperse el bloque, en el orden de la animación. */
    private const array ZONAS = [
        'arriba' => [[40, 2], [80, 2], [42, 40], [40, 40]],
        'abajo-izquierda' => [[2, 40], [40, 40], [40, 117], [2, 117]],
        'abajo-derecha' => [[42, 40], [120, 40], [120, 117], [80, 117], [80, 81], [40, 41]],
    ];

    private const int LADO = 7;

    /**
     * La marca de sesión que deja el ingreso para que la primera pantalla de adentro corra la animación (plan del logo,
     * D1). La toma, una sola vez, la pantalla que dibuja el logo.
     */
    public const string MARCA_INGRESO = 'nucleo.logo.recien_ingreso';

    /**
     * Colores de las piezas y el detalle de la fecha. Cada pieza brilla con su propio color; `intensa`: la luz se ve
     * más (aniversario). Detalles (pedidos del desarrollador): gorro en Navidad, escudo del Perú colgando del pecho de
     * la T en Fiestas Patrias, Biblia en Semana Santa y estrella en Año Nuevo.
     *
     * @return array{izquierda: string, derecha: string, abajo: string, intensa: bool, detalle: ?string}
     */
    public static function paleta(?Ocasion $ocasion): array
    {
        $normal = ['izquierda' => ColoresMarca::TURQUESA, 'derecha' => ColoresMarca::MORADO, 'abajo' => '#FFFFFF', 'intensa' => false, 'detalle' => null];

        return match ($ocasion) {
            Ocasion::Aniversario => [...$normal, 'intensa' => true],
            Ocasion::AnioNuevo => [...$normal, 'detalle' => 'estrella'],
            Ocasion::FiestasPatrias => [...$normal, 'izquierda' => '#D91023', 'derecha' => '#FFFFFF', 'abajo' => '#D91023', 'detalle' => 'escudo'],
            // Rojo, blanco y rojo de izquierda a derecha.
            Ocasion::Navidad => [...$normal, 'izquierda' => '#D7263D', 'derecha' => '#D7263D', 'detalle' => 'gorro'],
            // Morado litúrgico.
            Ocasion::SemanaSanta => [...$normal, 'izquierda' => '#B9A7E8', 'derecha' => '#7B5EA7', 'detalle' => 'biblia'],
            default => $normal,
        };
    }

    /** El centro de cada pieza: de ahí sale su luz. */
    public const array CENTROS = ['izquierda' => [21, 21], 'derecha' => [84, 21], 'abajo' => [60, 92]];

    /** Las fechas con logo propio: pocas, a pedido del desarrollador. */
    public static function tieneLogoPropio(?Ocasion $ocasion): bool
    {
        return in_array($ocasion, [Ocasion::Navidad, Ocasion::FiestasPatrias, Ocasion::Aniversario, Ocasion::AnioNuevo, Ocasion::SemanaSanta], true);
    }

    /**
     * Los escombros: cuadraditos que tapan el espacio entre las piezas y caen al romperse. Cada uno con su caída, giro y
     * retraso, calculados (no al azar) para que el logo se vea igual en cada carga.
     *
     * @return list<array{x: int, y: int, lado: int, zona: int, dx: int, dy: int, giro: int, retraso: float}>
     */
    public static function escombros(): array
    {
        $escombros = [];
        $n = 0;

        foreach (array_values(self::ZONAS) as $zona => $poligono) {
            for ($y = 0; $y < 119; $y += self::LADO) {
                for ($x = 0; $x < 122; $x += self::LADO) {
                    if (! self::dentro($x + self::LADO / 2, $y + self::LADO / 2, $poligono)) {
                        continue;
                    }

                    $n++;
                    $escombros[] = [
                        'x' => $x,
                        'y' => $y,
                        'lado' => self::LADO,
                        'zona' => $zona,
                        'dx' => (int) round(sin($n * 12.9898) * 18),
                        'dy' => 40 + (int) round(abs(sin($n * 78.233)) * 30),
                        'giro' => (int) round(sin($n * 3.7) * 160),
                        'retraso' => round(abs(sin($n * 5.1)) * 0.18, 2),
                    ];
                }
            }
        }

        return $escombros;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $poligono
     */
    private static function dentro(float $x, float $y, array $poligono): bool
    {
        $dentro = false;
        $total = count($poligono);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            [$xi, $yi] = $poligono[$i];
            [$xj, $yj] = $poligono[$j];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }
}
