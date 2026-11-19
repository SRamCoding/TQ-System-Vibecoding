<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Plan 1.3, D11: la búsqueda sin mayúsculas ni acentos es un scope del núcleo, y Catálogos lo usa. D6:
 * PartiesService::buscar() para los selectores, por número o por nombre, con límite.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function buscarParties(string $texto, ?string $rol = null, int $limite = 20): array
{
    return array_map(fn ($r): string => $r->etiqueta, app(PartiesService::class)->buscar($texto, $rol, $limite));
}

function registrarEmpresa(string $ruc, string $razonSocial, ?string $nombreComercial = null): int
{
    return app(PartiesService::class)->registrar(new DatosParty('6', $ruc, razonSocial: $razonSocial, nombreComercial: $nombreComercial))->id;
}

// ——— El scope ———

test('el scope encuentra sin importar mayúsculas ni acentos', function (string $texto) {
    Party::factory()->juridica()->create(['razon_social' => 'Municipalidad Distrital de Túcume']);
    Party::factory()->juridica()->create(['razon_social' => 'Hospital Regional']);

    expect(Party::query()->buscarTexto($texto, ['razon_social'])->pluck('razon_social')->all())
        ->toBe(['Municipalidad Distrital de Túcume']);
})->with(['tucume', 'TÚCUME', 'distrital de tú']);

test('los comodines que escribe la persona se buscan tal cual', function () {
    Party::factory()->juridica()->create(['razon_social' => 'Descuento 100% S.A.']);
    Party::factory()->juridica()->create(['razon_social' => 'Descuento 1000 S.A.']);
    Party::factory()->juridica()->create(['razon_social' => 'Mi_Empresa S.A.']);
    Party::factory()->juridica()->create(['razon_social' => 'MiXEmpresa S.A.']);

    expect(Party::query()->buscarTexto('100%', ['razon_social'])->pluck('razon_social')->all())->toBe(['Descuento 100% S.A.'])
        ->and(Party::query()->buscarTexto('mi_e', ['razon_social'])->pluck('razon_social')->all())->toBe(['Mi_Empresa S.A.']);
});

test('un texto vacío no filtra', function () {
    Party::factory()->juridica()->count(2)->create();

    expect(Party::query()->buscarTexto('  ', ['razon_social'])->count())->toBe(2);
});

test('el buscador de Catálogos usa el scope: sin acentos y también por código', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.ver'));
    Filament::setCurrentPanel('app');
    $credito = FormaPago::query()->where('codigo', 'CREDITO_30')->firstOrFail();
    $contado = FormaPago::query()->where('codigo', 'CONTADO')->firstOrFail();
    $tucume = Ubigeo::factory()->create(['codigo' => '140117', 'distrito' => 'TÚCUME']);

    Livewire::withQueryParams(['catalogo' => 'formas_pago'])->test(Catalogos::class)
        ->searchTable('credito a 30')
        ->assertCanSeeTableRecords([$credito])
        ->assertCanNotSeeTableRecords([$contado])
        ->searchTable('contado')
        ->assertCanSeeTableRecords([$contado]);

    Livewire::withQueryParams(['catalogo' => 'ubigeos'])->test(Catalogos::class)
        ->searchTable('tucume')
        ->assertCanSeeTableRecords([$tucume]);
});

// ——— PartiesService::buscar ———

test('busca por el comienzo del número, escrito como sea', function () {
    registrarEmpresa('20123456786', 'Comercial Ejemplo S.A.C.');
    registrarEmpresa('20555555556', 'Otra Empresa S.A.');

    expect(buscarParties('20-1234'))->toBe(['Comercial Ejemplo S.A.C. · 20123456786'])
        ->and(buscarParties('2055'))->toBe(['Otra Empresa S.A. · 20555555556'])
        ->and(buscarParties('3456786'))->toBe([]);
});

test('busca por razón social o nombre comercial sin acentos', function () {
    registrarEmpresa('20123456786', 'Municipalidad Distrital de Túcume');
    registrarEmpresa('20555555556', 'Inversiones Norte S.A.C.', 'Ferretería El Clavo');

    expect(buscarParties('tucume'))->toBe(['Municipalidad Distrital de Túcume · 20123456786'])
        ->and(buscarParties('FERRETERIA'))->toBe(['Inversiones Norte S.A.C. · 20555555556']);
});

test('solo devuelve activas, y con rol solo las que lo tienen vigente hoy', function () {
    test()->travelTo('2026-10-15 10:00');
    $activa = registrarEmpresa('20123456786', 'Proveedora Uno S.A.');
    $inactiva = registrarEmpresa('20555555556', 'Proveedora Dos S.A.');
    $cerrada = registrarEmpresa('20000003001', 'Proveedora Tres S.A.');
    $parties = app(PartiesService::class);
    $parties->asignarRol($activa, 'proveedor', now()->toImmutable()->subYear());
    $parties->asignarRol($cerrada, 'proveedor', now()->toImmutable()->subYear());
    $parties->cerrarRol($cerrada, 'proveedor', now()->toImmutable()->subDay(), 1);
    $parties->desactivar($inactiva, 1);

    expect(buscarParties('proveedora'))->toBe(['Proveedora Tres S.A. · 20000003001', 'Proveedora Uno S.A. · 20123456786'])
        ->and(buscarParties('proveedora', 'proveedor'))->toBe(['Proveedora Uno S.A. · 20123456786'])
        ->and(buscarParties('proveedora', 'cliente'))->toBe([]);
});

test('el número exacto sale primero; los demás, por razón social', function () {
    registrarEmpresa('20123456786', 'Zeta S.A.');
    registrarEmpresa('20555555556', 'Alfa 20123456786 S.A.');

    expect(buscarParties('20123456786'))->toBe(['Zeta S.A. · 20123456786', 'Alfa 20123456786 S.A. · 20555555556']);
});

test('menos de dos caracteres no busca; el límite se respeta y nunca pasa de 50', function () {
    foreach (range(1, 55) as $i) {
        Party::factory()->juridica()->create(['razon_social' => sprintf('Empresa %02d S.A.', $i)]);
    }

    expect(buscarParties('e'))->toBe([])
        ->and(buscarParties('empresa', limite: 5))->toHaveCount(5)
        ->and(buscarParties('empresa', limite: 500))->toHaveCount(50)
        ->and(buscarParties('empresa', limite: 5)[0])->toStartWith('Empresa 01 S.A.');
});
