<?php

declare(strict_types=1);

/*
 * Plan 1.4f, D2 y D12: queda un solo modo. Ni el código, ni las vistas, ni la configuración, ni las migraciones, ni las
 * pruebas nombran el modo paneles, el modo modular o la animación entre modos de la 1.3c. Solo la historia de docs/.
 */

test('no queda rastro del modo de navegación ni de la animación entre modos', function () {
    $prohibidas = [
        'ModoNavegacion', 'modo_navegacion', 'cambiar-modo-navegacion', 'cambiarModo', 'esModular', 'modular()',
        'data-enlace-modulo', 'transicion-modos', 'claveParaUrl', 'enlazarConSusCajas', 'visiblesPara',
        'modo paneles', 'modo modular', 'entre modos',
    ];
    $hallazgos = [];

    foreach (['app', 'resources', 'config', 'database', 'routes', 'lang', 'tests'] as $carpeta) {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($carpeta), FilesystemIterator::SKIP_DOTS));

        foreach ($archivos as $archivo) {
            if (! $archivo instanceof SplFileInfo || ! $archivo->isFile() || $archivo->getRealPath() === __FILE__) {
                continue;
            }

            $contenido = mb_strtolower((string) file_get_contents($archivo->getPathname()));

            foreach ($prohibidas as $palabra) {
                if (str_contains($contenido, mb_strtolower($palabra))) {
                    $hallazgos[] = str_replace(base_path().'/', '', $archivo->getPathname())." → {$palabra}";
                }
            }
        }
    }

    expect($hallazgos)->toBe([]);
});
