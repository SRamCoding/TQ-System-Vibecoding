<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Filament\PartidasDeControlDeProyecto;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * «Armar el árbol» en la pestaña «Partidas de control» (plan 2.9b, paso 6, D3 a D7): un solo botón, solo si el árbol
 * se puede llenar y hay un origen para la persona; el panel con la propuesta editable; al confirmar, el árbol en
 * borrador con la columna «Origen». Flujo 1 del v4; personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function quienArma(string ...$extra): User
{
    // Plan 2.11, D11: armar el árbol pide ver costos.
    $usuario = User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, 'erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso(), ...$extra]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Un proyecto con el flujo 1 aprobado y vigente. */
function proyectoParaArmar(): int
{
    test()->actingAs(User::factory()->create());
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return $proyecto;
}

/** Cambia filas de la propuesta abierta por su código. */
function ajustarPropuesta(Testable $pantalla, array $porCodigo): Testable
{
    $partidas = $pantalla->get('mountedActions.0.data.partidas');

    foreach ($partidas as $clave => $fila) {
        $partidas[$clave] = [...$fila, ...($porCodigo[$fila['codigo']] ?? [])];
    }

    return $pantalla->set('mountedActions.0.data.partidas', $partidas);
}

test('el botón aparece solo con el permiso de editar el árbol y un presupuesto aprobado', function () {
    $sinPresupuesto = PresupuestoDePrueba::proyecto();
    $proyecto = proyectoParaArmar();

    $this->actingAs(User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER]));
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionHidden('armarArbol');

    quienArma();
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $sinPresupuesto])->assertTableActionHidden('armarArbol');
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionVisible('armarArbol');
});

test('F1: el panel trae la propuesta con el jefe por omisión; confirmar arma el borrador y la columna «Origen» lo dice', function () {
    $proyecto = proyectoParaArmar();
    $jefe = app(ProyectosService::class)->resumen($proyecto)->jefeProyectoUserId;
    quienArma();

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('armarArbol')
        ->assertTableActionDataSet(['origen' => 'presupuesto', 'responsable_user_id' => $jefe]);
    $etiquetas = array_column($pantalla->get('mountedActions.0.data.partidas'), 'etiqueta');

    expect($etiquetas)->toHaveCount(9)
        ->and($etiquetas)->toContain('PC-01.01 · 01.01 · Instalación de cámara domo IP · 6,430.00')
        ->and($etiquetas)->toContain('PC-04 · gastos generales del presupuesto · 1,775.00');

    $pantalla->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Árbol armado: revísalo y apruébalo cuando cuadre.')
        ->assertTableActionHidden('armarArbol');

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee(['título 01', '01.01 · Instalación de cámara domo IP', '03.02 · Pruebas y puesta en marcha', 'gastos generales del presupuesto'])
        ->assertSee('Costo presupuestado 19,525.00 · repartido 19,525.00 · cuadra');
});

test('F1 en el panel: renombrar y quitar una partida; su monto queda por repartir', function () {
    $proyecto = proyectoParaArmar();
    quienArma();

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->mountTableAction('armarArbol');
    ajustarPropuesta($pantalla, ['PC-01.01' => ['nombre' => 'Cámaras del parque'], 'PC-02.02' => ['quitar' => true]])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-01.01')->value('nombre'))->toBe('Cámaras del parque')
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-02.02')->exists())->toBeFalse();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertSee('faltan 4,360.00 por repartir');
});

test('con el jefe inactivo y sin elegir responsable, el panel muestra el mensaje del servicio y no arma nada', function () {
    $proyecto = proyectoParaArmar();
    User::query()->whereKey(app(ProyectosService::class)->resumen($proyecto)->jefeProyectoUserId)->update(['es_activo' => false]);
    quienArma();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('armarArbol')
        ->assertTableActionDataSet(['responsable_user_id' => null])
        ->callMountedTableAction()
        ->assertNotified('Elige quién responde por las partidas: el jefe del proyecto no es un usuario activo, así que no hay uno por omisión.');

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->exists())->toBeFalse();
});

test('D11: sin ver costos no se ofrece «Armar el árbol», y la acción lo rechaza también sin la pantalla', function () {
    $proyecto = proyectoParaArmar();
    $sinCostos = User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, 'erp.presupuestos.arbol.editar']);
    $this->actingAs($sinCostos);

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionHidden('armarArbol');

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.armar-arbol-desde-presupuesto', ['proyecto_id' => $proyecto], $sinCostos))
        ->toThrow(ArbolRechazado::class, 'Armar el árbol de partidas pide el permiso de ver costos')
        ->and(PartidaControl::query()->where('proyecto_id', $proyecto)->exists())->toBeFalse();
});

test('si el borrador recibe partidas con el panel abierto, confirmar dice por qué (D7) y no arma nada', function () {
    $proyecto = proyectoParaArmar();
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '19525.00', User::factory()->create());
    $yo = quienArma();

    $pantalla = Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->mountTableAction('armarArbol');
    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-07', 'Hecha a mano', $yo->id);

    $pantalla->callMountedTableAction()->assertNotified(
        'El árbol de '.app(ProyectosService::class)->resumen($proyecto)->codigo.' (v1, en borrador) ya tiene partidas: sigue armándolo en su pestaña, o desactiva las que sobren. Armarlo desde otro origen pisaría lo que ya tiene.',
    );

    expect(PartidaControl::query()->where('proyecto_id', $proyecto)->count())->toBe(1);
});

test('D3: con un borrador vacío se ofrece; con partidas, no', function () {
    $proyecto = proyectoParaArmar();
    $arbol = app(ArbolPartidasService::class)->crear($proyecto, '100.00', User::factory()->create());
    quienArma();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionVisible('armarArbol');

    app(ArbolPartidasService::class)->agregarPartida($arbol->id, null, 'PC-01', 'Hecha a mano', User::factory()->create()->id);

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertTableActionHidden('armarArbol');
});
