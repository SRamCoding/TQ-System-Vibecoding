<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Parties\ContactosDeParty;
use App\Modules\Nucleo\Filament\Resources\Parties\CuentasBancariasDeParty;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Parties\SedesDeParty;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Parties\DatosCuentaBancaria;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\CuentasBancariasService;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * Pestañas Contactos, Sedes y Cuentas bancarias de la ficha (plan 1.3b, paso 5): solo presentación. Que abren con
 * su permiso, que cada formulario toma la versión al abrir, que precargan lo que deben y que los rechazos se
 * avisan. Las reglas ya se prueban en los servicios y las acciones.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    CarbonImmutable::setTestNow('2026-10-16 10:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function entrarEnFicha(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['parties.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function contactoEnFicha(int $partyId, string $nombres = 'Luis', string $apellidos = 'Fernández', mixed ...$otros): int
{
    return app(ContactosPartyService::class)->agregar($partyId, new DatosContacto($nombres, $apellidos, ...$otros));
}

function bancoEnFicha(string $nombre = 'Banco Ejemplo S.A.'): Party
{
    $banco = Party::factory()->juridica()->create(['razon_social' => $nombre]);
    app(PartiesService::class)->asignarRol($banco->id, 'banco', CarbonImmutable::parse('2026-01-01'));

    return $banco;
}

// --- La ficha ---

test('la ficha tiene las pestañas Contactos y Sedes; Cuentas bancarias, solo con su permiso', function () {
    $party = Party::factory()->juridica()->create();

    entrarEnFicha();
    Livewire::test(VerParty::class, ['record' => $party->id])
        ->assertSee('Contactos')->assertSee('Sedes')->assertDontSee('Cuentas bancarias');

    entrarEnFicha('campos.ver-datos_bancarios');
    Livewire::test(VerParty::class, ['record' => $party->id])->assertSee('Cuentas bancarias');
});

test('la pestaña de cuentas no abre sin su permiso aunque se monte aparte, ni se cambia de party', function () {
    $party = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();

    entrarEnFicha();
    Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])->assertForbidden();

    entrarEnFicha('campos.ver-datos_bancarios');
    expect(fn () => Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])->set('partyId', $otra->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('quien no ve cuentas no recibe los números en la página', function () {
    $party = Party::factory()->juridica()->create();
    app(CuentasBancariasService::class)->registrar($party->id, new DatosCuentaBancaria(bancoEnFicha()->id, '1911234567012', 'PEN', cci: '00219100123456701255'));

    entrarEnFicha();

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->assertDontSee('1911234567012')->assertDontSee('00219100123456701255');
});

test('contactos y sedes sin permiso de gestión: se ven, pero sin botones', function () {
    $party = Party::factory()->juridica()->create();
    contactoEnFicha($party->id);

    entrarEnFicha();

    Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])
        ->assertSee('Luis Fernández')
        ->assertActionHidden(TestAction::make('nucleoPartiesAgregarContacto')->table());
    Livewire::test(SedesDeParty::class, ['partyId' => $party->id])
        ->assertActionHidden(TestAction::make('nucleoPartiesAgregarSede')->table());
});

test('Datos muestra el contacto principal, o el aviso si hay vigentes sin principal', function () {
    $party = Party::factory()->juridica()->create();
    entrarEnFicha();

    Livewire::test(VerParty::class, ['record' => $party->id])->assertDontSee('Sin contacto principal');

    $luis = contactoEnFicha($party->id, cargo: 'Jefe de Logística');

    Livewire::test(VerParty::class, ['record' => $party->id])->assertSee('Sin contacto principal');
    Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])->assertSee('ninguno es el principal');

    app(ContactosPartyService::class)->marcarPrincipal($luis, 1);

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->assertSee('Luis Fernández · Jefe de Logística')->assertDontSee('Sin contacto principal');
});

// --- Contactos ---

test('agrega un contacto desde la pestaña y lo marca como principal', function () {
    entrarEnFicha('parties.gestionar-contactos');
    $party = Party::factory()->juridica()->create();

    Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesAgregarContacto')->table(), ['nombres' => 'Carmen', 'apellidos' => 'Ruiz', 'cargo' => 'Asistente', 'es_principal' => true])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.')
        ->assertDispatched('ficha-party-cambiada');

    expect(PartyContacto::query()->where('party_id', $party->id)->sole()->es_principal)->toBeTrue();
});

test('editar toma la versión al abrir: si otro guarda mientras tanto, se avisa y no se pisa', function () {
    entrarEnFicha('parties.gestionar-contactos');
    $party = Party::factory()->juridica()->create();
    $id = contactoEnFicha($party->id, cargo: 'Jefe');
    $contacto = PartyContacto::query()->findOrFail($id);

    $pantalla = Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])
        ->mountAction(TestAction::make('nucleoPartiesEditarContacto')->table($contacto))
        ->assertSchemaStateSet(['nombres' => 'Luis', 'cargo' => 'Jefe', 'version' => 1]);

    app(ContactosPartyService::class)->editar($id, new DatosContacto('Luis', 'Fernández', cargo: 'Gerente'), 1);

    $pantalla->fillForm(['cargo' => 'Subgerente'])->callMountedAction()
        ->assertNotified('Otra persona cambió este contacto mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');

    expect($contacto->refresh()->cargo)->toBe('Gerente');
});

test('el traslado abre con los datos precargados, el cierre en hoy y sin cargo', function () {
    entrarEnFicha('parties.gestionar-contactos');
    $pimentel = Party::factory()->juridica()->create(['razon_social' => 'Municipalidad de Ejemplo']);
    $gore = Party::factory()->juridica()->create(['razon_social' => 'Gobierno Regional de Ejemplo']);
    $id = contactoEnFicha($pimentel->id, 'Jorge', 'Castillo', cargo: 'Gerente', telefono: '979 555 214', email: 'jorge@muni.pe');
    $contacto = PartyContacto::query()->findOrFail($id);

    Livewire::test(ContactosDeParty::class, ['partyId' => $pimentel->id])
        ->mountAction(TestAction::make('nucleoPartiesTrasladarContacto')->table($contacto))
        ->assertSchemaStateSet(['nombres' => 'Jorge', 'apellidos' => 'Castillo', 'telefono' => '979 555 214', 'email' => 'jorge@muni.pe', 'hasta' => '2026-10-16', 'cargo' => null, 'version' => 1])
        ->fillForm(['nueva_party_id' => $gore->id, 'cargo' => 'Jefe de Abastecimiento', 'email' => 'jorge@gore.pe'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    Livewire::test(ContactosDeParty::class, ['partyId' => $pimentel->id])
        ->assertSee('Vínculo cerrado el 16/10/2026')->assertSee('ahora en Gobierno Regional de Ejemplo')
        ->assertActionHidden(TestAction::make('nucleoPartiesTrasladarContacto')->table($contacto->refresh()))
        ->assertActionHidden(TestAction::make('nucleoPartiesCerrarVinculoContacto')->table($contacto));
    Livewire::test(ContactosDeParty::class, ['partyId' => $gore->id])
        ->assertSee('antes en Municipalidad de Ejemplo')->assertSee('jorge@gore.pe');
});

test('el traslado sin cargo nuevo no se guarda', function () {
    entrarEnFicha('parties.gestionar-contactos');
    $party = Party::factory()->juridica()->create();
    $contacto = PartyContacto::query()->findOrFail(contactoEnFicha($party->id));

    Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesTrasladarContacto')->table($contacto), ['nueva_party_id' => Party::factory()->juridica()->create()->id])
        ->assertHasFormErrors(['cargo' => 'required']);

    expect($contacto->refresh()->hasta)->toBeNull();
});

test('cerrar vínculo y marcar principal se ofrecen solo donde aplican', function () {
    entrarEnFicha('parties.gestionar-contactos');
    $party = Party::factory()->juridica()->create();
    $principal = PartyContacto::query()->findOrFail(contactoEnFicha($party->id, esPrincipal: true));
    $cerrado = PartyContacto::query()->findOrFail(contactoEnFicha($party->id, 'Ana', 'Soto', hasta: CarbonImmutable::parse('2026-07-31')));

    Livewire::test(ContactosDeParty::class, ['partyId' => $party->id])
        ->assertActionHidden(TestAction::make('nucleoPartiesMarcarContactoPrincipal')->table($principal))
        ->assertActionVisible(TestAction::make('nucleoPartiesCerrarVinculoContacto')->table($principal))
        ->assertActionHidden(TestAction::make('nucleoPartiesMarcarContactoPrincipal')->table($cerrado))
        ->assertActionHidden(TestAction::make('nucleoPartiesCerrarVinculoContacto')->table($cerrado))
        ->callAction(TestAction::make('nucleoPartiesCerrarVinculoContacto')->table($principal), ['hasta' => '2026-10-15'])
        ->assertNotified('Guardado.');

    expect($principal->refresh()->es_principal)->toBeFalse();
});

// --- Sedes ---

test('agrega una sede con un contacto vigente, y al editar respeta el que ya tenía aunque se haya cerrado', function () {
    entrarEnFicha('parties.gestionar-sedes');
    $party = Party::factory()->juridica()->create();
    $luis = contactoEnFicha($party->id);

    Livewire::test(SedesDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesAgregarSede')->table(), ['nombre' => 'Almacén Chiclayo', 'direccion' => 'Av. 1', 'contacto_id' => $luis, 'codigo_establecimiento' => '0001'])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    $sede = PartySede::query()->where('party_id', $party->id)->sole();
    // El contacto rota después: la sede queda sin él (D4). Con un contacto cerrado guardado a mano (dato viejo), el
    // formulario sigue abriendo y guardando.
    PartySede::query()->whereKey($sede->id)->update(['contacto_id' => $luis]);
    PartyContacto::query()->whereKey($luis)->update(['hasta' => '2026-07-31']);

    Livewire::test(SedesDeParty::class, ['partyId' => $party->id])
        ->mountAction(TestAction::make('nucleoPartiesEditarSede')->table($sede->refresh()))
        ->assertSchemaStateSet(['contacto_id' => $luis, 'version' => 1])
        ->fillForm(['direccion' => 'Av. 2'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect($sede->refresh()->direccion)->toBe('Av. 2');
});

test('un nombre de sede repetido se avisa', function () {
    entrarEnFicha('parties.gestionar-sedes');
    $party = Party::factory()->juridica()->create();
    PartySede::factory()->create(['party_id' => $party->id, 'nombre' => 'Almacén Chiclayo']);

    Livewire::test(SedesDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesAgregarSede')->table(), ['nombre' => 'ALMACEN CHICLAYO', 'direccion' => 'Av. 9'])
        ->assertNotified('Ya hay una sede activa llamada «ALMACEN CHICLAYO» (sin contar mayúsculas ni acentos).');
});

// --- Cuentas bancarias ---

test('registra una cuenta eligiendo el banco por su nombre y la moneda del catálogo', function () {
    entrarEnFicha('campos.ver-datos_bancarios', 'parties.gestionar-cuentas-bancarias');
    $party = Party::factory()->juridica()->create();
    $banco = bancoEnFicha();

    Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesRegistrarCuentaBancaria')->table(), [
            'banco_party_id' => $banco->id, 'numero_cuenta' => '191-1234567-0-12', 'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'es_principal' => true,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])->assertSee('1911234567012')->assertSee('Principal');
});

test('la misma cuenta de otra persona se avisa sin mostrar el número', function () {
    entrarEnFicha('campos.ver-datos_bancarios', 'parties.gestionar-cuentas-bancarias');
    $banco = bancoEnFicha();
    app(CuentasBancariasService::class)->registrar(Party::factory()->juridica()->create()->id, new DatosCuentaBancaria($banco->id, '1911234567012', 'PEN'));
    $party = Party::factory()->juridica()->create();

    Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesRegistrarCuentaBancaria')->table(), ['banco_party_id' => $banco->id, 'numero_cuenta' => '1911234567012', 'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id')])
        ->assertNotified('Esa cuenta (o ese CCI) ya está registrada a nombre de otra persona o empresa. Revisa el número; si es correcto, consúltalo con Tesorería antes de registrarla.');

    expect(PartyCuentaBancaria::query()->where('party_id', $party->id)->exists())->toBeFalse();
});

test('completar CCI y marcar principal se ofrecen solo donde aplican', function () {
    entrarEnFicha('campos.ver-datos_bancarios', 'parties.gestionar-cuentas-bancarias');
    $party = Party::factory()->juridica()->create();
    $cuentas = app(CuentasBancariasService::class);
    $conCci = PartyCuentaBancaria::query()->findOrFail($cuentas->registrar($party->id, new DatosCuentaBancaria(bancoEnFicha()->id, '111', 'PEN', cci: '00219100123456701255')));
    $sinCci = PartyCuentaBancaria::query()->findOrFail($cuentas->registrar($party->id, new DatosCuentaBancaria(bancoEnFicha('Otro Banco S.A.')->id, '222', 'USD')));

    Livewire::test(CuentasBancariasDeParty::class, ['partyId' => $party->id])
        ->assertActionHidden(TestAction::make('nucleoPartiesCompletarCci')->table($conCci))
        ->assertActionVisible(TestAction::make('nucleoPartiesCompletarCci')->table($sinCci))
        ->assertActionVisible(TestAction::make('nucleoPartiesMarcarCuentaPrincipal')->table($sinCci))
        ->callAction(TestAction::make('nucleoPartiesCompletarCci')->table($sinCci), ['cci' => '002-191-001234567112-55'])
        ->assertNotified('Guardado.');

    expect($sinCci->refresh()->cci)->toBe('00219100123456711255');
});
