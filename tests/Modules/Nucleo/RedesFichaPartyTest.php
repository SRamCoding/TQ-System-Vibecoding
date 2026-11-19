<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Filament\Resources\Parties\RedesDeParty;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Plan 1.4o, paso 4: la pestaña Redes de la ficha de una party, con las de la empresa y las de cada contacto. Las de
 * una persona del equipo se ven sin botones y sin las que marcó «solo la veo yo».
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function entrarEnFichaDeRedes(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo(['parties.ver', ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function idRedFicha(string $codigo): int
{
    return RedSocial::query()->where('codigo', $codigo)->value('id');
}

test('la ficha tiene la pestaña Redes', function () {
    entrarEnFichaDeRedes();

    Livewire::test(VerParty::class, ['record' => Party::factory()->juridica()->create()->id])->assertSee('Redes');
});

test('sin el permiso de gestión, las redes se ven con su enlace, pero sin botones', function () {
    entrarEnFichaDeRedes();
    $contacto = PartyContacto::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Fernández Prueba']);
    PartyRedSocial::factory()->create(['party_id' => $contacto->party_id, 'red_social_id' => idRedFicha('facebook'), 'enlace' => 'https://facebook.com/empresa-prueba']);
    PartyRedSocial::factory()->create(['party_id' => $contacto->party_id, 'contacto_id' => $contacto->id, 'red_social_id' => idRedFicha('linkedin'), 'enlace' => 'https://linkedin.com/in/luis-prueba']);

    Livewire::test(RedesDeParty::class, ['partyId' => $contacto->party_id])
        ->assertSee(['Facebook', 'https://facebook.com/empresa-prueba', 'La persona o empresa', 'LinkedIn', 'Luis Fernández Prueba'])
        ->assertActionHidden(TestAction::make('nucleoPartiesAgregarRedSocial')->table());
});

test('agrega la red de la empresa y la de un contacto desde la pestaña', function () {
    entrarEnFichaDeRedes('parties.gestionar-redes');
    $contacto = PartyContacto::factory()->create();

    Livewire::test(RedesDeParty::class, ['partyId' => $contacto->party_id])
        ->callAction(TestAction::make('nucleoPartiesAgregarRedSocial')->table(), ['red_social_id' => idRedFicha('sitio_web'), 'enlace' => 'www.ejemplo.com.pe'])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.')
        ->callAction(TestAction::make('nucleoPartiesAgregarRedSocial')->table(), ['contacto_id' => $contacto->id, 'red_social_id' => idRedFicha('linkedin'), 'enlace' => 'linkedin.com/in/contacto-prueba'])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect(PartyRedSocial::query()->where('party_id', $contacto->party_id)->orderBy('id')->get(['contacto_id', 'enlace'])->toArray())->toBe([
        ['contacto_id' => null, 'enlace' => 'https://www.ejemplo.com.pe'],
        ['contacto_id' => $contacto->id, 'enlace' => 'https://linkedin.com/in/contacto-prueba'],
    ]);
});

test('un enlace de otra red se avisa y no se guarda', function () {
    entrarEnFichaDeRedes('parties.gestionar-redes');
    $empresa = Party::factory()->juridica()->create();

    Livewire::test(RedesDeParty::class, ['partyId' => $empresa->id])
        ->callAction(TestAction::make('nucleoPartiesAgregarRedSocial')->table(), ['red_social_id' => idRedFicha('linkedin'), 'enlace' => 'https://facebook.com/empresa'])
        ->assertNotified('El enlace no es de LinkedIn: debe ser de linkedin.com.');

    expect(PartyRedSocial::query()->where('party_id', $empresa->id)->exists())->toBeFalse();
});

test('cambiar toma la versión al abrir: si otro guarda mientras tanto, se avisa y no se pisa', function () {
    entrarEnFichaDeRedes('parties.gestionar-redes');
    $red = PartyRedSocial::factory()->create(['party_id' => Party::factory()->juridica(), 'red_social_id' => idRedFicha('sitio_web'), 'enlace' => 'https://antes.example.com']);

    $pantalla = Livewire::test(RedesDeParty::class, ['partyId' => $red->party_id])
        ->mountAction(TestAction::make('nucleoPartiesCambiarRedSocial')->table($red))
        ->assertSchemaStateSet(['red_social_id' => $red->red_social_id, 'enlace' => 'https://antes.example.com', 'version' => 1]);

    PartyRedSocial::query()->whereKey($red->id)->update(['enlace' => 'https://otro.example.com', 'version' => 2]);

    $pantalla->fillForm(['enlace' => 'https://despues.example.com'])->callMountedAction();

    expect($red->refresh()->enlace)->toBe('https://otro.example.com');
});

test('quita una red con confirmación', function () {
    entrarEnFichaDeRedes('parties.gestionar-redes');
    $red = PartyRedSocial::factory()->create(['party_id' => Party::factory()->juridica()]);

    Livewire::test(RedesDeParty::class, ['partyId' => $red->party_id])
        ->callAction(TestAction::make('nucleoPartiesQuitarRedSocial')->table($red))
        ->assertNotified('Guardado.');

    expect(PartyRedSocial::query()->whereKey($red->id)->exists())->toBeFalse();
});

test('las redes de una persona del equipo: sin botones aun con permiso, y sin las privadas salvo para ella', function () {
    $persona = User::factory()->create();
    PartyRedSocial::factory()->create(['party_id' => $persona->party_id, 'red_social_id' => idRedFicha('linkedin'), 'enlace' => 'https://linkedin.com/in/persona-prueba']);
    PartyRedSocial::factory()->create(['party_id' => $persona->party_id, 'red_social_id' => idRedFicha('instagram'), 'enlace' => 'https://instagram.com/persona.privada', 'es_privada' => true]);

    entrarEnFichaDeRedes('parties.gestionar-redes');

    Livewire::test(RedesDeParty::class, ['partyId' => $persona->party_id])
        ->assertSee(['https://linkedin.com/in/persona-prueba', 'desde «Mis datos»'])
        ->assertDontSee('https://instagram.com/persona.privada')
        ->assertActionHidden(TestAction::make('nucleoPartiesAgregarRedSocial')->table());

    $persona->givePermissionTo('parties.ver');
    test()->actingAs($persona);

    Livewire::test(RedesDeParty::class, ['partyId' => $persona->party_id])
        ->assertSee(['https://instagram.com/persona.privada', 'Solo la ve la persona']);
});

test('el historial de la ficha incluye las redes; las de una persona del equipo, solo para ella', function () {
    $empresa = Party::factory()->juridica()->create();
    $deEmpresa = PartyRedSocial::factory()->create(['party_id' => $empresa->id]);
    $persona = User::factory()->create();
    $privada = PartyRedSocial::factory()->create(['party_id' => $persona->party_id, 'es_privada' => true]);

    entrarEnFichaDeRedes();

    expect(collect(PartyResource::modelosDelHistorial($empresa))->contains(fn ($m) => $m instanceof PartyRedSocial && $m->is($deEmpresa)))->toBeTrue()
        ->and(collect(PartyResource::modelosDelHistorial($persona->party))->contains(fn ($m) => $m instanceof PartyRedSocial))->toBeFalse();

    test()->actingAs($persona);

    expect(collect(PartyResource::modelosDelHistorial($persona->party))->contains(fn ($m) => $m instanceof PartyRedSocial && $m->is($privada)))->toBeTrue();
});

test('la pestaña no se cambia de party desde el navegador', function () {
    entrarEnFichaDeRedes();
    $empresa = Party::factory()->juridica()->create();

    expect(fn () => Livewire::test(RedesDeParty::class, ['partyId' => $empresa->id])->set('partyId', Party::factory()->juridica()->create()->id))
        ->toThrow(Exception::class);
});
