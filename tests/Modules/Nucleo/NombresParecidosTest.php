<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Parties\CampoNombre;
use App\Modules\Nucleo\Parties\CoincidenciaNombre;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * Plan 2.20a, N1: los nombres normalizados de las fichas y el aviso de nombres iguales o parecidos que usará Cuentas al
 * crear una cuenta. Solo nombres inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** Un RUC 20 válido a partir de un número de ocho cifras. */
function rucInventado(int $n): string
{
    $base = '20'.str_pad((string) $n, 8, '0', STR_PAD_LEFT);

    return $base.DocumentoIdentidad::digitoVerificadorRuc($base);
}

function fichaInventada(int $n, string $razonSocial, ?string $nombreComercial = null): Party
{
    $registro = app(PartiesService::class)->registrar(new DatosParty('6', rucInventado($n), razonSocial: $razonSocial, nombreComercial: $nombreComercial));

    return Party::query()->findOrFail($registro->id);
}

/** @param  list<CoincidenciaNombre>  $coincidencias */
function resumenDeCoincidencias(array $coincidencias): array
{
    return array_map(fn (CoincidenciaNombre $c): string => explode(' · ', $c->party->etiqueta)[0].' | '.$c->aviso(), $coincidencias);
}

test('las dos columnas son la normalización de su nombre, también después de editar', function () {
    $ficha = fichaInventada(1001, 'Ferretería Los Andes S.A.C.', 'Ferromax');

    expect($ficha->nombre_normalizado)->toBe(PosiblesDuplicados::normalizar('Ferretería Los Andes S.A.C.'))
        ->and($ficha->nombre_normalizado)->toBe('ferreteria los andes')
        ->and($ficha->nombre_comercial_normalizado)->toBe('ferromax');

    app(PartiesService::class)->editar($ficha->id, ['razon_social' => 'Ferretería Los Andes del Norte S.A.C.', 'nombre_comercial' => 'Ferromax'], 1);
    $ficha->refresh();
    expect($ficha->nombre_normalizado)->toBe('ferreteria los andes del norte')
        ->and($ficha->nombre_comercial_normalizado)->toBe('ferromax');

    app(PartiesService::class)->editar($ficha->id, ['razon_social' => 'Ferretería Los Andes del Norte S.A.C.', 'nombre_comercial' => 'Ferromax Norte'], 2);
    $ficha->refresh();
    expect($ficha->nombre_normalizado)->toBe('ferreteria los andes del norte')
        ->and($ficha->nombre_comercial_normalizado)->toBe('ferromax norte');

    app(PartiesService::class)->editar($ficha->id, ['razon_social' => 'Ferretería Los Andes del Norte S.A.C.', 'nombre_comercial' => null], 3);
    expect($ficha->refresh()->nombre_comercial_normalizado)->toBeNull();

    // Ninguna ficha de la base se sale de la regla.
    foreach (Party::query()->get() as $party) {
        expect($party->nombre_normalizado)->toBe(PosiblesDuplicados::normalizar($party->razon_social))
            ->and($party->nombre_comercial_normalizado)->toBe(PosiblesDuplicados::normalizarOpcional($party->nombre_comercial));
    }
});

test('el comando recalcula; con --simular solo cuenta y no escribe', function () {
    $ficha = fichaInventada(1002, 'Distribuidora Inventada S.A.');
    // Como si la normalización hubiera cambiado: solo una prueba escribe así la tabla.
    DB::table('parties')->where('id', $ficha->id)->update(['nombre_normalizado' => 'viejo', 'nombre_comercial_normalizado' => 'viejo']);

    Artisan::call('nucleo:recalcular-nombres', ['--simular' => true]);
    expect(Artisan::output())->toContain('Cambiarían: 1')
        ->and($ficha->refresh()->nombre_normalizado)->toBe('viejo');

    Artisan::call('nucleo:recalcular-nombres');
    $ficha->refresh();
    expect(Artisan::output())->toContain('Recalculadas: 1')
        ->and($ficha->nombre_normalizado)->toBe('distribuidora inventada')
        ->and($ficha->nombre_comercial_normalizado)->toBeNull()
        ->and($ficha->version)->toBe(1);
});

test('avisa primero el mismo nombre y luego los parecidos, sin bloquear nada', function () {
    fichaInventada(1003, 'Ferretería Los Andes S.A.C.');
    fichaInventada(1004, 'Ferreteria Los Andez E.I.R.L.');
    fichaInventada(1005, 'Comercial Los Andes S.R.L.');

    expect(resumenDeCoincidencias(app(PartiesService::class)->posiblesPorNombre('FERRETERIA LOS ANDES SAC')))->toBe([
        'Ferretería Los Andes S.A.C. | mismo nombre',
        'Ferreteria Los Andez E.I.R.L. | razón social parecida',
    ]);
});

test('también compara el nombre comercial, y una ficha sale una vez con su mejor coincidencia', function () {
    fichaInventada(1006, 'Inversiones Pacífico Norte S.A.C.', 'Ferromax');
    fichaInventada(1007, 'Ferromax Perú S.A.C.', 'Ferromax');

    $coincidencias = app(PartiesService::class)->posiblesPorNombre('Ferromax');

    // Empatadas (mismo nombre comercial, parecido 1): primero la registrada antes.
    expect(resumenDeCoincidencias($coincidencias))->toBe([
        'Inversiones Pacífico Norte S.A.C. | mismo nombre comercial',
        'Ferromax Perú S.A.C. | mismo nombre comercial',
    ])
        ->and($coincidencias[0]->campo)->toBe(CampoNombre::NombreComercial)
        ->and($coincidencias[0]->parecido)->toBe(1.0);

    expect(resumenDeCoincidencias(app(PartiesService::class)->posiblesPorNombre('Ferromax Peru')))->toBe([
        'Ferromax Perú S.A.C. | mismo nombre',
    ]);
});

test('entidades distintas con palabras comunes no avisan con el umbral de fábrica; bajarlo da más avisos', function () {
    fichaInventada(1008, 'Municipalidad Distrital de Valle Alto');

    expect(app(PartiesService::class)->posiblesPorNombre('Municipalidad Distrital de Cerro Azul'))->toBe([]);

    DB::table('parametros')->where('grupo', 'parties')->where('clave', 'umbral_nombre_parecido')->update(['valor' => '0.40']);
    app()->forgetInstance(ParametrosService::class);

    expect(resumenDeCoincidencias(app(PartiesService::class)->posiblesPorNombre('Municipalidad Distrital de Cerro Azul')))
        ->toBe(['Municipalidad Distrital de Valle Alto | razón social parecida']);
});

test('un nombre vacío o solo con la forma societaria no busca nada', function () {
    fichaInventada(1009, 'Ferretería Los Andes S.A.C.');

    expect(app(PartiesService::class)->posiblesPorNombre('  '))->toBe([])
        ->and(app(PartiesService::class)->posiblesPorNombre('S.A.C.'))->toBe([]);
});

test('una muestra fija de la normalización: si cambia, este PR debe correr nucleo:recalcular-nombres', function () {
    // Revisión de arquitectura de la 2.20a: la migración que completó las columnas ya no se repite. Si esta prueba
    // falla porque cambió la normalización, se actualiza la muestra **y** se corre el comando en el mismo PR.
    expect(array_map(PosiblesDuplicados::normalizar(...), [
        'Ferretería El Norte S.A.C.',
        'FERRETERIA EL NORTE SAC',
        'Hermanos-López E.I.R.L.',
        'Inversiones Ñandú Sociedad Anónima Cerrada',
        '  Muni.  Distrital,  de  Valle Alto ',
        'S.A.C.',
    ]))->toBe([
        'ferreteria el norte',
        'ferreteria el norte',
        'hermanos lopez',
        'inversiones nandu',
        'muni distrital de valle alto',
        '',
    ]);
});
