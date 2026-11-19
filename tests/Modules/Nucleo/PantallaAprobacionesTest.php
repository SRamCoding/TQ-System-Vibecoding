<?php

declare(strict_types=1);

use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Filament\Resources\Aprobaciones\Pages\ListarReglasAprobacion;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * Configuración → Aprobaciones (plan 1.7, D8): las reglas con sus firmas en orden, editables por las acciones
 * registradas. Las reglas de negocio se prueban en AprobacionesTest; aquí, que la pantalla las muestra y llama a sus
 * acciones con la versión que se leyó al abrir.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
    ]));
    app(SincronizadorEstados::class)->sincronizar();
    Filament::setCurrentPanel('app');
});

function paConfigurador(): User
{
    $usuario = User::factory()->create()->givePermissionTo(['catalogos.ver', 'aprobaciones.configurar']);
    test()->actingAs($usuario);

    return $usuario;
}

test('con permiso para ver muestra cada regla con su rango y sus firmas en orden; sin él, no abre', function () {
    $operaciones = UnidadOrganizativa::factory()->create(['nombre' => 'Gerencia de Operaciones']);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Requerimiento sobre U1', 'documento_prueba', 'aprobar', '1000', null, '500', ['documento', "unidad:{$operaciones->id}"]));

    test()->actingAs(User::factory()->create());
    test()->get('/configuracion/aprobaciones')->assertForbidden();

    paConfigurador();
    test()->get('/configuracion/aprobaciones')->assertOk()
        ->assertSee('Requerimiento sobre U1')
        ->assertSee('Documento de prueba · Aprobar')
        ->assertSee('Más de 1,000.00')
        ->assertSee('La jefatura del documento → Jefatura de Gerencia de Operaciones')
        ->assertSee('S/ 500.00');
});

test('agregar, editar y desactivar pasan por sus acciones; editar lleva la versión que se leyó al abrir', function () {
    $operaciones = UnidadOrganizativa::factory()->create(['nombre' => 'Gerencia de Operaciones']);
    paConfigurador();

    Livewire::test(ListarReglasAprobacion::class)
        ->callAction(TestAction::make('nucleoAprobacionesCrearRegla')->table(), [
            'nombre' => 'Su jefatura',
            'documento_type' => 'documento_prueba',
            'transicion' => 'aprobar',
            'monto_hasta' => '1000',
            'pasos' => ['documento'],
        ])
        ->assertHasNoActionErrors();

    $regla = ReglaAprobacion::query()->sole();
    expect($regla->monto_hasta)->toBe('1000.00');

    $pagina = Livewire::test(ListarReglasAprobacion::class)->mountAction(TestAction::make('nucleoAprobacionesEditarRegla')->table($regla));
    // Otra persona la cambia mientras el formulario está abierto: la versión leída ya no vale.
    app(AprobacionesService::class)->activarRegla($regla->id, 1, true);
    $pagina->setActionData(['pasos' => ['documento', "unidad:{$operaciones->id}"]])->callMountedAction();
    expect($regla->refresh()->pasos()->count())->toBe(1);

    Livewire::test(ListarReglasAprobacion::class)
        ->callAction(TestAction::make('nucleoAprobacionesEditarRegla')->table($regla), ['pasos' => ['documento', "unidad:{$operaciones->id}"]])
        ->assertHasNoActionErrors();
    expect($regla->refresh()->pasos()->count())->toBe(2);

    Livewire::test(ListarReglasAprobacion::class)->callAction(TestAction::make('nucleoAprobacionesDesactivarRegla')->table($regla));
    expect($regla->refresh()->es_activa)->toBeFalse();
});

test('la pestaña «Autoaprobaciones por revisar» las muestra con su motivo y las marca revisadas por su acción', function () {
    Permission::findOrCreate('nucleo.prueba.pedir_aprobacion');
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => 'Jefatura de Proyectos']);
    $puesto = Puesto::factory()->create(['unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    $jefe = User::factory()->create(['puesto_id' => $puesto->id])->givePermissionTo(['nucleo.prueba.pedir_aprobacion', 'aprobaciones.autoaprobar']);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Su jefatura', 'documento_prueba', 'aprobar', null, null, '600', ['documento']));
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido', 'monto' => '500', 'unidad_id' => $unidad->id]), $jefe)->refresh();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', $documento->version, $jefe);
    $firma = app(AprobacionesService::class)->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Obra parada');

    $revisor = paConfigurador()->givePermissionTo('aprobaciones.revisar');

    Livewire::test(ListarReglasAprobacion::class)
        ->set('activeTab', 'autoaprobaciones')
        ->assertSee('Obra parada')
        ->assertSee($documento->numero)
        ->callAction(TestAction::make('nucleoAprobacionesRevisarAutoaprobacion')->table($firma))
        ->assertHasNoActionErrors();

    expect($firma->refresh()->revisada_por_user_id)->toBe($revisor->id);
});
