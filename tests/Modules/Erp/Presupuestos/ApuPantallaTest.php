<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\ApuDePartida;
use App\Modules\Erp\Presupuestos\Filament\PartidasDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\HistorialDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El APU de una partida en el panel deslizante de la pestaña «Partidas» (plan 2.10, D4; plan 2.9, D4, D5 y D12): abrir
 * y cerrar sin recargar con `?apu=`, lo escrito de cada línea y de dónde sale su rendimiento, agregar con el precio
 * sugerido, editar con rendimiento propio y quitar, y que el P.U. de la partida siga a su APU. Números del flujo 1.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnApu(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

test('presionar una partida abre su APU en el panel sin recargar; `?apu=` lo abre directo y solo con una partida de este presupuesto', function () {
    $p = PresupuestoDePrueba::flujo1();
    $otro = PresupuestoDePrueba::flujo1();
    entrarEnApu();
    $cable = $p->partidas['02.01'];

    Livewire::test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertDontSeeHtml('data-apu-abierto')
        ->callTableAction('verApu', "p{$cable}")
        ->assertSet('apu', $cable)
        ->assertSeeHtml('data-apu-abierto="'.$cable.'"')
        ->assertSeeHtml('tq-catalogos-detras')
        ->call('cerrarApu')
        ->assertSet('apu', null);

    Livewire::withQueryParams(['apu' => (string) $cable])->test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertSet('apu', $cable);
    Livewire::withQueryParams(['apu' => (string) $otro->partidas['02.01']])->test(PartidasDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertSet('apu', null);
});

test('el APU de 02.01 muestra lo escrito: cable 1.00000 + 5 % y técnico con el rendimiento de la partida; arriba el P.U. 4.02', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnApu(GrupoSensible::Costos->permiso());

    Livewire::test(ApuDePartida::class, ['partidaId' => $p->partidas['02.01']])
        ->assertSee('02.01 · Tendido de cable UTP')
        ->assertSee('Metrado 600.000 MTR · P.U. 4.02')
        ->assertSee(['CAB-UTP6', 'Neta 1.00000 + 5.00 % de desperdicio', '1.05000', '2.4000', '2.5200'])
        ->assertSee('rendimiento de la partida (20 MTR por hora)')
        ->assertSee('0.9000');
});

test('agregar un material sin escribir precio toma el de referencia y sube el P.U.; pide cantidad, no rendimiento', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnApu('erp.presupuestos.presupuesto.editar', GrupoSensible::Costos->permiso());
    $grapa = PresupuestoDePrueba::insumo('GRAPA', 'Grapa para cable', Insumo::MATERIAL, 'NIU', '0.10');
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['02.01']);

    Livewire::test(ApuDePartida::class, ['partidaId' => $partida->id])
        ->mountTableAction('agregarInsumo')
        ->assertTableActionDataSet(['version' => $partida->version])
        ->setTableActionData(['insumo_id' => $grapa])
        ->assertFormFieldVisible('cantidad_neta')
        ->assertFormFieldHidden('rendimiento')
        ->setTableActionData(['cantidad_neta' => '2', 'precio' => null])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertNotified('Guardado.');

    $linea = LineaApu::query()->where('presupuesto_partida_id', $partida->id)->where('insumo_id', $grapa)->sole();

    expect([$linea->precio, $linea->parcial])->toBe(['0.1000', '0.2000'])
        ->and($partida->refresh()->costo_unitario)->toBe('4.2200');
});

test('editar el técnico con rendimiento propio lo marca y recalcula; quitar la línea baja el P.U.', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnApu('erp.presupuestos.presupuesto.editar', GrupoSensible::Costos->permiso());
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['02.01']);
    $tecnico = LineaApu::query()->where('presupuesto_partida_id', $partida->id)->whereHas('insumo', fn ($q) => $q->where('codigo', 'MO-TEC'))->sole();

    Livewire::test(ApuDePartida::class, ['partidaId' => $partida->id])
        ->mountTableAction('editarInsumo', $tecnico)
        ->assertTableActionDataSet(['insumo_id' => $tecnico->insumo_id, 'rendimiento' => null, 'version' => $tecnico->version])
        ->assertFormFieldDisabled('insumo_id')
        ->assertFormFieldVisible('rendimiento')
        ->setTableActionData(['rendimiento' => '10', 'unidad_rendimiento' => 'hora'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertSee('rendimiento propio 10 MTR por hora');

    expect([$tecnico->refresh()->rendimiento_propio, $tecnico->cantidad])->toBe([true, '0.10000']);

    Livewire::test(ApuDePartida::class, ['partidaId' => $partida->id])
        ->callTableAction('quitarInsumo', $tecnico->refresh())
        ->assertNotified('Guardado.');

    expect(LineaApu::query()->whereKey($tecnico->id)->exists())->toBeFalse();
});

test('quien edita sin ver costos no recibe el precio de la línea en un formulario: no se le ofrece editarla (ADR 0016)', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnApu('erp.presupuestos.presupuesto.editar');
    $linea = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['02.01'])->firstOrFail();

    Livewire::test(ApuDePartida::class, ['partidaId' => $p->partidas['02.01']])
        ->assertDontSee('2.4000')
        ->assertTableActionHidden('editarInsumo', $linea)
        ->assertTableActionVisible('quitarInsumo', $linea);
});

test('en un presupuesto aprobado el APU se ve pero no ofrece cambios', function () {
    $p = PresupuestoDePrueba::flujo1();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, PresupuestoDePrueba::proyecto(), $p->fresco()->version, PresupuestoDePrueba::jefatura());
    entrarEnApu('erp.presupuestos.presupuesto.editar');
    $linea = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['02.01'])->firstOrFail();

    Livewire::test(ApuDePartida::class, ['partidaId' => $p->partidas['02.01']])
        ->assertSee('CAB-UTP6')
        ->assertTableActionHidden('agregarInsumo')
        ->assertTableActionHidden('editarInsumo', $linea)
        ->assertTableActionHidden('quitarInsumo', $linea);
});

test('el historial del presupuesto cuenta los cambios del APU con el código del insumo', function () {
    $p = PresupuestoDePrueba::flujo1();
    $linea = LineaApu::query()->where('presupuesto_partida_id', $p->partidas['03.02'])->firstOrFail();
    app(ArmadoPresupuestoService::class)->quitarLineaApu($linea->id, $linea->version);
    entrarEnApu();

    Livewire::test(HistorialDePresupuesto::class, ['registroId' => $p->presupuesto->id])
        ->assertSee('APU de la partida 03.02');
});
