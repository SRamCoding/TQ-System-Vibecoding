<?php

declare(strict_types=1);

/*
 * Crea una copia de trabajo para una sesión en paralelo (plan 2026-11-04 «trabajo en paralelo»): un `git worktree`
 * hermano de esta carpeta, en `main` al día y sin rama (la sesión crea la de su tarea), con su `.env`, sus
 * dependencias y su base de pruebas propia (la nombra tests/bootstrap.php por la carpeta).
 *
 * Uso, desde la carpeta principal:   php scripts/nueva-copia.php presupuesto
 * Crea ../TechQuk-presupuesto y la base techquk_test_presupuesto. Si la copia ya existe, no hace nada.
 */

$nombre = strtolower(trim($argv[1] ?? ''));

if (! preg_match('/^[a-z][a-z0-9-]{1,30}$/', $nombre)) {
    fwrite(STDERR, "Uso: php scripts/nueva-copia.php <nombre>   (minúsculas, números y guiones; por ejemplo: presupuesto)\n");
    exit(1);
}

$principal = dirname(__DIR__);
$copia = dirname($principal).'/TechQuk-'.$nombre;
$base = 'techquk_test_'.str_replace('-', '_', $nombre);

if (file_exists($copia)) {
    fwrite(STDERR, "Ya existe {$copia}: no se toca.\n");
    exit(1);
}

/** Corre un comando en una carpeta y se detiene si falla. */
function correr(string $comando, string $carpeta): void
{
    echo "\n> {$comando}\n";
    passthru('cd '.escapeshellarg($carpeta).' && '.$comando, $codigo);
    if ($codigo !== 0) {
        fwrite(STDERR, "Falló: {$comando}\n");
        exit($codigo);
    }
}

correr('git fetch origin', $principal);
correr('git worktree add --detach '.escapeshellarg($copia).' origin/main', $principal);

if (! copy($principal.'/.env', $copia.'/.env')) {
    fwrite(STDERR, "No se pudo copiar el .env: cópialo a mano a {$copia}/.env\n");
    exit(1);
}
echo "\n.env copiado.\n";

correr('composer install --no-interaction', $copia);

// La base de pruebas de la copia, con la conexión de desarrollo (no la crea si ya existe).
$crear = 'echo DB::selectOne("SELECT 1 AS si FROM pg_database WHERE datname = ?", ["'.$base.'"]) ? "ya existía" : (DB::statement("CREATE DATABASE '.$base.'") ? "creada" : "no se pudo");';
correr('php artisan tinker --execute='.escapeshellarg($crear), $copia);

echo <<<TXT


Lista: {$copia}
Base de pruebas: {$base} (las corridas en paralelo crean sus propias {$base}_test_N).

Para trabajar ahí: abre otra terminal, entra a la carpeta y abre Claude Code:
  cd {$copia}
  claude

Para quitarla cuando ya no se use (desde la carpeta principal):
  git worktree remove {$copia}

TXT;
