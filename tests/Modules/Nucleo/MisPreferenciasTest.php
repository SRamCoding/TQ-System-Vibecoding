<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Apariencia;
use App\Modules\Nucleo\Filament\BarraModulos;
use App\Modules\Nucleo\Filament\Pages\MisPreferencias;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4g, D6: la pantalla «Mis preferencias». Se arma sola desde el registro, guarda al cambiar con la acción
 * registrada, se aplica sin recargar y restablece por grupo. Lo que el navegador pinta lo revisa el desarrollador.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Permission::findOrCreate('ventas.ver', 'web');

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('notas', 'Notas', '/notas', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: 'ventas.ver', area: AreaModulo::Crm));
});

function pantallaDePreferencias(User $usuario): Testable
{
    return Livewire::actingAs($usuario)->test(MisPreferencias::class);
}

/**
 * Los campos de la pantalla, por su nombre.
 *
 * @return array<string, Field>
 */
function camposDePreferencias(Testable $pantalla): array
{
    $campos = [];

    foreach ($pantalla->instance()->form->getFlatFields() as $campo) {
        $campos[$campo->getName()] = $campo;
    }

    return $campos;
}

test('la abre toda persona con sesión, y el menú del avatar la ofrece en lugar de «Mi saludo»', function () {
    test()->get('/mis-preferencias')->assertRedirect('/login');

    test()->actingAs(User::factory()->create());
    test()->get('/mis-preferencias')->assertOk()->assertSee('Mis preferencias');
    test()->get('/')->assertSee('Mis preferencias')->assertDontSee('Mi saludo');
});

test('muestra los grupos con preferencias, en orden; en Avisos, los grupos de avisos del núcleo (plan 1.10); sin grupo Saludo (plan 1.4aa)', function () {
    pantallaDePreferencias(User::factory()->create())
        ->assertSeeInOrder(['Apariencia', 'Ambientación de la fecha', 'Navegación', 'Avisos', 'Control interno: también por correo', 'Fallas del sistema: también por correo'])
        ->assertDontSee('También en inglés')
        ->assertDontSee('Categorías de frases');
});

test('lo de la campana se dice una sola vez, en la descripción de Avisos, y no en cada preferencia (plan 1.4aa, D4)', function () {
    $html = pantallaDePreferencias(User::factory()->create())
        ->assertSeeInOrder(['Avisos', 'Para todas estas preferencias: los avisos siempre llegan a la campana', 'Control interno: también por correo'])
        ->html();

    expect(substr_count($html, 'siempre llegan a la campana'))->toBe(1)
        ->and($html)->not->toContain('Siempre llegan a la campana. Los obligatorios');
});

test('cada tipo con su control; el ancho de la barra no se muestra; las de la barra dicen «Solo en escritorio»', function () {
    $pantalla = pantallaDePreferencias(User::factory()->create());
    $campos = camposDePreferencias($pantalla);

    expect($campos['apariencia__tema'])->toBeInstanceOf(ToggleButtons::class)
        ->and($campos['apariencia__tema']->hasInlineLabel())->toBeTrue()
        ->and($campos['navegacion__pantalla_inicio'])->toBeInstanceOf(Select::class)
        ->and($campos['apariencia__ambientacion'])->toBeInstanceOf(Toggle::class)
        ->and($campos['navegacion__modulos_barra'])->toBeInstanceOf(CheckboxList::class)
        ->and($campos)->not->toHaveKey('navegacion__ancho_barra');

    $pantalla->assertSeeHtml('<span class="tq-solo-celular">Solo en escritorio</span>');
});

test('un número guarda al salir del campo, no con cada tecla', function () {
    app(RegistroPreferencias::class)->declarar(
        DefinicionPreferencia::numero(GrupoPreferencia::Avisos, 'prueba_numero', 'Número de prueba', 'Solo para la prueba.', 5, 1, 10),
    );

    $campo = camposDePreferencias(pantallaDePreferencias(User::factory()->create()))['avisos__prueba_numero'];

    expect($campo)->toBeInstanceOf(TextInput::class)
        ->and($campo->isLiveOnBlur())->toBeTrue();
});

test('abre con lo guardado', function () {
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, 'apariencia.tema', 'dark');
    app(NavegacionService::class)->ocultar($usuario, ['notas']);

    pantallaDePreferencias($usuario)
        ->assertSchemaStateSet(['apariencia__tema' => 'dark', 'apariencia__ambientacion' => true, 'navegacion__modulos_barra' => ['agenda']]);
});

test('cambiar la apariencia la guarda y la aplica sin recargar', function () {
    $usuario = User::factory()->create();

    pantallaDePreferencias($usuario)
        ->set('data.apariencia__tema', 'dark')
        ->assertDispatched(Apariencia::EVENTO, clases: [], tema: 'dark')
        ->set('data.apariencia__letra', 'grande')
        ->assertDispatched(Apariencia::EVENTO, clases: ['tq-letra-grande'], tema: 'dark')
        ->assertNoRedirect();

    expect(Apariencia::tema($usuario))->toBe('dark')
        ->and(Apariencia::clases($usuario))->toBe(['tq-letra-grande']);
});

test('prender la barra y elegir sus módulos se guarda y se aplica, con el menú al día', function () {
    $usuario = User::factory()->create();

    pantallaDePreferencias($usuario)
        ->set('data.navegacion__barra_modulos', true)
        ->assertDispatched(BarraModulos::EVENTO, visible: true, ocultos: [])
        ->assertDispatched('refresh-topbar')
        ->set('data.navegacion__modulos_barra', ['agenda'])
        ->assertDispatched(BarraModulos::EVENTO, visible: true, ocultos: ['notas']);

    expect(app(NavegacionService::class)->barraVisible($usuario))->toBeTrue()
        ->and(app(NavegacionService::class)->ocultos($usuario))->toBe(['notas']);
});

test('un módulo permitido con la pantalla abierta no queda oculto al guardar la lista', function () {
    $usuario = User::factory()->create();
    $pantalla = pantallaDePreferencias($usuario);

    $usuario->givePermissionTo('ventas.ver');
    $pantalla->set('data.navegacion__modulos_barra', ['agenda']);

    expect(app(NavegacionService::class)->ocultos($usuario->refresh()))->toBe(['notas']);
});

test('si algo se rechaza, el control vuelve a lo guardado y se avisa', function () {
    $usuario = User::factory()->create();

    pantallaDePreferencias($usuario)
        ->set('data.navegacion__pantalla_inicio', 'ventas')
        ->assertNotified('Ese valor no vale para «Pantalla al entrar».')
        ->assertSet('data.navegacion__pantalla_inicio', 'inicio')
        ->set('data.navegacion__modulos_barra', [])
        ->assertNotified('Deja al menos un módulo en la barra.')
        ->assertSet('data.navegacion__modulos_barra', ['agenda', 'notas']);

    expect(app(PreferenciasUsuarioService::class)->opcion($usuario, 'navegacion.pantalla_inicio'))->toBe('inicio');
});

test('«Restablecer» pide confirmación y vuelve el grupo a como viene, sin tocar los otros', function () {
    $usuario = User::factory()->create();
    $preferencias = app(PreferenciasUsuarioService::class);
    $preferencias->cambiar($usuario, 'apariencia.tema', 'dark');
    $preferencias->cambiar($usuario, 'apariencia.densidad', 'compacto');
    $preferencias->cambiar($usuario, 'navegacion.barra_modulos', true);

    // La misma llamada que hace el botón del encabezado de la sección.
    $pantalla = pantallaDePreferencias($usuario)->call('mountAction', 'restablecerApariencia', [], ['schemaComponent' => 'form.apariencia']);
    $accion = $pantalla->instance()->getMountedAction();

    expect($accion?->isConfirmationRequired())->toBeTrue()
        ->and($accion?->getModalHeading())->toBe('¿Volver Apariencia a como viene?');

    $pantalla->callMountedAction()
        ->assertDispatched(Apariencia::EVENTO, clases: [], tema: 'system')
        ->assertSet('data.apariencia__tema', 'system')
        ->assertSet('data.apariencia__densidad', 'comodo')
        ->assertSet('data.navegacion__barra_modulos', true);

    expect(Apariencia::tema($usuario))->toBe('system')
        ->and($preferencias->siNo($usuario, 'navegacion.barra_modulos'))->toBeTrue();
});
