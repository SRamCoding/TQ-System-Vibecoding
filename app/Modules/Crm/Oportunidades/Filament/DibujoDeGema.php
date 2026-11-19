<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use Closure;

/**
 * Las piezas del SVG de una gema (plan 2.21c, D3), en un lienzo de 100 × 88: la misma piedra tallada de etapa en etapa,
 * como en el prototipo del tablero. En bruto y desbastada, una piedra irregular de caras mates (la semilla la varía un
 * poco, siempre igual para la misma oportunidad); de tallada en adelante, una talla brillante vista de lado (corona,
 * cintura y pabellón). Pulida y brillante llevan un reflejo; brillante, destellos y halo turquesa de la marca; fisurada,
 * una grieta. Solo geometría y colores: el componente `oportunidades::gema` la dibuja.
 */
final readonly class DibujoDeGema
{
    /** Los tonos de cada talla: grises, violetas del CRM cada vez más intensos, el turquesa de la marca y gris apagado. */
    private const array PALETAS = [
        'en_bruto' => ['#8E8AA3', '#A29EB6', '#7A768F', '#B4B0C5', '#6E6A82', '#9590AA'],
        'desbastada' => ['#B3A0D3', '#C8B8E4', '#9C86C6', '#D8CCEE', '#8C74B8', '#BBA8DC'],
        'tallada' => ['#B07FD8', '#C99CE8', '#9864C9', '#DDBBF2', '#8553B6', '#BE8EE2'],
        'pulida' => ['#8A58E2', '#A87CF2', '#6C40CA', '#C7A2FB', '#5432AE', ColoresMarca::MORADO, '#3C2496'],
        'brillante' => ['#EFFEFF', '#BFF5FF', '#86E8FF', '#FFFFFF', '#A6DCFF', ColoresMarca::TURQUESA, '#DAF8FF', '#7CC2FF'],
        'fisurada' => ['#B4B4BC', '#C9C9D0', '#9A9AA3', '#D6D6DC', '#86868F', '#A8A8B0'],
    ];

    /**
     * @param  list<array{puntos: string, relleno: string, trazo: string, grosor: string}>  $caras
     * @param  array{cx: string, cy: string, rx: string, ry: string, relleno: string}  $sombra
     * @param  list<array{puntos: string, grosor: string}>  $grieta
     * @param  list<array{d: string, retraso: string}>  $destellos
     */
    public function __construct(
        public TallaDeGema $talla,
        public array $caras,
        /** El contorno: recorta el reflejo y se dibuja como borde. */
        public string $contorno,
        public string $borde,
        public array $sombra,
        public array $grieta,
        public array $destellos,
        /** Si lleva el reflejo que la recorre (pulida y brillante), con su retraso para que no vayan todas juntas. */
        public bool $reflejo,
        public string $retrasoReflejo,
    ) {}

    public static function de(TallaDeGema $talla, int $semilla = 0): self
    {
        $azar = self::azar($semilla + 7);
        $paleta = self::PALETAS[$talla->value];
        $caras = [];

        if ($talla === TallaDeGema::EnBruto || $talla === TallaDeGema::Desbastada) {
            $enBruto = $talla === TallaDeGema::EnBruto;
            $base = $enBruto
                ? [[16, 42], [26, 22], [46, 14], [70, 18], [86, 34], [82, 58], [62, 72], [36, 72], [18, 60]]
                : [[12, 38], [26, 18], [52, 12], [76, 18], [90, 38], [78, 62], [52, 74], [26, 64]];
            $vaiven = $enBruto ? 6 : 3;
            $contorno = array_map(fn (array $p): array => [round($p[0] + ($azar() - 0.5) * $vaiven, 1), round($p[1] + ($azar() - 0.5) * $vaiven, 1)], $base);
            $centro = [round(50 + ($azar() - 0.5) * 6, 1), round(42 + ($azar() - 0.5) * 6, 1)];
            $trazo = $enBruto ? 'rgba(0,0,0,.10)' : 'rgba(255,255,255,.28)';

            foreach ($contorno as $i => $punto) {
                $caras[] = self::cara([$centro, $punto, $contorno[($i + 1) % count($contorno)]], $paleta[$i % count($paleta)], $trazo, '.8');
            }

            if (! $enBruto) {
                $caras[] = self::cara([$contorno[1], $contorno[2], $centro], 'rgba(255,255,255,.22)');
            }

            $borde = $enBruto ? 'rgba(0,0,0,.18)' : 'rgba(255,255,255,.3)';
            $sombra = ['cx' => '50', 'cy' => '80', 'rx' => '30', 'ry' => '3.2', 'relleno' => 'rgba(9,9,11,.14)'];
        } else {
            $borde = 'rgba(255,255,255,.35)';
            [$t1, $t2, $t3, $punta] = [[30, 18], [50, 18], [70, 18], [50, 78]];
            $cintura = [[8, 34], [29, 34], [50, 34], [71, 34], [92, 34]];
            $corona = [
                [$cintura[0], $t1, $cintura[1]], [$t1, $cintura[2], $cintura[1]], [$t1, $t2, $cintura[2]],
                [$t2, $t3, $cintura[2]], [$t3, $cintura[3], $cintura[2]], [$t3, $cintura[4], $cintura[3]],
            ];
            $pabellon = [];

            if ($talla === TallaDeGema::Tallada) {
                for ($i = 0; $i < 4; $i++) {
                    $pabellon[] = [$cintura[$i], $cintura[$i + 1], $punta];
                }
            } else {
                // Pulida, brillante y fisurada: el pabellón con el doble de caras.
                for ($i = 0; $i < 4; $i++) {
                    $medio = [($cintura[$i][0] + $cintura[$i + 1][0]) / 2, 34];
                    $pabellon[] = [$cintura[$i], $medio, $punta];
                    $pabellon[] = [$medio, $cintura[$i + 1], $punta];
                }
            }

            foreach ($corona as $i => $cara) {
                $caras[] = self::cara($cara, $paleta[($i + 1) % count($paleta)], $borde, '.7');
            }

            foreach ($pabellon as $i => $cara) {
                $caras[] = self::cara($cara, $paleta[($i * 2 + 3) % count($paleta)], $borde, '.7');
            }

            $caras[] = self::cara([$t1, $t3, $cintura[3], $cintura[1]], $talla === TallaDeGema::Fisurada ? 'rgba(255,255,255,.08)' : 'rgba(255,255,255,.16)');
            $contorno = [$cintura[0], $t1, $t3, $cintura[4], $punta];
            $sombra = match ($talla) {
                TallaDeGema::Brillante => ['cx' => '50', 'cy' => '84', 'rx' => '28', 'ry' => '3.5', 'relleno' => 'rgba(0,218,255,.35)'],
                TallaDeGema::Fisurada => ['cx' => '50', 'cy' => '84', 'rx' => '24', 'ry' => '3', 'relleno' => 'rgba(0,0,0,.18)'],
                default => ['cx' => '50', 'cy' => '84', 'rx' => '26', 'ry' => '3.2', 'relleno' => 'rgba(9,9,11,.14)'],
            };
        }

        $grieta = $talla !== TallaDeGema::Fisurada ? [] : [
            ['puntos' => '58,18 54,28 60,36 52,48 56,58 50,66', 'grosor' => '1.6'],
            ['puntos' => '54,28 46,31', 'grosor' => '1'],
            ['puntos' => '52,48 60,52', 'grosor' => '.9'],
        ];
        $destellos = $talla !== TallaDeGema::Brillante ? [] : [
            self::destello(30, 18, 7, ($semilla % 5) * 0.3),
            self::destello(78, 30, 5, 0.8 + ($semilla % 3) * 0.3),
            self::destello(56, 52, 4, 1.4),
            self::destello(14, 36, 3.5, 0.4),
        ];

        return new self(
            talla: $talla,
            caras: $caras,
            contorno: self::puntos($contorno),
            borde: $borde,
            sombra: $sombra,
            grieta: $grieta,
            destellos: $destellos,
            reflejo: $talla === TallaDeGema::Pulida || $talla === TallaDeGema::Brillante,
            retrasoReflejo: self::numero(($semilla % 7) * 0.4).'s',
        );
    }

    /** El generador del prototipo (congruencial): la misma semilla da la misma piedra. */
    private static function azar(int $semilla): Closure
    {
        $estado = (abs($semilla) * 9301 + 49297) % 233280;

        return function () use (&$estado): float {
            $estado = ($estado * 9301 + 49297) % 233280;

            return $estado / 233280;
        };
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $puntos
     * @return array{puntos: string, relleno: string, trazo: string, grosor: string}
     */
    private static function cara(array $puntos, string $relleno, string $trazo = 'none', string $grosor = '0'): array
    {
        return ['puntos' => self::puntos($puntos), 'relleno' => $relleno, 'trazo' => $trazo, 'grosor' => $grosor];
    }

    /** @param  list<array{0: float|int, 1: float|int}>  $puntos */
    private static function puntos(array $puntos): string
    {
        return implode(' ', array_map(fn (array $p): string => self::numero($p[0]).','.self::numero($p[1]), $puntos));
    }

    /** @return array{d: string, retraso: string} */
    private static function destello(float $x, float $y, float $s, float $retraso): array
    {
        $n = fn (float $v): string => self::numero($v);
        $c = $s * 0.22;

        return [
            'd' => "M{$n($x)} {$n($y - $s)} L{$n($x + $c)} {$n($y - $c)} L{$n($x + $s)} {$n($y)} L{$n($x + $c)} {$n($y + $c)} "
                ."L{$n($x)} {$n($y + $s)} L{$n($x - $c)} {$n($y + $c)} L{$n($x - $s)} {$n($y)} L{$n($x - $c)} {$n($y - $c)}Z",
            'retraso' => $n($retraso).'s',
        ];
    }

    /** Un número corto y con punto decimal, sea cual sea la configuración regional. */
    private static function numero(float|int $valor): string
    {
        $texto = number_format((float) $valor, 2, '.', '');

        return (float) $texto === 0.0 ? '0' : rtrim(rtrim($texto, '0'), '.');
    }
}
