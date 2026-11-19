<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Saludo\CalendarioFestivo;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;

/*
 * Plan 1.4b, paso 3: qué fecha especial es cada día (D5, con sus ventanas) y la temporada de los demás.
 */

beforeEach(fn () => test()->seed(ParametrosSeeder::class));

function diaEnLima(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, Calendario::ZONA);
}

function calendarioFestivo(): CalendarioFestivo
{
    return app(CalendarioFestivo::class);
}

test('calcula el Domingo de Resurrección', function (int $anio, string $esperado) {
    expect(CalendarioFestivo::domingoDeResurreccion($anio)->toDateString())->toBe($esperado);
})->with([
    [2000, '2000-04-23'],
    [2024, '2024-03-31'],
    [2025, '2025-04-20'],
    [2026, '2026-04-05'],
    [2027, '2027-03-28'],
    [2038, '2038-04-25'],
]);

test('el Día de la Madre es el segundo domingo de mayo y el del Padre el tercero de junio', function (int $anio, string $madre, string $padre) {
    expect(CalendarioFestivo::domingoDelMes($anio, 5, 2)->toDateString())->toBe($madre)
        ->and(CalendarioFestivo::domingoDelMes($anio, 6, 3)->toDateString())->toBe($padre);
})->with([
    [2025, '2025-05-11', '2025-06-15'],
    [2026, '2026-05-10', '2026-06-21'],
    [2027, '2027-05-09', '2027-06-20'],
]);

test('cada fecha del calendario vale en su ventana y no fuera de ella', function (string $fecha, ?Ocasion $esperada) {
    expect(calendarioFestivo()->ocasion(diaEnLima($fecha)))->toBe($esperada);
})->with([
    'antes de Año Nuevo' => ['2026-12-26', Ocasion::AnioNuevo],
    'Año Nuevo' => ['2027-01-01', Ocasion::AnioNuevo],
    'vuelta al trabajo' => ['2027-01-02', Ocasion::AnioNuevo],
    'ya pasó Año Nuevo' => ['2027-01-03', null],
    'antes de Navidad' => ['2026-12-14', null],
    'empieza Navidad' => ['2026-12-15', Ocasion::Navidad],
    'Navidad' => ['2026-12-25', Ocasion::Navidad],
    'antes de Fiestas Patrias' => ['2026-07-20', null],
    'empiezan Fiestas Patrias' => ['2026-07-21', Ocasion::FiestasPatrias],
    'termina Fiestas Patrias' => ['2026-07-29', Ocasion::FiestasPatrias],
    'después de Fiestas Patrias' => ['2026-07-30', null],
    'sábado antes de Ramos 2026' => ['2026-03-28', null],
    'Domingo de Ramos 2026' => ['2026-03-29', Ocasion::SemanaSanta],
    'Miércoles Santo 2026' => ['2026-04-01', Ocasion::SemanaSanta],
    'Resurrección 2026' => ['2026-04-05', Ocasion::SemanaSanta],
    'lunes después de Pascua 2026' => ['2026-04-06', null],
    'Semana Santa 2027' => ['2027-03-24', Ocasion::SemanaSanta],
    'domingo antes de la semana de la Madre' => ['2026-05-03', null],
    'lunes de la semana de la Madre' => ['2026-05-04', Ocasion::DiaMadre],
    'Día de la Madre' => ['2026-05-10', Ocasion::DiaMadre],
    'lunes después' => ['2026-05-11', null],
    'lunes de la semana del Padre' => ['2026-06-15', Ocasion::DiaPadre],
    'Día del Padre' => ['2026-06-21', Ocasion::DiaPadre],
    'un día cualquiera' => ['2026-10-19', null],
]);

test('el cumpleaños vale su día y, si cae en fin de semana, el viernes anterior y el lunes siguiente', function (string $fecha, int $dia, int $mes, bool $celebra) {
    expect(calendarioFestivo()->ocasion(diaEnLima($fecha), $dia, $mes))->toBe($celebra ? Ocasion::Cumpleanos : null);
})->with([
    'en día de semana' => ['2026-10-14', 14, 10, true],
    'el día anterior no' => ['2026-10-13', 14, 10, false],
    'sábado: el viernes' => ['2026-10-16', 17, 10, true],
    'sábado: el día' => ['2026-10-17', 17, 10, true],
    'sábado: el lunes' => ['2026-10-19', 17, 10, true],
    'sábado: el jueves no' => ['2026-10-15', 17, 10, false],
    'domingo: el viernes' => ['2026-10-16', 18, 10, true],
    'domingo: el lunes' => ['2026-10-19', 18, 10, true],
    'domingo: el martes no' => ['2026-10-20', 18, 10, false],
    '29 de febrero en año bisiesto' => ['2028-02-29', 29, 2, true],
    '29 de febrero en año no bisiesto: el 28' => ['2027-02-28', 29, 2, true],
    '29 de febrero que cae en domingo 28: el lunes 1 de marzo' => ['2027-03-01', 29, 2, true],
    'sábado 1 de enero: el viernes 31 del año anterior' => ['2027-12-31', 1, 1, true],
]);

test('si coinciden, gana el cumpleaños, luego el aniversario y luego el calendario', function () {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-12-18', 1);

    expect(app(CalendarioFestivo::class)->ocasion(diaEnLima('2026-12-18')))->toBe(Ocasion::Aniversario)
        ->and(app(CalendarioFestivo::class)->ocasion(diaEnLima('2026-12-18'), 18, 12))->toBe(Ocasion::Cumpleanos)
        ->and(app(CalendarioFestivo::class)->ocasion(diaEnLima('2026-12-17')))->toBe(Ocasion::Navidad);
});

test('sin fecha de aniversario no hay ocasión de aniversario', function () {
    expect(calendarioFestivo()->ocasion(diaEnLima('2026-05-15')))->toBeNull();

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', '2010-05-15', 1);

    expect(app(CalendarioFestivo::class)->ocasion(diaEnLima('2026-05-15')))->toBe(Ocasion::Aniversario);
});

test('cuenta el día de Lima, aunque la hora venga en otra zona', function () {
    // 03:00 del 15 en UTC son las 22:00 del 14 en Lima: todavía no es Navidad.
    $utc = CarbonImmutable::parse('2026-12-15 03:00', 'UTC');

    expect(calendarioFestivo()->ocasion($utc->setTimezone(Calendario::ZONA)))->toBeNull()
        ->and(calendarioFestivo()->ocasion($utc->setTimezone(Calendario::ZONA)->addHours(2)))->toBe(Ocasion::Navidad);
});

test('la temporada sigue el hemisferio sur', function (string $fecha, Temporada $temporada) {
    expect(calendarioFestivo()->temporada(diaEnLima($fecha)))->toBe($temporada);
})->with([
    ['2026-12-20', Temporada::Primavera],
    ['2026-12-21', Temporada::Verano],
    ['2027-03-19', Temporada::Verano],
    ['2027-03-20', Temporada::Otono],
    ['2027-06-20', Temporada::Otono],
    ['2027-06-21', Temporada::Invierno],
    ['2027-09-21', Temporada::Invierno],
    ['2027-09-22', Temporada::Primavera],
]);

test('cuenta los años del aniversario por el aniversario que se celebra', function (string $fundacion, string $fecha, ?int $anios) {
    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', $fundacion, 1);

    expect(app(CalendarioFestivo::class)->aniosDelAniversario(diaEnLima($fecha)))->toBe($anios);
})->with([
    'el día' => ['2010-05-15', '2026-05-15', 16],
    'otro día' => ['2010-05-15', '2026-05-18', null],
    'el viernes antes de un sábado' => ['2012-10-17', '2026-10-16', 14],
    'viernes 31: el del sábado 1 de enero que viene' => ['2012-01-01', '2027-12-31', 16],
    'el mismo día de la fundación no es aniversario' => ['2026-10-19', '2026-10-19', null],
]);

test('sin fecha de fundación no hay años ni aniversario', function () {
    expect(app(CalendarioFestivo::class)->aniosDelAniversario(diaEnLima('2026-05-15')))->toBeNull();
});
