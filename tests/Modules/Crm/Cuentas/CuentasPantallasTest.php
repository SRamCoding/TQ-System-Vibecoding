<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Filament\CuentaComercialDeFicha;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\HistorialDeCuenta;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\ListarCuentas;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\NuevaCuenta;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\VerCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Parties\RegistroPestanasFicha;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las pantallas de Cuentas (plan 2.20, D11 y D13): la portada de la lista, Nueva cuenta en dos pasos, la ficha con sus
 * botones y su historial, y la pestaña «Cuenta comercial» en Personas y empresas. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-06 10:00');
});

function entrarEnCuentas(string ...$permisos): User
{
    $usuario = User::factory()->create(['name' => 'Elena Prueba Ríos'])->givePermissionTo([CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function rucDeCuentaInventado(string $diez): string
{
    return $diez.DocumentoIdentidad::digitoVerificadorRuc($diez);
}

/** Una cuenta de una empresa con RUC inventado, por la acción (como lo haría una persona). */
function cuentaEnPantalla(string $razonSocial, string $diez, ?User $ejecutivo = null, bool $publica = false): Cuenta
{
    $ejecutivo ??= User::factory()->create(['name' => 'Marco Ejecutivo Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $quien = User::factory()->create()->givePermissionTo('crm.cuentas.crear');
    $creada = app(EjecutorAcciones::class)->ejecutar('crm.cuentas.crear', [
        'ruc' => rucDeCuentaInventado($diez), 'razon_social' => $razonSocial, 'es_publica' => $publica,
        'nivel_gobierno_id' => $publica ? NivelGobierno::query()->value('id') : null,
        'ejecutivo_user_id' => $ejecutivo->id, 'origen_comercial_id' => OrigenComercial::query()->value('id'),
    ], $quien);

    return Cuenta::query()->findOrFail($creada->cuenta->id);
}

function motivoDeDesactivarEnPantalla(): int
{
    return (int) Motivo::query()->where('es_otro', false)
        ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR))->value('id');
}

// ——— Portada de la lista ———

test('las pestañas cuentan Todas (también las inactivas), Mías, clientes, prospectos, nuevas e inactivas; sin «Sin actividad»', function () {
    $yo = entrarEnCuentas()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $mia = cuentaEnPantalla('Constructora Mía S.A.C.', '2060000101', $yo);
    $inactiva = cuentaEnPantalla('Molinos Inactivos S.A.C.', '2060000102');
    $inactiva->forceFill(['es_activa' => false, 'motivo_inactiva_id' => motivoDeDesactivarEnPantalla()])->save();
    $cliente = cuentaEnPantalla('Agroindustrias Cliente S.A.C.', '2060000103');
    app(CuentasService::class)->marcarCliente($cliente->id, CarbonImmutable::parse('2026-05-01'), OportunidadesDePrueba::id($cliente->id));

    $pestanas = Livewire::withQueryParams([])->test(ListarCuentas::class)->instance()->getTabs();

    expect(array_keys($pestanas))->toBe(['todas', 'mias', 'clientes', 'prospectos', 'nuevas', 'inactivas'])
        ->and($pestanas['todas']->getBadge())->toEqual(3)
        ->and($pestanas['mias']->getBadge())->toEqual(1)
        ->and($pestanas['clientes']->getBadge())->toEqual(1)
        ->and($pestanas['prospectos']->getBadge())->toEqual(1)
        ->and($pestanas['nuevas']->getBadge())->toEqual(3)
        ->and($pestanas['inactivas']->getBadge())->toEqual(1);

    Livewire::withQueryParams([])->test(ListarCuentas::class)->set('activeTab', 'mias')
        ->assertCanSeeTableRecords([$mia])->assertCanNotSeeTableRecords([$inactiva, $cliente]);
});

test('los avisos dicen cuántas revisar y «Ver cuáles» filtra la tabla; nada de avisos de datos que aún no existen', function () {
    entrarEnCuentas();
    $saliente = User::factory()->create(['name' => 'Julio Saliente Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $huerfana = cuentaEnPantalla('Distribuidora Huérfana S.A.C.', '2060000104', $saliente);
    $saliente->forceFill(['es_activo' => false])->save();
    $conRubro = cuentaEnPantalla('Comercial Con Rubro S.A.C.', '2060000105');
    $conRubro->party->forceFill(['rubro_id' => Rubro::query()->value('id')])->save();

    $portada = Livewire::withQueryParams([])->test(ListarCuentas::class)
        ->assertSee('1 cuenta con ejecutivo inactivo')
        ->assertSee('1 cuenta sin rubro')
        ->assertDontSee('sin actividad')
        ->call('verAviso', 'ejecutivo-inactivo')
        ->assertCanSeeTableRecords([$huerfana])
        ->assertCanNotSeeTableRecords([$conRubro])
        ->assertSee('Ver todas');

    $portada->call('verAviso', null)->assertCanSeeTableRecords([$huerfana, $conRubro]);
});

test('la cartera muestra cuántas atiende cada ejecutivo y un clic filtra; el enlace del aviso abre la lista filtrada', function () {
    entrarEnCuentas();
    $ana = User::factory()->create(['name' => 'Ana Cartera Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $deAna = cuentaEnPantalla('Uno de Ana S.A.C.', '2060000106', $ana);
    $otra = cuentaEnPantalla('Otra Cuenta S.A.C.', '2060000107');

    Livewire::withQueryParams([])->test(ListarCuentas::class)
        ->assertSee('Cartera por ejecutivo')->assertSee('Ana Cartera Prueba')
        ->call('verEjecutivo', $ana->id)
        ->assertCanSeeTableRecords([$deAna])->assertCanNotSeeTableRecords([$otra]);

    Livewire::withQueryParams(['ejecutivo' => (string) $ana->id])->test(ListarCuentas::class)
        ->assertCanSeeTableRecords([$deAna])->assertCanNotSeeTableRecords([$otra]);
});

test('el buscador encuentra por razón social sin tildes o por RUC, activas o no', function () {
    entrarEnCuentas();
    $andes = cuentaEnPantalla('Constructora Los Andes S.A.C.', '2060000108');
    $otra = cuentaEnPantalla('Molinos del Norte S.A.C.', '2060000109');

    Livewire::withQueryParams([])->test(ListarCuentas::class)->searchTable('ANDÉS')
        ->assertCanSeeTableRecords([$andes])->assertCanNotSeeTableRecords([$otra]);
    Livewire::withQueryParams([])->test(ListarCuentas::class)->searchTable(rucDeCuentaInventado('2060000109'))
        ->assertCanSeeTableRecords([$otra])->assertCanNotSeeTableRecords([$andes]);
});

test('últimos cambios: quién creó qué cuenta, con enlace a ella', function () {
    entrarEnCuentas();
    $cuenta = cuentaEnPantalla('Comercial Reciente S.A.C.', '2060000110');

    Livewire::withQueryParams([])->test(ListarCuentas::class)
        ->assertSee('Últimos cambios')
        ->assertSee('Comercial Reciente S.A.C.')
        ->assertSeeHtml('href="'.e(VerCuenta::getUrl(['record' => $cuenta->id])).'"');
});

test('reasignar en bloque pasa las cuentas marcadas al nuevo ejecutivo, todo o nada', function () {
    entrarEnCuentas('crm.cuentas.reasignar');
    $nuevo = User::factory()->create(['name' => 'Nora Nueva Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $una = cuentaEnPantalla('Una S.A.C.', '2060000111');
    $dos = cuentaEnPantalla('Dos S.A.C.', '2060000112');

    Livewire::withQueryParams([])->test(ListarCuentas::class)
        ->callTableBulkAction('reasignar', [$una, $dos], ['ejecutivo_user_id' => $nuevo->id, 'incluir_oportunidades_abiertas' => true])
        ->assertNotified('Se reasignaron 2 cuentas.');

    expect(Cuenta::query()->where('ejecutivo_user_id', $nuevo->id)->count())->toBe(2);
});

test('sin ver cuentas no se entra; con el permiso, la lista abre y ofrece «Nueva cuenta» solo a quien crea', function () {
    $this->actingAs(User::factory()->create())->get('/cuentas')->assertForbidden();

    entrarEnCuentas();
    $this->get('/cuentas')->assertOk();
    Livewire::withQueryParams([])->test(ListarCuentas::class)->assertActionHidden('nueva');

    entrarEnCuentas('crm.cuentas.crear');
    Livewire::withQueryParams([])->test(ListarCuentas::class)->assertActionVisible('nueva');
});

// ——— Nueva cuenta en dos pasos ———

test('si el RUC ya es cuenta, lo dice con su ejecutivo y no sigue', function () {
    entrarEnCuentas('crm.cuentas.crear');
    cuentaEnPantalla('Constructora Existente S.A.C.', '2060000113');

    Livewire::test(NuevaCuenta::class)
        ->fillForm(['ruc' => rucDeCuentaInventado('2060000113')])
        ->goToNextWizardStep()
        ->assertNotDispatched('next-wizard-step')
        ->assertSee('Esta cuenta ya existe')
        ->assertSee('que atiende Marco Ejecutivo Prueba');
});

test('si la ficha está inactiva, pide revisarla y reactivarla en Personas y empresas', function () {
    entrarEnCuentas('crm.cuentas.crear');
    Party::factory()->juridica()->create(['numero_documento' => rucDeCuentaInventado('2060000114'), 'es_activo' => false]);

    Livewire::test(NuevaCuenta::class)
        ->fillForm(['ruc' => rucDeCuentaInventado('2060000114')])
        ->goToNextWizardStep()
        ->assertNotDispatched('next-wizard-step')
        ->assertSee('Esta ficha está inactiva');
});

test('un RUC con el dígito verificador mal se marca en el primer paso', function () {
    entrarEnCuentas('crm.cuentas.crear');
    $bueno = rucDeCuentaInventado('2060000115');

    Livewire::test(NuevaCuenta::class)
        ->fillForm(['ruc' => substr($bueno, 0, 10).(((int) substr($bueno, -1) + 1) % 10)])
        ->goToNextWizardStep()
        ->assertHasErrors(['data.ruc']);
});

test('sin ficha: muestra el RUC en grande, pide confirmarlo, avisa nombres parecidos y crea ficha y cuenta', function () {
    $yo = entrarEnCuentas('crm.cuentas.crear')->givePermissionTo(CuentasService::PERMISO_ATENDER);
    Party::factory()->juridica()->create(['razon_social' => 'Ferretería Inventada del Norte S.A.C.']);
    $ruc = rucDeCuentaInventado('2060000116');

    $pantalla = Livewire::test(NuevaCuenta::class)
        ->assertSet('data.ejecutivo_user_id', $yo->id)
        ->fillForm(['ruc' => $ruc])
        ->goToNextWizardStep()
        ->assertDispatched('next-wizard-step')
        ->assertSee($ruc)
        ->fillForm(['razon_social' => 'Ferreteria Inventada del Nortee', 'origen_comercial_id' => OrigenComercial::query()->value('id')])
        ->assertSee('Se parece a otras empresas registradas');

    $pantalla->call('crear')->assertHasErrors(['data.ruc_confirmado']);
    expect(Cuenta::query()->count())->toBe(0);

    $pantalla->fillForm(['ruc_confirmado' => true])->call('crear')->assertNotified('Cuenta creada.');

    $cuenta = Cuenta::query()->sole();
    expect($cuenta->party->razon_social)->toBe('Ferreteria Inventada del Nortee')
        ->and($cuenta->ejecutivo_user_id)->toBe($yo->id);
    $pantalla->assertRedirect(VerCuenta::getUrl(['record' => $cuenta->id]));
});

test('con ficha sin cuenta: la muestra, no pide su razón social y, si ya es entidad pública, pide el nivel', function () {
    entrarEnCuentas('crm.cuentas.crear');
    $ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $ficha = Party::factory()->juridica()->create(['numero_documento' => rucDeCuentaInventado('2060000117'), 'razon_social' => 'Municipalidad Inventada']);
    app(PartiesService::class)->asignarRol($ficha->id, 'entidad', CarbonImmutable::parse('2026-01-01'));

    Livewire::withQueryParams(['ruc' => $ficha->numero_documento])->test(NuevaCuenta::class)
        ->assertSet('data.ruc', $ficha->numero_documento)
        ->goToNextWizardStep()
        ->assertSee('Empresa: Municipalidad Inventada')
        ->assertSet('data.es_publica', true)
        ->fillForm(['ejecutivo_user_id' => $ejecutivo->id, 'origen_comercial_id' => OrigenComercial::query()->value('id'), 'nivel_gobierno_id' => NivelGobierno::query()->value('id')])
        ->call('crear')
        ->assertNotified('Cuenta creada.');

    expect(Cuenta::query()->where('party_id', $ficha->id)->value('nivel_gobierno_id'))->not->toBeNull();
});

// ——— La ficha de la cuenta ———

test('la ficha muestra la cuenta y sus botones según permiso', function () {
    $cuenta = cuentaEnPantalla('Constructora Ficha S.A.C.', '2060000118');

    entrarEnCuentas();
    Livewire::test(VerCuenta::class, ['record' => $cuenta->id])
        ->assertSee('Constructora Ficha S.A.C.')
        ->assertSee('Prospecto')
        ->assertSee('Privada')
        ->assertSee('Marco Ejecutivo Prueba')
        ->assertActionHidden('crmCuentasEditar')
        ->assertActionHidden('crmCuentasReasignar');

    entrarEnCuentas('crm.cuentas.editar', 'crm.cuentas.reasignar');
    Livewire::test(VerCuenta::class, ['record' => $cuenta->id])
        ->assertActionVisible('crmCuentasEditar')
        ->assertActionVisible('crmCuentasReasignar');
});

test('editar desde la ficha guarda con la versión leída al abrir', function () {
    $cuenta = cuentaEnPantalla('Constructora Editable S.A.C.', '2060000119');
    entrarEnCuentas('crm.cuentas.editar');
    // Uno de la lista: «Otro» (plan 2.0, D5) pediría además cuál es.
    $origen = OrigenComercial::query()->where('codigo', '!=', 'OTRO')->orderByDesc('id')->value('id');

    Livewire::test(VerCuenta::class, ['record' => $cuenta->id])
        ->callAction('crmCuentasEditar', ['origen_comercial_id' => $origen, 'notas' => 'Llamar en diciembre'])
        ->assertNotified('Guardado.');

    expect($cuenta->refresh()->notas)->toBe('Llamar en diciembre')
        ->and($cuenta->origen_comercial_id)->toBe($origen)
        ->and($cuenta->version)->toBe(2);
});

test('desactivar desde la ficha pide un motivo de su lista', function () {
    $cuenta = cuentaEnPantalla('Constructora Cerrada S.A.C.', '2060000120');
    entrarEnCuentas('crm.cuentas.desactivar');

    Livewire::test(VerCuenta::class, ['record' => $cuenta->id])
        ->callAction('crmCuentasDesactivar', ['motivo_id' => motivoDeDesactivarEnPantalla()])
        ->assertNotified('Cuenta desactivada.');

    expect($cuenta->refresh()->es_activa)->toBeFalse();
});

test('desactivar usa el selector común de motivos: el detalle aparece y se exige solo con «Otro» (plan 2.21, D7.3b)', function () {
    $cuenta = cuentaEnPantalla('Constructora Otro Motivo S.A.C.', '2060000122');
    entrarEnCuentas('crm.cuentas.desactivar');
    $otro = (int) Motivo::query()->where('es_otro', true)
        ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR))->value('id');

    Livewire::test(VerCuenta::class, ['record' => $cuenta->id])
        ->mountAction('crmCuentasDesactivar')
        ->fillForm(['motivo_id' => motivoDeDesactivarEnPantalla()])
        ->assertFormFieldHidden('detalle')
        ->fillForm(['motivo_id' => $otro])
        ->assertFormFieldVisible('detalle')
        ->callMountedAction()
        ->assertHasFormErrors(['detalle' => 'required']);

    expect($cuenta->refresh()->es_activa)->toBeTrue();
});

test('el historial cuenta la creación de la cuenta con nombres legibles', function () {
    $cuenta = cuentaEnPantalla('Constructora Historia S.A.C.', '2060000121');
    entrarEnCuentas();

    Livewire::test(HistorialDeCuenta::class, ['registroId' => $cuenta->id])
        ->assertSee('Cuenta')
        ->assertSee('Marco Ejecutivo Prueba');
});

// ——— Pestaña «Cuenta comercial» en Personas y empresas ———

test('la pestaña se suma solo en fichas con RUC y pide ver cuentas', function () {
    $pestana = collect(app(RegistroPestanasFicha::class)->todas())->firstWhere('clave', 'cuenta-comercial');
    $ficha = Party::factory()->juridica()->create();

    expect($pestana->aplicaA(Catalogo::RUC))->toBeTrue()
        ->and($pestana->aplicaA(Catalogo::DNI))->toBeFalse()
        ->and($pestana->permiso)->toBe(CuentasServiceProvider::PERMISO_VER);

    $this->actingAs(User::factory()->create()->givePermissionTo('parties.ver'));
    Livewire::test(CuentaComercialDeFicha::class, ['partyId' => $ficha->id])->assertForbidden();
});

test('con cuenta, dice quién la atiende y la abre; sin cuenta, ofrece crearla con el RUC puesto', function () {
    $cuenta = cuentaEnPantalla('Constructora Con Cuenta S.A.C.', '2060000122');
    $sinCuenta = Party::factory()->juridica()->create(['numero_documento' => rucDeCuentaInventado('2060000123')]);
    TipoDocumentoIdentidad::query()->where('codigo', Catalogo::RUC)->firstOrFail();
    entrarEnCuentas('parties.ver', 'crm.cuentas.crear');

    Livewire::test(CuentaComercialDeFicha::class, ['partyId' => $cuenta->party_id])
        ->assertSee('Es cuenta del CRM')
        ->assertSee('Marco Ejecutivo Prueba')
        ->assertSee('Prospecto')
        ->assertSeeHtml('href="'.e(VerCuenta::getUrl(['record' => $cuenta->id])).'"');

    Livewire::test(CuentaComercialDeFicha::class, ['partyId' => $sinCuenta->id])
        ->assertSee('Esta empresa no es cuenta del CRM')
        ->assertSeeHtml(e(NuevaCuenta::getUrl(['ruc' => $sinCuenta->numero_documento])));
});
