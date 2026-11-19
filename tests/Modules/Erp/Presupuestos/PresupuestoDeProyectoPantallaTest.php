<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\PresupuestoDeProyecto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\VerPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * La pestaña «Presupuesto» de la ficha del proyecto (plan 2.10, paso 8, D3): el vigente arriba con su costo directo y su
 * total (flujo 1: 17,750.00 y 24,715.10), todas las versiones con enlace a su ficha, y nada que editar. Proyectos y
 * presupuestos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnPresupuestoDeProyecto(string ...$permisos): User
{
    $usuario = User::factory()->create()
        ->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** El flujo 1 traído al proyecto (v1 vigente) y su versión 2 en borrador. */
function proyectoConVersiones(): array
{
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    $armado = app(ArmadoPresupuestoService::class);
    $armado->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $v2 = $armado->nuevaVersion($p->presupuesto->id, PresupuestoDePrueba::jefatura());

    return [$proyecto, $p->fresco(), $v2];
}

test('la pestaña se suma a la ficha del proyecto solo con el permiso de ver presupuestos, antes de las partidas de control', function () {
    $proyecto = PresupuestoDePrueba::proyecto();

    test()->actingAs(User::factory()->create()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER));
    Livewire::test(PresupuestoDeProyecto::class, ['proyectoId' => $proyecto])->assertForbidden();

    entrarEnPresupuestoDeProyecto();
    Livewire::test(VerProyecto::class, ['record' => $proyecto])->assertSeeInOrder(['Datos', 'Presupuesto', 'Partidas de control', 'Historial']);
});

test('el vigente arriba con costo directo 17,750.00 y total 24,715.10; las dos versiones con enlace a su ficha', function () {
    [$proyecto, $v1, $v2] = proyectoConVersiones();
    entrarEnPresupuestoDeProyecto(GrupoSensible::Costos->permiso());

    Livewire::test(PresupuestoDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertSee("Vigente: {$v1->numero} v1")
        ->assertSee('Costo directo 17,750.00 · Total 24,715.10')
        ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
        ->assertSee(['Aprobado · vigente', 'Borrador'])
        ->assertSeeHtml(e(VerPresupuesto::getUrl(['record' => $v2])))
        ->assertSeeHtml(e(VerPresupuesto::getUrl(['record' => $v1])));
});

test('sin el permiso de ver costos no se ven los montos', function () {
    [$proyecto] = proyectoConVersiones();
    entrarEnPresupuestoDeProyecto();

    Livewire::test(PresupuestoDeProyecto::class, ['proyectoId' => $proyecto])
        ->assertDontSee('17,750.00')
        ->assertDontSee('24,715.10');
});

test('sin presupuesto, lo dice y lleva a Presupuestos; el de otro proyecto no aparece', function () {
    [, $v1] = proyectoConVersiones();
    $otro = PresupuestoDePrueba::proyecto();
    entrarEnPresupuestoDeProyecto();

    Livewire::test(PresupuestoDeProyecto::class, ['proyectoId' => $otro])
        ->assertSee('Sin presupuesto vigente')
        ->assertSee('Este proyecto aún no tiene presupuesto')
        ->assertCanNotSeeTableRecords(Presupuesto::query()->whereKey($v1->id)->get());
});
