<?php

declare(strict_types=1);

/**
 * Corre solo las pruebas relacionadas con lo que cambió la rama (`composer check:tarea`): al cerrar cada tarea y al
 * cerrar la entrega. La corrida completa (`composer check`) va una sola vez por etapa del mapa (decisión del
 * desarrollador, 2026-11-12). Cuando corren
 * todas (o cuando a la tarea le toca más de PARTE_PARA_TODAS de los archivos), en paralelo: PROCESOS a la vez, cada uno con su propia base de pruebas (plan de pruebas en paralelo).
 *
 * Qué cambió: lo de la copia de trabajo desde su última corrida en verde de este script (con lo que no tiene commit y
 * los archivos nuevos), para que cada corrida pruebe solo lo tocado desde la anterior (plan 2026-11-11). Si no hay una
 * corrida verde registrada, o con `--base=<ref>`, lo de la rama desde que salió de esa base. La «foto» de la corrida
 * verde es el árbol completo de la copia, guardado como objeto de git; su referencia vive en la carpeta de git de la
 * copia (cada worktree tiene la suya y nunca entra a un commit). Solo se guarda si la corrida pasó sin argumentos extra
 * (un `--filter` en verde no prueba lo elegido); si falla, la siguiente vuelve a incluir todo desde la última verde.
 * Qué pruebas corre:
 *  - Una clase cambiada: las pruebas que la nombran, y las que nombran a las clases que la usan (un paso más allá:
 *    un servicio cambiado alcanza a la pantalla que lo llama y a la prueba de esa pantalla).
 *  - Una vista cambiada: las pruebas que nombran su carpeta (`filament/ambientacion/...` → «ambientacion»).
 *  - Una prueba cambiada: esa prueba.
 *  - Migraciones, seeders o traducciones de un módulo: todas las pruebas del módulo. Su proveedor cuenta como una clase
 *    más (cambia en casi todas las tareas): lo que se escape, lo atrapa la corrida completa de la etapa.
 *  - Algo de fuera de los módulos (config, rutas, `composer.json`, `tests/Pest.php`…): todas.
 *  - Siempre `tests/Arch` y `tests/Feature` (rápidas: límites entre módulos y el panel).
 *
 * Uso: php scripts/pruebas-de-la-tarea.php [--base=origin/main] [--listar] [otros argumentos para `artisan test`]
 *   --base=<ref>  ignora la última corrida verde y compara contra esa base (para revisar la tarea entera).
 * Sale con el código de las pruebas.
 */
/** Procesos a la vez (de los 8 núcleos del equipo de desarrollo, dos quedan para la app y Docker). Igual en composer.json. */
const PROCESOS = 6;

/**
 * Si a la tarea le toca más de esta parte de los archivos de prueba, corren todas en paralelo: tarda menos que esas en
 * un solo proceso (paratest acepta una sola ruta, así que una lista de archivos va en un proceso). Medido el
 * 2026-11-15 (plan 2.11): 175 de 227 archivos en un proceso, ~64 min; las 227 en paralelo, ~28 min. Un archivo tarda
 * en promedio ~20 s solo, así que desde ~60 archivos ya conviene todas en paralelo.
 */
const PARTE_PARA_TODAS = 0.25;

$raiz = getcwd();
$base = 'origin/main';
$baseForzada = false;
$soloListar = false;
$extra = [];

foreach (array_slice($argv, 1) as $argumento) {
    match (true) {
        str_starts_with($argumento, '--base=') => [$base, $baseForzada] = [substr($argumento, 7), true],
        $argumento === '--listar' => $soloListar = true,
        default => $extra[] = $argumento,
    };
}

/** @return list<string>|null las líneas de la salida, o null si git falló */
$gitOk = function (string $comando) use ($raiz): ?array {
    exec('git -C '.escapeshellarg($raiz).' '.$comando.' 2>/dev/null', $salida, $codigo);

    return $codigo === 0 ? array_values(array_filter($salida)) : null;
};

$git = fn (string $comando): array => $gitOk($comando) ?? [];

/** Ruta dentro de la carpeta de git de esta copia (en un worktree, la suya). */
$rutaGit = function (string $nombre) use ($git, $raiz): ?string {
    $ruta = $git('rev-parse --git-path '.escapeshellarg($nombre))[0] ?? null;

    return $ruta === null || preg_match('#^(/|[A-Za-z]:)#', $ruta) === 1 ? $ruta : $raiz.'/'.$ruta;
};

/** El árbol completo de la copia (con lo que no tiene commit y lo nuevo), sin tocar el índice de verdad. */
$foto = function () use ($gitOk, $rutaGit): ?string {
    $indice = $rutaGit('index');
    $temporal = $rutaGit('techquk-pruebas-indice');

    if ($temporal === null) {
        return null;
    }

    // Partir del índice real ahorra volver a leer los archivos que no cambiaron.
    if ($indice !== null && is_file($indice)) {
        copy($indice, $temporal);
    }

    putenv('GIT_INDEX_FILE='.$temporal);
    $arbol = $gitOk('add -A') === null ? null : ($gitOk('write-tree')[0] ?? null);
    putenv('GIT_INDEX_FILE');
    @unlink($temporal);

    return $arbol;
};

$marcador = $rutaGit('techquk-pruebas-ultima-verde');
$fotoActual = $foto();
$ultimaVerde = $marcador !== null && is_file($marcador) ? trim((string) file_get_contents($marcador)) : '';
$desdeVerde = ! $baseForzada && $fotoActual !== null && preg_match('/^[0-9a-f]{40,64}$/', $ultimaVerde) === 1
    && $gitOk('cat-file -e '.$ultimaVerde.'^{tree}') !== null;

if ($desdeVerde) {
    // Sin detección de renombres: el nombre viejo también cuenta (sus pruebas lo siguen nombrando).
    $cambiados = $git('diff --no-renames --name-only '.$ultimaVerde.' '.$fotoActual);
} else {
    $desde = $git('merge-base '.escapeshellarg($base).' HEAD')[0] ?? null;

    if ($desde === null) {
        fwrite(STDERR, "No encuentro la base «{$base}»: corro todas las pruebas.\n");
        $cambiados = null;
    } else {
        $cambiados = array_unique([...$git('diff --no-renames --name-only '.$desde), ...$git('ls-files --others --exclude-standard')]);
    }
}

if ($desdeVerde && $cambiados === []) {
    echo "Nada cambió desde la última corrida verde: no corro pruebas.\n";
    exit(0);
}

$todas = $cambiados === null;
$clases = [];
$palabras = [];
$pruebas = [];
$modulosEnteros = [];
$genericas = ['filament', 'components', 'partials', 'livewire', 'pages'];

foreach ($cambiados ?? [] as $archivo) {
    if (! file_exists($raiz.'/'.$archivo) && ! str_starts_with($archivo, 'app/') && ! str_starts_with($archivo, 'tests/')) {
        continue;
    }

    if (preg_match('#^tests/(Arch|Feature)/#', $archivo) === 1) {
        continue;
    }

    if (preg_match('#^tests/.+Test\.php$#', $archivo) === 1) {
        if (file_exists($raiz.'/'.$archivo)) {
            $pruebas[$archivo] = true;
        }

        continue;
    }

    if (preg_match('#^tests/Modules/([^/]+)/#', $archivo, $m) === 1) {
        // Fixtures, vistas de prueba y ayudantes del módulo.
        $modulosEnteros[$m[1]] = true;

        continue;
    }

    if (preg_match('#^app/Modules/([^/]+)/(.+)$#', $archivo, $m) === 1) {
        [, $modulo, $resto] = $m;

        if (preg_match('#^resources/views/(.+)\.blade\.php$#', $resto, $v) === 1) {
            $carpetas = array_values(array_diff(explode('/', dirname($v[1])), ['.', ...$genericas]));
            $palabras[$carpetas[0] ?? basename($v[1])] = true;
        } elseif (! str_ends_with($resto, '.php') || str_starts_with($resto, 'database/')) {
            $modulosEnteros[$modulo] = true;
        } else {
            $clases[basename($resto, '.php')] = true;
        }

        continue;
    }

    if (preg_match('#^(app|bootstrap|config|database|routes|resources|lang)/|^(composer\.(json|lock)|phpunit\.xml|tests/Pest\.php|tests/TestCase\.php)$#', $archivo) === 1) {
        $todas = true;
    }
}

/** @return list<string> los archivos PHP bajo la carpeta */
$php = function (string $carpeta) use ($raiz): array {
    if (! is_dir($raiz.'/'.$carpeta)) {
        return [];
    }

    $archivos = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/'.$carpeta, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() === 'php') {
            $archivos[] = substr($f->getPathname(), strlen($raiz) + 1);
        }
    }
    sort($archivos);

    return $archivos;
};

$nombra = fn (string $contenido, string $clase): bool => preg_match('/\b'.preg_quote($clase, '/').'\b/', $contenido) === 1;

// Un paso más allá: las clases que usan a las cambiadas.
if (! $todas && $clases !== []) {
    foreach ($php('app') as $archivo) {
        $contenido = (string) file_get_contents($raiz.'/'.$archivo);
        foreach (array_keys($clases) as $clase) {
            if ($nombra($contenido, $clase)) {
                $clases[basename($archivo, '.php')] ??= false;
                break;
            }
        }
    }
}

if (! $todas) {
    foreach ($php('tests') as $archivo) {
        if (! str_ends_with($archivo, 'Test.php') || preg_match('#^tests/(Arch|Feature)/#', $archivo) === 1) {
            continue;
        }

        if (preg_match('#^tests/Modules/([^/]+)/#', $archivo, $m) === 1 && isset($modulosEnteros[$m[1]])) {
            $pruebas[$archivo] = true;

            continue;
        }

        $contenido = (string) file_get_contents($raiz.'/'.$archivo);
        foreach (array_keys($clases) as $clase) {
            if ($nombra($contenido, (string) $clase)) {
                $pruebas[$archivo] = true;

                continue 2;
            }
        }
        foreach (array_keys($palabras) as $palabra) {
            if (stripos($contenido, (string) $palabra) !== false) {
                $pruebas[$archivo] = true;

                continue 2;
            }
        }
    }
}

$total = count(array_filter($php('tests'), fn (string $a): bool => str_ends_with($a, 'Test.php')));
$porMuchas = ! $todas && count($pruebas) > $total * PARTE_PARA_TODAS;
$objetivos = $todas || $porMuchas ? [] : ['tests/Arch', 'tests/Feature', ...array_keys($pruebas)];

echo $desdeVerde
    ? sprintf("Desde la última corrida verde: %d archivo(s) cambiado(s).\n", count($cambiados ?? []))
    : "Desde «{$base}» (no hay una corrida verde registrada, o se pidió --base).\n";
echo $todas
    ? "Cambió algo fuera de los módulos (o no hay base): corro todas las pruebas.\n"
    : sprintf("Pruebas de la tarea: %d de %d archivos, más tests/Arch y tests/Feature.\n", count($pruebas), $total);

if ($porMuchas) {
    printf("Son más de %d %% de las pruebas: corro todas en paralelo, que tarda menos.\n", (int) (PARTE_PARA_TODAS * 100));
}

if ($soloListar) {
    echo implode("\n", array_keys($pruebas)).($pruebas === [] ? '' : "\n");
    exit(0);
}

// En paralelo solo cuando corren todas: paratest acepta una sola ruta y con varios archivos falla («Too many
// arguments, expected arguments "path"», 2026-11-11). Los de una tarea van en un solo proceso.
$enParalelo = ($todas || $porMuchas) && ! in_array('--parallel', $extra, true) ? ['--parallel', '--processes='.PROCESOS] : [];

passthru(implode(' ', array_map('escapeshellarg', [PHP_BINARY, 'artisan', 'test', ...$enParalelo, ...$objetivos, ...$extra])), $codigo);

// La foto es de antes de correr: lo que se edite durante la corrida no quedó probado y entra en la siguiente.
if ($codigo === 0 && $extra === [] && $fotoActual !== null && $marcador !== null) {
    file_put_contents($marcador, $fotoActual."\n");
}

exit($codigo);
