<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\ComponentePestanaFicha;
use App\Modules\Nucleo\Parties\PestanaFicha;
use App\Modules\Nucleo\Parties\RegistroPestanasFicha;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4m, D5: otros módulos suman pestañas a la ficha de una persona o empresa, cada una con su permiso, revisado
 * también en cada petición del componente.
 */

final class PestanaFichaDePrueba extends ComponentePestanaFicha
{
    public function render(): string
    {
        return Blade::render('<div>Oportunidades de la ficha {{ $id }}</div>', ['id' => $this->partyId]);
    }
}

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    app()->instance(RegistroPestanasFicha::class, new RegistroPestanasFicha);
    Permission::findOrCreate('oportunidades.ver', 'web');
    app(RegistroPestanasFicha::class)->registrar(new PestanaFicha('oportunidades-prueba', 'Oportunidades de prueba', 'oportunidades.ver', PestanaFichaDePrueba::class, 'heroicon-o-presentation-chart-line'));
});

test('la pestaña de otro módulo sale en la ficha con su permiso, y no sin él', function () {
    $ficha = Party::factory()->juridica()->create();
    $conPermiso = User::factory()->create()->givePermissionTo(['parties.ver', 'oportunidades.ver']);
    $sinPermiso = User::factory()->create()->givePermissionTo('parties.ver');

    test()->actingAs($conPermiso)->get(VerParty::getUrl(['record' => $ficha]))->assertOk()
        ->assertSee('Oportunidades de prueba')
        ->assertSee("Oportunidades de la ficha {$ficha->id}");
    test()->actingAs($sinPermiso)->get(VerParty::getUrl(['record' => $ficha]))->assertOk()
        ->assertDontSee('Oportunidades de prueba');
});

test('sin permiso, el componente de la pestaña no responde aunque se lo pida directo', function () {
    $ficha = Party::factory()->juridica()->create();

    Livewire::actingAs(User::factory()->create()->givePermissionTo('parties.ver'))
        ->test('ficha.oportunidades-prueba', ['partyId' => $ficha->id])
        ->assertStatus(403);
});

test('clave repetida, mal formada o un componente que no es de pestaña, es un error', function () {
    $registro = app(RegistroPestanasFicha::class);

    expect(fn () => $registro->registrar(new PestanaFicha('oportunidades-prueba', 'Otra', null, PestanaFichaDePrueba::class, 'heroicon-o-star')))->toThrow(InvalidArgumentException::class, 'ya está registrada')
        ->and(fn () => new PestanaFicha('Mal Clave', 'X', null, PestanaFichaDePrueba::class, 'heroicon-o-star'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PestanaFicha('ajena', 'X', null, stdClass::class, 'heroicon-o-star'))->toThrow(InvalidArgumentException::class, 'ComponentePestanaFicha');
});

test('la pestaña de los tipos de la ficha se llama «Tipos», no «Roles»', function () {
    $ficha = Party::factory()->juridica()->create();

    $html = (string) test()->actingAs(User::factory()->create()->givePermissionTo('parties.ver'))->get(VerParty::getUrl(['record' => $ficha]))->getContent();

    // El texto de la pestaña misma, no cualquier «Tipos» de la página.
    expect($html)->toMatch('/<span class="fi-tabs-item-label">\s*Tipos\s*<\/span>/')
        ->not->toMatch('/<span class="fi-tabs-item-label">\s*Roles\s*<\/span>/');
});

test('una pestaña con tipos de documento sale solo en esas fichas (plan 2.20a, N6)', function () {
    app(RegistroPestanasFicha::class)->registrar(new PestanaFicha('solo-ruc-prueba', 'Solo con RUC', null, PestanaFichaDePrueba::class, 'heroicon-o-star', tiposDocumento: [Catalogo::RUC]));
    $usuario = User::factory()->create()->givePermissionTo('parties.ver');

    test()->actingAs($usuario)->get(VerParty::getUrl(['record' => Party::factory()->juridica()->create()]))->assertOk()
        ->assertSee('Solo con RUC');
    test()->actingAs($usuario)->get(VerParty::getUrl(['record' => Party::factory()->create()]))->assertOk()
        ->assertDontSee('Solo con RUC');
});

test('sin tipos de documento sale en todas; una lista vacía o con códigos en blanco es un error', function () {
    $todas = new PestanaFicha('en-todas', 'En todas', null, PestanaFichaDePrueba::class, 'heroicon-o-star');

    expect($todas->aplicaA(Catalogo::DNI))->toBeTrue()
        ->and($todas->aplicaA(Catalogo::RUC))->toBeTrue()
        ->and((new PestanaFicha('solo-ruc', 'X', null, PestanaFichaDePrueba::class, 'heroicon-o-star', tiposDocumento: [Catalogo::RUC]))->aplicaA(Catalogo::DNI))->toBeFalse()
        ->and(fn () => new PestanaFicha('vacia', 'X', null, PestanaFichaDePrueba::class, 'heroicon-o-star', tiposDocumento: []))->toThrow(InvalidArgumentException::class, 'tipo de documento')
        ->and(fn () => new PestanaFicha('blanco', 'X', null, PestanaFichaDePrueba::class, 'heroicon-o-star', tiposDocumento: ['']))->toThrow(InvalidArgumentException::class, 'tipo de documento');
});
