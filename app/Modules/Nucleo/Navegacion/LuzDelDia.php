<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;

/**
 * La luz de la barra superior y el tinte del fondo según la hora, la temperatura y el cielo (plan 1.4ac). Los números
 * son los de los dos simuladores que aprobó el desarrollador el 2026-11-15, tal cual («quiero que quede el fondo tal
 * cual como este»): ver «Valores de los simuladores aprobados» en el plan.
 *
 * La hora y la luna se saben en el servidor; la temperatura y el cielo, solo en el navegador (el clima de la 1.4u). Por
 * eso `css()` escribe la escena de la hora con `color-mix()` sobre una variable, `--tq-calor` (0 a 1), que pone el
 * script del clima: la misma mezcla lineal del simulador, hecha por el navegador. `escena()` hace la misma cuenta en
 * PHP, con el redondeo del simulador, para las pruebas y para revisar el contraste.
 */
final class LuzDelDia
{
    public const string NOCHE = 'noche';

    public const string AMANECER = 'amanecer';

    public const string DIA = 'dia';

    public const string ATARDECER = 'atardecer';

    /** Los cielos de la luz. El clima del navegador se resume en estos tres (`cielo()`). */
    public const string DESPEJADO = 'despejado';

    public const string NUBES = 'nubes';

    public const string LLUVIA = 'lluvia';

    /** Lo que pone el script del clima en `data-tq-clima`, por cielo de la luz. */
    public const array CLIMAS = [
        self::NUBES => ['nublado', 'niebla'],
        self::LLUVIA => ['llovizna', 'lluvia', 'tormenta'],
    ];

    /** Sin clima (sin permiso de ubicación o sin respuesta): templado (D6). */
    public const float CALOR_SIN_CLIMA = 0.5;

    /** Tinte del fondo: frío, tibio y calor. */
    private const array FONDO = [
        'claro' => [[240, 244, 248], [248, 247, 242], [250, 246, 236]],
        'oscuro' => [[34, 39, 48], [40, 41, 44], [47, 42, 36]],
    ];

    /** La barra: el fondo mezclado con este color, en esta parte. */
    private const array BARRA = ['claro' => [[255, 255, 255], 0.35], 'oscuro' => [[70, 72, 78], 0.45]];

    /** De noche: el fondo y la barra se enfrían (en claro, la barra sale del fondo ya enfriado; en oscuro, de la barra). */
    private const array NOCHE_FONDO = ['claro' => [236, 239, 247], 'oscuro' => [30, 34, 46]];

    private const array NOCHE_BARRA = ['claro' => [228, 232, 244], 'oscuro' => [42, 47, 64]];

    private const array LUNA = [200, 212, 255];

    /** Intensidad de la luna: [despejado, con nubes o lluvia]. */
    private const array ALFA_LUNA = ['claro' => [0.55, 0.18], 'oscuro' => [0.42, 0.15]];

    /** El color del astro de día (de templado-frío a calor) y al amanecer y al atardecer. */
    private const array SOL = [
        'claro' => [self::AMANECER => [255, 170, 110], self::DIA => [[255, 236, 150], [255, 210, 80]], self::ATARDECER => [255, 130, 100]],
        'oscuro' => [self::AMANECER => [255, 160, 100], self::DIA => [[255, 230, 140], [255, 200, 70]], self::ATARDECER => [255, 120, 95]],
    ];

    /** Intensidad del sol: base + calor × pendiente. En oscuro, todo por 0,55: resplandece, no ilumina (D5). */
    private const array ALFA_SOL = [self::AMANECER => [0.45, 0.2], self::DIA => [0.35, 0.4], self::ATARDECER => [0.5, 0.2]];

    private const array K = ['claro' => 1.0, 'oscuro' => 0.55];

    /** Nubes y lluvia: el gris con que se mezcla la luz, cuánto, y por cuánto se multiplica la intensidad. */
    private const array CIELO = [
        self::NUBES => ['claro' => [220, 220, 225], 'oscuro' => [150, 152, 160], 'mezcla' => 0.7, 'alfa' => 0.5],
        self::LLUVIA => ['claro' => [200, 205, 215], 'oscuro' => [130, 140, 160], 'mezcla' => 0.85, 'alfa' => 0.35],
    ];

    /** Con lluvia, en claro, la barra se agrisa. */
    private const array BARRA_LLUVIA = [[232, 236, 242], 0.5];

    /**
     * Dónde va el centro del halo, como en el simulador: un círculo de 220 px con su borde superior a 110 px por
     * encima de la barra (el centro, en el borde de arriba). De día, a 110 px del borde derecho (junto al buscador); al
     * amanecer, su borde izquierdo al 70 %; al atardecer, a 40 px del borde.
     */
    private const array CENTRO = [
        self::NOCHE => 'calc(100% - 220px)',
        self::AMANECER => 'calc(70% + 110px)',
        self::DIA => 'calc(100% - 220px)',
        self::ATARDECER => 'calc(100% - 150px)',
    ];

    /** El radio del degradado: `circle` en una caja de 220 px llega a la esquina, 110 × √2. */
    private const string RADIO = '155.56px';

    /**
     * Estrellas con cielo despejado de noche: [izquierda %, arriba %, brillo]. 7 en claro y 9 en oscuro (D4).
     *
     * @return list<array{0: int, 1: int, 2: float}>
     */
    public static function estrellas(string $tema): array
    {
        if ($tema === 'claro') {
            return array_map(fn (int $i): array => [8 + $i * 11 + ($i % 2) * 4, 12 + ($i * 37) % 60, 1.0], range(0, 6));
        }

        return array_map(fn (int $i): array => [6 + $i * 9 + ($i % 2) * 3, 10 + ($i * 37) % 70, 0.5 + ($i % 3) * 0.2], range(0, 8));
    }

    /** La fase del día por la hora: noche antes de las 5:30 o desde las 19:30; amanecer hasta las 7; día hasta las 17:30. */
    public static function fase(CarbonImmutable $ahora): string
    {
        $h = $ahora->hour + $ahora->minute / 60;

        return match (true) {
            $h < 5.5 || $h >= 19.5 => self::NOCHE,
            $h < 7 => self::AMANECER,
            $h < 17.5 => self::DIA,
            default => self::ATARDECER,
        };
    }

    /** `(temperatura − 10) / 24`, entre 0 y 1: 10 °C es 0 y 34 °C es 1. */
    public static function calor(float $grados): float
    {
        return min(1.0, max(0.0, ($grados - 10) / 24));
    }

    /** El cielo de la luz para lo que puso el clima en `data-tq-clima`; sin clima, despejado. */
    public static function cielo(?string $clima): string
    {
        foreach (self::CLIMAS as $cielo => $climas) {
            if (in_array($clima, $climas, true)) {
                return $cielo;
            }
        }

        return self::DESPEJADO;
    }

    /**
     * La escena, como la pinta el simulador (con su redondeo en cada mezcla). La luna (D4, que no está en el simulador)
     * multiplica la intensidad de la noche por `0,15 + 0,85 × iluminada`.
     *
     * @return array{fondo: list<int>, barra: list<int>, luz: list<int>, intensidad: float, centro: string, estrellas: int}
     */
    public static function escena(string $fase, float $calor, string $cielo, string $tema, ?FaseLunar $luna = null): array
    {
        $calor = min(1.0, max(0.0, $calor));
        [$frio, $tibio, $caliente] = self::FONDO[$tema];
        $fondo = $calor < 0.5 ? self::mezclar($frio, $tibio, $calor * 2) : self::mezclar($tibio, $caliente, ($calor - 0.5) * 2);
        $barra = self::mezclar($fondo, self::BARRA[$tema][0], self::BARRA[$tema][1]);

        if ($fase === self::NOCHE) {
            $fondo = self::mezclar($fondo, self::NOCHE_FONDO[$tema], 0.6);
            $barra = self::mezclar($tema === 'claro' ? $fondo : $barra, self::NOCHE_BARRA[$tema], 0.6);
            $luz = self::LUNA;
            $alfa = self::ALFA_LUNA[$tema][$cielo === self::DESPEJADO ? 0 : 1] * self::factorLunar($luna);
        } else {
            $sol = self::SOL[$tema][$fase];
            $luz = $fase === self::DIA ? self::mezclar($sol[0], $sol[1], $calor) : $sol;
            $alfa = (self::ALFA_SOL[$fase][0] + $calor * self::ALFA_SOL[$fase][1]) * self::K[$tema];
        }

        if (($cielo === self::NUBES && $fase !== self::NOCHE) || $cielo === self::LLUVIA) {
            $luz = self::mezclar($luz, self::CIELO[$cielo][$tema], self::CIELO[$cielo]['mezcla']);
            $alfa *= self::CIELO[$cielo]['alfa'];
        }

        if ($cielo === self::LLUVIA && $tema === 'claro') {
            $barra = self::mezclar($barra, self::BARRA_LLUVIA[0], self::BARRA_LLUVIA[1]);
        }

        return [
            'fondo' => $fondo,
            'barra' => $barra,
            'luz' => $luz,
            'intensidad' => round($alfa, 4),
            'centro' => self::CENTRO[$fase],
            'estrellas' => $fase === self::NOCHE && $cielo === self::DESPEJADO ? count(self::estrellas($tema)) : 0,
        ];
    }

    /** La luz para la persona, o null si apagó la apariencia según el día (D6, la misma preferencia de la estación). */
    public static function para(User $usuario): ?string
    {
        if (EstacionDeLaApariencia::para($usuario) === null) {
            return null;
        }

        $ahora = ModoDesarrollo::ahoraDecorativo();

        return self::css(self::fase($ahora), FaseLunar::de($ahora));
    }

    /**
     * El CSS de la escena de esta hora en claro y oscuro. El navegador mezcla por `--tq-calor` y cambia con
     * `data-tq-clima`, sin recargar.
     */
    public static function css(string $fase, FaseLunar $luna): string
    {
        $css = ['html { --tq-calor: '.self::CALOR_SIN_CLIMA.'; --tq-f1: clamp(0, var(--tq-calor) * 2, 1); --tq-f2: clamp(0, (var(--tq-calor) - 0.5) * 2, 1); }'];

        foreach (['claro' => 'html:not(.dark)', 'oscuro' => 'html.dark'] as $tema => $html) {
            $css = [...$css, ...self::cssDelTema($fase, $tema, $html, $luna)];
        }

        $css[] = 'html .fi-body { background-color: var(--tq-luz-fondo); }';
        $css[] = 'html .fi-topbar { background-image: '.implode(', ', [
            'var(--tq-luz-estrellas, linear-gradient(transparent, transparent))',
            ...self::sombraLunar($fase, $luna),
            'radial-gradient(circle '.self::RADIO.' at '.self::CENTRO[$fase].' 0, '
                .'color-mix(in srgb, var(--tq-luz) calc(var(--tq-luz-alfa) * 100%), transparent) 0%, '
                .'color-mix(in srgb, var(--tq-luz) calc(var(--tq-luz-alfa) * 35%), transparent) 40%, transparent 70%)',
        ]).'; }';

        return implode("\n", $css);
    }

    /** @return list<string> */
    private static function cssDelTema(string $fase, string $tema, string $html, FaseLunar $luna): array
    {
        [$frio, $tibio, $caliente] = self::FONDO[$tema];
        $base = 'color-mix(in srgb, color-mix(in srgb, '.self::rgb($frio).', '.self::rgb($tibio).' calc(var(--tq-f1) * 100%)), '.self::rgb($caliente).' calc(var(--tq-f2) * 100%))';
        $barraBase = self::mix('var(--tq-luz-fondo-base)', self::BARRA[$tema][0], self::BARRA[$tema][1]);
        $noche = $fase === self::NOCHE;

        $fondo = $noche ? self::mix('var(--tq-luz-fondo-base)', self::NOCHE_FONDO[$tema], 0.6) : 'var(--tq-luz-fondo-base)';
        $barra = $noche ? self::mix($tema === 'claro' ? 'var(--tq-luz-fondo)' : 'var(--tq-luz-barra-base)', self::NOCHE_BARRA[$tema], 0.6) : 'var(--tq-luz-barra-base)';

        if ($noche) {
            $factor = self::factorLunar($luna);
            [$astro, $alfa, $alfaNubes] = [self::rgb(self::LUNA), self::numero(self::ALFA_LUNA[$tema][0] * $factor), self::numero(self::ALFA_LUNA[$tema][1] * $factor)];
        } else {
            $sol = self::SOL[$tema][$fase];
            $astro = $fase === self::DIA ? 'color-mix(in srgb, '.self::rgb($sol[0]).', '.self::rgb($sol[1]).' calc(var(--tq-calor) * 100%))' : self::rgb($sol);
            $alfa = 'calc(('.self::ALFA_SOL[$fase][0].' + var(--tq-calor) * '.self::ALFA_SOL[$fase][1].') * '.self::K[$tema].')';
            $alfaNubes = 'calc('.$alfa.' * '.self::CIELO[self::NUBES]['alfa'].')';
        }

        $css = [
            "{$html} { --tq-luz-fondo-base: {$base}; --tq-luz-barra-base: {$barraBase}; --tq-luz-fondo: {$fondo}; --tq-luz-barra: {$barra}; --tq-barra-superior: var(--tq-luz-barra); --tq-astro: {$astro}; --tq-astro-alfa: {$alfa}; --tq-luz: var(--tq-astro); --tq-luz-alfa: var(--tq-astro-alfa); }",
        ];

        $nubes = $html.':is('.self::selectorClimas(self::NUBES).')';
        $lluvia = $html.':is('.self::selectorClimas(self::LLUVIA).')';

        // Con nubes, de noche solo baja la luna; de día, la luz además se agrisa.
        $css[] = $noche
            ? "{$nubes} { --tq-luz-alfa: {$alfaNubes}; }"
            : "{$nubes} { --tq-luz: ".self::mix('var(--tq-astro)', self::CIELO[self::NUBES][$tema], self::CIELO[self::NUBES]['mezcla'])."; --tq-luz-alfa: {$alfaNubes}; }";

        $alfaLluvia = $noche ? self::numero((float) $alfaNubes * self::CIELO[self::LLUVIA]['alfa']) : 'calc('.$alfa.' * '.self::CIELO[self::LLUVIA]['alfa'].')';
        $css[] = "{$lluvia} { --tq-luz: ".self::mix('var(--tq-astro)', self::CIELO[self::LLUVIA][$tema], self::CIELO[self::LLUVIA]['mezcla'])."; --tq-luz-alfa: {$alfaLluvia};"
            .($tema === 'claro' ? ' --tq-luz-barra: '.self::mix($noche ? $barra : 'var(--tq-luz-barra-base)', self::BARRA_LLUVIA[0], self::BARRA_LLUVIA[1]).';' : '').' }';

        if ($noche) {
            $css[] = "{$html} { --tq-luz-estrellas: ".implode(', ', array_map(
                fn (array $e): string => sprintf('radial-gradient(circle 2px at %d%% %d%%, rgb(255 255 255 / %s) 0 1px, rgb(136 153 204 / %s) 1px, transparent 2px)', $e[0], $e[1], self::numero($e[2]), self::numero($e[2] * 0.5)),
                self::estrellas($tema),
            )).'; }';
            $css[] = $html.':is('.self::selectorClimas(self::NUBES).', '.self::selectorClimas(self::LLUVIA).') { --tq-luz-estrellas: linear-gradient(transparent, transparent); }';
        }

        return $css;
    }

    /**
     * La luna con su fase (D4): fuera de la llena, un disco del color de la barra tapa el halo del lado oscuro y deja
     * ver el borde iluminado. En Chiclayo (hemisferio sur) la creciente se ilumina por la izquierda.
     *
     * @return list<string>
     */
    private static function sombraLunar(string $fase, FaseLunar $luna): array
    {
        if ($fase !== self::NOCHE || $luna->esLlena()) {
            return [];
        }

        $desplazamiento = (int) round($luna->iluminada * 120);
        $centro = sprintf('calc(100%% - 220px %s %dpx)', $luna->creciente ? '+' : '-', $desplazamiento);

        return ["radial-gradient(circle 75px at {$centro} 0, var(--tq-barra-superior) 60px, transparent 75px)"];
    }

    private static function factorLunar(?FaseLunar $luna): float
    {
        return $luna === null ? 1.0 : 0.15 + 0.85 * $luna->iluminada;
    }

    private static function selectorClimas(string $cielo): string
    {
        return implode(', ', array_map(fn (string $c): string => "[data-tq-clima=\"{$c}\"]", self::CLIMAS[$cielo]));
    }

    /**
     * `a + (b − a) × f` en cada canal, redondeado (el `mix` del simulador).
     *
     * @param  list<int>  $a
     * @param  list<int>  $b
     * @return list<int>
     */
    public static function mezclar(array $a, array $b, float $f): array
    {
        return array_map(fn (int $x, int $y): int => (int) round($x + ($y - $x) * $f), $a, $b);
    }

    /** @param list<int> $color */
    private static function mix(string $de, array $color, float $parte): string
    {
        return 'color-mix(in srgb, '.$de.', '.self::rgb($color).' '.self::numero($parte * 100).'%)';
    }

    /** @param list<int> $color */
    public static function rgb(array $color): string
    {
        return 'rgb('.implode(' ', $color).')';
    }

    private static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
