<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\FueraDeHorario;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\GeneradorSaludo;
use App\Modules\Nucleo\Saludo\Saludo;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use App\Modules\Nucleo\Tiempo\HorarioEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/*
 * Plan 1.4e: el saludo y la frase según la hora y el horario de la empresa. En horario, el saludo es de la sesión
 * (casual o de la hora, mitad y mitad) y la frase del día; fuera de horario, un saludo especial de esa situación y la
 * frase 70 % especial.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    test()->categoriaHorario = CategoriaFrase::factory()->create();
    porcentajeEspanolDelHorario(100);
});

/** Un martes sin fecha especial. */
const MARTES = '2026-10-20';

function horaDelHorario(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

/** Sale en español mientras el porcentaje esté en 100 (beforeEach). */
function personaDelHorario(): User
{
    return User::factory()->create(['party_id' => Party::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Soto'])->id]);
}

/** Cambia `interfaz.saludo_porcentaje_espanol` con la versión que tenga y olvida lo leído en esta «petición». */
function porcentajeEspanolDelHorario(int $porcentaje): void
{
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto('interfaz.saludo_porcentaje_espanol', (string) $porcentaje, $parametros->ficha('interfaz.saludo_porcentaje_espanol')['version']);
    app()->forgetScopedInstances();
}

/** @param  array<string, mixed>  $atributos */
function saludoDelHorario(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->saludo()->create(['texto' => $texto, ...$atributos]);
}

/** @param  array<string, mixed>  $atributos */
function fraseDelHorario(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->create(['texto' => $texto, 'categoria_frase_id' => test()->categoriaHorario->id, ...$atributos]);
}

/** Los saludos de siempre y los especiales, uno por situación. */
function saludosDelHorario(): void
{
    saludoDelHorario('Hola, {nombre}');
    saludoDelHorario('Qué gusto verte, {nombre}');
    saludoDelHorario('Buenos días, {nombre}', ['momento' => 'manana']);
    saludoDelHorario('Buenas tardes, {nombre}', ['momento' => 'tarde']);
    saludoDelHorario('Buenas noches, {nombre}', ['momento' => 'noche']);
    saludoDelHorario('Qué temprano, {nombre}.', ['fuera_de_horario' => 'temprano']);
    saludoDelHorario('Todavía por aquí, {nombre}.', ['fuera_de_horario' => 'noche']);
    saludoDelHorario('{nombre}, ¿trabajando en fin de semana?', ['fuera_de_horario' => 'fin_de_semana']);
    saludoDelHorario('Hoy es feriado, {nombre}.', ['fuera_de_horario' => 'feriado']);
}

function saludoA(User $persona, string $fecha, string $sesion = 'una'): Saludo
{
    return app(GeneradorSaludo::class)->para($persona, horaDelHorario($fecha), $sesion);
}

test('el horario: martes a las 10 en horario; antes de las 8 temprano; desde las 19 noche; sábado; feriado', function () {
    $horario = app(HorarioEmpresa::class);
    Feriado::factory()->create(['codigo' => '2026-07-28', 'nombre' => 'Fiestas Patrias', 'tipo' => 'feriado_nacional', 'techquk_no_trabaja' => true]);

    expect($horario->fueraDeHorario(horaDelHorario(MARTES.' 10:00')))->toBeNull()
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 07:59')))->toBe(FueraDeHorario::Temprano)
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 08:00')))->toBeNull()
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 18:59')))->toBeNull()
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 19:00')))->toBe(FueraDeHorario::Noche)
        ->and($horario->fueraDeHorario(horaDelHorario('2026-10-24 10:00')))->toBe(FueraDeHorario::FinDeSemana)
        ->and($horario->fueraDeHorario(horaDelHorario('2026-07-28 10:00')))->toBe(FueraDeHorario::Feriado);
});

test('el horario sale de los parámetros', function () {
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto('interfaz.horario_inicio', '9:00', 1);
    $parametros->cambiarDesdeTexto('interfaz.horario_fin', '18:00', 1);
    $parametros->cambiarDesdeTexto('interfaz.horario_dias', '1,2,3,4,5,6', 1);
    $horario = app(HorarioEmpresa::class);

    expect($horario->fueraDeHorario(horaDelHorario(MARTES.' 08:30')))->toBe(FueraDeHorario::Temprano)
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 18:00')))->toBe(FueraDeHorario::Noche)
        ->and($horario->fueraDeHorario(horaDelHorario('2026-10-24 10:00')))->toBeNull()
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.horario_fin', '25:00', 2))->toThrow(ParametroRechazado::class)
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.horario_dias', '1,8', 2))->toThrow(ParametroRechazado::class);
});

test('en horario: casual o de la hora, nunca de otra hora ni especial; el mismo en la misma sesión', function () {
    saludosDelHorario();
    $persona = personaDelHorario();

    $manana = collect(range(1, 40))->map(fn (int $n): string => saludoA($persona, MARTES.' 10:00', "s{$n}")->saludo);
    $tarde = collect(range(1, 40))->map(fn (int $n): string => saludoA($persona, MARTES.' 15:00', "s{$n}")->saludo);

    expect($manana->unique()->sort()->values()->all())->toBe(['Buenos días, Ana', 'Hola, Ana', 'Qué gusto verte, Ana'])
        ->and($tarde->unique()->sort()->values()->all())->toBe(['Buenas tardes, Ana', 'Hola, Ana', 'Qué gusto verte, Ana'])
        ->and(saludoA($persona, MARTES.' 10:00', 's7')->saludo)->toBe(saludoA($persona, MARTES.' 11:30', 's7')->saludo);
});

test('mitad y mitad entre los de la hora y los casuales, según el parámetro', function () {
    saludosDelHorario();
    $persona = personaDelHorario();
    $deLaHora = fn (): int => collect(range(1, 400))->filter(fn (int $n): bool => saludoA($persona, MARTES.' 10:00', "s{$n}")->saludo === 'Buenos días, Ana')->count();

    expect($deLaHora())->toBeGreaterThan(160)->toBeLessThan(240);

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.saludo_porcentaje_de_la_hora', '0', 1);
    expect($deLaHora())->toBe(0);
});

test('la frase es la misma en todo el tramo y en toda sesión: una hasta las 13:00, otra desde las 13:00, y otras al día siguiente (plan 1.4aa, D9)', function () {
    saludosDelHorario();
    foreach (range(1, 6) as $n) {
        fraseDelHorario("Frase {$n}");
    }
    $persona = personaDelHorario();
    $del = fn (array $horas): Collection => collect($horas)->flatMap(fn (string $hora): array => [saludoA($persona, MARTES." {$hora}", 'a')->frase, saludoA($persona, MARTES." {$hora}", 'b')->frase]);

    $manana = $del(['08:00', '09:00', '12:59']);
    $tarde = $del(['13:00', '15:30', '18:00']);

    expect($manana->unique()->count())->toBe(1)
        ->and($tarde->unique()->count())->toBe(1)
        ->and($tarde->first())->not->toBe($manana->first())
        ->and(saludoA($persona, '2026-10-21 09:00')->frase)->not->toBe($manana->first())
        ->and(saludoA($persona, '2026-10-21 15:00')->frase)->not->toBe($tarde->first());
});

test('fuera de horario, el saludo especial de cada situación (o el de la noche)', function () {
    saludosDelHorario();
    Feriado::factory()->create(['codigo' => '2026-07-28', 'nombre' => 'Fiestas Patrias', 'tipo' => 'feriado_nacional', 'techquk_no_trabaja' => true]);
    $persona = personaDelHorario();
    $de = fn (string $fecha): array => collect(range(1, 30))->map(fn (int $n): string => saludoA($persona, $fecha, "s{$n}")->saludo)->unique()->sort()->values()->all();

    expect($de(MARTES.' 07:00'))->toBe(['Qué temprano, Ana.'])
        ->and($de(MARTES.' 20:00'))->toBe(['Buenas noches, Ana', 'Todavía por aquí, Ana.'])
        ->and($de('2026-10-24 11:00'))->toBe(['Ana, ¿trabajando en fin de semana?'])
        ->and($de('2026-07-28 11:00'))->toBe(['Hoy es feriado, Ana.']);
});

test('la sesión que cruza el fin del horario o el mediodía cambia de saludo', function () {
    saludosDelHorario();
    $persona = personaDelHorario();

    expect(saludoA($persona, MARTES.' 19:05')->saludo)->toBeIn(['Todavía por aquí, Ana.', 'Buenas noches, Ana'])
        ->and(saludoA($persona, MARTES.' 12:05')->saludo)->toBeIn(['Buenas tardes, Ana', 'Hola, Ana', 'Qué gusto verte, Ana']);
});

test('fuera de horario, la frase es especial unos 70 de cada 100 tramos y si no, la del tramo; estable en el tramo', function () {
    saludosDelHorario();
    fraseDelHorario('La del día');
    fraseDelHorario('Un día a la vez.', ['fuera_de_horario' => 'cualquiera']);
    $persona = personaDelHorario();

    $frases = collect(range(1, 400))->map(fn (int $n): ?string => saludoA($persona, horaDelHorario(MARTES.' 20:30')->addDays($n)->format('Y-m-d H:i'), "s{$n}")->frase)->countBy();
    $enElTramo = collect(range(1, 20))->map(fn (int $n): ?string => saludoA($persona, MARTES.' 20:30', "t{$n}")->frase)->unique();

    expect($frases['Un día a la vez.'])->toBeGreaterThan(240)->toBeLessThan(320)
        ->and($frases['La del día'])->toBe(400 - $frases['Un día a la vez.'])
        ->and($enElTramo->count())->toBe(1)
        // En horario, la especial nunca.
        ->and(saludoA($persona, MARTES.' 10:00')->frase)->toBe('La del día');
});

test('en una fecha especial manda su saludo, también fuera de horario', function () {
    saludosDelHorario();
    saludoDelHorario('¡Feliz Navidad, {nombre}!', ['ocasion' => 'navidad']);

    expect(saludoA(personaDelHorario(), '2026-12-25 21:00')->saludo)->toBe('¡Feliz Navidad, Ana!');
});

test('fuera de horario, sin saludo especial en el idioma de la sesión, sale en español', function () {
    saludosDelHorario();
    saludoDelHorario('Hello, {nombre}', ['idioma' => 'en']);
    $persona = personaDelHorario();
    porcentajeEspanolDelHorario(0);

    expect(saludoA($persona, MARTES.' 10:00')->saludo)->toBe('Hello, Ana')
        ->and(saludoA($persona, MARTES.' 21:00')->saludo)->toBeIn(['Todavía por aquí, Ana.', 'Buenas noches, Ana']);
});

test('en un feriado de Navidad, de noche, la frase de Navidad manda sobre la especial', function () {
    saludosDelHorario();
    Feriado::factory()->create(['codigo' => '2026-12-25', 'nombre' => 'Navidad', 'tipo' => 'feriado_nacional', 'techquk_no_trabaja' => true]);
    fraseDelHorario('La del día');
    fraseDelHorario('Que esta Navidad llegue con paz.', ['ocasion' => 'navidad']);
    fraseDelHorario('Un día a la vez.', ['fuera_de_horario' => 'cualquiera']);
    $persona = personaDelHorario();

    $frases = collect(range(1, 40))->map(fn (int $n): ?string => saludoA($persona, '2026-12-25 21:00', "s{$n}")->frase)->unique()->values()->all();

    expect($frases)->toBe(['Que esta Navidad llegue con paz.']);
});

test('de madrugada es «noche», no «temprano»', function () {
    $horario = app(HorarioEmpresa::class);

    expect($horario->fueraDeHorario(horaDelHorario(MARTES.' 01:00')))->toBe(FueraDeHorario::Noche)
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 04:59')))->toBe(FueraDeHorario::Noche)
        ->and($horario->fueraDeHorario(horaDelHorario(MARTES.' 05:00')))->toBe(FueraDeHorario::Temprano);
});

test('un feriado que cae en un sábado laborable es feriado', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.horario_dias', '1,2,3,4,5,6', 1);
    Feriado::factory()->create(['codigo' => '2026-08-29', 'nombre' => 'Feriado de prueba', 'tipo' => 'feriado_nacional', 'techquk_no_trabaja' => true]);

    expect(app(HorarioEmpresa::class)->fueraDeHorario(horaDelHorario('2026-08-29 10:00')))->toBe(FueraDeHorario::Feriado)
        ->and(app(HorarioEmpresa::class)->fueraDeHorario(horaDelHorario('2026-08-22 10:00')))->toBeNull();
});

test('los días laborables mal escritos se rechazan, no se convierten', function () {
    $parametros = app(ParametrosService::class);

    expect(fn () => $parametros->cambiarDesdeTexto('interfaz.horario_dias', '1.5,2', 1))->toThrow(ParametroRechazado::class)
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.horario_dias', '2abc', 1))->toThrow(ParametroRechazado::class);

    $parametros->cambiarDesdeTexto('interfaz.horario_dias', ' 5, 1,1 ,3 ', 1);
    expect($parametros->texto('interfaz.horario_dias'))->toBe('1,3,5');
});

test('la base no deja un texto de fuera de horario con fecha especial o momento del día', function () {
    expect(fn () => saludoDelHorario('Todavía, {nombre}', ['fuera_de_horario' => 'noche', 'ocasion' => 'navidad']))->toThrow(QueryException::class)
        ->and(fn () => saludoDelHorario('Todavía, {nombre}', ['fuera_de_horario' => 'noche', 'momento' => 'noche']))->toThrow(QueryException::class);
});

test('con otros idiomas posibles, la frase en español es la del tramo en toda sesión; otro idioma, una sesión como mucho', function () {
    saludosDelHorario();
    saludoDelHorario('Hello, {nombre}', ['idioma' => 'en']);
    foreach (range(1, 4) as $n) {
        fraseDelHorario("Frase {$n}");
        fraseDelHorario("Sentence {$n}", ['idioma' => 'en']);
    }
    $persona = personaDelHorario();
    porcentajeEspanolDelHorario(0);

    $sesiones = collect(range(1, 30))->map(fn (int $n): Saludo => saludoA($persona, MARTES.($n % 2 === 0 ? ' 09:00' : ' 16:00'), "s{$n}"));
    $enEspanol = $sesiones->filter(fn (Saludo $saludo): bool => $saludo->idioma === IdiomaSaludo::Espanol);

    expect($sesiones->reject(fn (Saludo $saludo): bool => $saludo->idioma === IdiomaSaludo::Espanol)->count())->toBe(1)
        ->and($enEspanol->map(fn (Saludo $saludo): ?string => $saludo->frase)->unique()->count())->toBe(2);
});

test('las especiales respetan el área: una «solo ERP» no sale a quien no es del ERP', function () {
    saludoDelHorario('Hola, {nombre}');
    saludoDelHorario('Todavía en el ERP, {nombre}.', ['fuera_de_horario' => 'noche', 'area' => 'erp']);
    $persona = personaDelHorario();

    expect(collect(range(1, 20))->map(fn (int $n): string => saludoA($persona, MARTES.' 21:00', "s{$n}")->saludo)->unique()->all())->not->toContain('Todavía en el ERP, Ana.');
});
