<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Plan 1.4m: la portada de Personas y empresas. Pestañas por tipo con su cantidad (D1), avisos que filtran la tabla en
 * el sitio (D2 y D3) y últimos cambios (D4).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function quienVeFichas(): User
{
    $usuario = User::factory()->create()->givePermissionTo('parties.ver');
    test()->actingAs($usuario);

    return $usuario;
}

/** Una ficha del tipo dado, completa salvo lo que pida ese tipo. */
function fichaDeTipo(string $tipo, string $nombre): Party
{
    $ficha = Party::factory()->juridica()->create([
        'razon_social' => $nombre, 'telefono' => '074 111222',
        'direccion_fiscal' => 'Av. Ejemplo 1', 'ubigeo_id' => Ubigeo::factory()->create()->id,
    ]);
    app(PartiesService::class)->asignarRol($ficha->id, $tipo, CarbonImmutable::parse('2026-01-01'));

    return $ficha;
}

function portadaDeFichas(): Testable
{
    return Livewire::withQueryParams([])->actingAs(quienVeFichas())->test(ListarParties::class);
}

test('una pestaña «Todos» y una por cada tipo activo, con su cantidad; un tipo nuevo suma la suya', function () {
    fichaDeTipo('proveedor', 'Ferretería Uno S.A.C.');
    fichaDeTipo('proveedor', 'Ferretería Dos S.A.C.');
    fichaDeTipo('cliente', 'Constructora Tres S.A.C.');

    $pestanas = portadaDeFichas()->instance()->getTabs();
    expect(array_key_first($pestanas))->toBe('todos')
        ->and($pestanas['proveedor']->getBadge())->toEqual(2)
        ->and($pestanas['cliente']->getBadge())->toEqual(1)
        ->and($pestanas['todos']->getBadge())->toEqual(Party::query()->where('es_activo', true)->count());

    RolParty::query()->create(['codigo' => 'subcontratista', 'nombre' => 'Subcontratista', 'es_activo' => true]);
    RolParty::query()->where('codigo', 'aliado')->update(['es_activo' => false]);
    $pestanas = portadaDeFichas()->instance()->getTabs();

    expect($pestanas)->toHaveKey('subcontratista')
        ->and($pestanas)->not->toHaveKey('aliado');
});

test('una pestaña filtra la tabla en el sitio', function () {
    $proveedor = fichaDeTipo('proveedor', 'Ferretería Uno S.A.C.');
    $cliente = fichaDeTipo('cliente', 'Constructora Tres S.A.C.');

    portadaDeFichas()->set('activeTab', 'proveedor')
        ->assertCanSeeTableRecords([$proveedor])
        ->assertCanNotSeeTableRecords([$cliente]);
});

test('el aviso de incompletas dice cuántas y por qué, y «Ver cuáles» filtra la tabla', function () {
    $sinContacto = fichaDeTipo('cliente', 'Constructora Sin Contacto S.A.C.');
    $completo = fichaDeTipo('cliente', 'Constructora Completa S.A.C.');
    PartyContacto::factory()->create(['party_id' => $completo->id]);

    $portada = portadaDeFichas()
        ->assertSee('1 ficha incompleta')
        ->assertSee('1 clientes sin contacto')
        ->call('verCalidad', 'incompletas')
        ->assertCanSeeTableRecords([$sinContacto])
        ->assertCanNotSeeTableRecords([$completo])
        ->assertSee('Ver todas');

    $portada->call('verCalidad', null)->assertCanSeeTableRecords([$sinContacto, $completo]);
});

test('el aviso de posibles duplicados filtra las parejas sospechosas', function () {
    $una = fichaDeTipo('proveedor', 'Ferretería El Norte S.A.C.');
    $otra = fichaDeTipo('proveedor', 'FERRETERIA EL NORTE SAC');
    $distinta = fichaDeTipo('proveedor', 'Otra Empresa S.A.C.');

    portadaDeFichas()
        ->assertSee('2 fichas podrían estar duplicadas')
        ->call('verCalidad', 'duplicados')
        ->assertCanSeeTableRecords([$una, $otra])
        ->assertCanNotSeeTableRecords([$distinta]);
});

test('sin nada que avisar, no hay avisos', function () {
    portadaDeFichas()->assertDontSee('ficha incompleta')->assertDontSee('fichas incompletas')->assertDontSee('podrían estar duplicadas');
});

test('últimos cambios: quién creó qué ficha, con enlace a ella', function () {
    $usuario = quienVeFichas();
    $registrada = app(PartiesService::class)->registrar(new DatosParty('6', '20123456786', razonSocial: 'Comercial Ejemplo S.A.C.'));

    Livewire::withQueryParams([])->actingAs($usuario)->test(ListarParties::class)
        ->assertSee('Últimos cambios')
        ->assertSee($usuario->name.' creó')
        ->assertSee('Comercial Ejemplo S.A.C.')
        ->assertSeeHtml('href="'.e(VerParty::getUrl(['record' => $registrada->id])).'"');
});

test('valores inventados desde el navegador no rompen nada: muestran todas', function () {
    $una = fichaDeTipo('cliente', 'Constructora Sin Contacto S.A.C.');

    portadaDeFichas()->call('verCalidad', 'inventado')
        ->assertSet('tableFilters.calidad.value', null)
        ->assertCanSeeTableRecords([$una])
        ->set('tableFilters.calidad.value', 'otra-cosa')
        ->assertCanSeeTableRecords([$una]);
});

test('sin permiso para ver fichas, la portada no abre', function () {
    test()->actingAs(User::factory()->create())->get('/personas')->assertForbidden();
});

test('el aviso de cuentas bancarias solo lo ve quien puede ver datos bancarios', function () {
    fichaDeTipo('proveedor', 'Ferretería Sin Cuenta S.A.C.');

    portadaDeFichas()->assertDontSee('proveedores sin cuenta bancaria');

    $conBanco = User::factory()->create()->givePermissionTo(['parties.ver', GrupoSensible::DatosBancarios->permiso()]);
    Livewire::withQueryParams([])->actingAs($conBanco)->test(ListarParties::class)->assertSee('proveedores sin cuenta bancaria');
});
