<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\SesionSaludo;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\SaludoMostrado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Saludo\GeneradorSaludo;
use App\Modules\Nucleo\Saludo\HistorialSaludos;
use App\Modules\Nucleo\Saludo\Saludo;
use App\Modules\Nucleo\Saludo\SaludoPorReglas;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4aa, D6 a D9 y D11: el idioma de cada sesión (el español domina; otro, como mucho una sesión al día y nunca dos
 * seguidas), la clase de sesión (primera del día, vuelve, vuelve después de trabajar) y la frase por tramo. Reloj fijo
 * con travelTo y la semilla de la sesión; sin azar suelto.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    test()->categoriaSesion = CategoriaFrase::factory()->create();
});

/** Un martes sin fecha especial, en horario. */
const MARTES_SESION = '2026-10-20 10:00';

function sesionEnLima(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

function personaDeLaSesion(): User
{
    return User::factory()->create(['party_id' => Party::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Soto'])->id]);
}

/** Cambia `interfaz.saludo_porcentaje_espanol` con la versión que tenga y olvida lo leído en esta «petición». */
function porcentajeEspanolDeLaSesion(int $porcentaje): void
{
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto('interfaz.saludo_porcentaje_espanol', (string) $porcentaje, $parametros->ficha('interfaz.saludo_porcentaje_espanol')['version']);
    app()->forgetScopedInstances();
}

/** @param  array<string, mixed>  $atributos */
function saludoDeLaSesion(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->saludo()->create(['texto' => $texto, ...$atributos]);
}

/** @param  array<string, mixed>  $atributos */
function fraseDeLaSesion(string $texto, array $atributos = []): FraseSaludo
{
    return FraseSaludo::factory()->create(['texto' => $texto, 'categoria_frase_id' => test()->categoriaSesion->id, ...$atributos]);
}

/** Un saludo (también para fuera de horario) y unas frases en cada idioma. */
function contenidoEnCincoIdiomas(): void
{
    foreach (IdiomaSaludo::cases() as $idioma) {
        saludoDeLaSesion("[{$idioma->value}] Hola, {nombre}", ['idioma' => $idioma->value]);
        saludoDeLaSesion("[{$idioma->value}] A esta hora, {nombre}", ['idioma' => $idioma->value, 'fuera_de_horario' => 'cualquiera']);

        foreach (range(1, 3) as $n) {
            fraseDeLaSesion("[{$idioma->value}] Frase {$n}", ['idioma' => $idioma->value]);
        }
    }
}

/** La sesión `$sesion` de la persona, con el reloj puesto en `$fecha` (hora de Lima). */
function entrar(User $persona, string $fecha, string $sesion): Saludo
{
    test()->travelTo(sesionEnLima($fecha));

    return app(GeneradorSaludo::class)->para($persona, CarbonImmutable::now(Calendario::ZONA), $sesion);
}

/** Algo que la persona cambió a esa hora (una fila suya en la bitácora). */
function hizoAlgo(User $persona, string $fecha): void
{
    AuditoriaCambio::query()->create([
        'auditable_type' => 'party', 'auditable_id' => 1, 'evento' => EventoAuditoria::Actualizado,
        'user_id' => $persona->id, 'fecha' => sesionEnLima($fecha),
    ]);
}

// --- Idioma ---

test('con el porcentaje en 100, todas las sesiones de todos los días salen en español', function () {
    contenidoEnCincoIdiomas();
    porcentajeEspanolDeLaSesion(100);
    $persona = personaDeLaSesion();

    $idiomas = collect(range(0, 9))->flatMap(fn (int $dia): array => collect(range(1, 4))
        ->map(fn (int $n): string => entrar($persona, sesionEnLima(MARTES_SESION)->addDays($dia)->addHours($n)->format('Y-m-d H:i'), "d{$dia}s{$n}")->idioma->value)->all());

    expect($idiomas->unique()->values()->all())->toBe(['es']);
});

test('otro idioma, como mucho una sesión al día por persona, aunque el porcentaje lo pida siempre', function () {
    contenidoEnCincoIdiomas();
    porcentajeEspanolDeLaSesion(0);
    $persona = personaDeLaSesion();

    foreach (range(0, 6) as $dia) {
        $delDia = collect(range(1, 6))->map(fn (int $n): IdiomaSaludo => entrar($persona, sesionEnLima(MARTES_SESION)->addDays($dia)->addMinutes($n * 50)->format('Y-m-d H:i'), "d{$dia}s{$n}")->idioma);

        expect($delDia->reject(fn (IdiomaSaludo $idioma): bool => $idioma === IdiomaSaludo::Espanol)->count())->toBeLessThanOrEqual(1);
    }
});

test('nunca dos sesiones seguidas en otro idioma, tampoco de un día al siguiente', function () {
    contenidoEnCincoIdiomas();
    porcentajeEspanolDeLaSesion(0);
    $persona = personaDeLaSesion();

    // Una sola sesión por día: la de ayer fue en otro idioma, así que la de hoy va en español, y así alternando.
    $idiomas = collect(range(0, 9))->map(fn (int $dia): IdiomaSaludo => entrar($persona, sesionEnLima(MARTES_SESION)->addDays($dia)->format('Y-m-d H:i'), "d{$dia}")->idioma);

    foreach (range(1, 9) as $n) {
        expect($idiomas[$n] !== IdiomaSaludo::Espanol && $idiomas[$n - 1] !== IdiomaSaludo::Espanol)->toBeFalse();
    }

    expect($idiomas->filter(fn (IdiomaSaludo $idioma): bool => $idioma === IdiomaSaludo::Espanol)->count())->toBe(5);
});

test('portugués y alemán salen como los demás; el otro idioma no repite el último si hay otro', function () {
    contenidoEnCincoIdiomas();
    porcentajeEspanolDeLaSesion(0);
    $persona = personaDeLaSesion();

    // Un día sí y otro no sale otro idioma: 20 días, 10 en otro idioma, nunca el mismo dos veces seguidas.
    $otros = collect(range(0, 19))->map(fn (int $dia): IdiomaSaludo => entrar($persona, sesionEnLima(MARTES_SESION)->addDays($dia)->format('Y-m-d H:i'), "d{$dia}")->idioma)
        ->reject(fn (IdiomaSaludo $idioma): bool => $idioma === IdiomaSaludo::Espanol)->values();

    expect($otros)->toHaveCount(10);

    foreach (range(1, 9) as $n) {
        expect($otros[$n])->not->toBe($otros[$n - 1]);
    }

    expect($otros->map(fn (IdiomaSaludo $idioma): string => $idioma->value)->unique()->count())->toBeGreaterThanOrEqual(3);
});

test('si el único otro idioma con frases es el portugués o el alemán, sale ese, con su saludo y su frase', function (string $idioma) {
    saludoDeLaSesion('Hola, {nombre}');
    fraseDeLaSesion('Una frase');
    saludoDeLaSesion("[{$idioma}] {nombre}", ['idioma' => $idioma]);
    fraseDeLaSesion("[{$idioma}] Uma frase", ['idioma' => $idioma]);
    porcentajeEspanolDeLaSesion(0);

    $saludo = entrar(personaDeLaSesion(), MARTES_SESION, 'una');

    expect($saludo->idioma->value)->toBe($idioma)
        ->and($saludo->saludo)->toBe("[{$idioma}] Ana")
        ->and($saludo->frase)->toBe("[{$idioma}] Uma frase");
})->with(['pt', 'de']);

test('el respaldo existe en los cinco idiomas', function () {
    expect(array_keys(SaludoPorReglas::RESPALDO))->toBe(array_map(fn (IdiomaSaludo $idioma): string => $idioma->value, IdiomaSaludo::cases()));
});

// --- Una fila por sesión ---

test('cada sesión se guarda una vez, con su idioma, y se queda igual toda la sesión', function () {
    contenidoEnCincoIdiomas();
    porcentajeEspanolDeLaSesion(0);
    $persona = personaDeLaSesion();

    $primera = entrar($persona, MARTES_SESION, 'a');
    porcentajeEspanolDeLaSesion(100);
    $despues = collect(['10:05', '11:30', '12:40'])->map(fn (string $hora): IdiomaSaludo => entrar($persona, "2026-10-20 {$hora}", 'a')->idioma);
    entrar($persona, '2026-10-20 15:00', 'b');

    expect($primera->idioma)->not->toBe(IdiomaSaludo::Espanol)
        ->and($despues->unique()->all())->toBe([$primera->idioma])
        ->and(SaludoMostrado::query()->where('user_id', $persona->id)->orderBy('id')->get()->map(fn (SaludoMostrado $fila): array => [$fila->sesion, $fila->idioma])->all())
        ->toBe([['a', $primera->idioma], ['b', IdiomaSaludo::Espanol]]);
});

test('sin sesión (texto vacío) no se guarda nada', function () {
    contenidoEnCincoIdiomas();
    $persona = personaDeLaSesion();

    entrar($persona, MARTES_SESION, '');

    expect(SaludoMostrado::query()->where('user_id', $persona->id)->exists())->toBeFalse();
});

test('con una fecha simulada del modo desarrollo, el saludo no queda en la historia de la persona', function () {
    contenidoEnCincoIdiomas();
    $persona = personaDeLaSesion();
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
    ModoDesarrollo::prender();
    ModoDesarrollo::simular(CarbonImmutable::parse('2026-12-24 10:00', 'America/Lima'));

    entrar($persona, MARTES_SESION, 'sesion-simulada');

    expect(SaludoMostrado::query()->where('user_id', $persona->id)->exists())->toBeFalse();
});

test('la base no deja dos filas de la misma sesión ni un idioma fuera de los cinco', function () {
    $persona = personaDeLaSesion();
    $guardar = fn (string $sesion, string $idioma) => DB::transaction(fn () => DB::table('saludos_mostrados')
        ->insert(['user_id' => $persona->id, 'sesion' => $sesion, 'idioma' => $idioma, 'mostrado_at' => now(), 'created_by' => $persona->id, 'created_at' => now()]));
    $guardar('a', 'es');

    expect(fn () => $guardar('a', 'en'))->toThrow(QueryException::class, 'saludos_mostrados_unique')
        ->and(fn () => $guardar('b', 'it'))->toThrow(QueryException::class, 'saludos_mostrados_valores_check')
        ->and(fn () => $guardar(' ', 'es'))->toThrow(QueryException::class, 'saludos_mostrados_valores_check');
});

// --- Clase de sesión ---

test('la primera sesión del día, la de quien vuelve y la de quien vuelve después de trabajar', function () {
    $persona = personaDeLaSesion();
    $otra = personaDeLaSesion();
    $historial = app(HistorialSaludos::class);
    $clase = function (string $fecha, string $sesion) use ($persona, $historial): SesionSaludo {
        test()->travelTo(sesionEnLima($fecha));

        return $historial->contexto($persona, $sesion, CarbonImmutable::now(Calendario::ZONA))->sesion;
    };

    // Ayer entró, pero hoy todavía no: la primera del día.
    entrar($persona, '2026-10-19 17:00', 'ayer');
    expect($clase('2026-10-20 08:30', 'a'))->toBe(SesionSaludo::PrimeraDelDia);

    entrar($persona, '2026-10-20 08:30', 'a');
    // Los cambios de otra persona no cuentan.
    hizoAlgo($otra, '2026-10-20 09:00');
    expect($clase('2026-10-20 10:00', 'b'))->toBe(SesionSaludo::Vuelve)
        // La sesión ya guardada sigue siendo la primera del día mientras dure.
        ->and($clase('2026-10-20 10:00', 'a'))->toBe(SesionSaludo::PrimeraDelDia);

    hizoAlgo($persona, '2026-10-20 10:30');
    expect($clase('2026-10-20 11:00', 'c'))->toBe(SesionSaludo::VuelveConActividad);
});

test('al volver, sale casi siempre un saludo de quien vuelve; en la primera del día, nunca', function () {
    saludoDeLaSesion('Hola, {nombre}');
    saludoDeLaSesion('De vuelta, {nombre}.', ['sesion' => 'vuelve']);
    fraseDeLaSesion('Una frase');
    $persona = personaDeLaSesion();

    $primera = entrar($persona, '2026-10-20 08:30', 'primera')->saludo;
    $alVolver = collect(range(1, 40))->map(fn (int $n): string => entrar($persona, sesionEnLima('2026-10-20 09:00')->addMinutes($n * 5)->format('Y-m-d H:i'), "s{$n}")->saludo)->countBy();

    expect($primera)->toBe('Hola, Ana')
        ->and($alVolver['De vuelta, Ana.'])->toBeGreaterThan(22)->toBeLessThan(38)
        ->and($alVolver['Hola, Ana'] ?? 0)->toBeGreaterThan(0);
});

test('quien vuelve después de trabajar ve los suyos; si no hay, los de quien vuelve', function () {
    saludoDeLaSesion('Hola, {nombre}');
    saludoDeLaSesion('De vuelta, {nombre}.', ['sesion' => 'vuelve']);
    fraseDeLaSesion('Una frase');
    $persona = personaDeLaSesion();
    entrar($persona, '2026-10-20 08:30', 'primera');
    hizoAlgo($persona, '2026-10-20 08:45');
    $saludos = fn (string $prefijo): Collection => collect(range(1, 30))->map(fn (int $n): string => entrar($persona, sesionEnLima('2026-10-20 09:00')->addMinutes($n)->format('Y-m-d H:i'), "{$prefijo}{$n}")->saludo)->unique()->sort()->values();

    expect($saludos('a')->all())->toBe(['De vuelta, Ana.', 'Hola, Ana']);

    saludoDeLaSesion('{nombre}, seguimos donde lo dejaste.', ['sesion' => 'vuelve_con_actividad']);

    expect($saludos('b')->all())->toBe(['Ana, seguimos donde lo dejaste.', 'Hola, Ana']);
});

test('el cumpleaños y fuera de horario mandan sobre los saludos de la sesión', function () {
    saludoDeLaSesion('Hola, {nombre}');
    saludoDeLaSesion('De vuelta, {nombre}.', ['sesion' => 'vuelve']);
    saludoDeLaSesion('¡Feliz cumpleaños, {nombre}!', ['ocasion' => 'cumpleanos']);
    saludoDeLaSesion('Todavía por aquí, {nombre}.', ['fuera_de_horario' => 'noche']);
    fraseDeLaSesion('Una frase');
    $persona = personaDeLaSesion();
    $persona->forceFill(['cumpleanos_dia' => 21, 'cumpleanos_mes' => 10])->save();
    entrar($persona, '2026-10-20 08:30', 'primera');
    entrar($persona, '2026-10-21 08:30', 'primera-cumple');

    $deNoche = collect(range(1, 20))->map(fn (int $n): string => entrar($persona, '2026-10-20 21:00', "noche{$n}")->saludo)->unique()->all();
    $enSuCumpleanos = collect(range(1, 20))->map(fn (int $n): string => entrar($persona, '2026-10-21 10:00', "cumple{$n}")->saludo)->unique()->all();

    expect($deNoche)->toBe(['Todavía por aquí, Ana.'])
        ->and($enSuCumpleanos)->toBe(['¡Feliz cumpleaños, Ana!']);
});

test('un saludo de sesión no puede ser frase, ni llevar fecha especial ni fuera de horario', function () {
    expect(fn () => DB::transaction(fn () => fraseDeLaSesion('Una frase', ['sesion' => 'vuelve'])))->toThrow(QueryException::class, 'frases_saludo_sesion_solo_check')
        ->and(fn () => DB::transaction(fn () => saludoDeLaSesion('De vuelta, {nombre}', ['sesion' => 'vuelve', 'ocasion' => 'navidad'])))->toThrow(QueryException::class, 'frases_saludo_sesion_solo_check')
        ->and(fn () => DB::transaction(fn () => saludoDeLaSesion('De vuelta, {nombre}', ['sesion' => 'vuelve', 'fuera_de_horario' => 'noche'])))->toThrow(QueryException::class, 'frases_saludo_sesion_solo_check')
        ->and(fn () => DB::transaction(fn () => DB::table('frases_saludo')->insert([
            'codigo' => 'sesion-rara', 'tipo' => 'saludo', 'idioma' => 'es', 'texto' => 'De vuelta, {nombre}', 'sesion' => 'siempre', 'created_by' => personaDeLaSesion()->id, 'created_at' => now(),
        ])))->toThrow(QueryException::class, 'frases_saludo_sesion_check');
});

// --- Frase por tramo ---

test('la frase cambia como mucho dos veces al día, en el corte de las 13:00, y es la misma en toda sesión del tramo', function () {
    saludoDeLaSesion('Hola, {nombre}');
    foreach (range(1, 8) as $n) {
        fraseDeLaSesion("Frase {$n}");
    }
    $persona = personaDeLaSesion();
    $horas = ['08:05', '09:40', '11:10', '12:59', '13:00', '14:20', '16:45', '18:50'];

    $frases = collect($horas)->map(fn (string $hora, int $n): ?string => entrar($persona, "2026-10-20 {$hora}", "s{$n}")->frase);

    expect($frases->take(4)->unique()->count())->toBe(1)
        ->and($frases->skip(4)->unique()->count())->toBe(1)
        ->and($frases[0])->not->toBe($frases[4]);
});

test('en un día de versículo, los dos tramos son versículos', function () {
    $versiculos = CategoriaFrase::factory()->create(['codigo' => SaludoPorReglas::CATEGORIA_VERSICULOS]);
    saludoDeLaSesion('Hola, {nombre}');
    fraseDeLaSesion('Una frase');
    foreach (range(1, 3) as $n) {
        FraseSaludo::factory()->create(['texto' => "Versículo {$n}", 'categoria_frase_id' => $versiculos->id, 'fuente' => 'Salmos 1:1 (RVR1960)']);
    }
    $parametros = app(ParametrosService::class);
    $parametros->cambiarDesdeTexto('interfaz.saludo_dias_versiculo', '5', $parametros->ficha('interfaz.saludo_dias_versiculo')['version']);
    $persona = personaDeLaSesion();

    $manana = entrar($persona, '2026-10-20 09:00', 'a')->frase;
    $tarde = entrar($persona, '2026-10-20 15:00', 'b')->frase;

    expect([$manana, $tarde])->each->toStartWith('Versículo')
        ->and($manana)->not->toBe($tarde);
});
