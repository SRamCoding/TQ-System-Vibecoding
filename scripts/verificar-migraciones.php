<?php

declare(strict_types=1);

/**
 * Verifica las convenciones de docs/convenciones/base-de-datos.md en las migraciones
 * de los módulos (app/Modules/<Modulo>/database/migrations) y en database/migrations (tablas de
 * framework y paquetes: mismas reglas, salvo auditoría y forma de la llave primaria, que son del
 * framework o del paquete).
 *
 * Uso: php scripts/verificar-migraciones.php
 * Sale con 1 si encuentra violaciones (para CI y para `composer check`).
 *
 * Excepción de auditoría: un comentario `// harness:sin-auditoria <motivo>` dentro del
 * bloque Schema::create correspondiente.
 */
$raiz = dirname(__DIR__);
$carpetas = array_values(array_filter([$raiz.'/app/Modules', $raiz.'/database/migrations'], 'is_dir'));

if ($carpetas === []) {
    echo "Sin migraciones todavía. Nada que verificar.\n";
    exit(0);
}

$prohibidos = [
    '/->\s*(float|double|unsignedFloat|unsignedDouble)\s*\(/' => 'float/double prohibido: usa decimal(14,2) para dinero',
    '/->\s*char\s*\(/' => 'char prohibido: usa string()',
    '/->\s*binary\s*\(/' => 'binarios en la base prohibidos: guarda la ruta del archivo (M7 Adjuntos)',
    '/DB::unprepared\s*\(|CREATE\s+(OR\s+REPLACE\s+)?(FUNCTION|TRIGGER|PROCEDURE)/i' => 'lógica en la base prohibida: va en PHP',
    '/[\'"]empresa_id[\'"]/' => 'empresa_id prohibido: el sistema es de una sola empresa (ADR 0003)',
];

// Forma de la llave primaria: solo en módulos (framework y paquetes traen la suya).
$prohibidosEnModulos = [
    '/->\s*primary\s*\(/' => 'la llave primaria es siempre id(): el código de negocio va con unique()',
    '/->\s*(uuid|ulid)\s*\(\s*[\'"]id[\'"]/' => 'la llave primaria es id() autoincremental, no uuid/ulid',
];

$archivos = [];
foreach ($carpetas as $carpeta) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $archivos[] = str_replace('\\', '/', $archivo->getPathname());
    }
}
$errores = [];
$revisados = 0;

foreach ($archivos as $ruta) {
    if (! str_contains($ruta, '/database/migrations/') || ! str_ends_with($ruta, '.php')) {
        continue;
    }
    $revisados++;
    $relativa = substr($ruta, strlen(str_replace('\\', '/', $raiz)) + 1);
    $esDeModulo = str_starts_with($relativa, 'app/Modules/');
    $contenido = (string) file_get_contents($ruta);
    $lineas = explode("\n", $contenido);

    foreach ($lineas as $i => $linea) {
        if (preg_match('/^\s*(\/\/|\*|#)/', $linea)) {
            continue; // comentarios
        }
        foreach ($esDeModulo ? [...$prohibidos, ...$prohibidosEnModulos] : $prohibidos as $patron => $mensaje) {
            if (preg_match($patron, $linea)) {
                $errores[] = sprintf('%s:%d  %s', $relativa, $i + 1, $mensaje);
            }
        }
    }

    // Índice obligatorio en llaves foráneas (PostgreSQL no lo crea solo).
    $offset = 0;
    while (preg_match('/Schema::(create|table)\(/', $contenido, $mm, PREG_OFFSET_CAPTURE, $offset)) {
        $pos = $mm[0][1];
        $siguiente = strpos($contenido, 'Schema::', $pos + 10);
        $bloque = substr($contenido, $pos, $siguiente === false ? null : $siguiente - $pos);
        $lineaBloque = substr_count(substr($contenido, 0, $pos), "\n") + 1;
        $cursor = 0;
        $partes = explode(';', $bloque);
        foreach ($partes as $k => $sentencia) {
            $resto = isset($partes[$k + 1]) ? (string) strtok($partes[$k + 1], "\n") : '';
            // ->change() modifica una columna que ya existe; su índice se revisó al crearla.
            if (! preg_match('/->\s*change\s*\(/', $sentencia) && preg_match('/->\s*(?:foreignId|foreign)\s*\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $sentencia, $fk)) {
                $col = $fk[1];
                $conIndice = preg_match('/->\s*(index|unique|primary)\s*\(/', $sentencia)
                    || str_contains($sentencia.$resto, 'harness:sin-indice')
                    || preg_match('/->\s*(index|unique)\s*\(\s*\[?\s*[\'"]'.preg_quote($col, '/').'[\'"]/', $bloque);
                if (! $conIndice) {
                    $linea = $lineaBloque + substr_count(substr($bloque, 0, strpos($bloque, $sentencia, $cursor)), "\n") + substr_count(ltrim($sentencia, "\n"), "\n", 0, 0);
                    $errores[] = sprintf('%s:%d  llave foránea "%s" sin índice: agrega ->index() (o // harness:sin-indice <motivo>)', $relativa, $linea, $col);
                }
            }
            $cursor += strlen($sentencia) + 1;
        }
        $offset = $pos + 10;
    }

    // Auditoría: cada Schema::create debe llamar ->auditoria() o declarar la excepción.
    $offset = 0;
    while (($pos = strpos($contenido, 'Schema::create(', $offset)) !== false) {
        $siguiente = strpos($contenido, 'Schema::', $pos + 15);
        $bloque = substr($contenido, $pos, $siguiente === false ? null : $siguiente - $pos);
        $linea = substr_count(substr($contenido, 0, $pos), "\n") + 1;
        preg_match('/Schema::create\(\s*[\'"]([^\'"]+)/', $bloque, $m);
        $tabla = $m[1] ?? '?';
        $exenta = ! $esDeModulo || str_contains($bloque, 'harness:sin-auditoria');

        if (! $exenta && ! preg_match('/->\s*auditoria\s*\(/', $bloque)) {
            $errores[] = sprintf('%s:%d  tabla "%s" sin $table->auditoria() (o marca // harness:sin-auditoria <motivo>)', $relativa, $linea, $tabla);
        }
        if (! $exenta && preg_match('/->\s*timestamps(Tz)?\s*\(/', $bloque)) {
            $errores[] = sprintf('%s:%d  tabla "%s" usa timestamps(): usa auditoria(), que incluye created_by/updated_by', $relativa, $linea, $tabla);
        }
        $offset = $pos + 15;
    }
}

if ($errores !== []) {
    echo "Violaciones de convenciones en migraciones (docs/convenciones/base-de-datos.md):\n\n";
    foreach ($errores as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n".count($errores)." problema(s) en {$revisados} migración(es).\n";
    exit(1);
}

echo "✓ {$revisados} migración(es) cumplen las convenciones.\n";
exit(0);
