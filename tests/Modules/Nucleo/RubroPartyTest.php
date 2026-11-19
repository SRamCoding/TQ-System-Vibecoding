<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Plan 2.20a, N4: el rubro de la empresa (una sección de la CIIU) vive en la ficha y se cambia con su propia acción,
 * con su propio permiso: quien corrige el rubro no necesita poder editar la razón social. Nombres inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function rubroDeLaSeccion(string $letra): int
{
    return (int) Rubro::query()->where('codigo', $letra)->value('id');
}

function fichaConRubro(?int $rubroId = null): Party
{
    $registro = app(PartiesService::class)->registrar(new DatosParty('6', '20123456786', razonSocial: 'Clínica Inventada S.A.C.', rubroId: $rubroId));

    return Party::query()->findOrFail($registro->id);
}

function cambiarRubro(Party $ficha, ?int $rubroId, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.parties.cambiar-rubro', ['party_id' => $ficha->id, 'rubro_id' => $rubroId, 'version' => $ficha->version], $usuario);
}

test('al registrar se puede dar el rubro, y queda en la ficha', function () {
    expect(fichaConRubro(rubroDeLaSeccion('Q'))->rubro?->nombre)->toBe('Salud y asistencia social')
        ->and(Party::query()->where('numero_documento', '20123456786')->value('rubro_id'))->toBe(rubroDeLaSeccion('Q'));
});

test('con su permiso se cambia y se quita el rubro, con versión nueva y en la bitácora', function () {
    $ficha = fichaConRubro();
    $usuario = User::factory()->create()->givePermissionTo('parties.cambiar-rubro');

    cambiarRubro($ficha, rubroDeLaSeccion('P'), $usuario);
    $ficha->refresh();

    expect($ficha->rubro_id)->toBe(rubroDeLaSeccion('P'))
        ->and($ficha->version)->toBe(2);

    $cambio = AuditoriaCambio::query()->where('auditable_type', 'party')->where('auditable_id', $ficha->id)->latest('id')->firstOrFail();
    expect($ficha->etiquetasBitacora()['rubro_id'])->toBe('Rubro')
        ->and($ficha->valorLegibleBitacora('rubro_id', rubroDeLaSeccion('P')))->toBe('Enseñanza')
        ->and(json_encode($cambio->getAttributes(), JSON_UNESCAPED_UNICODE))->toContain('rubro_id');

    cambiarRubro($ficha, null, $usuario);

    expect($ficha->refresh()->rubro_id)->toBeNull();
});

test('editar la ficha no basta para cambiar el rubro, ni el rubro deja editar lo demás', function () {
    $ficha = fichaConRubro();

    expect(fn () => cambiarRubro($ficha, rubroDeLaSeccion('P'), User::factory()->create()->givePermissionTo('parties.editar')))
        ->toThrow(AccionRechazada::class);

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.parties.cambiar-rubro', ['party_id' => $ficha->id, 'rubro_id' => null, 'razon_social' => 'Otra', 'version' => 1], User::factory()->create()->givePermissionTo('parties.cambiar-rubro')))
        ->not->toThrow(Throwable::class);

    expect($ficha->refresh()->razon_social)->toBe('Clínica Inventada S.A.C.');
});

test('la versión leída al abrir protege del cambio de otra persona', function () {
    $ficha = fichaConRubro();
    app(PartiesService::class)->cambiarRubro($ficha->id, rubroDeLaSeccion('A'), 1);

    expect(fn () => app(PartiesService::class)->cambiarRubro($ficha->id, rubroDeLaSeccion('B'), 1))->toThrow(VersionDesactualizada::class);
});

test('un rubro desactivado no se elige, pero el que ya tiene la ficha se conserva', function () {
    $ficha = fichaConRubro(rubroDeLaSeccion('U'));
    Rubro::query()->where('codigo', 'U')->update(['es_activo' => false]);

    expect(fn () => app(PartiesService::class)->registrar(new DatosParty('6', '20100070970', razonSocial: 'Otra Inventada S.A.', rubroId: rubroDeLaSeccion('U'))))
        ->toThrow(PartyRechazada::class, 'rubro');

    app(PartiesService::class)->cambiarRubro($ficha->id, rubroDeLaSeccion('U'), 1);

    expect($ficha->refresh()->rubro_id)->toBe(rubroDeLaSeccion('U'));
});

test('la ficha muestra el rubro y ofrece cambiarlo a quien tiene el permiso', function () {
    Filament::setCurrentPanel('app');
    $ficha = fichaConRubro(rubroDeLaSeccion('Q'));
    $usuario = User::factory()->create()->givePermissionTo(['parties.ver', 'parties.cambiar-rubro']);

    Livewire::actingAs($usuario)->test(VerParty::class, ['record' => $ficha->id])
        ->assertSee('Q · Salud y asistencia social')
        ->assertActionVisible('nucleoPartiesCambiarRubro');
});
