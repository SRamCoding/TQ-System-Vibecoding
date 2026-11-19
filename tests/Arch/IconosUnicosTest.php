<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;

/*
 * Plan 2.20a, N9 (desarrollador, 2026-11-05): un ícono no puede significar dos cosas distintas; el mismo concepto sí
 * puede repetirlo (docs/convenciones/interfaz.md, «Íconos»). Vale para cajas, pantallas, pestañas y catálogos. Lee el código, sin arrancar la aplicación:
 * las cajas de módulo (`new CajaModulo(... icono: '…')`), las pantallas (`$navigationIcon`), las pestañas de Filament
 * (`Tab::make('…')->icon('…')`), las que otros módulos suman a la ficha (`new PestanaFicha(...)`) y los catálogos
 * (`Catalogo::icono()`). La única pareja permitida: una pantalla y la caja de su módulo, que son lo mismo.
 */

/**
 * Dónde se usa cada ícono.
 *
 * @return array<string, list<string>> ícono => lugares
 */
function iconosDelSistema(string $raiz): array
{
    $usos = [];

    foreach (Catalogo::cases() as $catalogo) {
        $usos[$catalogo->icono()][] = "catálogo {$catalogo->name}";
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/app', FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (! str_ends_with($ruta, '.php')) {
            continue;
        }

        foreach (iconosDelCodigo((string) file_get_contents($ruta)) as [$icono, $lugar]) {
            $usos[$icono][] = $lugar.' ('.basename($ruta, '.php').')';
        }
    }

    return $usos;
}

/**
 * Los íconos de cajas y pestañas que declara un archivo.
 *
 * @return list<array{string, string}> [ícono, lugar]
 */
function iconosDelCodigo(string $codigo): array
{
    $encontrados = [];

    preg_match_all("/Tab::make\\('([^']*)'\\)(?:(?!;|Tab::make).)*?->icon\\('([^']+)'\\)/s", $codigo, $pestanas, PREG_SET_ORDER);
    foreach ($pestanas as $m) {
        $encontrados[] = [$m[2], "pestaña «{$m[1]}»"];
    }

    preg_match_all('/new CajaModulo\((.*?)\)\);/s', $codigo, $cajas, PREG_SET_ORDER);
    foreach ($cajas as $m) {
        if (preg_match("/icono: '([^']+)'/", $m[1], $icono) === 1) {
            $nombre = preg_match("/^\\s*'[^']*', '([^']*)'/", $m[1], $n) === 1 ? $n[1] : '?';
            $encontrados[] = [$icono[1], "caja «{$nombre}»"];
        }
    }

    if (preg_match('/\$navigationIcon = \'([^\']+)\'/', $codigo, $pantalla) === 1) {
        $encontrados[] = [$pantalla[1], 'pantalla'];
    }

    preg_match_all('/new PestanaFicha\((.*?)\)\)?;/s', $codigo, $deFicha, PREG_SET_ORDER);
    foreach ($deFicha as $m) {
        if (preg_match("/'((?:heroicon|tq)-[^']+)'/", $m[1], $icono) === 1) {
            $encontrados[] = [$icono[1], 'pestaña de ficha'];
        }
    }

    return $encontrados;
}

/** Una pantalla y la caja de su módulo comparten el ícono a propósito: son lo mismo (Personas y empresas, Mien…). */
function esPantallaConSuCaja(array $lugares): bool
{
    $tipos = array_map(fn (string $lugar): string => str_starts_with($lugar, 'caja ') ? 'caja' : (str_starts_with($lugar, 'pantalla') ? 'pantalla' : 'otro'), $lugares);
    sort($tipos);

    return $tipos === ['caja', 'pantalla'];
}

/*
 * El mismo ícono para el mismo concepto: permitido. Cada grupo dice qué representa y dónde está; un lugar nuevo con
 * ese ícono se agrega aquí solo si es el mismo concepto (y en interfaz.md, «Íconos»).
 */
const ICONOS_MISMO_CONCEPTO = [
    // Plan 2.20: la caja y la pantalla de Cuentas, el resumen de su ficha y su pestaña en Personas y empresas.
    'heroicon-o-building-storefront' => [
        'concepto' => 'Una cuenta del CRM',
        'lugares' => ['caja «Cuentas» (CuentasServiceProvider)', 'pantalla (CuentaResource)', 'pestaña de ficha (CuentasServiceProvider)', 'pestaña «Resumen» (CuentaResource)'],
    ],
    // Plan 2.20 (desarrollador, 2026-11-06): los contactos dejan `users` (Usuarios) y comparten este.
    'heroicon-o-user-circle' => [
        'concepto' => 'Los contactos de una ficha',
        'lugares' => ['pestaña «Contactos» (CuentaResource)', 'pestaña «Contactos» (PartyResource)'],
    ],
    'heroicon-o-building-office-2' => [
        'concepto' => 'Una unidad del organigrama',
        'lugares' => ['pantalla (UnidadOrganizativaResource)', 'pestaña «Roles por unidad» (UsuarioResource)'],
    ],
    'heroicon-o-identification' => [
        'concepto' => 'Los datos de una ficha (persona, usuario, unidad; los míos)',
        'lugares' => ['pantalla (MisDatos)', 'pestaña «Datos» (PartyResource)', 'pestaña «Datos» (ProyectoResource)', 'pestaña «Datos» (UnidadOrganizativaResource)', 'pestaña «Datos» (UsuarioResource)'],
    ],
    'tq-redes-sociales' => [
        'concepto' => 'Redes sociales',
        'lugares' => ['catálogo RedesSociales', 'pestaña «Redes» (PartyResource)'],
    ],
];

/*
 * Repetidos que ya existían con conceptos distintos (2026-11-05). No se cambian aquí: son deuda de la mejora visual, a
 * cargo de Sebastián al final (plan 2.20a, bitácora). Cuando se arregle uno, se saca de esta lista.
 */
const ICONOS_REPETIDOS_DEUDA = [
    'heroicon-o-map-pin' => ['catálogo Ubigeos', 'pestaña «Sedes» (PartyResource)'],
    'heroicon-o-sparkles' => ['pantalla (InteligenciaArtificial)', 'pantalla (VistaPreviaFechas)'],
    'heroicon-o-tag' => ['catálogo Cubso', 'pestaña «Tipos» (PartyResource)'],
];

test('un ícono no significa dos cosas: los repetidos son del mismo concepto o están anotados como deuda', function () {
    $repetidos = array_filter(
        iconosDelSistema(dirname(__DIR__, 2)),
        fn (array $lugares): bool => count($lugares) > 1 && ! esPantallaConSuCaja($lugares),
    );
    $repetidos = array_map(function (array $lugares): array {
        sort($lugares);

        return $lugares;
    }, $repetidos);
    ksort($repetidos);

    $esperados = [...array_map(fn (array $grupo): array => $grupo['lugares'], ICONOS_MISMO_CONCEPTO), ...ICONOS_REPETIDOS_DEUDA];
    ksort($esperados);

    // Igual, no «contenido en»: un repetido nuevo falla, y uno arreglado también hay que sacarlo de su lista.
    expect($repetidos)->toBe($esperados);
});

test('cada grupo permitido dice qué concepto representa, y ningún ícono está en las dos listas', function () {
    foreach (ICONOS_MISMO_CONCEPTO as $icono => $grupo) {
        expect(trim($grupo['concepto']))->not->toBe('', "{$icono} no dice qué concepto representa")
            ->and(count($grupo['lugares']))->toBeGreaterThan(1);
    }

    expect(array_intersect_key(ICONOS_MISMO_CONCEPTO, ICONOS_REPETIDOS_DEUDA))->toBe([]);
});

test('una pantalla puede compartir con su caja, y nada más', function () {
    expect(esPantallaConSuCaja(['caja «Mien» (NucleoServiceProvider)', 'pantalla (Mien)']))->toBeTrue()
        ->and(esPantallaConSuCaja(['pantalla (A)', 'pantalla (B)']))->toBeFalse()
        ->and(esPantallaConSuCaja(['caja «A» (X)', 'pantalla (A)', 'pestaña «Datos» (Y)']))->toBeFalse();
});

test('el lector de íconos reconoce cajas, pantallas, pestañas de Filament y pestañas de la ficha', function () {
    $codigo = <<<'PHP'
        $registro->registrar(new CajaModulo(
            'cuentas', 'Cuentas', '/cuentas', permiso: 'crm.cuentas.ver', area: AreaModulo::Crm, icono: 'heroicon-o-building-storefront',
        ));
        Tab::make('Contactos')->icon('heroicon-o-users')->schema([]);
        protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog';
        $pestanas->registrar(new PestanaFicha('cuenta', 'Cuenta comercial', 'crm.cuentas.ver', Componente::class, 'heroicon-o-star', tiposDocumento: [Catalogo::RUC]));
        PHP;

    expect(iconosDelCodigo($codigo))->toBe([
        ['heroicon-o-users', 'pestaña «Contactos»'],
        ['heroicon-o-building-storefront', 'caja «Cuentas»'],
        ['heroicon-o-cog', 'pantalla'],
        ['heroicon-o-star', 'pestaña de ficha'],
    ]);
});
