<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\PlantillaVersion;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Las tablas de plantillas (ADR 0022; plan 2.11, paso 2): lo que la base garantiza aunque un servicio se equivoque.
 * Personal con dueño y de la empresa sin él (D1); nombre único entre las activas del mismo tipo y dueño; versiones
 * numeradas, inmutables y con contenido de objeto (D3, D4).
 */

beforeEach(fn () => test()->actingAs(User::factory()->create()));

/** La falla esperada corre en un punto de guardado: así no aborta la transacción de la prueba. */
function enPuntoDeGuardado(Closure $tarea): Closure
{
    return fn () => DB::transaction($tarea);
}

function versionCruda(int $plantillaId, array $cambios = []): void
{
    DB::table('plantilla_versiones')->insert([
        'plantilla_id' => $plantillaId, 'numero' => 1, 'formato' => 1, 'contenido' => '{"partidas": []}',
        'created_by' => auth()->id(), ...$cambios,
    ]);
}

test('una personal lleva dueño y una de la empresa no; el alcance es de una lista cerrada', function () {
    $personal = Plantilla::factory()->create();
    $empresa = Plantilla::factory()->deLaEmpresa()->create();

    expect([$personal->propietario_user_id !== null, $empresa->propietario_user_id])->toBe([true, null])
        ->and(enPuntoDeGuardado(fn () => DB::table('plantillas')->where('id', $empresa->id)->update(['propietario_user_id' => auth()->id()])))
        ->toThrow(QueryException::class, 'plantillas_propietario_check')
        ->and(enPuntoDeGuardado(fn () => DB::table('plantillas')->where('id', $personal->id)->update(['propietario_user_id' => null])))
        ->toThrow(QueryException::class, 'plantillas_propietario_check')
        ->and(enPuntoDeGuardado(fn () => DB::table('plantillas')->where('id', $personal->id)->update(['alcance' => 'equipo'])))
        ->toThrow(QueryException::class, 'plantillas_alcance_check');
});

test('dos activas del mismo tipo y dueño no se llaman igual, ni con tildes o mayúsculas; desactivada libera el nombre', function () {
    $yo = User::factory()->create();
    $primera = Plantilla::factory()->create(['nombre' => 'Videovigilancia municipal', 'propietario_user_id' => $yo->id]);

    expect(enPuntoDeGuardado(fn () => Plantilla::factory()->create(['nombre' => '  VIDEOVIGILANCIA  Municipal ', 'propietario_user_id' => $yo->id])))
        ->toThrow(QueryException::class, 'plantillas_nombre_activo_unique');

    // Otro dueño, la empresa u otro tipo pueden usar el mismo nombre.
    Plantilla::factory()->create(['nombre' => 'Videovigilancia municipal']);
    Plantilla::factory()->deLaEmpresa()->create(['nombre' => 'Videovigilancia municipal']);
    Plantilla::factory()->create(['nombre' => 'Videovigilancia municipal', 'propietario_user_id' => $yo->id, 'tipo' => 'pruebas.otra']);

    $primera->update(['es_activa' => false]);
    Plantilla::factory()->create(['nombre' => 'Videovigilancia municipal', 'propietario_user_id' => $yo->id]);

    expect(Plantilla::query()->where('nombre_comparable', 'videovigilancia municipal')->count())->toBe(5);
});

test('el tipo, el alcance y el dueño no cambian; una plantilla no se borra', function () {
    $plantilla = Plantilla::factory()->create();

    expect(fn () => $plantilla->update(['alcance' => Plantilla::EMPRESA, 'propietario_user_id' => null]))->toThrow(LogicException::class, 'alcance')
        ->and(fn () => $plantilla->refresh()->update(['tipo' => 'pruebas.otra']))->toThrow(LogicException::class, 'tipo')
        ->and(fn () => $plantilla->refresh()->delete())->toThrow(LogicException::class, 'se desactiva');
});

test('las versiones se numeran sin repetirse, la vigente es la mayor, y no se modifican ni se borran', function () {
    $plantilla = Plantilla::factory()->create();
    PlantillaVersion::query()->create(['plantilla_id' => $plantilla->id, 'numero' => 1, 'formato' => 1, 'contenido' => ['partidas' => []]]);
    $segunda = PlantillaVersion::query()->create(['plantilla_id' => $plantilla->id, 'numero' => 2, 'formato' => 1, 'contenido' => ['partidas' => [['codigo' => 'PC-01']]]]);

    expect($plantilla->vigente->id)->toBe($segunda->id)
        ->and($segunda->contenido)->toBe(['partidas' => [['codigo' => 'PC-01']]])
        ->and(enPuntoDeGuardado(fn () => versionCruda($plantilla->id, ['numero' => 2])))->toThrow(QueryException::class, 'plantilla_versiones_plantilla_id_numero_unique')
        ->and(fn () => $segunda->update(['formato' => 2]))->toThrow(LogicException::class, 'no se modifica')
        ->and(fn () => $segunda->delete())->toThrow(LogicException::class, 'no se borra');
});

test('el contenido es un objeto, número y formato desde 1, y el origen va completo o no va', function () {
    $id = Plantilla::factory()->create()->id;

    expect(enPuntoDeGuardado(fn () => versionCruda($id, ['contenido' => '[1, 2]'])))->toThrow(QueryException::class, 'plantilla_versiones_contenido_check')
        ->and(enPuntoDeGuardado(fn () => versionCruda($id, ['numero' => 0])))->toThrow(QueryException::class, 'plantilla_versiones_numero_check')
        ->and(enPuntoDeGuardado(fn () => versionCruda($id, ['formato' => 0])))->toThrow(QueryException::class, 'plantilla_versiones_formato_check')
        ->and(enPuntoDeGuardado(fn () => versionCruda($id, ['origen_documento' => 'erp.presupuestos.arbol'])))->toThrow(QueryException::class, 'plantilla_versiones_origen_check');

    versionCruda($id, ['origen_documento' => 'erp.presupuestos.arbol', 'origen_id' => 7]);

    expect(PlantillaVersion::query()->where('plantilla_id', $id)->value('origen_id'))->toBe(7);
});

test('la versión no se envía con su contenido al serializarse (puede llevar montos, D5)', function () {
    $plantilla = Plantilla::factory()->create();
    $version = PlantillaVersion::query()->create(['plantilla_id' => $plantilla->id, 'numero' => 1, 'formato' => 1, 'contenido' => ['precio' => '12.50']]);

    expect($version->toArray())->not->toHaveKey('contenido');
});
