<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Erp\Presupuestos\Filament\InsumosDelCatalogo;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\ListarPresupuestos;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * El catálogo de insumos en el panel deslizante de Presupuestos (plan 2.10, D2; plan 2.9, D8 y D10): abrir y cerrar el
 * panel sin recargar, la tabla con sus filtros, crear, editar (con el producto solo en materiales) y desactivar por las
 * acciones; el insumo del sistema no se toca. Insumos y productos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnInsumos(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function unidadDeInsumo(string $codigo): int
{
    return (int) UnidadMedida::query()->where('codigo', $codigo)->value('id');
}

function insumoEnPantalla(string $codigo, string $tipo = Insumo::MATERIAL, string $unidad = 'MTR', ?string $precio = '2.4000'): Insumo
{
    return app(InsumosService::class)->crear(new DatosInsumo($codigo, "Insumo {$codigo}", $tipo, unidadDeInsumo($unidad), $precio));
}

test('sin ver presupuestos no se entra; «Insumos» abre el panel sin recargar, la URL lo sigue y la X lo cierra', function () {
    $this->actingAs(User::factory()->create())->get('/presupuestos')->assertForbidden();

    entrarEnInsumos();
    $this->get('/presupuestos')->assertOk();
    $this->get('/presupuestos?panel=insumos')->assertOk()->assertSee('data-panel-abierto="insumos"', false);

    Livewire::withQueryParams([])->test(ListarPresupuestos::class)
        ->assertDontSeeHtml('data-panel-abierto')
        ->callAction('insumos')
        ->assertSet('panel', 'insumos')
        ->assertSeeHtml('data-panel-abierto="insumos"')
        ->assertSeeHtml('tq-catalogos-detras')
        ->call('cerrarPanel')
        ->assertSet('panel', null)
        ->assertDontSeeHtml('data-panel-abierto');

    Livewire::withQueryParams(['panel' => 'otro'])->test(ListarPresupuestos::class)->assertSet('panel', null);
});

test('la tabla muestra los activos; el filtro deja ver los materiales sin producto', function () {
    entrarEnInsumos(GrupoSensible::Costos->permiso());
    $cable = insumoEnPantalla('CAB-UTP6');
    $tecnico = insumoEnPantalla('MO-TEC', Insumo::MANO_OBRA, 'HUR', '18.0000');
    $viejo = insumoEnPantalla('CAB-VIEJO');
    app(InsumosService::class)->desactivar($viejo->id, $viejo->version);

    Livewire::test(InsumosDelCatalogo::class)
        ->assertCanSeeTableRecords([$cable, $tecnico])
        ->assertCanNotSeeTableRecords([$viejo])
        ->assertSee('Mano de obra')
        ->assertSee('18.0000')
        ->filterTable('sin_producto', true)
        ->assertCanSeeTableRecords([$cable])
        ->assertCanNotSeeTableRecords([$tecnico]);
});

test('sin el permiso de ver costos, el precio de referencia no se ve', function () {
    entrarEnInsumos();
    insumoEnPantalla('MO-TEC', Insumo::MANO_OBRA, 'HUR', '18.0000');

    Livewire::test(InsumosDelCatalogo::class)->assertSee('MO-TEC')->assertDontSee('18.0000');
});

test('crear un material con su producto; el producto solo se pide en materiales', function () {
    entrarEnInsumos('erp.presupuestos.insumos.editar');
    $producto = Producto::factory()->create(['codigo' => 'CAM-D4M', 'unidad_medida_id' => unidadDeInsumo('NIU')]);

    Livewire::test(InsumosDelCatalogo::class)
        ->mountTableAction('crearInsumo')
        ->setTableActionData(['tipo' => Insumo::MANO_OBRA])
        ->assertTableActionDataSet(['tipo' => Insumo::MANO_OBRA])
        ->assertFormFieldHidden('producto_id')
        ->setTableActionData([
            'codigo' => 'cam-1', 'tipo' => Insumo::MATERIAL, 'nombre' => 'Cámara domo inventada',
            'unidad_medida_id' => unidadDeInsumo('NIU'), 'precio_referencia' => '250', 'producto_id' => $producto->id,
        ])
        ->assertFormFieldVisible('producto_id')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Guardado.');

    expect(Insumo::query()->where('codigo', 'CAM-1')->value('producto_id'))->toBe($producto->id);
});

test('el servicio rechaza lo que no cuadra y la pantalla lo dice: el producto con otra unidad', function () {
    entrarEnInsumos('erp.presupuestos.insumos.editar');
    $producto = Producto::factory()->create(['codigo' => 'CAM-D4M', 'unidad_medida_id' => unidadDeInsumo('NIU')]);

    Livewire::test(InsumosDelCatalogo::class)
        ->mountTableAction('crearInsumo')
        ->setTableActionData([
            'codigo' => 'CAM-2', 'tipo' => Insumo::MATERIAL, 'nombre' => 'Cámara en metros',
            'unidad_medida_id' => unidadDeInsumo('MTR'), 'producto_id' => $producto->id,
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('El producto CAM-D4M se mide en '.UnidadMedida::query()->whereKey(unidadDeInsumo('NIU'))->value('abreviatura').': el insumo va en la misma unidad, para comparar lo presupuestado con lo comprado.');

    expect(Insumo::query()->where('codigo', 'CAM-2')->exists())->toBeFalse();
});

test('editar guarda con la versión leída al abrir; el código y el tipo se ven sin poder cambiarse', function () {
    entrarEnInsumos('erp.presupuestos.insumos.editar', GrupoSensible::Costos->permiso());
    $cable = insumoEnPantalla('CAB-UTP6');

    Livewire::test(InsumosDelCatalogo::class)
        ->mountTableAction('editarInsumo', $cable)
        ->assertTableActionDataSet(['codigo' => 'CAB-UTP6', 'version' => $cable->version])
        ->assertFormFieldDisabled('codigo')
        ->assertFormFieldDisabled('tipo')
        ->setTableActionData(['nombre' => 'Cable UTP cat 6 exterior'])
        ->callMountedTableAction()
        ->assertNotified('Guardado.');

    expect($cable->refresh()->nombre)->toBe('Cable UTP cat 6 exterior');
});

test('desactivar saca el insumo de los activos; el del sistema no ofrece editar ni desactivar', function () {
    entrarEnInsumos('erp.presupuestos.insumos.editar');
    $cable = insumoEnPantalla('CAB-UTP6');
    $sistema = app(InsumosService::class)->sinDetallar();

    Livewire::test(InsumosDelCatalogo::class)
        ->assertTableActionHidden('editarInsumo', $sistema)
        ->assertTableActionHidden('desactivarInsumo', $sistema)
        ->assertSee('Del sistema')
        ->callTableAction('desactivarInsumo', $cable)
        ->assertNotified('Guardado.');

    expect($cable->refresh()->es_activo)->toBeFalse();
});

test('quien edita sin ver costos no recibe el precio de referencia en un formulario: no se le ofrece editar (ADR 0016)', function () {
    entrarEnInsumos('erp.presupuestos.insumos.editar');
    $cable = insumoEnPantalla('CAB-UTP6');

    Livewire::test(InsumosDelCatalogo::class)
        ->assertTableActionHidden('editarInsumo', $cable)
        ->assertTableActionVisible('desactivarInsumo', $cable);
});

test('sin el permiso de editar, no se ofrecen las acciones', function () {
    entrarEnInsumos();
    $cable = insumoEnPantalla('CAB-UTP6');

    Livewire::test(InsumosDelCatalogo::class)
        ->assertTableActionHidden('crearInsumo')
        ->assertTableActionHidden('editarInsumo', $cable);
});
