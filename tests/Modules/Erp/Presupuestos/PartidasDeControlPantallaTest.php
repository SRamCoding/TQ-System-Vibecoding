<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\Montos;
use App\Modules\Erp\Presupuestos\Filament\PartidasDeControlDeProyecto;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * La pestaña «Partidas de control» de la ficha del proyecto (plan 2.10, paso 3 y D5): el árbol con sangría y montos,
 * lo que falta repartir, crear el árbol con el costo sugerido, agregar, fijar, desactivar y aprobar por las acciones.
 * Personas y proyectos inventados; los números, los del flujo 1.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnPartidas(string ...$permisos): User
{
    $usuario = User::factory()->create(['name' => 'Rosa Prueba Vela'])
        ->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Un árbol en borrador con PC-01 (padre) › PC-01.01 (6,430.00) y PC-02 (hoja sin monto), de 19,525.00. */
function arbolEnPantalla(int $proyectoId, User $quien): ArbolPartidas
{
    $servicio = app(ArbolPartidasService::class);
    $arbol = $servicio->crear($proyectoId, '19525.00', $quien);
    $camaras = $servicio->agregarPartida($arbol->id, null, 'PC-01', 'Cámaras', $quien->id);
    $suministro = $servicio->agregarPartida($arbol->id, $camaras->id, 'PC-01.01', 'Suministro de cámaras', $quien->id);
    $servicio->agregarPartida($arbol->id, null, 'PC-02', 'Cableado y postes', $quien->id);
    $servicio->fijarPresupuestado($suministro->id, '6430.00', $suministro->refresh()->version);

    return $arbol;
}

test('los montos se escriben con miles y sin pasar por float', function () {
    expect(Montos::texto('19525.00'))->toBe('19,525.00')
        ->and(Montos::texto('-525.00'))->toBe('-525.00')
        ->and(Montos::texto('1234567890123.45'))->toBe('1,234,567,890,123.45')
        ->and(Montos::texto('0.00'))->toBe('0.00');
});

test('la pestaña se suma a la ficha del proyecto solo con el permiso de ver presupuestos', function () {
    $proyecto = PresupuestoDePrueba::proyecto();

    test()->actingAs(User::factory()->create()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER));
    Livewire::test(VerProyecto::class, ['record' => $proyecto])->assertDontSee('Partidas de control');
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])->assertForbidden();

    entrarEnPartidas();
    Livewire::test(VerProyecto::class, ['record' => $proyecto])->assertSee('Partidas de control');
});

test('sin árbol, ofrece crearlo; sin presupuesto aprobado, el costo se escribe', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    entrarEnPartidas('erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso());

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee('todavía no tiene árbol de partidas')
        ->mountTableAction('crearArbol')
        ->assertTableActionDataSet(['costo_presupuestado' => null])
        ->setTableActionData(['costo_presupuestado' => '1000.00'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Guardado.');

    expect(ArbolPartidas::query()->where('proyecto_id', $proyecto)->value('costo_presupuestado'))->toBe('1000.00');
});

test('D5: con presupuesto aprobado, el costo viene sugerido con costo directo + gastos generales (19,525.00)', function () {
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    entrarEnPartidas('erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso());

    expect(app(ConsultaArbol::class)->costoSugerido($proyecto))->toBe('19525.00');

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('crearArbol')
        ->assertTableActionDataSet(['costo_presupuestado' => '19525.00']);

    // Plan 2.11, D11: sin ver costos no se ofrece crear el árbol, y el sugerido nunca llega (ADR 0016).
    entrarEnPartidas('erp.presupuestos.arbol.editar');
    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertTableActionHidden('crearArbol')
        ->assertTableActionHidden('armarArbol')
        ->assertDontSee('19,525.00');
});

test('el árbol se ve en orden con sangría, los padres suman sus hojas y arriba dice cuánto falta repartir', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $yo = entrarEnPartidas(GrupoSensible::Costos->permiso());
    arbolEnPantalla($proyecto, $yo);

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSeeInOrder(['PC-01', 'Cámaras', 'PC-01.01', 'Suministro de cámaras', 'PC-02', 'Cableado y postes'])
        ->assertSee('Suma de sus hojas')
        ->assertSee('6,430.00')
        ->assertSee('Costo presupuestado 19,525.00 · repartido 6,430.00 · faltan 13,095.00 por repartir')
        ->assertSeeHtml('padding-inline-start: 1.25rem');
});

test('sin el permiso de ver costos, los montos no se ven', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $yo = entrarEnPartidas();
    arbolEnPantalla($proyecto, $yo);

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee('Suministro de cámaras')
        ->assertDontSee('6,430.00')
        ->assertDontSee('19,525.00')
        ->assertSee('Los montos se ven con el permiso de ver costos.');
});

test('agregar una hija trae el código siguiente; fijar el monto de una hoja cuadra el árbol', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $yo = entrarEnPartidas('erp.presupuestos.arbol.editar', GrupoSensible::Costos->permiso());
    $arbol = arbolEnPantalla($proyecto, $yo);
    $camaras = PartidaControl::query()->where('codigo', 'PC-01')->firstOrFail();
    $cableado = PartidaControl::query()->where('codigo', 'PC-02')->firstOrFail();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->mountTableAction('agregarHija', "p{$camaras->id}")
        ->assertTableActionDataSet(['codigo' => 'PC-01.02'])
        ->setTableActionData(['nombre' => 'Mano de obra de instalación', 'responsable_user_id' => $yo->id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->callTableAction('fijarPresupuestado', "p{$cableado->id}", ['monto' => '13095.00'])
        ->assertHasNoTableActionErrors()
        ->assertSee('cuadra ✓');

    expect(PartidaControl::query()->where('codigo', 'PC-01.02')->value('padre_id'))->toBe($camaras->id)
        ->and(app(ConsultaArbol::class)->porRepartir($arbol->id))->toBe('0.00');
});

test('aprobar un árbol que no cuadra lo rechaza con el motivo; uno que cuadra crea la PC-99 y deja de ser editable', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $yo = entrarEnPartidas('erp.presupuestos.arbol.editar', 'erp.presupuestos.arbol.aprobar', GrupoSensible::Costos->permiso());
    arbolEnPantalla($proyecto, $yo);
    $cableado = PartidaControl::query()->where('codigo', 'PC-02')->firstOrFail();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->callTableAction('aprobarArbol')
        ->assertNotNotified('Guardado.');

    app(ArbolPartidasService::class)->fijarPresupuestado($cableado->id, '13095.00', $cableado->version);

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->callTableAction('aprobarArbol')
        ->assertNotified('Guardado.');

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee('Aprobado')
        ->assertSee('PC-99')
        ->assertTableActionHidden('agregarPartida')
        ->assertTableActionHidden('aprobarArbol');

    expect(Proyecto::query()->findOrFail($proyecto)->estado->codigo)->toBe('en_ejecucion');
});

test('desactivar una hoja sin monto la marca como desactivada', function () {
    $proyecto = PresupuestoDePrueba::proyecto();
    $yo = entrarEnPartidas('erp.presupuestos.arbol.editar');
    arbolEnPantalla($proyecto, $yo);
    $cableado = PartidaControl::query()->where('codigo', 'PC-02')->firstOrFail();

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $proyecto])
        ->callTableAction('desactivarPartida', "p{$cableado->id}")
        ->assertNotified('Guardado.')
        ->assertSee('Desactivada');

    expect($cableado->refresh()->es_activa)->toBeFalse();
});
