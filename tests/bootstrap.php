<?php

declare(strict_types=1);

/*
 * Arranque de las pruebas. Además de cargar las clases, separa la base de pruebas de cada copia de trabajo (plan
 * 2026-11-04 «trabajo en paralelo»): con varias sesiones en la misma máquina, cada una en su `git worktree`, dos
 * corridas a la vez contra la misma base se rompen entre sí. En un worktree (su `.git` es un archivo, no una carpeta),
 * las bases llevan el nombre de su carpeta: `TechQuk-presupuesto` usa `techquk_test_presupuesto` y
 * `techquk_concurrencia_presupuesto`. La copia principal y la integración continua siguen con `techquk_test` y
 * `techquk_concurrencia`. Corre después de las variables de `phpunit.xml`, así que gana sobre ellas.
 */

require __DIR__.'/../vendor/autoload.php';

$raiz = dirname(__DIR__);

if (is_file($raiz.'/.git')) {
    $sufijo = '_'.trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) preg_replace('/^techquk-?/i', '', basename($raiz)))), '_');

    if ($sufijo !== '_') {
        foreach (['DB_DATABASE' => 'techquk_test'.$sufijo, 'TECHQUK_BASE_CONCURRENCIA' => 'techquk_concurrencia'.$sufijo] as $variable => $valor) {
            putenv("{$variable}={$valor}");
            $_ENV[$variable] = $valor;
            $_SERVER[$variable] = $valor;
        }
    }
}
