<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Saludo\GeneradorSaludo;
use App\Modules\Nucleo\Saludo\Saludo;
use App\Modules\Nucleo\Saludo\SaludoPorReglas;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4b, paso 4: cómo se elige el saludo y la frase (D1, D1a, D1b, D2, D3). Cada prueba arma sus propias frases;
 * las del seeder se prueban en FrasesSaludoSeederTest.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    test()->categoria = CategoriaFrase::factory()->create();
    // Solo español, salvo la prueba que pide otro porcentaje (desde la 1.4aa los cinco idiomas son para todos).
    porcentajeEspanolDelSaludo(100);
});

/** Cambia `interfaz.saludo_porcentaje_espanol` con la versión que tenga y olvida lo leído en esta «petición». */
function porcentajeEspanolDelSaludo(int $porcentaje): void
{
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto('interfaz.saludo_porcentaje_espanol', (string) $porcentaje, $parametros->ficha('interfaz.saludo_porcentaje_espanol')['version']);
    app()->forgetScopedInstances();
}

/** Un lunes por la mañana sin fecha especial. */
const LUNES_MANANA = '2026-10-19 09:00';

function horaLima(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

/** La persona de estas pruebas. Sale en español mientras el porcentaje esté en 100 (beforeEach). */
function personaDelSaludo(): User
{
    return User::factory()->create(['party_id' => Party::factory()->create(['nombres' => 'Ana María', 'apellidos' => 'Quispe Soto'])->id]);
}

/** @param  array<string, mixed>  $atributos */
function frase(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->create(['texto' => $texto, 'categoria_frase_id' => test()->categoria->id, ...$atributos]);
}

/** @param  array<string, mixed>  $atributos */
function saludoDe(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->saludo()->create(['texto' => $texto, ...$atributos]);
}

function saludar(User $usuario, string $fecha = LUNES_MANANA, string $sesion = ''): Saludo
{
    return app(GeneradorSaludo::class)->para($usuario, horaLima($fecha), $sesion);
}

test('el contenedor da el generador por reglas', function () {
    expect(app(GeneradorSaludo::class))->toBeInstanceOf(SaludoPorReglas::class);
});

test('saluda por el primer nombre, con la frase y su fuente', function () {
    saludoDe('Buenos días, {nombre}', ['momento' => 'manana']);
    frase('Poco a poco se va lejos.', ['fuente' => 'Refrán']);

    $saludo = saludar(personaDelSaludo());

    expect($saludo->saludo)->toBe('Buenos días, Ana')
        ->and($saludo->frase)->toBe('Poco a poco se va lejos.')
        ->and($saludo->fuente)->toBe('Refrán')
        ->and($saludo->idioma)->toBe(IdiomaSaludo::Espanol)
        ->and($saludo->ocasion)->toBeNull()
        ->and($saludo->temporada)->toBe(Temporada::Primavera);
});

test('sin saludos en el catálogo sale el respaldo, también en el idioma del día', function () {
    $persona = personaDelSaludo();

    expect(saludar($persona)->saludo)->toBe('Hola, Ana')
        ->and(saludar($persona)->frase)->toBeNull();

    // Un idioma sin saludos pero con frase: el saludo es el de respaldo, en ese idioma.
    frase('A sentence', ['idioma' => 'en']);
    porcentajeEspanolDelSaludo(0);

    expect(saludar($persona)->saludo)->toBe('Hello, Ana');
});

test('la ocasión y el día mandan; la frase es del día: las de un momento del día no salen (plan 1.4e, D4)', function () {
    frase('General');
    frase('De mañana', ['momento' => 'manana']);
    frase('De lunes', ['dia_semana' => 1]);
    frase('De Navidad', ['ocasion' => 'navidad']);
    $persona = personaDelSaludo();
    $martesPorLaManana = collect(range(0, 6))->map(fn (int $semana): ?string => saludar($persona, horaLima('2026-10-20 09:00')->addWeeks($semana)->format('Y-m-d H:i'))->frase);

    expect(saludar($persona)->frase)->toBe('De lunes')
        ->and($martesPorLaManana->unique()->sort()->values()->all())->toBe(['General'])
        ->and(saludar($persona, '2026-10-20 15:00')->frase)->toBe('General')
        ->and(saludar($persona, '2026-12-21 09:00')->frase)->toBe('De Navidad');
});

test('en su fecha especial sale el saludo de la ocasión, también el cumpleaños', function () {
    saludoDe('Buenos días, {nombre}', ['momento' => 'manana']);
    saludoDe('¡Feliz cumpleaños, {nombre}!', ['ocasion' => 'cumpleanos']);
    $persona = personaDelSaludo();
    $persona->forceFill(['cumpleanos_dia' => 19, 'cumpleanos_mes' => 10])->save();

    $saludo = saludar($persona);

    expect($saludo->saludo)->toBe('¡Feliz cumpleaños, Ana!')
        ->and($saludo->ocasion)->toBe(Ocasion::Cumpleanos)
        ->and(saludar($persona, '2026-10-21 09:00')->saludo)->toBe('Buenos días, Ana');
});

test('la elección es estable al recargar y depende de la persona', function () {
    foreach (range(1, 10) as $n) {
        frase("Frase {$n}");
    }
    $personas = collect(range(1, 12))->map(fn () => personaDelSaludo());

    $vistas = $personas->map(fn (User $persona): ?string => saludar($persona)->frase);

    expect($personas->map(fn (User $persona): ?string => saludar($persona)->frase)->all())->toBe($vistas->all())
        ->and($vistas->unique()->count())->toBeGreaterThan(1);
});

test('no repite la frase del mismo momento de ayer si hay otra', function () {
    frase('Una');
    frase('Otra');
    $persona = personaDelSaludo();

    $dias = collect(range(0, 20))->map(fn (int $n): ?string => saludar($persona, horaLima(LUNES_MANANA)->addDays($n)->format('Y-m-d H:i'))->frase);

    foreach (range(1, 20) as $n) {
        expect($dias[$n])->not->toBe($dias[$n - 1]);
    }
});

test('con una sola frase, la repite: es mejor que no mostrar nada', function () {
    frase('La única');
    $persona = personaDelSaludo();

    expect(saludar($persona)->frase)->toBe('La única')
        ->and(saludar($persona, '2026-10-20 09:00')->frase)->toBe('La única');
});

test('nunca sale una frase inactiva, ni una de categoría apagada para la empresa', function () {
    $apagadaParaTodos = CategoriaFrase::factory()->create(['es_activa' => false]);
    frase('Inactiva', ['es_activa' => false]);
    frase('De categoría apagada', ['categoria_frase_id' => $apagadaParaTodos->id]);
    $persona = personaDelSaludo();

    foreach (range(0, 15) as $n) {
        expect(saludar($persona, horaLima(LUNES_MANANA)->addDays($n)->format('Y-m-d H:i'))->frase)->toBeNull();
    }
});

test('con el porcentaje en 100 todo sale en español, aunque haya frases en inglés y francés', function () {
    frase('Una frase');
    frase('A sentence', ['idioma' => 'en']);
    frase('Une phrase', ['idioma' => 'fr']);
    $persona = personaDelSaludo();

    foreach (range(0, 30) as $n) {
        expect(saludar($persona, horaLima(LUNES_MANANA)->addDays($n)->format('Y-m-d H:i'))->idioma)->toBe(IdiomaSaludo::Espanol);
    }
});

test('el porcentaje decide cuántos salen en español; los demás, en los otros idiomas', function () {
    saludoDe('Hola, {nombre}');
    saludoDe('Hello, {nombre}', ['idioma' => 'en']);
    saludoDe('Bonjour, {nombre}', ['idioma' => 'fr']);
    frase('Una frase');
    frase('A sentence', ['idioma' => 'en']);
    frase('Une phrase', ['idioma' => 'fr']);
    $persona = personaDelSaludo();
    porcentajeEspanolDelSaludo(70);

    // El idioma es de la persona y el día (plan 1.4e: así la frase sigue siendo del día); 300 días.
    $idiomas = collect(range(0, 299))->map(fn (int $n): string => saludar($persona, horaLima(LUNES_MANANA)->addDays($n)->format('Y-m-d H:i'))->idioma->value)->countBy();

    expect($idiomas['es'])->toBeGreaterThan(165)->toBeLessThan(255)
        ->and($idiomas['en'] ?? 0)->toBeGreaterThan(15)
        ->and($idiomas['fr'] ?? 0)->toBeGreaterThan(15);
});

test('saludo y frase salen en el mismo idioma; sin frase en ese idioma, todo en español', function () {
    saludoDe('Hola, {nombre}');
    saludoDe('Hello, {nombre}', ['idioma' => 'en']);
    frase('Solo en español');
    $persona = personaDelSaludo();
    porcentajeEspanolDelSaludo(0);

    $saludo = saludar($persona);

    expect($saludo->idioma)->toBe(IdiomaSaludo::Espanol)
        ->and($saludo->saludo)->toBe('Hola, Ana')
        ->and($saludo->frase)->toBe('Solo en español');

    frase('Only in English', ['idioma' => 'en']);

    expect(saludar($persona)->saludo)->toBe('Hello, Ana')
        ->and(saludar($persona)->frase)->toBe('Only in English');
});

test('las frases de un área salen a quien solo ve módulos de esa área', function () {
    Permission::findOrCreate('cuentas.ver', 'web');
    Permission::findOrCreate('compras.ver', 'web');
    app(RegistroModulos::class)->registrar(new CajaModulo('cuentas', 'Cuentas', '/cuentas', 'cuentas.ver', AreaModulo::Crm));
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', 'compras.ver', AreaModulo::Erp));
    frase('Para el CRM', ['area' => 'crm']);
    frase('Para el ERP', ['area' => 'erp']);
    $comercial = personaDelSaludo()->givePermissionTo('cuentas.ver');
    $deLosDos = personaDelSaludo()->givePermissionTo(['cuentas.ver', 'compras.ver']);

    expect(saludar($comercial)->frase)->toBe('Para el CRM')
        ->and(saludar($deLosDos)->frase)->toBeNull();
});

test('con varias frases empatadas, las muestra todas antes de repetir', function () {
    foreach (range(1, 5) as $n) {
        frase("Frase {$n}");
    }
    $persona = personaDelSaludo();

    $semana = collect(range(0, 4))->map(fn (int $n): ?string => saludar($persona, horaLima(LUNES_MANANA)->addDays($n)->format('Y-m-d H:i'))->frase);

    expect($semana->unique()->count())->toBe(5);
});

/*
 * Días de versículo (regla 6 de SaludoPorReglas, pedido del desarrollador, 2026-10-21): al menos cuatro días por
 * semana, de lunes a viernes, la frase es un versículo en todos los momentos.
 */

/** @param  array<string, mixed>  $atributos */
function versiculo(string $texto, array $atributos = []): FraseSaludo
{
    $categoria = CategoriaFrase::query()->firstWhere('codigo', SaludoPorReglas::CATEGORIA_VERSICULOS)
        ?? CategoriaFrase::factory()->create(['codigo' => SaludoPorReglas::CATEGORIA_VERSICULOS]);

    return FraseSaludo::factory()->create(['texto' => $texto, 'categoria_frase_id' => $categoria->id, 'fuente' => 'Salmos 1:1 (RVR1960)', ...$atributos]);
}

function diasDeVersiculo(int $versiculosPorSemana): void
{
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.saludo_dias_versiculo', (string) $versiculosPorSemana, 1);
}

/**
 * La frase de cada día de lunes a viernes de la semana que empieza el lunes dado, a la hora dada.
 *
 * @return list<?string>
 */
function fraseDeLaSemana(User $persona, CarbonImmutable $lunes, string $hora): array
{
    return collect(range(0, 4))->map(fn (int $n): ?string => saludar($persona, $lunes->addDays($n)->format('Y-m-d')." {$hora}")->frase)->values()->all();
}

test('cada semana hay al menos cuatro días de lunes a viernes con versículo, de mañana y de noche', function () {
    foreach (range(1, 10) as $n) {
        frase("Frase {$n}");
    }
    versiculo('El versículo');

    foreach (range(1, 6) as $p) {
        $persona = personaDelSaludo();

        foreach (range(0, 3) as $semana) {
            $lunes = horaLima(LUNES_MANANA)->addWeeks($semana)->startOfDay();
            $manana = fraseDeLaSemana($persona, $lunes, '09:00');
            $noche = fraseDeLaSemana($persona, $lunes, '20:00');
            $todoElDia = collect(range(0, 4))->filter(fn (int $d): bool => $manana[$d] === 'El versículo' && $noche[$d] === 'El versículo');

            expect($todoElDia->count())->toBeGreaterThanOrEqual(4);
        }
    }
});

test('los días de versículo cambian entre personas y entre semanas', function () {
    foreach (range(1, 10) as $n) {
        frase("Frase {$n}");
    }
    versiculo('El versículo');
    $lunes = horaLima(LUNES_MANANA)->startOfDay();
    $patron = fn (User $persona, CarbonImmutable $desde): string => collect(fraseDeLaSemana($persona, $desde, '09:00'))
        ->map(fn (?string $f): string => $f === 'El versículo' ? 'V' : '-')->implode('');
    $personas = collect(range(1, 12))->map(fn () => personaDelSaludo());

    expect($personas->map(fn (User $p): string => $patron($p, $lunes))->unique()->count())->toBeGreaterThan(1)
        ->and(collect(range(0, 7))->map(fn (int $s): string => $patron($personas[0], $lunes->addWeeks($s)))->unique()->count())->toBeGreaterThan(1);
});

test('con cero días de versículo vuelve la rotación de siempre', function () {
    foreach (range(1, 20) as $n) {
        frase("Frase {$n}");
    }
    versiculo('El versículo');
    diasDeVersiculo(0);
    $persona = personaDelSaludo();

    $semana = collect(fraseDeLaSemana($persona, horaLima(LUNES_MANANA)->startOfDay(), '09:00'));

    expect($semana->filter(fn (?string $f): bool => $f === 'El versículo')->count())->toBeLessThanOrEqual(1);
});

test('la fecha especial manda sobre el día de versículo, salvo que tenga su propio versículo', function () {
    frase('De Navidad', ['ocasion' => 'navidad']);
    versiculo('El versículo');
    diasDeVersiculo(5);
    $persona = personaDelSaludo();

    expect(saludar($persona, '2026-12-21 09:00')->frase)->toBe('De Navidad')
        ->and(saludar($persona, '2026-10-19 09:00')->frase)->toBe('El versículo');

    versiculo('Versículo de Navidad', ['ocasion' => 'navidad']);

    expect(saludar($persona, '2026-12-21 09:00')->frase)->toBe('Versículo de Navidad');
});

test('el día de versículo sale en español si el idioma del día no tiene versículo', function () {
    saludoDe('Hola, {nombre}');
    saludoDe('Hello, {nombre}', ['idioma' => 'en']);
    frase('A sentence', ['idioma' => 'en']);
    versiculo('El versículo');
    $persona = personaDelSaludo();
    porcentajeEspanolDelSaludo(0);
    diasDeVersiculo(5);
    // El sábado, de horario (plan 1.4e): si no, el saludo sería de fuera de horario.
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.horario_dias', '1,2,3,4,5,6', 1);

    $saludo = saludar($persona);

    expect($saludo->idioma)->toBe(IdiomaSaludo::Espanol)
        ->and($saludo->saludo)->toBe('Hola, Ana')
        ->and($saludo->frase)->toBe('El versículo')
        ->and(saludar($persona, '2026-10-24 09:00')->frase)->toBe('A sentence');
});

test('los días de versículo por semana van de 0 a 5', function () {
    expect(app(ParametrosService::class)->entero('interfaz.saludo_dias_versiculo'))->toBe(4)
        ->and(fn () => diasDeVersiculo(6))->toThrow(ParametroRechazado::class, 'entre 0 y 5');
});
