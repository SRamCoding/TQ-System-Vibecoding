<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\FrasesSaludoSeeder;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\MomentoDia;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\FraseSaludo;

/*
 * Plan 1.4b, paso 2: las frases de inicio. Se puede repetir, no pisa lo editado, cubre cada idioma y momento, y
 * cada cita lleva su fuente (los versículos, su versión).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(CatalogosSeeder::class);
    test()->seed(FrasesSaludoSeeder::class);
});

test('hay un saludo para cada idioma y cada momento del día, sin otra condición', function (IdiomaSaludo $idioma, MomentoDia $momento) {
    expect(FraseSaludo::query()->where('tipo', 'saludo')->where('idioma', $idioma)->where('momento', $momento)
        ->whereNull('dia_semana')->whereNull('area')->whereNull('ocasion')->exists())->toBeTrue();
})->with(IdiomaSaludo::cases())->with(MomentoDia::cases());

test('las cuatro categorías tienen frases, y hay frases en los cinco idiomas', function () {
    $frases = FraseSaludo::query()->where('tipo', 'frase')->with('categoria')->get();

    expect($frases->map(fn (FraseSaludo $f): string => (string) $f->categoria?->codigo)->unique()->sort()->values()->all())
        ->toBe(['autores', 'propias', 'refranes', 'versiculos'])
        ->and($frases->map(fn (FraseSaludo $f): string => $f->idioma->value)->unique()->sort()->values()->all())
        ->toBe(['de', 'en', 'es', 'fr', 'pt']);
});

test('toda cita lleva su fuente, y todo versículo su referencia y versión', function () {
    $frases = FraseSaludo::query()->where('tipo', 'frase')->with('categoria')->get();

    foreach ($frases as $frase) {
        $categoria = $frase->categoria?->codigo;

        if ($categoria === 'versiculos') {
            expect($frase->fuente)->toMatch('/^\S.* \d+:\d+(-\d+)? \((RVR1960|KJV|LSG|BPM)\)$|^\S.* \d+,\d+(-\d+)? \(Luther 1912\)$/');
        } elseif ($categoria !== 'propias') {
            expect($frase->fuente)->not->toBeNull();
        }
    }
});

test('el versículo de ejemplo del plan está con su texto exacto', function () {
    expect(FraseSaludo::query()->where('codigo', 'versiculo-pr16-3-es')->value('texto'))
        ->toBe('Encomienda a Jehová tus obras, Y tus pensamientos serán afirmados.');
});

test('todo saludo lleva {nombre}', function () {
    FraseSaludo::query()->where('tipo', TipoFraseSaludo::Saludo)->get()
        ->each(fn (FraseSaludo $saludo) => expect($saludo->texto)->toContain('{nombre}'));
});

test('se puede repetir sin duplicar y no pisa lo editado en la pantalla', function () {
    $total = FraseSaludo::query()->count();
    FraseSaludo::query()->where('codigo', 'propia-dia-es')->update(['texto' => 'Editada por alguien']);

    test()->seed(FrasesSaludoSeeder::class);

    expect(FraseSaludo::query()->count())->toBe($total)
        ->and(FraseSaludo::query()->where('codigo', 'propia-dia-es')->value('texto'))->toBe('Editada por alguien');
});

test('el archivo de frases no repite códigos ni textos: un código repetido se saltaría sin aviso', function () {
    $datos = require base_path('app/Modules/Nucleo/database/data/frases-saludo.php');
    $filas = [...$datos['saludos'], ...array_merge(...array_values($datos['frases'])), ...$datos['agradecimientos']];
    $codigos = array_column($filas, 0);
    $textos = array_column($filas, 1);

    expect(array_diff_assoc($codigos, array_unique($codigos)))->toBe([])
        ->and(array_diff_assoc($textos, array_unique($textos)))->toBe([])
        ->and(FraseSaludo::query()->count())->toBe(count($filas));
});

test('hay variedad suficiente: al menos 40 frases generales en español y 5 por momento del día', function () {
    $generales = fn () => FraseSaludo::query()->where('tipo', 'frase')->where('idioma', 'es')->whereNull('dia_semana')->whereNull('ocasion')->whereNull('area');

    expect($generales()->whereNull('momento')->count())->toBeGreaterThanOrEqual(40);

    foreach (MomentoDia::cases() as $momento) {
        expect($generales()->where('momento', $momento)->count())->toBeGreaterThanOrEqual(5);
    }
});
