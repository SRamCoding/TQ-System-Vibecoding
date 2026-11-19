<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;
use App\Modules\Erp\Presupuestos\Filament\PartidasDeControlDeProyecto;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\ArbolDePrueba;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * «Editar partida» en el árbol en borrador (plan 2.9b, paso 5, D5): el nombre y el responsable, con la versión leída
 * al abrir; el código, el monto y el origen no cambian. Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create());
});

/** El árbol del flujo 1 armado desde el presupuesto, en borrador; devuelve su PC-02.01. */
function partidaDelFlujo1(): PartidaControl
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    app(ArbolPartidasService::class)->crearDesdePropuesta(app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyecto), new AjustesPropuesta, User::factory()->create());

    return PartidaControl::query()->where('proyecto_id', $proyecto)->where('codigo', 'PC-02.01')->sole();
}

test('cambia el nombre y el responsable; el código, el monto y el origen quedan igual', function () {
    $partida = partidaDelFlujo1();
    $origen = $partida->origen_partida_id;
    $otra = User::factory()->create();

    app(ArbolPartidasService::class)->editarPartida($partida->id, '  Cableado UTP del parque ', $otra->id, $partida->version);
    $editada = $partida->refresh();
    $montos = collect(app(ConsultaArbol::class)->filas($editada->arbol_alta_id))->keyBy('codigo');

    expect([$editada->codigo, $editada->nombre, $editada->responsable_user_id, $editada->origen_partida_id])
        ->toBe(['PC-02.01', 'Cableado UTP del parque', $otra->id, $origen])
        ->and($montos['PC-02.01']->presupuestado)->toBe('2412.00');
});

test('con la versión vieja, otra persona ya la cambió; un responsable inactivo o un nombre vacío no sirven', function () {
    $partida = partidaDelFlujo1();
    $vieja = $partida->version;
    app(ArbolPartidasService::class)->editarPartida($partida->id, 'Primero', User::factory()->create()->id, $vieja);

    expect(fn () => app(ArbolPartidasService::class)->editarPartida($partida->id, 'Después', User::factory()->create()->id, $vieja))
        ->toThrow(VersionDesactualizada::class, 'la partida PC-02.01')
        ->and(fn () => app(ArbolPartidasService::class)->editarPartida($partida->id, 'Otro', User::factory()->create(['es_activo' => false])->id, $partida->refresh()->version))
        ->toThrow(ArbolRechazado::class, 'usuario activo')
        ->and(fn () => app(ArbolPartidasService::class)->editarPartida($partida->id, '   ', User::factory()->create()->id, $partida->refresh()->version))
        ->toThrow(ArbolRechazado::class, 'va con texto');
});

test('en un árbol aprobado no se edita', function () {
    $a = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]]);
    $partida = PartidaControl::query()->findOrFail($a->partidas['PC-01']);

    expect(fn () => app(ArbolPartidasService::class)->editarPartida($partida->id, 'Otro', $a->responsable->id, $partida->version))
        ->toThrow(ArbolRechazado::class, 'ya está aprobado');
});

test('la acción está registrada con el permiso de editar el árbol', function () {
    expect(app(RegistroAcciones::class)->obtener('erp.presupuestos.editar-partida')->permiso())->toBe('erp.presupuestos.arbol.editar');
});

test('en la pestaña, «Editar» abre con el nombre y el responsable y guarda; el responsable inactivo se muestra para cambiarlo', function () {
    $partida = partidaDelFlujo1();
    User::query()->whereKey($partida->responsable_user_id)->update(['es_activo' => false]);
    $nueva = User::factory()->create(['name' => 'Rosa Prueba Vela']);
    $this->actingAs(User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, 'erp.presupuestos.arbol.editar']));

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $partida->proyecto_id])
        ->mountTableAction('editarPartida', "p{$partida->id}")
        ->assertTableActionDataSet(['nombre' => 'Tendido de cable UTP', 'responsable_user_id' => $partida->responsable_user_id, 'version' => $partida->version])
        ->setTableActionData(['nombre' => 'Cableado del parque', 'responsable_user_id' => $nueva->id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Guardado.')
        ->assertSee('Cableado del parque')
        ->assertSee('Rosa Prueba Vela');
});

test('sin el permiso de editar el árbol, no se ofrece', function () {
    $partida = partidaDelFlujo1();
    $this->actingAs(User::factory()->create()->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER]));

    Livewire::test(PartidasDeControlDeProyecto::class, ['proyectoId' => $partida->proyecto_id])
        ->assertTableActionHidden('editarPartida', "p{$partida->id}");
});

test('una partida desactivada o la PC-99 no se editan, ni sin pantalla', function () {
    $partida = partidaDelFlujo1();
    $sinMonto = app(ArbolPartidasService::class)->agregarPartida($partida->arbol_alta_id, null, 'PC-05', 'Sobra', User::factory()->create()->id);
    app(ArbolPartidasService::class)->desactivar($sinMonto->id, $sinMonto->refresh()->version);
    $pc99 = PartidaControl::query()->create([
        'proyecto_id' => $partida->proyecto_id, 'arbol_alta_id' => $partida->arbol_alta_id, 'codigo' => 'PC-99', 'nombre' => 'No presupuestados',
        'responsable_user_id' => $partida->responsable_user_id, 'es_no_presupuestado' => true,
    ]);

    expect(fn () => app(ArbolPartidasService::class)->editarPartida($sinMonto->id, 'Otra', User::factory()->create()->id, $sinMonto->refresh()->version))
        ->toThrow(ArbolRechazado::class, 'está desactivada: no se edita')
        ->and(fn () => app(ArbolPartidasService::class)->editarPartida($pc99->id, 'Imprevistos', User::factory()->create()->id, $pc99->refresh()->version))
        ->toThrow(ArbolRechazado::class, 'La PC-99 es la de no presupuestados');
});
