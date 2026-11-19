<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\InteligenciaArtificial;
use App\Modules\Nucleo\Ia\ClienteModeloLenguajeFalso;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\IaConfiguracionService;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
 * Configuración → IA en pantalla (plan 1.11, D11): las cajas de procesos (hoy ninguno, y lo dice) y de proveedores; el
 * panel de un proceso (qué envía, autorización, gasto, acierto; el administrador lo configura, Gerencia lo autoriza) y
 * el de un proveedor con sus modelos. Todo pasa por las acciones. Personas inventadas; nunca el servicio real.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    app(ClientesModeloLenguaje::class)->registrar('falso', ClienteModeloLenguajeFalso::class);
});

function pantIaAdministrador(): User
{
    return User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
}

function pantIaGerencia(): User
{
    return User::factory()->create()->givePermissionTo('ia.autorizar');
}

function pantIaProceso(): IaProceso
{
    app(RegistroProcesosIa::class)->registrar(new DefinicionProcesoIa('ejemplo_ia', 'Nombre comercial', 'Propone el nombre comercial.', 'Comercial', ['party'], [], ['party' => ['razon_social']], [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party']));
    app(IaConfiguracionService::class)->sincronizar();

    return IaProceso::query()->where('codigo', 'ejemplo_ia')->firstOrFail();
}

function pantIaModelo(): IaModelo
{
    $modelo = IaModelo::factory()->create(['proveedor_id' => IaProveedor::factory()->create(['nombre' => 'Claude API'])->id, 'nombre' => 'Opus']);
    IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '5', 'usd_millon_salida' => '25']);

    return $modelo;
}

it('entra quien configura o quien autoriza; nadie más', function () {
    $this->actingAs(pantIaAdministrador())->get(InteligenciaArtificial::getUrl())->assertOk();
    $this->actingAs(pantIaGerencia())->get(InteligenciaArtificial::getUrl())->assertOk();
    $this->actingAs(User::factory()->create()->givePermissionTo('parties.editar'))->get(InteligenciaArtificial::getUrl())->assertForbidden();
});

it('sin procesos lo dice, y muestra los proveedores con la caja de agregar uno', function () {
    IaProveedor::factory()->create(['nombre' => 'Claude API']);

    Livewire::actingAs(pantIaAdministrador())->test(InteligenciaArtificial::class)
        ->assertSee('Todavía no hay procesos de IA')
        ->assertSee('Claude API')
        ->assertSee('Agregar proveedor');
});

it('el panel de un proceso dice qué envía, si está autorizado, cuánto gastó y su acierto', function () {
    pantIaProceso();

    Livewire::actingAs(pantIaAdministrador())->test(InteligenciaArtificial::class)
        ->assertSee('Apagado · Autorizado')
        ->call('abrir', 'proceso:ejemplo_ia')
        ->assertSet('abierto', 'proceso:ejemplo_ia')
        ->assertSee('party: razon_social')
        ->assertSee('No hace falta: no envía datos fuera')
        ->assertSee('0.00 de 0.00 USD')
        ->assertSee('Sin sugerencias resueltas')
        // El administrador no ve el bloque de Gerencia.
        ->assertDontSee('Autorización de Gerencia');
});

it('el administrador elige el modelo; no lo enciende sin autorización si envía datos fuera; Gerencia autoriza con su tope', function () {
    $proceso = pantIaProceso();
    $modelo = pantIaModelo();
    $admin = pantIaAdministrador();

    Livewire::actingAs($admin)->test(InteligenciaArtificial::class)
        ->call('abrir', 'proceso:ejemplo_ia')
        ->set('proceso.modelo_id', $modelo->id)
        ->set('proceso.esta_activo', true)
        ->call('guardarProceso')
        ->assertNotified('Este proceso envía datos fuera de la empresa: Gerencia tiene que autorizarlo antes de encenderlo.');

    expect($proceso->refresh()->modelo_id)->toBeNull();

    Livewire::actingAs($admin)->test(InteligenciaArtificial::class)
        ->call('abrir', 'proceso:ejemplo_ia')
        ->set('proceso.modelo_id', $modelo->id)
        ->call('guardarProceso')
        ->assertNotified('Guardado.');

    Livewire::actingAs(pantIaGerencia())->test(InteligenciaArtificial::class)
        ->call('abrir', 'proceso:ejemplo_ia')
        ->assertSee('Pendiente de Gerencia')
        ->set('autorizacion.tope_mensual_usd', '25.00')
        ->call('autorizarProceso')
        ->assertNotified('Guardado.');

    Livewire::actingAs($admin)->test(InteligenciaArtificial::class)
        ->call('abrir', 'proceso:ejemplo_ia')
        ->set('proceso.esta_activo', true)
        ->call('guardarProceso')
        ->assertNotified('Guardado.');

    expect($proceso->refresh()->only(['modelo_id', 'esta_activo', 'tope_mensual_usd']))->toBe(['modelo_id' => $modelo->id, 'esta_activo' => true, 'tope_mensual_usd' => '25.00']);
});

it('la versión se toma al abrir: si otro guardó antes, no pisa su cambio', function () {
    $proceso = pantIaProceso();
    $modelo = pantIaModelo();
    $pantalla = Livewire::actingAs(pantIaAdministrador())->test(InteligenciaArtificial::class)->call('abrir', 'proceso:ejemplo_ia');
    app(IaConfiguracionService::class)->asignarModelos($proceso->id, $modelo->id, null, $proceso->refresh()->version);

    $pantalla->set('proceso.esta_activo', false)->call('guardarProceso')->assertNotNotified('Guardado.');

    expect($proceso->refresh()->modelo_id)->toBe($modelo->id);
});

it('agrega un proveedor, lo edita y le agrega un modelo con su precio, que se marca por defecto', function () {
    $admin = pantIaAdministrador();

    $pantalla = Livewire::actingAs($admin)->test(InteligenciaArtificial::class)
        ->call('abrir', InteligenciaArtificial::NUEVO_PROVEEDOR)
        ->set('proveedor.codigo', 'claude_api')
        ->set('proveedor.nombre', 'Claude API')
        ->set('proveedor.variable_clave', 'IA_CLAVE_CLAUDE')
        ->call('guardarProveedor')
        ->assertNotified('Guardado.');

    $proveedor = IaProveedor::query()->where('codigo', 'claude_api')->sole();
    $pantalla->assertSet('abierto', 'proveedor:'.$proveedor->id)
        ->set('proveedor.nombre', 'Claude')
        ->call('guardarProveedor')
        ->callAction('nucleoIaCrearModelo', ['codigo' => 'claude-opus-5-5', 'nombre' => 'Opus 5.5', 'max_tokens_salida' => 8000])
        ->assertHasNoActionErrors();

    $modelo = IaModelo::query()->where('proveedor_id', $proveedor->id)->sole();
    $pantalla->call('abrir', 'proveedor:'.$proveedor->id)
        ->assertSee('Sin precio: no se llama')
        ->callAction(TestAction::make('nucleoIaAgregarPrecio')->arguments(['modelo' => $modelo->id]), ['vigente_desde' => now('America/Lima')->toDateString(), 'usd_millon_entrada' => '5', 'usd_millon_salida' => '25'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('nucleoIaMarcarModeloPorDefecto')->arguments(['modelo' => $modelo->id]))
        ->assertHasNoActionErrors()
        ->call('abrir', 'proveedor:'.$proveedor->id)
        ->assertSee('5 / 25 USD por millón')
        ->assertSee('Por defecto');

    expect($proveedor->refresh()->nombre)->toBe('Claude')
        ->and($modelo->refresh()->es_por_defecto)->toBeTrue();
});

it('una variable de clave que apunta a otro secreto se rechaza con su mensaje', function () {
    Livewire::actingAs(pantIaAdministrador())->test(InteligenciaArtificial::class)
        ->call('abrir', InteligenciaArtificial::NUEVO_PROVEEDOR)
        ->set('proveedor.codigo', 'raro')
        ->set('proveedor.nombre', 'Raro')
        ->set('proveedor.variable_clave', 'DB_PASSWORD')
        ->call('guardarProveedor')
        ->assertNotified('«DB_PASSWORD» no sirve como variable de la clave: va como IA_CLAVE_ seguido de mayúsculas, números o guion bajo.');

    expect(IaProveedor::query()->count())->toBe(0);
});

it('Gerencia ve los proveedores pero no los cambia; un panel que no existe se cierra', function () {
    $proveedor = IaProveedor::factory()->create();

    Livewire::actingAs(pantIaGerencia())->test(InteligenciaArtificial::class)
        ->assertDontSee('Agregar proveedor')
        ->call('abrir', 'proveedor:'.$proveedor->id)
        ->assertDontSee('Agregar modelo')
        ->call('abrir', 'proceso:no_existe')
        ->assertSet('abierto', null);
});
