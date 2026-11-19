<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\PartidasDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\HistorialDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\ListarPresupuestos;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\VerPresupuesto;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Las pantallas de Presupuestos (plan 2.10, D1 y D4): la portada de la lista, «Nuevo presupuesto», la ficha con sus
 * totales, la pestaña «Partidas» (títulos y partidas, agregar, editar y quitar) y el historial. Los números, los del
 * flujo 1 (PRE de la Plaza de Armas): costo directo 17,750.00 y total 24,715.10.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnPresupuestos(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function aprobarEnProyecto(PresupuestoDePrueba $p): int
{
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());

    return $proyecto;
}

// ——— Portada de la lista ———

test('las pestañas cuentan cada estado; el aviso de materiales sin producto filtra la tabla', function () {
    entrarEnPresupuestos();
    $aprobado = PresupuestoDePrueba::flujo1();
    aprobarEnProyecto($aprobado);
    $borrador = PresupuestoDePrueba::nuevo();

    $pestanas = Livewire::withQueryParams([])->test(ListarPresupuestos::class)->instance()->getTabs();

    expect(array_keys($pestanas))->toBe(['todos', 'borradores', 'ofertados', 'vigentes', 'reemplazados', 'anulados'])
        ->and($pestanas['todos']->getBadge())->toEqual(2)
        ->and($pestanas['borradores']->getBadge())->toEqual(1)
        ->and($pestanas['vigentes']->getBadge())->toEqual(1)
        ->and($pestanas['ofertados']->getBadge())->toEqual(0);

    // Los materiales del flujo 1 son inventados y no tienen producto (D13 de la 2.9).
    Livewire::withQueryParams([])->test(ListarPresupuestos::class)
        ->assertSee('1 presupuesto en curso con materiales sin producto')
        ->call('verAviso', 'sin-producto')
        ->assertCanSeeTableRecords([$aprobado->presupuesto])
        ->assertCanNotSeeTableRecords([$borrador->presupuesto]);
});

test('«Nuevo presupuesto» trae soles, el IGV del parámetro (18.00) y APU; crea por la acción y abre la ficha', function () {
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar');

    Livewire::withQueryParams([])->test(ListarPresupuestos::class)
        ->mountAction('nuevo')
        ->assertActionDataSet(['moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'igv_pct' => '18.00', 'usa_apu' => true])
        ->setActionData(['nombre' => 'Cámaras del mercado inventado', 'gastos_generales_pct' => '10', 'utilidad_pct' => '8'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Presupuesto creado.');

    $creado = Presupuesto::query()->where('nombre', 'Cámaras del mercado inventado')->sole();

    expect([$creado->numero_version, $creado->usa_apu, $creado->gastos_generales_pct])->toBe([1, true, '10.00']);
});

test('sin el permiso de editar no se ofrece «Nuevo presupuesto»', function () {
    entrarEnPresupuestos();

    Livewire::withQueryParams([])->test(ListarPresupuestos::class)->assertActionHidden('nuevo');
});

// ——— Ficha ———

test('F1 en pantalla: la ficha muestra costo directo 17,750.00, gastos generales 1,775.00, utilidad 1,420.00, IGV 3,770.10 y total 24,715.10', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos(GrupoSensible::Costos->permiso(), GrupoSensible::Margen->permiso());

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertSee($p->presupuesto->numero)
        ->assertSee('Borrador')
        ->assertSee('Con análisis de precios')
        ->assertSee('Sin proyecto')
        ->assertSeeInOrder(['Costo directo', '17,750.00', 'Gastos generales', '1,775.00', '10.00 %', 'Utilidad', '1,420.00', 'IGV', '3,770.10', 'Total', '24,715.10'])
        ->assertSee('Sin detallar');
});

test('sin el permiso de ver costos, la ficha no muestra montos', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos();

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertDontSee('17,750.00')
        ->assertDontSee('24,715.10')
        ->assertSee('Los montos se ven con el permiso de ver costos.');
});

test('con costos y sin márgenes, la utilidad y su porcentaje no se ven ni se ofrece editar el encabezado (ADR 0016)', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar', GrupoSensible::Costos->permiso());

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertSee(['17,750.00', '24,715.10'])
        ->assertDontSee('1,420.00')
        ->assertSee('La utilidad se ve con el permiso de ver márgenes.')
        ->assertActionHidden('editarEncabezado');
});

test('editar el encabezado en borrador guarda con la versión leída; en un aprobado no se ofrece', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar', GrupoSensible::Margen->permiso());

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('editarEncabezado', ['nombre' => 'Videovigilancia de plaza (corregido)'])
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect($p->fresco()->nombre)->toBe('Videovigilancia de plaza (corregido)');

    aprobarEnProyecto($p);
    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])->assertActionHidden('editarEncabezado');
});

// ——— Pestaña «Partidas» ———

test('las partidas salen en orden con su título, que suma sus parciales; P.U. con los decimales del presupuesto', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos(GrupoSensible::Costos->permiso());

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertSeeInOrder(['01', 'CÁMARAS', '6,430.00', '01.01', 'Instalación de cámara domo IP', '643.00', '6,430.00',
            '02', 'CABLEADO Y POSTES', '6,772.00', '02.01', 'Tendido de cable UTP', '600.000', '4.02', '2,412.00'])
        ->assertSeeHtml('padding-inline-start: 1.25rem');
});

test('agregar un título y una partida debajo trae el código siguiente; editar y quitar la partida', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar', GrupoSensible::Costos->permiso());
    $glb = (int) UnidadMedida::query()->where('codigo', 'GLB')->value('id');

    $tabla = Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->mountTableAction('agregarTitulo')
        ->assertTableActionDataSet(['codigo' => '01'])
        ->setTableActionData(['nombre' => 'OBRAS CIVILES'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();
    $titulo = TituloDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->sole();

    $tabla->mountTableAction('agregarPartida', "t{$titulo->id}")
        ->assertTableActionDataSet(['codigo' => '01.01'])
        ->setTableActionData(['descripcion' => 'Obras civiles por subcontrato', 'unidad_medida_id' => $glb, 'metrado' => '1', 'precio_unitario' => '8500'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertSee('8,500.00');
    $partida = PartidaDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->sole();

    $tabla->mountTableAction('editarPartida', "p{$partida->id}")
        ->assertTableActionDataSet(['metrado' => '1.000', 'version' => $partida->version])
        ->setTableActionData(['metrado' => '2'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertSee('17,000.00');

    expect($p->fresco()->costo_directo)->toBe('17000.00');

    $tabla->callTableAction('quitarPartida', "p{$partida->refresh()->id}")->assertNotified('Guardado.');

    expect(PartidaDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->exists())->toBeFalse();
});

test('con APU, la partida no pide P.U. sino rendimiento; un título con partidas no se quita', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar');
    $titulo = TituloDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->where('codigo', '01')->sole();

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->mountTableAction('agregarPartida', "t{$titulo->id}")
        ->assertFormFieldDoesNotExist('precio_unitario')
        ->assertFormFieldExists('rendimiento');

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->callTableAction('quitarTitulo', "t{$titulo->id}")
        ->assertNotified('El título 01 tiene títulos o partidas debajo: primero quita esos.');
});

test('sin APU, quien edita sin ver costos no recibe el P.U. en un formulario: no se le ofrece editar la partida', function () {
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    $p->titulo('01', 'OBRAS CIVILES');
    $partida = $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar');

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertDontSee('8,500.00')
        ->assertTableActionHidden('editarPartida', "p{$partida}");
});

test('un presupuesto aprobado no ofrece agregar, editar ni quitar', function () {
    $p = PresupuestoDePrueba::flujo1();
    aprobarEnProyecto($p);
    entrarEnPresupuestos('erp.presupuestos.presupuesto.editar');

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertTableActionHidden('agregarTitulo')
        ->assertTableActionHidden('editarPartida', 'p'.$p->partidas['01.01']);
});

test('el historial cuenta los cambios de las partidas con nombres legibles, también las quitadas', function () {
    $p = PresupuestoDePrueba::flujo1();
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['03.02']);
    app(ArmadoPresupuestoService::class)->quitarPartida($partida->id, $partida->version);
    entrarEnPresupuestos();

    Livewire::test(HistorialDePresupuesto::class, ['registroId' => $p->presupuesto->id])
        ->assertSee('Partida 03.02')
        ->assertSee('Descripción');
});
