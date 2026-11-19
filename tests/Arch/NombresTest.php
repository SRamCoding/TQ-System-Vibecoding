<?php

declare(strict_types=1);

/*
 * Producción corre en Linux, que distingue mayúsculas. Windows no. Esta prueba falla si el namespace
 * y el nombre de una clase no coinciden exactamente con su carpeta y su archivo (PSR-4).
 */

function harnessErroresDeNombres(string $raizApp): array
{
    $errores = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizApp, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());
        if (! str_ends_with($ruta, '.php')) {
            continue;
        }
        $codigo = (string) file_get_contents($ruta);
        if (! preg_match('/^namespace\s+([^;]+);/m', $codigo, $ns)) {
            continue;
        }
        if (! preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $codigo, $cl)) {
            continue;
        }
        $esperada = 'app/'.str_replace('\\', '/', preg_replace('/^App\\\\/', '', $ns[1])).'/'.$cl[1].'.php';
        $relativa = substr($ruta, strrpos($ruta, '/app/') + 1);
        if ($relativa !== $esperada) {
            $errores[] = "{$relativa} debería llamarse {$esperada}";
        }
    }

    return $errores;
}

test('namespace, clase, carpeta y archivo coinciden con mayúsculas exactas', function () {
    expect(harnessErroresDeNombres(dirname(__DIR__, 2).'/app'))->toBe([]);
});
