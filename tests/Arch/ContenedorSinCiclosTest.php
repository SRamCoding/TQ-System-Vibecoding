<?php

declare(strict_types=1);

/*
 * Ninguna clase de `app/` se pide a sí misma a través de su constructor (pasó el 2026-10-29: NotificacionesService →
 * ArchivoHistoricoService → AlertasService → NotificacionesService). Laravel no avisa un ciclo así: se queda armando las
 * clases sin fin hasta agotar la memoria y la app no arranca, sin decir por qué. Esta prueba arma el grafo con
 * reflexión, sin crear ningún objeto, y muestra la cadena. Para romper un ciclo, la dependencia que solo usa un método
 * va como parámetro de ese método (un comando la recibe en `handle()`), nunca escondida con `app()`.
 *
 * Su límite: sigue solo clases concretas. Un ciclo que pasa por una interfaz ligada en el contenedor
 * (`ClienteModeloLenguaje`, `FuentePadronContribuyentes`…) o por una clase abstracta no lo ve.
 */

/** @return array<class-string, list<class-string>> cada clase concreta de `app/` y las clases concretas que pide */
function contenedorGrafo(): array
{
    $raiz = dirname(__DIR__, 2).'/app';
    $grafo = [];
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));

    foreach ($archivos as $archivo) {
        if ($archivo->getExtension() !== 'php' || str_contains($archivo->getPathname(), '/database/') || str_contains($archivo->getPathname(), '/resources/')) {
            continue;
        }

        $clase = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($archivo->getPathname(), strlen($raiz) + 1));

        if (! class_exists($clase)) {
            continue;
        }

        $reflexion = new ReflectionClass($clase);

        if ($reflexion->isAbstract()) {
            continue;
        }

        $grafo[$clase] = [];

        foreach ($reflexion->getConstructor()?->getParameters() ?? [] as $parametro) {
            $tipo = $parametro->getType();

            if ($tipo instanceof ReflectionNamedType && ! $tipo->isBuiltin() && str_starts_with($tipo->getName(), 'App\\') && class_exists($tipo->getName())) {
                $grafo[$clase][] = $tipo->getName();
            }
        }
    }

    return $grafo;
}

/**
 * @param  array<class-string, list<class-string>>  $grafo
 * @return list<string> cada ciclo como «A → B → A»
 */
function contenedorCiclos(array $grafo): array
{
    $estado = [];
    $camino = [];
    $ciclos = [];

    $visitar = function (string $clase) use (&$visitar, &$estado, &$camino, &$ciclos, $grafo): void {
        $estado[$clase] = 'abierta';
        $camino[] = $clase;

        foreach ($grafo[$clase] ?? [] as $pide) {
            if (($estado[$pide] ?? null) === 'abierta') {
                $desde = array_search($pide, $camino, true);
                $ciclos[] = implode(' → ', array_map(fn (string $c): string => class_basename($c), [...array_slice($camino, (int) $desde), $pide]));
            } elseif (! isset($estado[$pide])) {
                $visitar($pide);
            }
        }

        array_pop($camino);
        $estado[$clase] = 'cerrada';
    };

    foreach (array_keys($grafo) as $clase) {
        if (! isset($estado[$clase])) {
            $visitar($clase);
        }
    }

    return $ciclos;
}

test('ninguna clase de la app se pide a sí misma por su constructor', function () {
    expect(contenedorCiclos(contenedorGrafo()))->toBe([]);
});

test('la prueba de ciclos encuentra uno armado a propósito', function () {
    expect(contenedorCiclos(['A\\Uno' => ['A\\Dos'], 'A\\Dos' => ['A\\Tres'], 'A\\Tres' => ['A\\Uno'], 'A\\Cuatro' => ['A\\Uno']]))
        ->toBe(['Uno → Dos → Tres → Uno']);
});
