<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Filament\ListaDeInsumosDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\VerPresupuesto;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * La lista de insumos y los estados del presupuesto en su ficha (plan 2.10, paso 7; plan 2.9, D5, D6, D9, D10 y D13):
 * la lista por tipo que suma el costo directo, con lo sin detallar aparte; ofertar y traer al proyecto con el aviso de
 * materiales sin producto (avisa, no bloquea); nueva versión; pasar con o sin APU; anular con motivo.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnEstados(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([PresupuestosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Lo que dice la ventana de confirmación abierta (Livewire::test no dibuja las ventanas). */
function confirmacionMontada(Testable $pantalla): string
{
    return (string) $pantalla->instance()->getMountedAction()?->getModalDescription();
}

/** Sin APU, una partida global de 8,500.00. */
function presupuestoPorPartida(): PresupuestoDePrueba
{
    $p = PresupuestoDePrueba::nuevo(usaApu: false);
    $p->titulo('01', 'OBRAS CIVILES');
    $p->partida('01', '01.01', 'Obras civiles por subcontrato', 'GLB', '1', precioUnitario: '8500');

    return $p;
}

test('la lista de insumos del flujo 1 va por tipo y suma el costo directo 17,750.00', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnEstados(GrupoSensible::Costos->permiso());

    Livewire::test(ListaDeInsumosDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertSeeInOrder(['MANO DE OBRA', 'MO-AYU', 'MO-TEC', 'MATERIALES', 'CAB-UTP6'])
        ->assertSeeInOrder(['Costo directo', '17,750.00'])
        ->assertDontSee('Precio sin detallar');
});

test('pasar a análisis de precios deja la partida «Precio sin detallar»; volver a precio por partida pide confirmar', function () {
    $p = presupuestoPorPartida();
    entrarEnEstados('erp.presupuestos.presupuesto.editar', GrupoSensible::Costos->permiso());

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('cambiarUsaApu')
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect($p->fresco()->usa_apu)->toBeTrue();

    Livewire::test(ListaDeInsumosDePresupuesto::class, ['presupuestoId' => $p->presupuesto->id])
        ->assertSeeInOrder(['Precio sin detallar', '8,500.00', 'Costo directo', '8,500.00']);

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('cambiarUsaApu', ['confirma_descartar_apu' => false])
        ->assertNotified('Sin APU, cada partida se queda con su precio unitario y se descartan sus análisis: confírmalo.');

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('cambiarUsaApu', ['confirma_descartar_apu' => true])
        ->assertNotified('Guardado.');

    expect([$p->fresco()->usa_apu, LineaApu::query()->whereIn('presupuesto_partida_id', $p->partidas)->count()])->toBe([false, 0]);
});

test('ofertar con materiales sin producto avisa y deja ofertar; ya ofertado no se edita ni se vuelve a ofertar', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnEstados('erp.presupuestos.presupuesto.ofertar', 'erp.presupuestos.presupuesto.editar');

    $pantalla = Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])->mountAction('ofertar');

    expect(confirmacionMontada($pantalla))->toContain('Queda congelado')->toContain('Materiales sin producto enlazado: CAB-UTP6');

    $pantalla->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::OFERTADO);

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertActionHidden('ofertar')
        ->assertActionHidden('editarEncabezado');
});

test('un presupuesto incompleto no se oferta y la pantalla dice qué le falta', function () {
    $p = PresupuestoDePrueba::nuevo();
    entrarEnEstados('erp.presupuestos.presupuesto.ofertar');

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('ofertar')
        ->assertNotified('El presupuesto está incompleto: no tiene partidas.');

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::BORRADOR);
});

test('traer al proyecto lo aprueba y vigente; la nueva versión abre en borrador con el proyecto ya elegido', function () {
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    entrarEnEstados('erp.presupuestos.presupuesto.traer_al_proyecto', 'erp.presupuestos.presupuesto.nueva-version');

    $pantalla = Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertActionHidden('nuevaVersion')
        ->mountAction('traerAlProyecto');

    expect(confirmacionMontada($pantalla))->toContain('Lo aprueba y lo deja vigente en el proyecto')->toContain('Materiales sin producto enlazado');

    $pantalla->setActionData(['proyecto_id' => $proyecto])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect($p->fresco()->only(['proyecto_id', 'es_vigente']))->toBe(['proyecto_id' => $proyecto, 'es_vigente' => true])
        ->and($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::APROBADO);

    $pantalla = Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertActionHidden('traerAlProyecto')
        ->callAction('nuevaVersion')
        ->assertNotified('Versión 2 creada, en borrador.');
    $nueva = Presupuesto::query()->where('numero', $p->presupuesto->numero)->where('numero_version', 2)->sole();
    $pantalla->assertRedirect(VerPresupuesto::getUrl(['record' => $nueva]));

    Livewire::test(VerPresupuesto::class, ['record' => $nueva->id])
        ->mountAction('traerAlProyecto')
        ->assertActionDataSet(['proyecto_id' => $proyecto])
        ->assertFormFieldDisabled('proyecto_id');
});

test('anular pide motivo y solo en borrador', function () {
    $p = PresupuestoDePrueba::flujo1();
    entrarEnEstados('erp.presupuestos.presupuesto.anular');
    $otro = Motivo::query()->where('codigo', Motivo::CODIGO_OTRO)
        ->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DefinicionPresupuesto::CLAVE.'.anular'))->value('id');

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('anular', ['motivo_id' => null])
        ->assertHasActionErrors(['motivo_id' => 'required']);

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->callAction('anular', ['motivo_id' => $otro, 'motivo_detalle' => 'El cliente desistió de la obra inventada'])
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect($p->fresco()->estado->codigo)->toBe(DefinicionPresupuesto::ANULADO);

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])->assertActionHidden('anular');
});

test('sin APU no hay pestaña de lista de insumos; sin permisos no se ofrecen los estados', function () {
    $p = presupuestoPorPartida();
    entrarEnEstados();

    Livewire::test(VerPresupuesto::class, ['record' => $p->presupuesto->id])
        ->assertDontSee('Lista de insumos')
        ->assertActionHidden('ofertar')
        ->assertActionHidden('traerAlProyecto')
        ->assertActionHidden('cambiarUsaApu')
        ->assertActionHidden('anular');
});
