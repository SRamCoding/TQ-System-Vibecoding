<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use Filament\Support\Colors\Color;

/**
 * Los colores de la marca Techquk, los del logo (ADR 0017). Único lugar donde se escriben: el proveedor del panel los
 * da a Filament (`primary`, `info`, `morado`, `dorado`) y el logo y el agradecimiento del aniversario los leen de aquí. Si llega
 * el manual de marca con otros códigos, se cambian aquí y en ningún otro lugar.
 */
final class ColoresMarca
{
    public const string AZUL_NOCHE = '#01006F';

    public const string TURQUESA = '#00DAFF';

    public const string MORADO = '#7C6EFF';

    /**
     * Las piezas del logo en modo claro (pedido del desarrollador, 2026-10-26): la celeste, más turquesa, y la morada,
     * en orquídea. El morado del CRM en modo claro es esta orquídea (pedido del desarrollador, 2026-11-09: «guíate del
     * ícono»); en modo oscuro, el morado de la marca, como el logo.
     */
    public const string TURQUESA_LOGO_CLARO = '#0FC2C4';

    public const string MORADO_CLARO = '#B35FD0';

    /**
     * El dorado de «Mien» y su área «Personal» (plan 1.4l, D2), en modo claro: un ocre que se lee como texto sobre el
     * fondo de la página (4,86:1 sobre #FAFAFA). Un dorado más vivo no llega al 4,5:1.
     */
    public const string DORADO = '#936600';

    /** El dorado en modo oscuro: brillante, 10,6:1 sobre el fondo casi negro. */
    public const string DORADO_OSCURO = '#F2C14E';

    /** MV-033: los colores de las franjas que no tenían uno propio para el modo oscuro, y el gris de Plataforma. */
    public const string AZUL_NOCHE_OSCURO = '#6F9BFF';

    public const string LILA_OSCURO = '#C3A6FF';

    /** El turquesa como texto en modo claro: el del logo no llega a 4,5:1 sobre blanco (2,2); este, 5,2. */
    public const string TURQUESA_TEXTO = '#00788A';

    public const string GRIS_PLATAFORMA = '#6E7781';

    public const string GRIS_PLATAFORMA_OSCURO = '#9DA5AE';

    /** Fondo de la barra superior en modo claro (plan 1.3d, D4): distingue la barra, del logo al avatar, del resto. */
    public const string PERLA = '#EEEAE2';

    /**
     * La tinta de un documento impreso (plan 1.15): es papel, blanco también en modo oscuro, y un PDF no tiene las
     * variables de Filament. La suave, para lo secundario; las dos en AA sobre blanco (15,8:1 y 6,4:1).
     */
    public const string TINTA = '#1F2328';

    public const string TINTA_SUAVE = '#57606A';

    /**
     * La paleta de `primary` para Filament, con el azul noche exacto en el tono 600 (el de los botones y enlaces en modo
     * claro). `Color::hex()` no sirve aquí: arma toda paleta con la misma escala de claridad y solo toma el tono del
     * color, así que el azul noche salía como un azul medio cualquiera y el botón de ingreso, celeste con texto oscuro.
     * Los tonos claros (50–500) son el mismo matiz aclarado; los oscuros (700–950), oscurecido.
     *
     * @return array<int, string>
     */
    public static function paletaPrincipal(): array
    {
        $matiz = 264.466; // El del azul noche, oklch(0.245 0.169 264.466).
        $escala = [
            50 => [0.97, 0.014], 100 => [0.94, 0.03], 200 => [0.88, 0.06], 300 => [0.78, 0.10], 400 => [0.66, 0.14],
            500 => [0.50, 0.18], 600 => [0.245, 0.169], 700 => [0.22, 0.15], 800 => [0.19, 0.13], 900 => [0.16, 0.10],
            950 => [0.12, 0.07],
        ];

        // MV-042: el azul de resaltado en modo oscuro (enlaces, pestañas, foco, filtros activos) es #6F9BFF, el mismo de la
        // franja Estratégicos en oscuro: Filament lo toma del tono 400.
        return array_replace(array_map(fn (array $tono): string => "oklch({$tono[0]} {$tono[1]} {$matiz})", $escala), [400 => self::AZUL_NOCHE_OSCURO]);
    }

    /**
     * La paleta de `dorado` para Filament: la de `Color::hex()` con los dos tonos exactos donde se usan, el 600 en modo
     * claro y el 400 en oscuro (como en las demás áreas).
     *
     * @return array<int, string>
     */
    public static function paletaDorada(): array
    {
        return array_replace(Color::hex(self::DORADO), [400 => self::DORADO_OSCURO, 600 => self::DORADO]);
    }

    /**
     * La paleta de `morado` (el área CRM): la de la orquídea del logo, con sus dos tonos exactos donde se usan, como el
     * logo: el 600 en modo claro (la orquídea) y el 400 en oscuro (el morado de la marca).
     *
     * @return array<int, string>
     */
    public static function paletaMorada(): array
    {
        return array_replace(Color::hex(self::MORADO_CLARO), [400 => self::MORADO, 600 => self::MORADO_CLARO]);
    }

    /**
     * El tono 600 de `primary` en modo oscuro: el mismo azul, más luminoso. Sobre el fondo casi negro, el azul noche
     * exacto apenas se distinguía (pedido del desarrollador, 2026-10-19). En modo claro sigue siendo el azul noche.
     */
    public static function principalModoOscuro(): string
    {
        return 'oklch(0.40 0.20 264.466)';
    }

    /**
     * Fondo de la barra superior en modo oscuro (plan 1.3d, D4): un gris con el matiz del azul noche, más claro que el
     * fondo. No el azul noche puro: las piezas azul noche del logo se perderían sobre él. Con el oscuro atenuado (plan
     * 1.4t, D2), de 0,30 a 0,32, para seguir más clara que el fondo.
     */
    public static function barraSuperiorOscura(): string
    {
        return 'oklch(0.32 0.045 264.466)';
    }

    /**
     * El oscuro atenuado (plan 1.4t, D1; pedido del desarrollador, 2026-10-28: «el hecho que es puro negro ya debería
     * cambiar»): en modo oscuro, los grises de Filament que hacen de fondo, tarjetas, relieves y bordes suben a un gris
     * carbón, con el matiz y la croma mínima de los suyos. Se corren juntos para que nada quede más oscuro que el fondo.
     * El modo claro no los usa así y no cambia.
     *
     * @return array<int, string>
     */
    public static function grisesModoOscuro(): array
    {
        return [
            950 => 'oklch(0.25 0.005 285.823)',
            900 => 'oklch(0.29 0.006 285.885)',
            800 => 'oklch(0.34 0.006 286.033)',
            700 => 'oklch(0.41 0.013 285.805)',
            600 => 'oklch(0.47 0.017 285.786)',
        ];
    }

    /**
     * El matiz de cada área en cada tema (plan 1.4r): el de su color de la marca. Lo usa el estilo de las cajas por
     * estación, que solo cambia claridad e intensidad; un área nueva toma el suyo de aquí. El CRM, como el logo: orquídea
     * en claro y el morado de la marca en oscuro.
     */
    public static function matizDeArea(AreaModulo $area, string $tema): float
    {
        return round(Oklch::matiz(match ($area) {
            AreaModulo::Personal => self::DORADO,
            AreaModulo::Nucleo => self::AZUL_NOCHE,
            AreaModulo::Crm => $tema === 'claro' ? self::MORADO_CLARO : self::MORADO,
            AreaModulo::Erp => self::TURQUESA,
            AreaModulo::Plataforma => self::GRIS_PLATAFORMA,
        }), 1);
    }
}
