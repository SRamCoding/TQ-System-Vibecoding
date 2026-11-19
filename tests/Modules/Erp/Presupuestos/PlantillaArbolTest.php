<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Plantillas\PlantillaArbol;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\PlantillasService;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El árbol de partidas como primer tipo de plantilla (plan 2.11, paso 6; ADR 0022): guardar el árbol del flujo 1 sin
 * montos ni responsables (D6), aplicarlo en otro proyecto sin árbol o con un borrador vacío con el costo sugerido (D8),
 * el rechazo de D7 con partidas, la versión que cambia al confirmar (D10), y que editar la plantilla no toca lo ya
 * creado (D3). Números del flujo 1 del v4; personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function quienUsaPlantillas(): User
{
    return User::factory()->create()->givePermissionTo(['erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso()]);
}

/** Un proyecto con el flujo 1 aprobado y vigente. */
function proyectoConFlujo1(): int
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return $proyecto;
}

/** El árbol del flujo 1 armado desde su presupuesto y guardado como plantilla «Videovigilancia»; devuelve su id. */
function plantillaDelFlujo1(User $usuario): int
{
    $proyecto = proyectoConFlujo1();
    $arbol = app(ArbolPartidasService::class)->crearDesdePropuesta(app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto), new AjustesPropuesta, $usuario);

    return app(PlantillasService::class)->guardar(PlantillaArbol::CLAVE, $arbol->id, 'Videovigilancia', null, $usuario)->id;
}

/**
 * Por la acción registrada; sin `plantilla_version_id`, con la vigente (la que la pantalla habría mostrado).
 *
 * @param  array<string, mixed>  $datos
 */
function armarDesdePlantilla(array $datos, User $usuario): ArbolPartidas
{
    $datos['plantilla_version_id'] ??= app(ConsultaArbol::class)->propuestaDesdePlantilla($datos['proyecto_id'], $datos['plantilla_id'], null, null, $usuario)->plantillaVersionId;

    return app(EjecutorAcciones::class)->ejecutar(app(RegistroAcciones::class)->obtener('erp.presupuestos.armar-arbol-desde-plantilla'), $datos, $usuario);
}

test('está registrado: usar con el permiso de editar el árbol, publicar con el suyo; la acción, con el de editar', function () {
    $tipo = app(RegistroPlantillas::class)->obtener('erp.presupuestos.arbol');

    expect([$tipo->nombre(), $tipo->permisoUsar(), $tipo->permisoPublicar(), $tipo->grupoSensible()])
        ->toBe(['Árbol de partidas de control', 'erp.presupuestos.arbol.editar', 'erp.presupuestos.plantillas.publicar', null])
        ->and(app(RegistroAcciones::class)->obtener('erp.presupuestos.armar-arbol-desde-plantilla')->permiso())->toBe('erp.presupuestos.arbol.editar');
});

test('F1: guarda códigos, nombres, jerarquía y la de gastos generales; ni montos ni responsables ni la PC-99', function () {
    $usuario = quienUsaPlantillas();
    $proyecto = proyectoConFlujo1();
    $arbol = app(ArbolPartidasService::class)->crearDesdePropuesta(app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto), new AjustesPropuesta, $usuario);
    PartidaControl::query()->create([
        'proyecto_id' => $proyecto, 'arbol_alta_id' => $arbol->id, 'codigo' => 'PC-99', 'nombre' => 'No presupuestados',
        'responsable_user_id' => $usuario->id, 'es_no_presupuestado' => true,
    ]);

    $contenido = (new PlantillaArbol)->capturar($arbol->id, $usuario);

    expect($contenido['partidas'])->toHaveCount(9)
        ->and($contenido['partidas'][0])->toBe(['codigo' => 'PC-01', 'nombre' => 'CÁMARAS', 'padre' => null, 'gastos_generales' => false])
        ->and($contenido['partidas'][1])->toBe(['codigo' => 'PC-01.01', 'nombre' => 'Instalación de cámara domo IP', 'padre' => 'PC-01', 'gastos_generales' => false])
        ->and($contenido['partidas'][8])->toBe(['codigo' => 'PC-04', 'nombre' => 'Gastos generales', 'padre' => null, 'gastos_generales' => true])
        ->and((new PlantillaArbol)->validar($contenido, 1))->toBe([]);
});

test('un árbol sin partidas no se guarda como plantilla', function () {
    $usuario = quienUsaPlantillas();
    $arbol = app(ArbolPartidasService::class)->crear(PresupuestoDePrueba::proyecto(), '100.00', $usuario);

    expect(fn () => app(PlantillasService::class)->guardar(PlantillaArbol::CLAVE, $arbol->id, 'Vacía', null, $usuario))
        ->toThrow(Exception::class, 'Este árbol todavía no tiene partidas');
});

test('el contenido se valida: la PC-99, repetidas, padres que no cuadran y una sola de gastos generales sin hijas', function () {
    $p = fn (string $codigo, ?string $padre, bool $gg = false): array => ['codigo' => $codigo, 'nombre' => "Partida {$codigo}", 'padre' => $padre, 'gastos_generales' => $gg];

    expect((new PlantillaArbol)->validar(['partidas' => []], 1))->toBe(['no tiene partidas'])
        ->and((new PlantillaArbol)->validar(['partidas' => [$p('PC-01', null)]], 2))->toBe(['el formato 2 no existe para el árbol'])
        ->and((new PlantillaArbol)->validar(['partidas' => [
            $p('PC-01', null, true), $p('PC-01.01', 'PC-01'), $p('PC-01.01', 'PC-01'), $p('PC-02.01', 'PC-02'), $p('PC-03', 'PC-01'), $p('PC-04', null, true), $p('PC-99', null), $p('X', null),
        ]], 1))->toBe([
            'PC-01.01 está repetida',
            'PC-02.01 cuelga de PC-02, que no está antes',
            'PC-03 debe colgar de la raíz',
            'la PC-99 la crea el sistema al aprobar: no va en la plantilla',
            'la partida 8 no tiene un código como «PC-01» o «PC-01.02»',
            'solo una partida es la de gastos generales',
            'PC-01, la de gastos generales, no puede tener partidas debajo',
        ]);
});

test('F1 en otro proyecto sin árbol: costo sugerido del presupuesto, partidas sin monto, el jefe por omisión y la versión guardada', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $otro = proyectoConFlujo1();
    $jefe = app(ProyectosService::class)->resumen($otro)->jefeProyectoUserId;

    $propuesta = app(ConsultaArbol::class)->propuestaDesdePlantilla($otro, $plantilla, null, null, $usuario);

    expect([$propuesta->arbolId, $propuesta->costoPresupuestado, $propuesta->responsableUserId, $propuesta->presupuestoId])->toBe([null, '19525.00', $jefe, null])
        ->and(array_map(fn ($n): array => [$n->codigo, $n->esHoja, $n->monto], [$propuesta->nodos[0], $propuesta->nodos[1], $propuesta->nodos[8]]))
        ->toBe([['PC-01', false, null], ['PC-01.01', true, null], ['PC-04', true, null]]);

    $arbol = armarDesdePlantilla(['proyecto_id' => $otro, 'plantilla_id' => $plantilla, 'plantilla_version_id' => $propuesta->plantillaVersionId, 'costo_presupuestado' => '19525.00', 'quitar' => ['PC-03']], $usuario);
    $filas = collect(app(ConsultaArbol::class)->filas($arbol->id))->keyBy('codigo');

    expect($filas->keys()->all())->toBe(['PC-01', 'PC-01.01', 'PC-02', 'PC-02.01', 'PC-02.02', 'PC-04'])
        ->and($filas->pluck('responsableUserId')->unique()->all())->toBe([$jefe])
        ->and($filas->pluck('origen')->filter()->all())->toBe([])
        ->and($filas['PC-04']->esGastosGenerales)->toBeTrue()
        ->and([$arbol->presupuesto_id, $arbol->plantilla_version_id])->toBe([null, $propuesta->plantillaVersionId])
        ->and(app(ConsultaArbol::class)->plantillaDe($arbol))->toBe('Videovigilancia v1')
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('19525.00');
});

test('con un borrador vacío, se sugiere su costo y se llena ese mismo; sin presupuesto ni borrador, no hay sugerido', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $proyecto = PresupuestoDePrueba::proyecto();

    expect(app(ConsultaArbol::class)->costoParaLlenar($proyecto))->toBeNull();

    $borrador = app(ArbolPartidasService::class)->crear($proyecto, '500.00', $usuario);

    expect(app(ConsultaArbol::class)->costoParaLlenar($proyecto))->toBe('500.00');

    $arbol = armarDesdePlantilla(['proyecto_id' => $proyecto, 'plantilla_id' => $plantilla, 'costo_presupuestado' => '600.00'], $usuario);

    expect($arbol->id)->toBe($borrador->id)
        ->and($arbol->costo_presupuestado)->toBe('600.00')
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->count())->toBe(9);
});

test('D7: con un borrador que ya tiene partidas se rechaza diciendo por qué y qué hacer, y no crea nada', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $proyecto = PresupuestoDePrueba::proyecto();
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '100.00', $usuario);
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-01', 'Hecha a mano', $usuario->id);
    $codigo = app(ProyectosService::class)->resumen($proyecto)->codigo;

    expect(fn () => armarDesdePlantilla(['proyecto_id' => $proyecto, 'plantilla_id' => $plantilla, 'costo_presupuestado' => '100.00'], $usuario))
        ->toThrow(ArbolRechazado::class, "El árbol de {$codigo} (v1, en borrador) ya tiene partidas: sigue armándolo en su pestaña, o desactiva las que sobren.")
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->count())->toBe(1);
});

test('D10: si la plantilla tiene otra versión desde que se abrió, se rechaza; editarla no cambia el árbol ya creado (D3)', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $hecho = PresupuestoDePrueba::proyecto();
    $creado = armarDesdePlantilla(['proyecto_id' => $hecho, 'plantilla_id' => $plantilla, 'costo_presupuestado' => '100.00'], $usuario);
    $vista = $creado->plantilla_version_id;

    // Otra versión: desde un árbol con una partida renombrada.
    app(ArbolPartidasService::class)->editarPartida(
        $p = PartidaControl::query()->where('proyecto_id', $hecho)->where('codigo', 'PC-01')->sole()->id,
        'Cámaras del parque', $usuario->id, PartidaControl::query()->findOrFail($p)->version,
    );
    $resumen = collect(app(PlantillasService::class)->disponibles(PlantillaArbol::CLAVE, $usuario))->firstWhere('id', $plantilla);
    app(PlantillasService::class)->actualizarDesde($plantilla, $creado->id, $resumen->version, $usuario);
    $otro = PresupuestoDePrueba::proyecto();

    expect(fn () => armarDesdePlantilla(['proyecto_id' => $otro, 'plantilla_id' => $plantilla, 'plantilla_version_id' => $vista, 'costo_presupuestado' => '100.00'], $usuario))
        ->toThrow(ArbolRechazado::class, 'La plantilla «Videovigilancia» cambió (ahora v2) mientras revisabas la propuesta: vuelve a abrirla.')
        ->and(PartidaControl::query()->where('proyecto_id', $otro)->exists())->toBeFalse()
        ->and($creado->refresh()->plantilla_version_id)->toBe($vista)
        ->and(app(ConsultaArbol::class)->plantillaDe($creado))->toBe('Videovigilancia v1');

    $nuevo = armarDesdePlantilla(['proyecto_id' => $otro, 'plantilla_id' => $plantilla, 'costo_presupuestado' => '100.00'], $usuario);

    expect(PartidaControl::query()->where('proyecto_id', $otro)->where('codigo', 'PC-01')->value('nombre'))->toBe('Cámaras del parque')
        ->and(app(ConsultaArbol::class)->plantillaDe($nuevo))->toBe('Videovigilancia v2');
});

test('sin el permiso de editar el árbol, la plantilla no se aplica', function () {
    $plantilla = plantillaDelFlujo1(quienUsaPlantillas());

    expect(fn () => app(ConsultaArbol::class)->propuestaDesdePlantilla(PresupuestoDePrueba::proyecto(), $plantilla, null, '1.00', User::factory()->create()))
        ->toThrow(Exception::class);
});

test('la base no deja que un árbol salga del presupuesto y de una plantilla a la vez', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $arbol = ArbolPartidas::query()->whereNotNull('presupuesto_id')->sole();
    $version = armarDesdePlantilla(['proyecto_id' => PresupuestoDePrueba::proyecto(), 'plantilla_id' => $plantilla, 'costo_presupuestado' => '1.00'], $usuario)->plantilla_version_id;

    expect(fn () => ArbolPartidas::query()->whereKey($arbol->id)->update(['plantilla_version_id' => $version]))
        ->toThrow(QueryException::class, 'partida_arboles_un_origen_check');
});

/** @param array<string, mixed> $datos */
function porLaAccion(array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar(app(RegistroAcciones::class)->obtener('erp.presupuestos.armar-arbol-desde-plantilla'), $datos, $usuario);
}

test('la acción exige la versión revisada (D10), un costo no negativo y ver costos (D11)', function () {
    $usuario = quienUsaPlantillas();
    $plantilla = plantillaDelFlujo1($usuario);
    $proyecto = PresupuestoDePrueba::proyecto();
    $sinCostos = User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.editar');

    expect(fn () => porLaAccion(['proyecto_id' => $proyecto, 'plantilla_id' => $plantilla, 'costo_presupuestado' => '100.00'], $usuario))->toThrow(ValidationException::class, 'Versión revisada')
        ->and(fn () => porLaAccion(['proyecto_id' => $proyecto, 'plantilla_id' => $plantilla, 'plantilla_version_id' => 1, 'costo_presupuestado' => '-5'], $usuario))->toThrow(ValidationException::class, 'Costo presupuestado')
        ->and(fn () => porLaAccion(['proyecto_id' => $proyecto, 'plantilla_id' => $plantilla, 'plantilla_version_id' => 1, 'costo_presupuestado' => '100.00'], $sinCostos))->toThrow(ArbolRechazado::class, 'pide el permiso de ver costos')
        ->and(ArbolPartidas::query()->where('proyecto_id', $proyecto)->exists())->toBeFalse();
});

test('la plantilla personal de otra persona «no existe», y un proyecto anulado no se arma', function () {
    $plantilla = plantillaDelFlujo1(quienUsaPlantillas());
    $otra = quienUsaPlantillas();

    expect(fn () => app(ConsultaArbol::class)->propuestaDesdePlantilla(PresupuestoDePrueba::proyecto(), $plantilla, null, '1.00', $otra))
        ->toThrow(PlantillaRechazada::class, 'Esa plantilla no existe.');

    $id = PresupuestoDePrueba::proyecto();
    $proyecto = Proyecto::query()->findOrFail($id);
    $motivo = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->value('id');
    app(TransicionesService::class)->transicionar($proyecto, 'anular', $proyecto->version, User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.anular'), new MotivoElegido($motivo, 'Se registró dos veces'), 'Se registró dos veces');
    $duena = User::query()->whereKey(Plantilla::query()->findOrFail($plantilla)->propietario_user_id)->sole();

    expect(fn () => app(ConsultaArbol::class)->propuestaDesdePlantilla($id, $plantilla, null, '1.00', $duena))
        ->toThrow(ArbolRechazado::class, 'está anulado: no se le arma un árbol.');
});
