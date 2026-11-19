<?php

declare(strict_types=1);

/*
 * Plan 2.20a, N1 (revisión de arquitectura): `parties.nombre_normalizado` y `nombre_comercial_normalizado` los llena el
 * modelo Party al guardar. Una escritura que no pase por él (DB::table, o un update/insert/upsert masivo de Eloquent)
 * dejaría la razón social nueva con el normalizado viejo, y el aviso de nombres parecidos fallaría en silencio. Ningún
 * archivo de app/ escribe así `parties`, salvo la migración que completó las columnas una vez.
 */

/** @return list<string> lo que escribe `parties` sin el modelo, como «archivo: lo encontrado» */
function escriturasDePartiesSinModelo(string $codigo): array
{
    $patrones = [
        "/DB::table\\(\\s*'parties'\\s*\\)(?:(?!;).)*?->(?:update|insert|insertOrIgnore|upsert|delete|increment|decrement)\\(/s",
        '/Party::query\\(\\)(?:(?!;).)*?->(?:update|insert|insertOrIgnore|upsert|increment|decrement)\\(/s',
        '/Party::(?:insert|upsert|where[A-Za-z]*)\\((?:(?!;).)*?->(?:update|insert|upsert)\\(/s',
    ];
    $encontradas = [];

    foreach ($patrones as $patron) {
        preg_match_all($patron, $codigo, $m);
        array_push($encontradas, ...$m[0]);
    }

    return array_map(fn (string $s): string => mb_substr((string) preg_replace('/\s+/', ' ', $s), 0, 80), $encontradas);
}

test('nada en app/ escribe parties sin pasar por el modelo, salvo la migración de los nombres normalizados', function () {
    $raiz = dirname(__DIR__, 2).'/app';
    $permitida = 'Modules/Nucleo/database/migrations/2026_11_05_000003_agregar_nombres_normalizados_a_parties.php';
    $encontradas = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());
        $relativa = substr($ruta, strlen($raiz) + 1);

        if (! str_ends_with($ruta, '.php') || $relativa === $permitida) {
            continue;
        }

        foreach (escriturasDePartiesSinModelo((string) file_get_contents($ruta)) as $hallazgo) {
            $encontradas[] = "{$relativa}: {$hallazgo}";
        }
    }

    expect($encontradas)->toBe([]);
});

test('el detector reconoce las escrituras masivas y deja pasar las lecturas y el modelo', function () {
    expect(escriturasDePartiesSinModelo("DB::table('parties')->where('id', 1)->update(['razon_social' => 'X']);"))->toHaveCount(1)
        ->and(escriturasDePartiesSinModelo("Party::query()->whereKey(1)->update(['razon_social' => 'X']);"))->toHaveCount(1)
        ->and(escriturasDePartiesSinModelo("Party::where('id', 1)->update(['razon_social' => 'X']);"))->toHaveCount(1)
        ->and(escriturasDePartiesSinModelo("DB::table('parties')->insert(['razon_social' => 'X']);"))->toHaveCount(1)
        ->and(escriturasDePartiesSinModelo("DB::table('parties')->where('id', 1)->value('razon_social');"))->toBe([])
        ->and(escriturasDePartiesSinModelo('Party::query()->lockForUpdate()->find($id); $party->save();'))->toBe([]);
});
