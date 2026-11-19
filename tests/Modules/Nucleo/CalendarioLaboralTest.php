<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\FeriadosSeeder;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\TipoFeriado;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Tiempo\CalendarioLaboral;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/*
 * Plan 1.4d, D2: el catálogo de feriados y qué días trabaja Techquk. En 2026 el 25 de diciembre y en 2027 el 1 de enero
 * caen viernes y son feriados: el gran momento sale el lunes siguiente.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function diaLaboral(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, 'America/Lima');
}

function calendarioLaboral(): CalendarioLaboral
{
    // Uno nuevo en cada consulta: los cambios de la prueba se ven sin la memoria de la petición.
    return new CalendarioLaboral;
}

test('después de Navidad de 2026 y de Año Nuevo de 2027, que caen viernes, el primer día hábil es el lunes', function () {
    expect(calendarioLaboral()->primerHabilDesde(diaLaboral('2026-12-25'))->toDateString())->toBe('2026-12-28')
        ->and(calendarioLaboral()->primerHabilDesde(diaLaboral('2027-01-01'))->toDateString())->toBe('2027-01-04')
        ->and(calendarioLaboral()->primerHabilDesde(diaLaboral('2026-12-28'))->toDateString())->toBe('2026-12-28');
});

test('los fines de semana y los feriados nacionales no son hábiles; un martes cualquiera, sí', function () {
    expect(calendarioLaboral()->esHabil(diaLaboral('2026-12-26')))->toBeFalse()
        ->and(calendarioLaboral()->esHabil(diaLaboral('2026-07-28')))->toBeFalse()
        ->and(calendarioLaboral()->esHabil(diaLaboral('2026-12-01')))->toBeTrue();
});

test('Jueves y Viernes Santo se calculan cada año', function () {
    expect(Feriado::query()->whereIn('nombre', ['Jueves Santo', 'Viernes Santo'])->orderBy('codigo')->pluck('codigo')->all())
        ->toBe(['2026-04-02', '2026-04-03', '2027-03-25', '2027-03-26']);
});

test('un día no laborable del gobierno es hábil, salvo que la empresa lo dé', function () {
    $dia = Feriado::factory()->create(['codigo' => '2026-12-31', 'nombre' => 'Día no laborable', 'tipo' => TipoFeriado::NoLaborable, 'techquk_no_trabaja' => false]);

    expect(calendarioLaboral()->esHabil(diaLaboral('2026-12-31')))->toBeTrue();

    $dia->update(['techquk_no_trabaja' => true]);

    expect(calendarioLaboral()->esHabil(diaLaboral('2026-12-31')))->toBeFalse();
});

test('un día libre de la empresa no es hábil, y uno desactivado no cuenta', function () {
    $libre = Feriado::factory()->create(['codigo' => '2026-12-24', 'nombre' => 'Nochebuena', 'tipo' => TipoFeriado::LibreEmpresa]);

    expect(calendarioLaboral()->esHabil(diaLaboral('2026-12-24')))->toBeFalse();

    $libre->update(['es_activo' => false]);

    expect(calendarioLaboral()->esHabil(diaLaboral('2026-12-24')))->toBeTrue();
});

test('un año sin feriados cargados solo descuenta los fines de semana', function () {
    expect(calendarioLaboral()->esHabil(diaLaboral('2031-12-25')))->toBeTrue()
        ->and(calendarioLaboral()->primerHabilDesde(diaLaboral('2031-12-27'))->toDateString())->toBe('2031-12-29');
});

test('la base no deja trabajar un feriado nacional ni un día libre de la empresa, ni una fecha que no existe', function (array $datos) {
    expect(fn () => Feriado::factory()->create($datos))->toThrow(QueryException::class);
})->with([
    'feriado nacional trabajado' => [['tipo' => TipoFeriado::FeriadoNacional, 'techquk_no_trabaja' => false]],
    'día libre trabajado' => [['tipo' => TipoFeriado::LibreEmpresa, 'techquk_no_trabaja' => false]],
    'fecha que no existe' => [['codigo' => '2030-02-30']],
    'fecha mal escrita' => [['codigo' => '30-12-2030']],
]);

test('desde el catálogo, lo que la base rechazaría sale con un mensaje claro', function () {
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');
    $crear = fn (array $datos) => app(EjecutorAcciones::class)->ejecutar('nucleo.feriados.crear', [
        'codigo' => '2030-12-31', 'nombre' => 'Fin de año', 'tipo' => 'feriado_nacional', 'techquk_no_trabaja' => false, ...$datos,
    ], $editor);

    expect(fn () => $crear([]))->toThrow(ElementoCatalogoRechazado::class, 'Un feriado nacional nunca se trabaja')
        ->and(fn () => $crear(['codigo' => '2030-02-30', 'techquk_no_trabaja' => true]))->toThrow(ElementoCatalogoRechazado::class, 'La fecha no existe');

    $crear(['tipo' => 'no_laborable']);

    expect(Feriado::query()->where('codigo', '2030-12-31')->value('techquk_no_trabaja'))->toBeFalse();
});

test('lo que el administrador cambia o borra no vuelve con el siguiente despliegue', function () {
    Feriado::query()->where('codigo', '2026-12-25')->update(['nombre' => 'Navidad del Señor']);
    Feriado::query()->where('codigo', '2026-06-29')->delete();
    $antes = Feriado::query()->count();

    test()->seed(FeriadosSeeder::class);

    expect(Feriado::query()->count())->toBe($antes)
        ->and(Feriado::query()->where('codigo', '2026-12-25')->value('nombre'))->toBe('Navidad del Señor')
        ->and(Feriado::query()->where('codigo', '2026-06-29')->exists())->toBeFalse();
});

test('un año que todavía no tiene feriados se carga entero', function () {
    Feriado::query()->where('codigo', 'like', '2027-%')->delete();

    test()->seed(FeriadosSeeder::class);

    expect(Feriado::query()->where('codigo', 'like', '2027-%')->count())->toBe(16);
});
