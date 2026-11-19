<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\MisDatos;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\CodigoCambioClave;
use App\Modules\Nucleo\Services\ClaveService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Plan 1.4h, D1 a D4: la pantalla «Mis datos». Se ven los datos de la cuenta; se cambian el cumpleaños, el teléfono y
 * la contraseña, por sus acciones registradas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Notification::fake();
});

function pantallaMisDatos(User $usuario): Testable
{
    return Livewire::actingAs($usuario)->test(MisDatos::class);
}

/** La ventana de «Cambiar mi contraseña», abierta con la misma llamada que hace su botón (está dentro del formulario). */
function abrirCambioDeClave(User $usuario): Testable
{
    return pantallaMisDatos($usuario)->call('mountAction', 'cambiarClave', [], ['schemaComponent' => 'form']);
}

test('la abre toda persona con sesión; el menú de la foto la ofrece y ya no tiene «Mi cumpleaños»', function () {
    test()->get('/mis-datos')->assertRedirect('/login');

    $usuario = User::factory()->create(['email' => 'persona@techquk.test']);
    test()->actingAs($usuario)->get('/mis-datos')->assertOk()->assertSee('Mis datos')->assertSee('persona@techquk.test');
    test()->actingAs($usuario)->get('/')->assertSee('Mis datos')->assertDontSee('Mi cumpleaños');
});

test('«Mi cuenta» se ve sin campos para cambiarla', function () {
    $usuario = User::factory()->create();
    $ficha = Party::query()->findOrFail($usuario->party_id);

    pantallaMisDatos($usuario)
        ->assertSee($usuario->name)
        ->assertSee($ficha->numero_documento)
        ->assertSee('Si algo está mal, pídelo a quien administra los usuarios.')
        ->assertDontSeeHtml('name="data.correo"')
        ->assertDontSeeHtml('wire:model="data.correo"');
});

test('«Guardar» cambia el cumpleaños y el teléfono de la propia persona', function () {
    $usuario = User::factory()->create();

    pantallaMisDatos($usuario)
        ->fillForm(['cumpleanos_dia' => 15, 'cumpleanos_mes' => 8, 'telefono' => '987 654 321'])
        ->call('guardar')
        ->assertNotified('Guardado.');

    $usuario->refresh();
    expect([$usuario->cumpleanos_dia, $usuario->cumpleanos_mes])->toBe([15, 8])
        ->and(Party::query()->findOrFail($usuario->party_id)->telefono)->toBe('987 654 321');
});

test('si alguien cambió la cuenta mientras tanto, el aviso de siempre y no se pisa', function () {
    $usuario = User::factory()->create();
    $pantalla = pantallaMisDatos($usuario);

    // Quien gestiona usuarios cambia el cumpleaños desde la ficha mientras la pantalla está abierta.
    User::query()->whereKey($usuario->id)->update(['cumpleanos_dia' => 1, 'cumpleanos_mes' => 1, 'version' => $usuario->version + 1]);

    $pantalla->fillForm(['cumpleanos_dia' => 15, 'cumpleanos_mes' => 8])->call('guardar')->assertNotified();

    expect($usuario->refresh()->cumpleanos_dia)->toBe(1);
});

test('el cumpleaños incompleto se rechaza con su mensaje', function () {
    pantallaMisDatos(User::factory()->create())
        ->fillForm(['cumpleanos_dia' => 15, 'cumpleanos_mes' => null])
        ->call('guardar')
        ->assertNotified('Escribe el día y el mes del cumpleaños, o deja los dos vacíos.');
});

test('«Cambiar mi contraseña» con el código del correo; esta sesión sigue', function () {
    $usuario = User::factory()->create();
    app(ClaveService::class)->pedirCodigo($usuario);
    $codigo = '';
    Notification::assertSentTo($usuario, CodigoCambioClave::class, function (CodigoCambioClave $aviso) use (&$codigo): bool {
        $codigo = $aviso->codigo();

        return true;
    });

    abrirCambioDeClave($usuario)
        ->fillForm(['codigo' => $codigo, 'clave' => 'Nueva-clave2', 'repetida' => 'Nueva-clave2'])
        ->callMountedAction()
        ->assertNotified('Listo: tu contraseña nueva está guardada. Se cerraron tus sesiones en otros equipos.');

    expect(Hash::check('Nueva-clave2', $usuario->refresh()->password))->toBeTrue();
});

test('con un código equivocado no cambia y la ventana queda abierta', function () {
    $usuario = User::factory()->create();
    app(ClaveService::class)->pedirCodigo($usuario);
    $verdadero = '';
    Notification::assertSentTo($usuario, CodigoCambioClave::class, function (CodigoCambioClave $aviso) use (&$verdadero): bool {
        $verdadero = $aviso->codigo();

        return true;
    });

    $pantalla = abrirCambioDeClave($usuario)
        ->fillForm(['codigo' => $verdadero === '000000' ? '111111' : '000000', 'clave' => 'Nueva-clave2', 'repetida' => 'Nueva-clave2'])
        ->callMountedAction()
        ->assertNotified();

    expect($pantalla->instance()->mountedActions)->not->toBe([])
        ->and(Hash::check('Nueva-clave2', $usuario->refresh()->password))->toBeFalse();
});
