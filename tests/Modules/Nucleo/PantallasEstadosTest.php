<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Estados\CambiarPresentacionEstado;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\Resources\Estados\Pages\ListarEstados;
use App\Modules\Nucleo\Filament\Resources\Motivos\Pages\ListarMotivos;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Livewire\Livewire;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * Pantallas «Estados de documentos» y «Motivos» en Configuración (plan 1.5, D10): solo presentación. Las reglas se
 * prueban en sus acciones; aquí, que abren con su permiso, editan solo lo que se puede y llaman a sus acciones.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
    app(SincronizadorEstados::class)->sincronizar();
    Filament::setCurrentPanel('app');
});

function peEntrar(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['catalogos.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function peEstado(string $codigo): Estado
{
    return Estado::query()->where('documento_type', 'documento_prueba')->where('codigo', $codigo)->sole();
}

function peMotivo(string $codigo): Motivo
{
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->sole();

    return Motivo::query()->firstOrCreate(['tipo_motivo_id' => $familia->id, 'codigo' => $codigo], ['nombre' => ucfirst($codigo)]);
}

test('con permiso para ver, abren las dos pantallas; sin él, no', function () {
    peMotivo('precio');

    test()->actingAs(User::factory()->create());
    test()->get('/configuracion/estados')->assertForbidden();
    test()->get('/configuracion/motivos')->assertForbidden();

    peEntrar();
    test()->get('/configuracion/estados')->assertOk()->assertSee('Documento de prueba')->assertSee('Por aprobar');
    test()->get('/configuracion/motivos')->assertOk();
    // Con las listas de los módulos, la de prueba puede caer en otra página: se filtra por ella (plan 2.21).
    Livewire::test(ListarMotivos::class)
        ->filterTable('tipo_motivo_id', TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->value('id'))
        ->assertSee('Precio')->assertSee('Otro');
});

test('el color de un estado se muestra con su nombre: el nombre lleva el tono y dice cuál es', function () {
    peEntrar();
    $enviado = peEstado('enviado');
    /** @var TextColumn $columna */
    $columna = Livewire::test(ListarEstados::class)->instance()->getTable()->getColumn('nombre');
    $columna->record($enviado);

    expect($columna->getColor($enviado->nombre))->toBe('info')
        ->and($columna->getDescriptionBelow())->toBe('Color: En curso')
        ->and($columna->isBadge())->toBeTrue();
});

test('de un estado solo se editan nombre y color, por su acción', function () {
    peEntrar('nucleo.estados.editar-presentacion');
    $enviado = peEstado('enviado');

    Livewire::test(ListarEstados::class)
        ->callAction(TestAction::make('nucleoEstadosCambiarPresentacion')->table($enviado), ['nombre' => 'Enviado al cliente', 'color' => 'alerta'])
        ->assertHasNoFormErrors()
        ->assertNotified('Estado actualizado.');

    $enviado->refresh();

    expect([$enviado->nombre, $enviado->color, $enviado->es_editable, $enviado->codigo])->toBe(['Enviado al cliente', TonoEstado::Alerta, false, 'enviado'])
        ->and(array_map(fn (Campo $campo): string => $campo->nombre, array_values(array_filter(app(CambiarPresentacionEstado::class)->campos(), fn (Campo $campo): bool => ! $campo->control))))
        ->toBe(['estado_id', 'nombre', 'color']);
});

test('sin permiso de edición, la acción de un estado no aparece', function () {
    peEntrar();

    Livewire::test(ListarEstados::class)->assertActionHidden(TestAction::make('nucleoEstadosCambiarPresentacion')->table(peEstado('enviado')));
});

test('un motivo se agrega, se renombra, se desactiva y se activa desde la pantalla', function () {
    peEntrar('nucleo.motivos.editar');
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->sole();

    Livewire::test(ListarMotivos::class)
        ->callAction('nucleoMotivosAgregar', ['tipo_motivo_id' => $familia->id, 'nombre' => 'Precio alto'])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');
    $motivo = Motivo::query()->where('codigo', 'precio_alto')->sole();

    Livewire::test(ListarMotivos::class)
        ->callAction(TestAction::make('nucleoMotivosCambiar')->table($motivo), ['nombre' => 'Precio muy alto'])
        ->assertHasNoFormErrors();
    $motivo->refresh();

    Livewire::test(ListarMotivos::class)
        ->assertActionHidden(TestAction::make('nucleoMotivosActivar')->table($motivo))
        ->callAction(TestAction::make('nucleoMotivosDesactivar')->table($motivo));
    $motivo->refresh();

    Livewire::test(ListarMotivos::class)
        ->assertActionHidden(TestAction::make('nucleoMotivosDesactivar')->table($motivo))
        ->callAction(TestAction::make('nucleoMotivosActivar')->table($motivo));

    expect([$motivo->refresh()->nombre, $motivo->es_activo])->toBe(['Precio muy alto', true]);
});

test('«Otro» no tiene desactivar', function () {
    peEntrar('nucleo.motivos.editar');
    $otro = Motivo::query()->where('es_otro', true)->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', 'nucleo.prueba.anular'))->sole();

    Livewire::test(ListarMotivos::class)
        ->assertActionHidden(TestAction::make('nucleoMotivosDesactivar')->table($otro))
        ->assertActionVisible(TestAction::make('nucleoMotivosDesactivar')->table(peMotivo('precio')));
});

test('un rechazo del servicio se muestra como aviso', function () {
    peEntrar('nucleo.motivos.editar');
    peMotivo('precio');
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->sole();

    Livewire::test(ListarMotivos::class)
        ->callAction('nucleoMotivosAgregar', ['tipo_motivo_id' => $familia->id, 'nombre' => 'PRECIO'])
        ->assertNotified('Ya hay un motivo «PRECIO» en esta lista.');
});

test('sin permiso de edición, no aparece ninguna acción de motivos', function () {
    peEntrar();
    $motivo = peMotivo('precio');

    Livewire::test(ListarMotivos::class)
        ->assertActionHidden('nucleoMotivosAgregar')
        ->assertActionHidden(TestAction::make('nucleoMotivosCambiar')->table($motivo))
        ->assertActionHidden(TestAction::make('nucleoMotivosDesactivar')->table($motivo));
});
