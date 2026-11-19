<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Parties\HistorialDeParty;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Historial\FilaHistorial;
use App\Modules\Nucleo\Historial\HistorialLegible;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Parties\DatosCuentaBancaria;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DatosSede;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\CuentasBancariasService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SedesPartyService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * La pestaña Historial de la ficha con contactos, sedes y cuentas (plan 1.3b, paso 6): una sola línea de tiempo,
 * también con lo ya borrado; las cuentas solo para quien puede verlas, filtradas en el servidor; y al día después
 * de cada cambio en una pestaña, sin recargar.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function verFichaCon(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['parties.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Una empresa con un contacto, una sede y una cuenta, y el banco con su rol. */
function fichaCompleta(): Party
{
    $party = Party::factory()->juridica()->create();
    $banco = Party::factory()->juridica()->create(['razon_social' => 'Banco Ejemplo S.A.']);
    app(PartiesService::class)->asignarRol($banco->id, 'banco', CarbonImmutable::parse('2026-01-01'));

    app(ContactosPartyService::class)->agregar($party->id, new DatosContacto('Luis', 'Fernández', cargo: 'Jefe de Logística', esPrincipal: true));
    app(SedesPartyService::class)->agregar($party->id, new DatosSede('Almacén Chiclayo', 'Av. Principal 123'));
    app(CuentasBancariasService::class)->registrar($party->id, new DatosCuentaBancaria($banco->id, '191-1234567-0-12', 'PEN', cci: '00219100123456701255'));

    return $party;
}

test('el historial de la ficha muestra lo hecho en contactos, sedes y cuentas', function () {
    $party = fichaCompleta();
    $sede = $party->sedes()->sole();
    app(SedesPartyService::class)->editar($sede->id, new DatosSede('Almacén Chiclayo', 'Calle Nueva 456'), 1);

    verFichaCon('campos.ver-datos_bancarios');

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('Agregó contacto Luis Fernández · Jefe de Logística · principal')
        ->assertSee('Agregó sede Almacén Chiclayo · Av. Principal 123')
        ->assertSee('Sede Almacén Chiclayo')->assertSee('Calle Nueva 456')
        ->assertSee('Registró cuenta Banco Ejemplo S.A. 1911234567012 en PEN · CCI 00219100123456701255');
});

test('sin campos.ver-datos_bancarios, el historial no trae ni sus números ni su CCI', function () {
    $party = fichaCompleta();
    $cuenta = $party->cuentasBancarias()->sole();
    app(CuentasBancariasService::class)->desactivar($cuenta->id, 1);

    verFichaCon();

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('Agregó contacto Luis Fernández')
        ->assertDontSee('1911234567012')
        ->assertDontSee('00219100123456701255')
        ->assertDontSee('Registró cuenta')
        ->assertDontSee('Banco Ejemplo');

    // Filtrado en el servidor: las cuentas ni siquiera se leen para el historial.
    expect(collect(PartyResource::modelosDelHistorial($party))->filter(fn ($m): bool => $m instanceof PartyCuentaBancaria))->toBeEmpty();
});

test('segunda barrera: aunque la cuenta llegue al historial, sin el permiso no salen ni su número ni su CCI', function () {
    $cuenta = fichaCompleta()->cuentasBancarias()->with(['banco', 'moneda'])->sole();
    $textos = fn (User $usuario): string => collect(app(HistorialLegible::class)->filas($usuario, [$cuenta]))
        ->map(fn (FilaHistorial $f): string => implode(' | ', [$f->sujeto, $f->campo, $f->anterior, $f->nuevo, (string) $f->resumen]))
        ->implode("\n");

    $sinPermiso = $textos(verFichaCon());
    $conPermiso = $textos(verFichaCon('campos.ver-datos_bancarios'));

    expect($sinPermiso)->toContain('Cuenta Banco Ejemplo S.A. en PEN')
        ->and($sinPermiso)->not->toContain('1911234567012')
        ->and($sinPermiso)->not->toContain('00219100123456701255')
        ->and($sinPermiso)->not->toContain('Número de cuenta')
        ->and($conPermiso)->toContain('Registró cuenta Banco Ejemplo S.A. 1911234567012 en PEN · CCI 00219100123456701255');
});

test('lo borrado sigue en el historial de la ficha', function () {
    $party = Party::factory()->juridica()->create();
    $id = app(ContactosPartyService::class)->agregar($party->id, new DatosContacto('Carmen', 'Ruiz', telefono: '944 555 871'));
    app(ContactosPartyService::class)->borrar($id, 1);

    verFichaCon();

    expect(PartyContacto::query()->find($id))->toBeNull();

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('Agregó contacto Carmen Ruiz')
        ->assertSee('Borró · Contacto Carmen Ruiz')
        ->assertSee('944 555 871');
});

test('una cuenta borrada tampoco aparece en el historial de quien no ve cuentas', function () {
    $party = fichaCompleta();
    $cuenta = $party->cuentasBancarias()->sole();
    app(CuentasBancariasService::class)->borrar($cuenta->id, 1);

    verFichaCon();
    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])->assertDontSee('1911234567012');

    verFichaCon('campos.ver-datos_bancarios');
    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('Borró · Cuenta Banco Ejemplo S.A. en PEN')
        ->assertSee('1911234567012');
});

test('después de un cambio en una pestaña, el historial se pone al día sin recargar', function () {
    $party = Party::factory()->juridica()->create();
    verFichaCon();

    $historial = Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])->assertDontSee('Agregó contacto Ana Soto');
    $ficha = Livewire::test(VerParty::class, ['record' => $party->id])->assertDontSee('Sin contacto principal');

    app(ContactosPartyService::class)->agregar($party->id, new DatosContacto('Ana', 'Soto'));

    $historial->dispatch('ficha-party-cambiada')->assertSee('Agregó contacto Ana Soto');
    $ficha->dispatch('ficha-party-cambiada')->assertSee('Sin contacto principal');
});

test('la tabla del historial es una tabla de Filament: frase en «Qué» para un alta, campo por campo para un cambio', function () {
    $party = Party::factory()->juridica()->create(['telefono' => null]);
    app(PartiesService::class)->editar($party->id, ['telefono' => '074 123456'], 1);
    verFichaCon();

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('fi-ta-table', false)
        ->assertSee('Cambió · Datos')->assertSee('Teléfono')->assertSee('074 123456');
});

test('sin cambios, la tabla del historial lo dice', function () {
    verFichaCon();
    $party = Party::factory()->juridica()->create();
    DB::table('auditoria_cambios')->delete();

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])->assertSee('Todavía no hay cambios registrados.');
});

test('la tabla del historial no abre sin permiso ni se cambia de ficha', function () {
    $party = Party::factory()->juridica()->create();
    $otra = Party::factory()->juridica()->create();
    test()->actingAs(User::factory()->create());

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])->assertForbidden();

    verFichaCon();
    expect(fn () => Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])->set('registroId', $otra->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('el alta de la party es una sola fila resumida, como los roles', function () {
    $party = Party::query()->findOrFail(app(PartiesService::class)->registrar(new DatosParty('1', '01234567', nombres: 'Ana', apellidos: 'Pérez Soto'))->id);
    verFichaCon();

    Livewire::test(HistorialDeParty::class, ['registroId' => $party->id])
        ->assertSee('Registró persona natural · DNI 01234567 · Pérez Soto Ana')
        ->assertDontSee('Creó · Datos');
});
