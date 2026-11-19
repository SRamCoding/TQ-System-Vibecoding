<?php

declare(strict_types=1);

/*
 * Plan 2.0, D4 (desarrollador, 2026-11-15): ningún formulario arma un selector de catálogo a mano. Todos usan el
 * común (`Campo::elementoDe`/`listaDe` en una acción, o `SelectorCatalogo::de` en un formulario suelto), que busca en
 * la base, trae el valor actual desactivado y ofrece el «+». Lee el código: si aparece `CatalogosService::opciones`
 * fuera de su servicio, alguien está llenando un `Select` con la lista entera. Los filtros de tabla usan `paraFiltro`.
 * Es parcial: no ve un `Select` armado con una consulta propia al modelo del catálogo; eso lo cuida la revisión. La
 * única excepción conocida es `tipo_motivo_id` de MotivoResource: elige una familia de motivos, que no es un catálogo M5.
 */

test('nadie llena un selector con CatalogosService::opciones: va el selector de catálogo común', function () {
    $raiz = dirname(__DIR__, 2).'/app';
    $culpables = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());

        if (! str_ends_with($ruta, '.php') || str_ends_with($ruta, 'Nucleo/Services/CatalogosService.php')) {
            continue;
        }

        if (preg_match('/->opciones\(\s*(Catalogo::|\$catalogo|\$campo->catalogo)/', (string) file_get_contents($ruta)) === 1) {
            $culpables[] = substr($ruta, strlen($raiz) + 1);
        }
    }

    expect($culpables)->toBe([]);
});
