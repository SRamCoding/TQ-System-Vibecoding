<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\PartidasDeControlDeProyecto;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Plantillas\PlantillaArbol;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\PlantillasService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El árbol desde una plantilla en la pestaña «Partidas de control» (plan 2.11, paso 6, D7 a D10): «Desde una
 * plantilla» como opción de «Armar el árbol», el costo sugerido y editable, «Creado desde …» a la vista, el rechazo
 * de D7 en la pantalla, y el botón «Plantillas» que abre el panel común solo para administrar. Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create());
});

function conPlantillas(string ...$extra): User
{
    $usuario = User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, 'erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso(), ...$extra]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Una plantilla «Cámaras» con PC-01 (padre), PC-01.01 y PC-02 de gastos generales, del usuario. */
function plantillaChica(User $usuario): int
{
    $arbol = app(ArbolPartidasService::class)->crear(PresupuestoDePrueba::proyecto(), '100.00', $usuario);
    $pc01 = app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-01', 'Cámaras', $usuario->id);
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, $pc01->id, 'PC-01.01', 'Instalación de cámaras', $usuario->id);
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-02', 'Gastos generales', $usuario->id, esGastosGenerales: true);

    return app(PlantillasService::class)->guardar(PlantillaArbol::CLAVE, $arbol->id, 'Cámaras', null, $usuario)->id;
}

test('sin presupuesto aprobado, la plantilla es el único origen: se escribe el costo, se confirma y se ve de dónde salió', function () {
    $usuario = conPlantillas();
    $plantilla = plantillaChica($usuario);
    $proyecto = PresupuestoDePrueba::proyecto();

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('armarArbol')
        ->assertTableActionDataSet(['origen' => 'plantilla', 'plantilla_id' => $plantilla, 'costo_presupuestado' => null]);

    expect(array_column($pantalla->get('mountedActions.0.data.partidas'), 'codigo'))->toBe(['PC-01', 'PC-01.01', 'PC-02']);

    $pantalla->setTableActionData(['costo_presupuestado' => '1200.00'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Árbol armado: revísalo y apruébalo cuando cuadre.');

    expect(ArbolPartidas::query()->where('proyecto_id', $proyecto)->sole()->plantilla_version_id)->not->toBeNull();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee('Creado desde la plantilla Cámaras v1')
        ->assertSee('faltan 1,200.00 por repartir');
});

test('con presupuesto aprobado, cambiar a «Desde una plantilla» trae su propuesta y el costo del presupuesto (D8)', function () {
    $usuario = conPlantillas();
    $plantilla = plantillaChica($usuario);
    test()->actingAs(User::factory()->create());
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    test()->actingAs($usuario);

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('armarArbol')
        ->assertTableActionDataSet(['origen' => 'presupuesto'])
        ->setTableActionData(['origen' => 'plantilla'])
        ->assertTableActionDataSet(['plantilla_id' => $plantilla, 'costo_presupuestado' => '19525.00']);

    expect(array_column($pantalla->get('mountedActions.0.data.partidas'), 'etiqueta'))->toBe(['PC-01', 'PC-01.01', 'PC-02']);
});

test('D7 en la pantalla: si el borrador recibe partidas mientras se revisa, el panel dice por qué y el botón se va', function () {
    $usuario = conPlantillas();
    $plantilla = plantillaChica($usuario);
    $proyecto = PresupuestoDePrueba::proyecto();
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '100.00', $usuario);

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->mountTableAction('armarArbol');
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-07', 'Hecha a mano', $usuario->id);

    // Volver a elegir la plantilla arma la propuesta otra vez, con la misma comprobación.
    $pantalla->setTableActionData(['plantilla_id' => $plantilla])->assertNotified(
        'El árbol de '.app(ProyectosService::class)->resumen($proyecto)->codigo.' (v1, en borrador) ya tiene partidas: sigue armándolo en su pestaña, o desactiva las que sobren. Armarlo desde otro origen pisaría lo que ya tiene.',
    );

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionHidden('armarArbol');

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->count())->toBe(1);
});

test('«Plantillas» abre el panel común sin «Usar» (D9), y solo con el permiso de editar el árbol', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    test()->actingAs(User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER]));
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionHidden('plantillas');

    conPlantillas();
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertDontSee('Cerrar las plantillas')
        ->callTableAction('plantillas')
        ->assertSet('plantillasAbiertas', true)
        ->assertSee('Cerrar las plantillas')
        ->call('cerrarPlantillas')
        ->assertSet('plantillasAbiertas', false);
});
