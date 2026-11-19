<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\MarcarMomentoVisto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\GranMomentoRechazado;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\GranMomento;
use App\Modules\Nucleo\Models\MomentoVisto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;
use App\Modules\Nucleo\Saludo\VarianteGranMomento;
use App\Modules\Nucleo\Services\GranMomentoService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4d, D3, D4, D7 y D9: cuándo sale el gran momento, qué se elige cada año y «Lo vi». Navidad 2026: el 25 cae
 * viernes y es feriado, sale del lunes 28 al 31. Año Nuevo 2027: el 1 cae viernes, sale del lunes 4 al 7.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);

    // Una segunda variante de Navidad y dos de Año Nuevo, con la vista de la del nacimiento.
    app(RegistroGranMomento::class)->registrar(
        new VarianteGranMomento(Ocasion::Navidad, 'navidad-prueba', 'Prueba', '¡Feliz Navidad!', 'nucleo::filament.gran-momento.navidad-establo'),
        new VarianteGranMomento(Ocasion::AnioNuevo, 'anio-nuevo-uno', 'Uno', 'Feliz Año Nuevo', 'nucleo::filament.gran-momento.navidad-establo'),
        new VarianteGranMomento(Ocasion::AnioNuevo, 'anio-nuevo-dos', 'Dos', 'Feliz Año Nuevo', 'nucleo::filament.gran-momento.navidad-establo'),
    );
});

function diaDelMomento(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse("{$fecha} 09:00", 'America/Lima');
}

function granMomento(): GranMomentoService
{
    return app(GranMomentoService::class);
}

/** @return ?array{0: string, 1: int} */
function vigenteEl(string $fecha): ?array
{
    $vigente = granMomento()->vigente(diaDelMomento($fecha));

    return $vigente === null ? null : [$vigente[0]->value, $vigente[1]];
}

test('Navidad 2026 sale del lunes 28 al 31; el 25, el fin de semana y el 1 de enero, no', function () {
    expect(vigenteEl('2026-12-25'))->toBeNull()
        ->and(vigenteEl('2026-12-27'))->toBeNull()
        ->and(vigenteEl('2026-12-28'))->toBe(['navidad', 2026])
        ->and(vigenteEl('2026-12-31'))->toBe(['navidad', 2026])
        ->and(vigenteEl('2027-01-01'))->toBeNull();
});

test('Año Nuevo 2027 sale del lunes 4 al 7', function () {
    expect(vigenteEl('2027-01-03'))->toBeNull()
        ->and(vigenteEl('2027-01-04'))->toBe(['anio_nuevo', 2027])
        ->and(vigenteEl('2027-01-07'))->toBe(['anio_nuevo', 2027])
        ->and(vigenteEl('2027-01-08'))->toBeNull();
});

test('una fecha sin variantes no tiene gran momento; el aniversario sin fecha de fundación tampoco', function () {
    expect(vigenteEl('2027-03-29'))->toBeNull()
        ->and(granMomento()->diaClave(Ocasion::Aniversario, 2027))->toBeNull()
        ->and(granMomento()->diaClave(Ocasion::SemanaSanta, 2027)?->toDateString())->toBe('2027-03-28');
});

test('si se juntan dos vigentes, solo el más reciente; si ya lo vio, nada, aunque quede el anterior sin ver', function () {
    // Con el plazo de Navidad en 30 días, Navidad 2026 sigue vigente cuando empieza Año Nuevo 2027.
    DB::table('parametros')->where('grupo', 'interfaz')->where('clave', 'gran_momento_plazo_navidad')->update(['valor' => '30']);
    app()->forgetInstance(ParametrosService::class);
    $persona = User::factory()->create();

    expect(vigenteEl('2027-01-05'))->toBe(['anio_nuevo', 2027]);

    $pendiente = granMomento()->pendientePara($persona, diaDelMomento('2027-01-05'));
    granMomento()->marcarVisto($persona, (int) $pendiente?->id, diaDelMomento('2027-01-05'));

    expect(granMomento()->pendientePara($persona, diaDelMomento('2027-01-06')))->toBeNull()
        ->and(MomentoVisto::query()->where('user_id', $persona->id)->count())->toBe(1);
});

test('pasado el plazo, nada', function () {
    $persona = User::factory()->create();

    expect(granMomento()->pendientePara($persona, diaDelMomento('2027-01-15')))->toBeNull();
});

test('la elección del año es la misma para todas las personas y queda guardada', function () {
    $una = granMomento()->pendientePara(User::factory()->create(), diaDelMomento('2026-12-28'));
    $otra = granMomento()->pendientePara(User::factory()->create(), diaDelMomento('2026-12-29'));

    expect($una?->id)->toBe($otra?->id)
        ->and($una?->escena->variante->clave)->toBe($otra?->escena->variante->clave)
        ->and($una?->escena->versiculo)->not->toBeNull()
        ->and($una?->escena->versiculo)->toBe($otra?->escena->versiculo)
        ->and(GranMomento::query()->count())->toBe(1);
});

test('al sumar una variante o cambiar los versículos activos, no se repite lo del año anterior', function () {
    $persona = User::factory()->create();
    $de2025 = GranMomento::query()->create(['ocasion' => 'navidad', 'anio' => 2025, 'variante' => 'navidad-establo', 'frase_saludo_id' => granMomento()->versiculos(Ocasion::Navidad)[0]->id]);

    // Se suma una tercera variante y se desactiva otro versículo antes de que se elija 2026.
    app(RegistroGranMomento::class)->registrar(new VarianteGranMomento(Ocasion::Navidad, 'navidad-tercera', 'Tercera', '¡Feliz Navidad!', 'nucleo::filament.gran-momento.navidad-establo'));
    granMomento()->versiculos(Ocasion::Navidad)[1]->update(['es_activa' => false]);

    $de2026 = granMomento()->pendientePara($persona, diaDelMomento('2026-12-28'));

    expect($de2026?->escena->variante->clave)->not->toBe($de2025->variante)
        ->and(GranMomento::query()->where('anio', 2026)->value('frase_saludo_id'))->not->toBe($de2025->frase_saludo_id);
});

test('si la variante guardada del año ya no existe, se elige otra para todos y no se rompe', function () {
    GranMomento::query()->create(['ocasion' => 'navidad', 'anio' => 2026, 'variante' => 'navidad-retirada']);

    $pendiente = granMomento()->pendientePara(User::factory()->create(), diaDelMomento('2026-12-28'));

    expect($pendiente?->escena->variante->clave)->toBeIn(['navidad-establo', 'navidad-prueba'])
        ->and(GranMomento::query()->where('anio', 2026)->value('variante'))->toBe($pendiente?->escena->variante->clave);
});

test('quien apagó la ambientación, o si la apagó la empresa, no lo ve', function () {
    $persona = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($persona, 'apariencia.ambientacion', false);

    expect(granMomento()->pendientePara($persona, diaDelMomento('2026-12-28')))->toBeNull();

    DB::table('parametros')->where('grupo', 'interfaz')->where('clave', 'ambientacion_activa')->update(['valor' => 'false']);
    // El servicio de parámetros y la ambientación guardan lo leído en la petición: se renuevan.
    app()->forgetInstance(ParametrosService::class);
    app()->forgetInstance(Ambientacion::class);

    expect(app(GranMomentoService::class)->pendientePara(User::factory()->create(), diaDelMomento('2026-12-28')))->toBeNull();
});

test('el núcleo registra «Lo vi»; no recibe a la persona: siempre es quien inició sesión', function () {
    $accion = app(RegistroAcciones::class)->obtener('nucleo.marcar-momento-visto');

    expect($accion)->toBeInstanceOf(MarcarMomentoVisto::class)
        ->and($accion->permiso())->toBeNull()
        ->and(array_map(fn ($campo): string => $campo->nombre, $accion->campos()))->toBe(['gran_momento']);
});

test('«Lo vi» marca a nombre de quien lo vio, devuelve si fue la primera vez y no falla la segunda', function () {
    $persona = User::factory()->create();
    $pendiente = granMomento()->pendientePara($persona, diaDelMomento('2026-12-28'));

    expect(granMomento()->marcarVisto($persona, (int) $pendiente?->id, diaDelMomento('2026-12-28')))->toBeTrue()
        ->and(granMomento()->marcarVisto($persona, (int) $pendiente?->id, diaDelMomento('2026-12-28')))->toBeFalse()
        ->and(MomentoVisto::query()->where('user_id', $persona->id)->sole()->created_by)->toBe($persona->id)
        ->and(granMomento()->pendientePara($persona, diaDelMomento('2026-12-29')))->toBeNull();
});

test('«Lo vi» solo acepta el gran momento vigente', function () {
    $persona = User::factory()->create();
    $viejo = GranMomento::query()->create(['ocasion' => 'navidad', 'anio' => 2025, 'variante' => 'navidad-establo']);

    expect(fn () => granMomento()->marcarVisto($persona, $viejo->id, diaDelMomento('2026-12-28')))->toThrow(GranMomentoRechazado::class)
        ->and(fn () => granMomento()->marcarVisto($persona, $viejo->id, diaDelMomento('2026-12-15')))->toThrow(GranMomentoRechazado::class);
});

test('la acción rechaza al usuario de sistema y a uno inactivo', function () {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.marcar-momento-visto', ['gran_momento' => 1], $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.marcar-momento-visto', ['gran_momento' => 1], User::factory()->inactivo()->create()))->toThrow(AccionRechazada::class);
});

test('la base no admite dos elecciones de la misma fecha y año, ni dos marcas de la misma persona', function () {
    $momento = GranMomento::query()->create(['ocasion' => 'navidad', 'anio' => 2030, 'variante' => 'navidad-establo']);
    $persona = User::factory()->create();
    MomentoVisto::query()->create(['user_id' => $persona->id, 'gran_momento_id' => $momento->id]);

    expect(fn () => GranMomento::query()->create(['ocasion' => 'navidad', 'anio' => 2030, 'variante' => 'navidad-prueba']))->toThrow(QueryException::class)
        ->and(fn () => MomentoVisto::query()->create(['user_id' => $persona->id, 'gran_momento_id' => $momento->id]))->toThrow(QueryException::class)
        ->and(fn () => GranMomento::query()->create(['ocasion' => 'dia_madre', 'anio' => 2030, 'variante' => 'x']))->toThrow(QueryException::class);
});

test('un versículo desactivado después de elegido ya no se muestra', function () {
    $pendiente = granMomento()->pendientePara(User::factory()->create(), diaDelMomento('2026-12-28'));
    FraseSaludo::query()->whereKey(GranMomento::query()->where('anio', 2026)->value('frase_saludo_id'))->update(['es_activa' => false]);

    expect($pendiente?->escena->versiculo)->not->toBeNull()
        ->and(granMomento()->pendientePara(User::factory()->create(), diaDelMomento('2026-12-28'))?->escena->versiculo)->toBeNull();
});
