<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\PlantillaVersion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\PlantillasService;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\TipoPlantillaDePrueba;

/*
 * PlantillasService y sus acciones (ADR 0022; plan 2.11, paso 4), con un tipo inventado («lista de cosas»): guardar,
 * editar, actualizar (versión nueva), publicar, desactivar y aplicar; quién ve y cambia qué (D1, D2), siempre la
 * vigente y copiando (D3), el contenido sensible (D5) y el rechazo del tipo que explica qué hacer (D7).
 */

const TIPO_COSAS = 'pruebas.listas.cosas';
const TIPO_PRECIOS = 'pruebas.listas.precios';

beforeEach(function () {
    $registro = app(RegistroPlantillas::class);
    $registro->registrar(new TipoPlantillaDePrueba);
    $registro->registrar(new TipoPlantillaDePrueba(TIPO_PRECIOS, sensible: true));

    foreach (['pruebas.listas.editar', 'pruebas.listas.plantillas.publicar', GrupoSensible::Costos->permiso()] as $permiso) {
        Permission::findOrCreate($permiso);
    }

    TipoPlantillaDePrueba::$documentos = [
        1 => [['nombre' => 'Cámara domo', 'precio' => '250.00'], ['nombre' => 'Conector', 'precio' => '1.50']],
        2 => [['nombre' => 'Poste', 'precio' => '480.00']],
    ];
});

function plantillas(): PlantillasService
{
    return app(PlantillasService::class);
}

function quienUsa(string ...$extra): User
{
    $usuario = User::factory()->create()->givePermissionTo(['pruebas.listas.editar', ...$extra]);
    test()->actingAs($usuario);

    return $usuario;
}

test('guardar como plantilla crea una personal con la versión 1, copiando el documento y de dónde salió', function () {
    $yo = quienUsa();

    $resumen = plantillas()->guardar(TIPO_COSAS, 1, '  Videovigilancia   básica ', 'Para plazas', $yo);
    $version = PlantillaVersion::query()->where('plantilla_id', $resumen->id)->sole();

    expect([$resumen->nombre, $resumen->esDeLaEmpresa, $resumen->esPropia, $resumen->puedeCambiar, $resumen->numeroVigente])
        ->toBe(['Videovigilancia básica', false, true, true, 1])
        ->and([$version->formato, $version->contenido, $version->origen_documento, $version->origen_id])
        ->toBe([2, ['cosas' => TipoPlantillaDePrueba::$documentos[1]], TIPO_COSAS, 1]);
});

test('sin el permiso de usar el tipo no se guarda ni se ve nada; un contenido inválido no se guarda', function () {
    $sinPermiso = User::factory()->create();
    TipoPlantillaDePrueba::$documentos[3] = [];

    expect(fn () => plantillas()->guardar(TIPO_COSAS, 1, 'Mía', null, $sinPermiso))->toThrow(PlantillaRechazada::class, 'No tienes permiso')
        ->and(plantillas()->disponibles(TIPO_COSAS, $sinPermiso))->toBe([])
        ->and(fn () => plantillas()->guardar(TIPO_COSAS, 3, 'Vacía', null, quienUsa()))->toThrow(PlantillaRechazada::class, 'La plantilla no se guarda: la lista está vacía.')
        ->and(Plantilla::query()->count())->toBe(0);
});

test('cada quien ve las suyas y las de la empresa, no las personales de otros; las suyas primero', function () {
    $ana = quienUsa('pruebas.listas.plantillas.publicar');
    $luis = quienUsa();
    $deAna = plantillas()->guardar(TIPO_COSAS, 1, 'Zonas de Ana', null, $ana);
    plantillas()->publicar($deAna->id, 'Videovigilancia de la empresa', $ana);
    plantillas()->guardar(TIPO_COSAS, 2, 'Postes de Luis', null, $luis);

    expect(array_map(fn ($p) => $p->nombre, plantillas()->disponibles(TIPO_COSAS, $luis)))->toBe(['Postes de Luis', 'Videovigilancia de la empresa'])
        ->and(array_map(fn ($p) => $p->nombre, plantillas()->disponibles(TIPO_COSAS, $ana)))->toBe(['Zonas de Ana', 'Videovigilancia de la empresa'])
        ->and(fn () => plantillas()->aplicar($deAna->id, [], $luis))->toThrow(PlantillaRechazada::class, 'Esa plantilla no existe.');
});

test('el nombre se repite solo entre plantillas distintas: la mía dos veces no, la mía y la de la empresa sí', function () {
    $yo = quienUsa('pruebas.listas.plantillas.publicar');
    $mia = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $yo);

    expect(fn () => plantillas()->guardar(TIPO_COSAS, 2, 'videovigilancia', null, $yo))->toThrow(PlantillaRechazada::class, 'Ya tienes una plantilla «videovigilancia»')
        ->and(plantillas()->publicar($mia->id, null, $yo)->nombre)->toBe('Videovigilancia')
        ->and(fn () => plantillas()->publicar($mia->id, null, $yo))->toThrow(PlantillaRechazada::class, 'La empresa ya tiene una plantilla «Videovigilancia»');
});

test('actualizar desde un documento crea la versión 2 y se aplica esa; lo creado con la 1 sigue diciendo v1 (D3)', function () {
    $yo = quienUsa();
    $p = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $yo);
    $primera = plantillas()->aplicar($p->id, [], $yo);

    $actualizada = plantillas()->actualizarDesde($p->id, 2, $p->version, $yo);
    $segunda = plantillas()->aplicar($p->id, [], $yo);

    expect([$actualizada->numeroVigente, $actualizada->version])->toBe([2, $p->version + 1])
        ->and($segunda->numero)->toBe(2)
        ->and($segunda->propuesta->cosas)->toBe(TipoPlantillaDePrueba::$documentos[2])
        ->and(plantillas()->origen($primera->plantillaVersionId)?->texto())->toBe('Videovigilancia v1')
        ->and(PlantillaVersion::query()->findOrFail($primera->plantillaVersionId)->contenido)->toBe(['cosas' => TipoPlantillaDePrueba::$documentos[1]]);
});

test('dos personas cambian la misma plantilla a la vez: la segunda, con la versión vieja, no pisa a la primera', function () {
    $yo = quienUsa();
    $p = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $yo);

    plantillas()->actualizarDesde($p->id, 2, $p->version, $yo);

    expect(fn () => plantillas()->actualizarDesde($p->id, 1, $p->version, $yo))->toThrow(VersionDesactualizada::class, 'la plantilla «Videovigilancia»')
        ->and(fn () => plantillas()->editar($p->id, 'Otro nombre', null, $p->version, $yo))->toThrow(VersionDesactualizada::class)
        ->and(PlantillaVersion::query()->where('plantilla_id', $p->id)->pluck('numero')->all())->toBe([1, 2]);
});

test('publicar pide el permiso del tipo y explica qué hacer; la personal sigue siendo de su dueño', function () {
    $sinPublicar = quienUsa();
    $p = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $sinPublicar);

    expect(fn () => plantillas()->publicar($p->id, null, $sinPublicar))
        ->toThrow(PlantillaRechazada::class, 'necesita su permiso: pídeselo a quien administra los roles. Mientras, la tuya sigue siendo personal.');

    $otro = quienUsa('pruebas.listas.plantillas.publicar');

    expect(fn () => plantillas()->publicar($p->id, null, $otro))->toThrow(PlantillaRechazada::class, 'Solo se publica una plantilla tuya');
});

test('una de la empresa la cambia solo quien publica; quien solo la usa recibe por qué', function () {
    $editor = quienUsa('pruebas.listas.plantillas.publicar');
    $mia = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $editor);
    $empresa = plantillas()->publicar($mia->id, null, $editor);
    $usuario = quienUsa();
    $vista = plantillas()->disponibles(TIPO_COSAS, $usuario)[0];

    expect([$vista->esDeLaEmpresa, $vista->puedeCambiar])->toBe([true, false])
        ->and(fn () => plantillas()->desactivar($empresa->id, $empresa->version, $usuario))->toThrow(PlantillaRechazada::class, 'la cambia quien tiene el permiso de publicar');

    plantillas()->editar($empresa->id, 'Videovigilancia municipal', 'Plazas y parques', $empresa->version, $editor);

    expect(Plantilla::query()->findOrFail($empresa->id)->only(['nombre', 'descripcion']))->toBe(['nombre' => 'Videovigilancia municipal', 'descripcion' => 'Plazas y parques']);
});

test('desactivada no se ofrece ni se aplica, y no se borra', function () {
    $yo = quienUsa();
    $p = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $yo);

    plantillas()->desactivar($p->id, $p->version, $yo);

    expect(plantillas()->disponibles(TIPO_COSAS, $yo))->toBe([])
        ->and(fn () => plantillas()->aplicar($p->id, [], $yo))->toThrow(PlantillaRechazada::class, 'está desactivada: elige otra')
        ->and(Plantilla::query()->whereKey($p->id)->exists())->toBeTrue();
});

test('el rechazo del tipo al aplicar llega tal cual, con qué hacer (D7), y no crea nada', function () {
    $yo = quienUsa();
    $p = plantillas()->guardar(TIPO_COSAS, 1, 'Videovigilancia', null, $yo);

    expect(fn () => plantillas()->aplicar($p->id, ['ocupado' => true], $yo))->toThrow(PlantillaRechazada::class, 'Esa lista ya tiene cosas: vacíala antes de usar una plantilla.');
});

test('D5: un tipo con montos pide también ver costos para guardar, ver y aplicar', function () {
    $sinCostos = quienUsa();
    $conCostos = quienUsa(GrupoSensible::Costos->permiso());

    expect(fn () => plantillas()->guardar(TIPO_PRECIOS, 1, 'Con precios', null, $sinCostos))->toThrow(PlantillaRechazada::class, 'No tienes permiso');

    $p = plantillas()->guardar(TIPO_PRECIOS, 1, 'Con precios', null, $conCostos);

    expect(plantillas()->aplicar($p->id, [], $conCostos)->propuesta->cosas[0]['precio'])->toBe('250.00')
        ->and(plantillas()->disponibles(TIPO_PRECIOS, $sinCostos))->toBe([]);
});

test('las acciones están registradas sin permiso propio y llegan al servicio, que revisa el del tipo', function () {
    $yo = quienUsa();
    $acciones = app(RegistroAcciones::class);
    $ejecutor = app(EjecutorAcciones::class);

    foreach (['guardar', 'editar', 'actualizar', 'publicar', 'desactivar'] as $operacion) {
        expect($acciones->obtener("nucleo.plantillas.{$operacion}")->permiso())->toBeNull();
    }

    $guardada = $ejecutor->ejecutar($acciones->obtener('nucleo.plantillas.guardar'), ['tipo' => TIPO_COSAS, 'documento_id' => 1, 'nombre' => 'Desde la acción'], $yo);
    $ejecutor->ejecutar($acciones->obtener('nucleo.plantillas.desactivar'), ['plantilla_id' => $guardada->id, 'version' => $guardada->version], $yo);

    expect(Plantilla::query()->findOrFail($guardada->id)->es_activa)->toBeFalse()
        ->and(fn () => $ejecutor->ejecutar($acciones->obtener('nucleo.plantillas.guardar'), ['tipo' => TIPO_COSAS, 'documento_id' => 1, 'nombre' => 'Ajena'], User::factory()->create()))
        ->toThrow(PlantillaRechazada::class, 'No tienes permiso');
});
