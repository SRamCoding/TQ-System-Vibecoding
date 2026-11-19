<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Filament\Pages\VistaPreviaFechas;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;
use App\Modules\Nucleo\Saludo\RotacionAnual;
use App\Modules\Nucleo\Saludo\VarianteGranMomento;
use App\Modules\Nucleo\Services\GranMomentoService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Plan 1.4d, D7 y D8: la rotación por año que no repite la elección del año anterior, el registro de variantes y la
 * vista previa del gran momento, que no guarda nada.
 */

beforeEach(function () {
    // La vista previa vive en el modo desarrollo (plan 1.4i, D4): estas pruebas lo prenden, salvo las que lo comprueban.
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
    session()->put('nucleo.modo_desarrollo.activo', true);
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

test('la rotación nunca repite lo del año anterior, aunque la lista haya cambiado', function () {
    foreach (range(2026, 2060) as $anio) {
        $anterior = RotacionAnual::elegir(['a', 'b'], $anio - 1, 'navidad');

        // Con la misma lista, con un elemento más o con otro orden, el año siguiente no repite.
        foreach ([['a', 'b'], ['a', 'b', 'c'], ['c', 'a', 'b'], ['b', 'c']] as $lista) {
            expect(RotacionAnual::elegir($lista, $anio, 'navidad', $anterior))->not->toBe($anterior);
        }
    }
});

test('la rotación sin lo del año anterior es estable, y con un solo elemento no tiene otro remedio', function () {
    expect(RotacionAnual::elegir(['a', 'b', 'c'], 2026, 'navidad'))->toBe(RotacionAnual::elegir(['a', 'b', 'c'], 2026, 'navidad'))
        ->and(RotacionAnual::elegir(['a'], 2026, 'navidad', 'a'))->toBe('a')
        ->and(RotacionAnual::elegir([], 2026, 'navidad'))->toBeNull()
        ->and(RotacionAnual::elegir([7, 9], 2026, 'versiculo|navidad', 7))->toBe(9);
});

test('el núcleo registra la variante del nacimiento en silueta; una clave repetida es un error', function () {
    $registro = app(RegistroGranMomento::class);
    $variante = $registro->variante(Ocasion::Navidad, 'navidad-establo');

    expect($variante?->titulo)->toBe('¡Feliz Navidad!')
        ->and(view()->exists((string) $variante?->vista))->toBeTrue()
        ->and($registro->variante(Ocasion::Navidad, 'ya-no-existe'))->toBeNull()
        ->and(fn () => $registro->registrar(new VarianteGranMomento(Ocasion::Navidad, 'navidad-establo', 'Otra', 'Otra', 'x')))->toThrow(LogicException::class);
});

test('la vista previa trae el versículo en español de la fecha que toca ese año', function () {
    $previa = app(GranMomentoService::class)->previa(Ocasion::Navidad, 'navidad-establo', 2026);
    $textos = array_map(fn ($frase): string => $frase->texto, app(GranMomentoService::class)->versiculos(Ocasion::Navidad));

    expect($textos)->toHaveCount(3)
        ->and($textos)->toContain($previa?->versiculo)
        ->and($previa?->fuente)->not->toBeNull();
});

test('con el modo desarrollo prendido la abre cualquier cuenta, y ya no está en Configuración', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.ver'));

    test()->get('/vista-previa-fechas')->assertOk()->assertSee('Vista previa de fechas');
    test()->get('/configuracion')->assertDontSee('Vista previa de fechas');
});

test('sin el modo desarrollo no existe, ni siquiera con permisos', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo(['catalogos.ver', 'parametros.editar']));

    session()->forget('nucleo.modo_desarrollo');
    test()->get('/vista-previa-fechas')->assertNotFound();

    // Fuera de desarrollo, aunque la sesión diga que el modo está prendido (plan 1.4i, D7).
    session()->put('nucleo.modo_desarrollo.activo', true);
    app()->detectEnvironment(fn (): string => 'production');
    test()->get('/vista-previa-fechas')->assertNotFound();
});

test('elegida en el sitio desde el modo desarrollo, abre ya mostrando esa variante', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/vista-previa-fechas?ocasion=navidad&variante=navidad-establo')->assertOk()
        ->assertSee('data-tq-gran-momento="navidad-establo"', false);
});

test('muestra el gran momento animado o quieto, como diálogo, sin guardar nada', function () {
    $usuario = User::factory()->create()->givePermissionTo(['catalogos.ver', 'parametros.editar']);
    $tablas = fn (): int => DB::table('auditoria_cambios')->count();
    $antes = $tablas();
    $pagina = Livewire::actingAs($usuario)->test(VistaPreviaFechas::class);

    $pagina->call('ver', true)
        ->assertSeeHtml('data-tq-gran-momento="navidad-establo"')
        ->assertSeeHtml('role="dialog"')
        ->assertSeeHtml('class="tq-gm tq-gm-corre"')
        ->assertSee('Feliz Navidad');

    $pagina->call('ver', false)->assertSeeHtml('class="tq-gm tq-gm-quieto"');
    $pagina->call('cerrar')->assertDontSeeHtml('data-tq-gran-momento');

    expect($tablas())->toBe($antes);
});

test('el gran momento quieto no se mueve: el marco y la escena respetan las dos formas de reducir animaciones', function () {
    foreach (['marco', 'navidad-establo'] as $vista) {
        $contenido = (string) file_get_contents(base_path("app/Modules/Nucleo/resources/views/filament/gran-momento/{$vista}.blade.php"));

        expect($contenido)->toContain('prefers-reduced-motion: reduce')->toContain('html.tq-sin-animaciones');
    }
});
