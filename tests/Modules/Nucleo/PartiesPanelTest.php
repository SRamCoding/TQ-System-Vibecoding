<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\NuevaParty;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Parties\RolesDeParty;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * Plan 1.3, D12: la pantalla «Personas y empresas» es solo presentación. Aquí: que abre con su permiso,
 * que el alta en dos pasos avisa lo que debe, y que la ficha llama a las acciones registradas con la versión
 * leída al abrir. Las reglas ya se prueban en el servicio y las acciones.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarCon(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['parties.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function idTipoDoc(string $codigo): int
{
    return TipoDocumentoIdentidad::query()->where('codigo', $codigo)->value('id');
}

function idDelRol(string $codigo): int
{
    return RolParty::query()->where('codigo', $codigo)->value('id');
}

function empresaEnPantalla(string $ruc = '20123456786', string $razonSocial = 'Comercial Ejemplo S.A.C.'): Party
{
    return Party::query()->findOrFail(app(PartiesService::class)->registrar(new DatosParty('6', $ruc, razonSocial: $razonSocial))->id);
}

function personaEnPantalla(string $dni = '45678912'): Party
{
    return Party::query()->findOrFail(app(PartiesService::class)->registrar(new DatosParty('1', $dni, nombres: 'Ana María', apellidos: 'Pérez Gómez'))->id);
}

// ——— Lista ———

test('sin permiso para ver, la pantalla no abre ni aparece', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/personas')->assertForbidden();
    test()->get('/')->assertDontSee('Personas y empresas');
});

test('con permiso, la lista muestra las activas con su documento y sus roles', function () {
    entrarCon();
    $empresa = empresaEnPantalla();
    app(PartiesService::class)->asignarRol($empresa->id, 'cliente', CarbonImmutable::parse('2026-01-01'));
    $inactiva = empresaEnPantalla('20555555556', 'Cerrada S.A.');
    $inactiva->update(['es_activo' => false]);

    test()->get('/personas')->assertOk()->assertSee('Comercial Ejemplo S.A.C.')->assertSee('RUC 20123456786')->assertSee('Cliente');

    Livewire::test(ListarParties::class)
        ->assertCanSeeTableRecords([$empresa])
        ->assertCanNotSeeTableRecords([$inactiva]);
});

test('el inicio muestra la caja de Personas y empresas a quien puede verla', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'));

    test()->get('/')->assertSee('Personas y empresas');
});

test('el buscador de la lista encuentra por nombre sin acentos y por el comienzo del número', function () {
    entrarCon();
    $tucume = empresaEnPantalla('20123456786', 'Municipalidad Distrital de Túcume');
    $otra = empresaEnPantalla('20555555556', 'Otra Empresa S.A.');

    Livewire::test(ListarParties::class)
        ->searchTable('tucume')->assertCanSeeTableRecords([$tucume])->assertCanNotSeeTableRecords([$otra])
        ->searchTable('20-5555')->assertCanSeeTableRecords([$otra])->assertCanNotSeeTableRecords([$tucume]);
});

test('el filtro por rol muestra solo las que lo tienen vigente', function () {
    entrarCon();
    $proveedor = empresaEnPantalla();
    $otra = empresaEnPantalla('20555555556', 'Otra Empresa S.A.');
    app(PartiesService::class)->asignarRol($proveedor->id, 'proveedor', CarbonImmutable::parse('2026-01-01'));

    Livewire::test(ListarParties::class)
        ->filterTable('rol', 'proveedor')
        ->assertCanSeeTableRecords([$proveedor])
        ->assertCanNotSeeTableRecords([$otra]);
});

test('registrar solo aparece y abre con su permiso', function () {
    entrarCon();
    Livewire::test(ListarParties::class)->assertActionHidden('registrar');
    test()->get('/personas/registrar')->assertForbidden();

    entrarCon('parties.crear');
    Livewire::test(ListarParties::class)->assertActionVisible('registrar');
    test()->get('/personas/registrar')->assertOk()->assertSee('Se empieza por el documento');
});

// ——— Alta en dos pasos ———

test('un RUC con dígito verificador mal se marca en el primer paso y no sigue', function () {
    entrarCon('parties.crear');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456787'])
        ->goToNextWizardStep()
        ->assertHasErrors(['data.numero_documento'])
        ->assertSee('su último dígito no corresponde');
});

test('si el documento ya existe, avisa y no sigue', function () {
    entrarCon('parties.crear', 'parties.desactivar');
    empresaEnPantalla();

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20 123 456 786'])
        ->goToNextWizardStep()
        ->assertNotDispatched('next-wizard-step')
        ->assertSee('Ya está registrada')
        ->assertDontSee('pero está inactiva')
        ->assertSee('Comercial Ejemplo S.A.C. · 20123456786')
        ->assertSet('existente.activa', true);
});

test('si existe pero está inactiva, lo dice y ofrece activarla a quien puede', function () {
    entrarCon('parties.crear', 'parties.desactivar');
    $party = empresaEnPantalla();
    app(PartiesService::class)->desactivar($party->id, 1);

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456786'])
        ->goToNextWizardStep()
        ->assertSee('Ya está registrada, pero está inactiva')
        ->assertSet('existente.activa', false)
        ->assertSet('existente.version', 2)
        ->call('mountAction', 'nucleoPartiesActivar', [], ['schemaComponent' => 'form.existente'])
        ->callMountedAction()
        ->assertNotified('Activada.')
        ->assertRedirect(VerParty::getUrl(['record' => $party->id]));

    expect($party->refresh()->es_activo)->toBeTrue();
});

test('sin el permiso de desactivar, la opción de activar no aparece', function () {
    entrarCon('parties.crear');
    $party = empresaEnPantalla();
    app(PartiesService::class)->desactivar($party->id, 1);

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456786'])
        ->goToNextWizardStep()
        ->assertSee('pero está inactiva')
        ->assertActionHidden(TestAction::make('nucleoPartiesActivar')->schemaComponent('existente', schema: 'form'));
});

test('si existe el DNI de un RUC 10, avisa en el segundo paso y deja registrar', function () {
    entrarCon('parties.crear');
    $dni = personaEnPantalla('45678912');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '10456789124'])
        ->goToNextWizardStep()
        ->assertDispatched('next-wizard-step')
        ->assertSee('Puede ser la misma persona')
        ->assertSee('Pérez Gómez Ana María · 45678912')
        ->fillForm(['nombres' => 'Ana María', 'apellidos' => 'Pérez Gómez'])
        ->call('registrar')
        ->assertNotified();

    expect(Party::query()->where('numero_documento', '10456789124')->exists())->toBeTrue()
        ->and($dni->refresh()->es_activo)->toBeTrue();
});

test('registra una empresa con sus roles y abre su ficha', function () {
    test()->travelTo('2026-10-15 10:00');
    entrarCon('parties.crear', 'parties.asignar-roles');

    $pantalla = Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20-123-456-786'])
        ->goToNextWizardStep()
        ->assertWizardCurrentStep(2)
        ->assertSee('Tipo de persona: Persona jurídica')
        ->fillForm(['razon_social' => 'Comercial Ejemplo S.A.C.', 'telefono' => '074 123456', 'roles' => [idDelRol('cliente'), idDelRol('entidad')]])
        ->call('registrar')
        ->assertNotified('Registrada.');

    $party = Party::query()->where('numero_documento', '20123456786')->firstOrFail();
    $pantalla->assertRedirect(VerParty::getUrl(['record' => $party->id]));
    expect($party->telefono)->toBe('074 123456')
        ->and(PartyRol::query()->where('party_id', $party->id)->count())->toBe(2)
        ->and(app(PartiesService::class)->tieneRol($party->id, 'entidad'))->toBeTrue();
});

test('si el rol elegido se borra antes de enviar, el error se ve bajo el campo y no se registra nada', function () {
    entrarCon('parties.crear', 'parties.asignar-roles');
    $aliado = RolParty::factory()->create(['codigo' => 'socio', 'nombre' => 'Socio']);

    $pantalla = Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456786'])
        ->goToNextWizardStep()
        ->fillForm(['razon_social' => 'Comercial Ejemplo S.A.C.', 'roles' => [$aliado->id]]);

    // Otra persona borra el rol entre que se eligió y se envía.
    $aliado->delete();

    // Lo detiene el propio selector (el rol ya no está entre las opciones), antes de llegar a la acción.
    $pantalla->call('registrar')->assertHasErrors(['data.roles.0']);

    expect(Party::query()->where('numero_documento', '20123456786')->exists())->toBeFalse();
});

test('sin el permiso de asignar roles, el campo de roles no aparece', function () {
    entrarCon('parties.crear');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456786'])
        ->goToNextWizardStep()
        ->assertFormFieldHidden('roles');

    entrarCon('parties.crear', 'parties.asignar-roles');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('6'), 'numero_documento' => '20123456786'])
        ->goToNextWizardStep()
        ->assertFormFieldVisible('roles');
});

test('el tipo de persona se elige solo si el documento no lo dice, y cambia los campos', function () {
    entrarCon('parties.crear');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('1'), 'numero_documento' => '45678912'])
        ->goToNextWizardStep()
        ->assertFormFieldHidden('tipo')
        ->assertFormFieldVisible('nombres')
        ->assertFormFieldHidden('razon_social');

    Livewire::test(NuevaParty::class)
        ->fillForm(['tipo_documento_id' => idTipoDoc('0'), 'numero_documento' => 'US12345'])
        ->goToNextWizardStep()
        ->assertFormFieldVisible('tipo')
        ->fillForm(['tipo' => 'juridica'])
        ->assertFormFieldVisible('razon_social')
        ->assertFormFieldHidden('nombres')
        ->fillForm(['razon_social' => 'Acme Inc.'])
        ->call('registrar')
        ->assertNotified('Registrada.');

    expect(Party::query()->where('numero_documento', 'US12345')->firstOrFail()->tipo->value)->toBe('juridica');
});

// ——— Ficha ———

test('la ficha muestra datos, roles e historial', function () {
    test()->travelTo('2026-10-04 12:00');
    entrarCon();
    $party = empresaEnPantalla();
    app(PartiesService::class)->asignarRol($party->id, 'cliente', CarbonImmutable::parse('2026-10-04'));

    test()->get(VerParty::getUrl(['record' => $party->id]))
        ->assertOk()
        ->assertSee('Comercial Ejemplo S.A.C.')
        ->assertSee('RUC 20123456786 · Persona jurídica')
        ->assertSee('Datos tributarios')
        ->assertSee('Historial')
        ->assertSee('Asignó rol Cliente desde 04/10/2026');
});

test('la pestaña de roles exige el permiso de ver y no deja cambiar de party desde el navegador', function () {
    $party = empresaEnPantalla();
    $otra = empresaEnPantalla('20000003001', 'Otra Empresa S.A.C.');

    test()->actingAs(User::factory()->create());
    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])->assertForbidden();

    entrarCon();
    expect(fn () => Livewire::test(RolesDeParty::class, ['partyId' => $party->id])->set('partyId', $otra->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('editar abre con los datos y la versión, y en una natural no ofrece la razón social', function () {
    entrarCon('parties.editar');
    $party = personaEnPantalla();

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->mountAction('nucleoPartiesEditar')
        ->assertSchemaStateSet(['nombres' => 'Ana María', 'apellidos' => 'Pérez Gómez', 'version' => 1])
        ->fillForm(['apellidos' => 'Pérez Rojas'])
        ->callMountedAction()
        ->assertNotified('Guardado.')
        // Para quien la muestra desde afuera, como la vista previa del asistente (plan 1.15).
        ->assertDispatched('ficha-party-cambiada');

    expect($party->refresh()->razon_social)->toBe('Pérez Rojas Ana María')
        ->and($party->version)->toBe(2);
});

test('si otra persona guarda mientras el formulario está abierto, se avisa y no se pisa', function () {
    entrarCon('parties.editar', 'parties.desactivar');
    $party = empresaEnPantalla();

    $pantalla = Livewire::test(VerParty::class, ['record' => $party->id])->mountAction('nucleoPartiesDesactivar');
    app(PartiesService::class)->editar($party->id, ['telefono' => '111'], 1);

    $pantalla->callMountedAction()->assertNotified('Otra persona cambió «Comercial Ejemplo S.A.C.» mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');

    expect($party->refresh()->es_activo)->toBeTrue();
});

test('las acciones de la ficha aparecen según el permiso y el estado', function () {
    entrarCon('parties.editar');
    $party = empresaEnPantalla();

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->assertActionVisible('nucleoPartiesEditar')
        ->assertActionHidden('nucleoPartiesEditarTributarios')
        ->assertActionHidden('nucleoPartiesDesactivar')
        ->assertActionHidden('nucleoPartiesBorrar');

    entrarCon('parties.editar-tributarios', 'parties.desactivar');

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->assertActionVisible('nucleoPartiesEditarTributarios')
        ->assertActionVisible('nucleoPartiesDesactivar')
        ->assertActionHidden('nucleoPartiesActivar');
});

test('los datos tributarios se cambian desde su propio formulario', function () {
    entrarCon('parties.editar-tributarios');
    $party = empresaEnPantalla();

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->mountAction('nucleoPartiesEditarTributarios')
        ->assertSchemaStateSet(['es_agente_retencion' => false, 'version' => 1])
        ->fillForm(['es_agente_retencion' => true])
        ->callMountedAction()
        ->assertNotified('Guardado.');

    expect($party->refresh()->es_agente_retencion)->toBeTrue();
});

test('borrar una que nunca se usó vuelve a la lista', function () {
    entrarCon('parties.borrar');
    $party = empresaEnPantalla();

    Livewire::test(VerParty::class, ['record' => $party->id])
        ->callAction('nucleoPartiesBorrar')
        ->assertNotified('Borrada.')
        ->assertRedirect(ListarParties::getUrl());

    expect(Party::query()->whereKey($party->id)->exists())->toBeFalse();
});

// ——— Pestaña Roles ———

test('en la pestaña Roles se asigna un rol y se cierra con la versión leída', function () {
    entrarCon('parties.asignar-roles');
    $party = empresaEnPantalla();

    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesAsignarRol')->table(), ['rol_party_id' => idDelRol('proveedor'), 'desde' => '2026-01-01'])
        ->assertNotified('Guardado.');

    $periodo = PartyRol::query()->where('party_id', $party->id)->firstOrFail();

    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])
        ->mountAction(TestAction::make('nucleoPartiesCerrarRol')->table($periodo))
        ->assertSchemaStateSet(['version' => 1])
        ->fillForm(['hasta' => '2026-06-30'])
        ->callMountedAction()
        ->assertNotified('Guardado.');

    expect($periodo->refresh()->hasta?->toDateString())->toBe('2026-06-30');

    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])
        ->assertActionHidden(TestAction::make('nucleoPartiesCerrarRol')->table($periodo));
});

test('un periodo que se cruza se rechaza con aviso en la pestaña Roles', function () {
    entrarCon('parties.asignar-roles');
    $party = empresaEnPantalla();
    app(PartiesService::class)->asignarRol($party->id, 'cliente', CarbonImmutable::parse('2026-01-01'));

    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])
        ->callAction(TestAction::make('nucleoPartiesAsignarRol')->table(), ['rol_party_id' => idDelRol('cliente'), 'desde' => '2026-03-01'])
        ->assertNotified('Ya tiene el rol Cliente en esas fechas. Un rol no puede tener dos periodos que se crucen.');
});

test('sin el permiso de roles, la pestaña Roles solo muestra', function () {
    entrarCon();
    $party = empresaEnPantalla();

    Livewire::test(RolesDeParty::class, ['partyId' => $party->id])
        ->assertActionHidden(TestAction::make('nucleoPartiesAsignarRol')->table());
});
