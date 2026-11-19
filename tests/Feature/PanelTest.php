<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use Filament\Auth\Pages\Login;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('un invitado va al login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('se inicia sesión con correo y clave', function () {
    $usuario = User::factory()->create(['email' => 'ana@techquk.test']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'ana@techquk.test', 'password' => UserFactory::CLAVE])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertAuthenticatedAs($usuario);
});

test('un usuario inactivo no entra', function () {
    $this->actingAs(User::factory()->inactivo()->create())->get('/')->assertForbidden();
});

test('una cuenta nueva entra sin barra lateral, al inicio de cajas, con «Mien» como única caja', function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    $this->seed(NucleoSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        // La barra se dibuja (ya tiene «Mien»), pero apagada: el CSS la esconde desde antes de pintar la página.
        ->assertSee("document.documentElement.classList.add('tq-barra-apagada')", false)
        ->assertSee('data-modulo="mien"', false)
        ->assertDontSee('Aún no hay módulos');
});

test('con la barra de módulos prendida hay barra lateral, y el inicio sigue siendo el de cajas', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));

    $this->actingAs(User::factory()->conBarraModulos()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('fi-sidebar-nav', false)
        ->assertSee('data-modulo="compras"', false);
});

test('cada módulo permitido es una caja que abre en otra pestaña en escritorio', function () {
    app(RegistroModulos::class)->registrar(new CajaModulo('compras', 'Compras', '/compras', permiso: null, area: AreaModulo::Erp));
    app(RegistroModulos::class)->registrar(new CajaModulo('rrhh', 'RRHH', '/rrhh', permiso: 'ver-rrhh', area: AreaModulo::Erp));

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('data-modulo="compras"', false)
        ->assertSee('data-tq-pestana="nueva-en-escritorio"', false)
        ->assertDontSee('data-modulo="rrhh"', false);
});

test('ninguna prueba sale a internet: una llamada sin simular falla', function () {
    expect(fn () => Http::get('https://www.sunat.gob.pe/a/txt/tipoCambio.txt'))->toThrow(StrayRequestException::class);
});

test('la pestaña lleva el ícono de Techquk, y sus archivos son imágenes de verdad', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('favicon.png', false);

    expect(getimagesize(public_path('favicon.png')))->toMatchArray([0 => 192, 1 => 192, 'mime' => 'image/png'])
        ->and(getimagesize(public_path('apple-touch-icon.png')))->toMatchArray([0 => 180, 1 => 180])
        ->and(filesize(public_path('favicon.ico')))->toBeGreaterThan(0);
});
