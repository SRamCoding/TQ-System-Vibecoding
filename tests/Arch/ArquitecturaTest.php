<?php

declare(strict_types=1);

/*
 * Reglas de ARCHITECTURE.md verificadas automáticamente.
 * Si una prueba de aquí falla, no la desactives: corrige la dependencia o, si la regla está
 * mal, cámbiala en ARCHITECTURE.md con acuerdo del equipo y luego aquí.
 */

const HARNESS_MODULOS_BASE = 'App\\Modules';

/** Descubre módulos: Nucleo + Crm/* + Erp/* */
function harnessModulos(): array
{
    $raiz = dirname(__DIR__, 2).'/app/Modules';
    $modulos = is_dir("{$raiz}/Nucleo") ? ['Nucleo'] : [];

    foreach (['Crm', 'Erp'] as $grupo) {
        foreach (glob("{$raiz}/{$grupo}/*", GLOB_ONLYDIR) ?: [] as $dir) {
            $modulos[] = $grupo.'\\'.basename($dir);
        }
    }

    return $modulos;
}

/**
 * Lo que cada módulo de negocio expone a los demás (ARCHITECTURE.md, regla 2; desarrollador, 2026-11-07): sus Services/
 * y Events/, más los registros de extensión que declare públicos. Un módulo que no aparece expone solo Services/ y
 * Events/. Agregar una carpeta aquí es cambiar la regla: va con su motivo en ARCHITECTURE.md.
 */
const HARNESS_PUBLICAS = [
    'Crm\\Cuentas' => ['Services', 'Events', 'Actividad', 'Pestanas'],
    'Crm\\Oportunidades' => ['Services', 'Events', 'Requisitos', 'Impedimentos', 'Secciones', 'Pestanas'],
    'Erp\\Proyectos' => ['Services', 'Events', 'Pestanas', 'Resumen'],
];

/**
 * Lo que otro módulo no puede usar de este: sus carpetas que no son públicas y las clases sueltas en su raíz (el
 * provider). Con `$conModelos` en falso, deja fuera Models/ (un Models/ ajeno puede declarar relaciones con ellos).
 *
 * @return list<string>
 */
function harnessPrivadasDe(string $modulo, bool $conModelos = true): array
{
    $raiz = dirname(__DIR__, 2).'/app/Modules/'.str_replace('\\', '/', $modulo);
    $publicas = HARNESS_PUBLICAS[$modulo] ?? ['Services', 'Events'];
    $privadas = [];

    foreach (glob("{$raiz}/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $carpeta = basename($dir);

        if (! in_array($carpeta, $publicas, true) && ($conModelos || $carpeta !== 'Models') && $carpeta !== 'database' && $carpeta !== 'resources') {
            $privadas[] = HARNESS_MODULOS_BASE."\\{$modulo}\\{$carpeta}";
        }
    }

    foreach (glob("{$raiz}/*.php") ?: [] as $archivo) {
        $privadas[] = HARNESS_MODULOS_BASE."\\{$modulo}\\".basename($archivo, '.php');
    }

    return $privadas;
}

/** Capas de un módulo que NO pueden usar modelos de otro módulo (Models/ sí, para relaciones). */
const HARNESS_CAPAS = ['Services', 'Acciones', 'Efectos', 'Events', 'Listeners', 'Jobs', 'Policies', 'Filament', 'Http', 'Console'];

arch('código PHP sano')->preset()->php();
arch('sin funciones inseguras')->preset()->security();

arch('strict_types en todos los módulos')
    ->expect(HARNESS_MODULOS_BASE)
    ->toUseStrictTypes();

arch('los módulos no dependen de código fuera de app/Modules salvo framework')
    ->expect(HARNESS_MODULOS_BASE)
    ->not->toUse(['App\\Http\\Controllers', 'App\\Filament']);

foreach (harnessModulos() as $modulo) {
    $otros = array_values(array_filter(
        harnessModulos(),
        fn (string $m) => $m !== $modulo && $m !== 'Nucleo',
    ));

    if ($otros === [] || str_ends_with($modulo, 'Analitica')) {
        continue; // Analitica puede leer modelos de cualquiera (ver regla 5)
    }

    $modelosAjenos = array_map(fn (string $m) => HARNESS_MODULOS_BASE."\\{$m}\\Models", $otros);
    $internosAjenos = array_merge(
        array_map(fn (string $m) => HARNESS_MODULOS_BASE."\\{$m}\\Acciones", $otros),
        array_map(fn (string $m) => HARNESS_MODULOS_BASE."\\{$m}\\Filament", $otros),
        array_map(fn (string $m) => HARNESS_MODULOS_BASE."\\{$m}\\Jobs", $otros),
    );

    foreach (HARNESS_CAPAS as $capa) {
        arch("{$modulo}\\{$capa} no usa modelos de otros módulos (usa sus Services)")
            ->expect(HARNESS_MODULOS_BASE."\\{$modulo}\\{$capa}")
            ->not->toUse($modelosAjenos);
    }

    arch("{$modulo} no usa internos (Acciones/Filament/Jobs) de otros módulos")
        ->expect(HARNESS_MODULOS_BASE."\\{$modulo}")
        ->not->toUse($internosAjenos);
}

foreach (harnessModulos() as $modulo) {
    if ($modulo === 'Nucleo' || str_ends_with($modulo, 'Analitica')) {
        continue;
    }

    $otros = array_values(array_filter(harnessModulos(), fn (string $m): bool => $m !== $modulo && $m !== 'Nucleo'));
    $privadas = array_merge(...array_map(fn (string $m): array => harnessPrivadasDe($m), $otros ?: ['Nucleo']));
    $privadasSinModelos = array_merge(...array_map(fn (string $m): array => harnessPrivadasDe($m, conModelos: false), $otros ?: ['Nucleo']));

    if ($otros === []) {
        continue;
    }

    arch("{$modulo} usa de otros módulos solo lo que estos exponen (Services/, Events/ y sus registros públicos)")
        ->expect(HARNESS_MODULOS_BASE."\\{$modulo}")
        ->not->toUse($privadas)
        ->ignoring(HARNESS_MODULOS_BASE."\\{$modulo}\\Models");

    arch("{$modulo}\\Models usa de otros módulos solo lo público y sus modelos (para relaciones)")
        ->expect(HARNESS_MODULOS_BASE."\\{$modulo}\\Models")
        ->not->toUse($privadasSinModelos);
}

arch('el núcleo no depende de ningún módulo de negocio')
    ->expect(HARNESS_MODULOS_BASE.'\\Nucleo')
    ->not->toUse([HARNESS_MODULOS_BASE.'\\Crm', HARNESS_MODULOS_BASE.'\\Erp']);

arch('Analitica solo lee: no invoca servicios ni acciones de otros módulos')
    ->expect(HARNESS_MODULOS_BASE.'\\Erp\\Analitica')
    ->not->toUse(array_merge(
        ...array_map(
            fn (string $m) => [HARNESS_MODULOS_BASE."\\{$m}\\Services", HARNESS_MODULOS_BASE."\\{$m}\\Acciones"],
            array_filter(harnessModulos(), fn (string $m) => ! str_ends_with($m, 'Analitica')),
        ),
    ) ?: ['__ninguno__']);

arch('fuera del núcleo nadie escribe contactos, sedes ni cuentas bancarias sin pasar por sus acciones registradas (plan 1.3b, D7)')
    ->expect([
        HARNESS_MODULOS_BASE.'\\Nucleo\\Services\\ContactosPartyService',
        HARNESS_MODULOS_BASE.'\\Nucleo\\Services\\SedesPartyService',
        HARNESS_MODULOS_BASE.'\\Nucleo\\Services\\CuentasBancariasService',
    ])
    ->toOnlyBeUsedIn(HARNESS_MODULOS_BASE.'\\Nucleo');

arch('fuera del núcleo, sus acciones se ejecutan solo por su nombre con EjecutorAcciones (plan 1.3b)')
    // Llamar ejecutar() de la clase se salta el permiso, la validación y el autor de la auditoría: eso lo hace
    // EjecutorAcciones. Otro módulo la pide por su clave («nucleo.parties.agregar-contacto»), nunca por su clase.
    ->expect(HARNESS_MODULOS_BASE.'\\Nucleo\\Acciones')
    ->toOnlyBeUsedIn(HARNESS_MODULOS_BASE.'\\Nucleo');

/**
 * Escrituras a un modelo del núcleo en un archivo de otro módulo (plan 1.4, paso 1; ARCHITECTURE.md regla 1: sus
 * modelos se usan para relaciones y lectura). Detecta `new Modelo`, los métodos estáticos que escriben y las cadenas
 * `Modelo::...->update()/delete()`. No ve `$party->save()` sobre una instancia leída: esa la frenan la revisión
 * y que los servicios del núcleo sean la única forma documentada de cambiar sus datos.
 *
 * @return list<string>
 */
function harnessEscriturasANucleo(string $codigo): array
{
    preg_match_all('/^use\s+App\\\\Modules\\\\Nucleo\\\\Models\\\\(\w+)(?:\s+as\s+(\w+))?;/m', $codigo, $usos, PREG_SET_ORDER);
    $escribe = 'create|forceCreate|firstOrCreate|updateOrCreate|createOrFirst|createQuietly|insert\w*|upsert|destroy|truncate';
    $encadenado = 'update|delete|forceDelete|increment|decrement|insert\w*|upsert';
    $hallazgos = [];

    foreach ($usos as $uso) {
        $nombre = $uso[2] ?? $uso[1];
        $patrones = [
            "/\\bnew\\s+{$nombre}\\b/",
            "/\\b{$nombre}::(?:{$escribe})\\s*\\(/",
            "/\\b{$nombre}::[^;]*->\\s*(?:{$encadenado})\\s*\\(/",
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $codigo, $coincidencia)) {
                $hallazgos[] = $coincidencia[0];
            }
        }
    }

    return $hallazgos;
}

test('el detector de escrituras al núcleo reconoce las formas que vigila', function () {
    $codigo = <<<'PHP'
        use App\Modules\Nucleo\Models\Party;
        use App\Modules\Nucleo\Models\User as Usuario;
        $a = new Party;
        Usuario::create(['name' => 'x']);
        Party::query()->where('id', 1)->update(['razon_social' => 'x']);
        $b = Party::query()->where('id', 1)->first();
        PHP;

    expect(harnessEscriturasANucleo($codigo))->toHaveCount(3);
});

test('fuera del núcleo nadie escribe modelos del núcleo: usa sus acciones o servicios (plan 1.4)', function () {
    $raiz = dirname(__DIR__, 2).'/app/Modules';
    $errores = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (! str_ends_with($ruta, '.php') || str_starts_with($ruta, "{$raiz}/Nucleo/")) {
            continue;
        }

        foreach (harnessEscriturasANucleo((string) file_get_contents($ruta)) as $hallazgo) {
            $errores[] = substr($ruta, strlen($raiz) + 1).": {$hallazgo}";
        }
    }

    expect($errores)->toBe([]);
});

/**
 * Tablas de documentos con estado y de sus partes (modelos con TieneEstados o ParteDeDocumento), con el nombre corto
 * de su modelo (plan 1.5, D8).
 *
 * @return array<string, string> tabla => clase corta
 */
function harnessTablasConEstado(string $raiz): array
{
    $tablas = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $codigo = (string) file_get_contents($archivo->getPathname());

        if (! str_ends_with($archivo->getPathname(), '.php') || ! preg_match('/^\s+use\s+[^;]*\b(?:TieneEstados|ParteDeDocumento)\b[^;]*;/m', $codigo)
            || ! preg_match('/^namespace\s+([\w\\\\]+);/m', $codigo, $espacio) || ! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $codigo, $clase)) {
            continue;
        }

        $nombre = "{$espacio[1]}\\{$clase[1]}";
        $tablas[(new $nombre)->getTable()] = $clase[1];
    }

    ksort($tablas);

    return $tablas;
}

/**
 * Escrituras que se saltan los modelos de un documento con estado y, con ellos, el bloqueo de edición: el SQL a mano
 * (`DB::table('ordenes')->update()`, `DB::update('UPDATE ordenes …')`) y la actualización masiva de Eloquent
 * (`Orden::query()->where(…)->update()`), que no dispara los eventos del modelo. No ve la masiva por una relación
 * (`$orden->lineas()->update()`): esa la frena la revisión.
 *
 * @param  array<string, string>  $tablas  tabla => clase corta
 * @return list<string>
 */
function harnessEscriturasCrudas(string $codigo, array $tablas): array
{
    if ($tablas === []) {
        return [];
    }

    $tabla = implode('|', array_map(fn (string $t): string => preg_quote($t, '/'), array_keys($tablas)));
    $clase = implode('|', array_map(fn (string $c): string => preg_quote($c, '/'), $tablas));
    $masiva = 'update|updateOrInsert|upsert|insert\w*|delete|forceDelete|increment|decrement|truncate';
    $hallazgos = [];

    preg_match_all("/DB::table\\(\\s*['\"](?:{$tabla})['\"]\\s*\\)[^;]*->\\s*(?:{$masiva})\\s*\\(/", $codigo, $porTabla);
    preg_match_all("/DB::(?:update|delete|insert|statement|unprepared)\\(\\s*['\"][^;]*\\b(?:{$tabla})\\b/i", $codigo, $porSql);
    preg_match_all("/\\b(?:{$clase})::[^;]*->\\s*(?:{$masiva})\\s*\\(/", $codigo, $porModelo);

    // Sobre una fila ya leída (`find`, `first`, `sole`) la escritura pasa por el modelo y su bloqueo.
    $masivas = array_filter($porModelo[0], fn (string $hallado): bool => ! preg_match('/->\s*(?:find\w*|first\w*|sole)\s*\(/', $hallado));

    return [...$porTabla[0], ...$porSql[0], ...array_values($masivas)];
}

test('el detector de escrituras crudas a documentos con estado reconoce las formas que vigila', function () {
    $codigo = <<<'PHP'
        DB::table('ordenes')->where('id', 1)->update(['total' => '1.00']);
        DB::update('UPDATE ordenes SET total = 0 WHERE id = ?', [1]);
        DB::table('lineas_orden')->where('orden_id', 1)->delete();
        Orden::query()->where('proveedor_id', 2)->update(['total' => '1.00']);
        Orden::query()->findOrFail(1)->update(['total' => '1.00']);
        DB::table('partidas')->update(['monto' => '0.00']);
        PHP;

    expect(harnessEscriturasCrudas($codigo, ['ordenes' => 'Orden', 'lineas_orden' => 'LineaOrden']))->toHaveCount(4)
        ->and(harnessTablasConEstado(dirname(__DIR__).'/Modules/Nucleo/Fixtures'))
        ->toBe(['documentos_prueba' => 'DocumentoPrueba', 'documentos_versionados_prueba' => 'DocumentoVersionadoPrueba', 'lineas_documento_prueba' => 'LineaDocumentoPrueba']);
});

test('nadie escribe un documento con estado ni sus partes sin pasar por su modelo (plan 1.5, D8)', function () {
    $raiz = dirname(__DIR__, 2).'/app/Modules';
    $tablas = harnessTablasConEstado($raiz);
    $errores = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        // Las migraciones escriben DDL sobre esas tablas (`ALTER TABLE … ADD CONSTRAINT`): es lo que pide la convención.
        if (! str_ends_with($ruta, '.php') || str_contains($ruta, '/database/migrations/')) {
            continue;
        }

        foreach (harnessEscriturasCrudas((string) file_get_contents($ruta), $tablas) as $hallazgo) {
            $errores[] = substr($ruta, strlen($raiz) + 1).": {$hallazgo}";
        }
    }

    expect($errores)->toBe([]);
});

test('solo las acciones de transición dejan el permiso al servicio (permisoPorAmbito, plan 1.5)', function () {
    $raiz = dirname(__DIR__, 2).'/app/Modules';
    $permitidas = ['Nucleo/RegistroAcciones/Accion.php', 'Nucleo/Acciones/Estados/AccionTransicion.php'];
    $encontradas = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (str_ends_with($ruta, '.php') && str_contains((string) file_get_contents($ruta), 'function permisoPorAmbito(')) {
            $encontradas[] = substr($ruta, strlen($raiz) + 1);
        }
    }

    // Otra acción que lo necesite debe llamar a un servicio que revise el permiso con ámbitos; agrégala aquí a propósito.
    expect($encontradas)->toEqualCanonicalizing($permitidas);
});

arch('las versiones en memoria de C1 y C2 solo las usa la página de modo desarrollo del selector (plan 2.2, D8)')
    // En la app solo se usan en pruebas y en esa página; nunca se registran en el contenedor.
    ->expect([
        HARNESS_MODULOS_BASE.'\\Erp\\Presupuestos\\Services\\EnMemoria',
        HARNESS_MODULOS_BASE.'\\Erp\\Proyectos\\Services\\EnMemoria',
    ])
    ->toOnlyBeUsedIn([
        HARNESS_MODULOS_BASE.'\\Erp\\Presupuestos\\Services\\EnMemoria',
        HARNESS_MODULOS_BASE.'\\Erp\\Presupuestos\\Filament\\Desarrollo\\ProbarSelectorImputacion',
    ]);
