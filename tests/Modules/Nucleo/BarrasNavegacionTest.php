<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\BordeBarraLateral;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Livewire\Livewire;

/*
 * Plan 1.3d: la barra superior con fondo propio y la barra lateral con el ancho de cada persona. Que el arrastre se
 * sienta bien lo revisa el desarrollador en pantalla; aquí, lo que la página trae y lo que se guarda.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function inicioDeBarras(User $usuario): string
{
    return (string) test()->actingAs($usuario)->get('/')->assertOk()->getContent();
}

test('la barra superior es perla en modo claro y gris azulado en modo oscuro', function () {
    // Sin la apariencia según el día, que la tiñe (plan 1.4ac; lo prueba LuzDelDiaTest).
    $usuario = User::factory()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, EstacionDeLaApariencia::PREFERENCIA, false);

    expect(inicioDeBarras($usuario))
        ->toContain(':root { --tq-barra-superior: '.ColoresMarca::PERLA.'; }')
        ->toContain('html.dark { --tq-barra-superior: '.ColoresMarca::barraSuperiorOscura().'; }')
        ->toContain('html .fi-topbar { background-color: var(--tq-barra-superior); }');
});

test('la barra lateral no tiene flecha de plegar: se pliega solo arrastrando su borde', function () {
    expect(inicioDeBarras(User::factory()->create()))->toContain('html .fi-topbar-collapse-sidebar-btn-ctn { display: none; }');
});

test('la barra lateral sale con el ancho que guardó la persona y con su borde', function () {
    // Con un módulo permitido: sin ninguno no hay barra (plan 1.4f, D5).
    $usuario = User::factory()->conBarraModulos()->create()->givePermissionTo('catalogos.ver');
    app(PreferenciasUsuarioService::class)->cambiar($usuario, 'navegacion.ancho_barra', 260);

    expect(inicioDeBarras($usuario))
        ->toContain('--sidebar-width: 260px')
        ->toContain('class="tq-borde-barra"')
        ->toContain("localStorage.setItem('_x_isOpenDesktop', 'true')");
});

test('si la guardó plegada, Filament la recibe plegada y al abrirla vuelve al ancho inicial', function () {
    $usuario = User::factory()->conBarraModulos()->create();
    app(PreferenciasUsuarioService::class)->cambiar($usuario, 'navegacion.ancho_barra', 0);

    expect(inicioDeBarras($usuario))
        ->toContain('--sidebar-width: 320px')
        ->toContain("localStorage.setItem('_x_isOpenDesktop', 'false')");
});

test('al soltar el borde se guarda el ancho de quien lo arrastró', function () {
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(BordeBarraLateral::class)->call('guardar', 300)->assertNotNotified();

    expect(app(NavegacionService::class)->anchoBarra($usuario))->toBe(300);
});

test('un ancho fuera de los límites no se guarda y se avisa', function () {
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(BordeBarraLateral::class)->call('guardar', 150)
        ->assertNotified('No se pudo guardar el ancho de la barra lateral.');

    expect(app(NavegacionService::class)->anchoBarra($usuario))->toBe(320);
});

test('con la barra de módulos apagada, la barra lateral y su borde quedan escondidos desde antes de pintar', function () {
    // Toda cuenta tiene «Mien» (plan 1.4l): la barra se dibuja, pero `<html>` lleva `tq-barra-apagada` y el CSS la esconde
    // con su borde.
    expect(inicioDeBarras(User::factory()->create()))->toContain("document.documentElement.classList.add('tq-barra-apagada')");
});

test('al pasar el mouse, el ícono de un módulo de la barra crece como el de las cajas, y queda quieto con el movimiento reducido (1.4v)', function () {
    expect(inicioDeBarras(User::factory()->create()))
        ->toContain('.fi-sidebar-item-btn:is(:hover, :focus-visible) > .fi-icon { transform: scale(1.2); }')
        ->toContain('html.tq-sin-animaciones .fi-sidebar-item-btn > .fi-icon { transition: none; transform: none !important; }');
});

test('con avisos sin leer la campana se enciende en los dos modos, fija; las clases que usa son las que dibuja Filament (1.4x)', function () {
    $html = inicioDeBarras(User::factory()->create());

    expect($html)
        ->toContain('html .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) { color: var(--primary-600);')
        ->toContain('html.dark .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) > .fi-icon { filter: drop-shadow(');

    // Si Filament cambiara los nombres, la campana dejaría de encenderse sin que nada fallara: esto lo detecta.
    $conAvisos = (string) view('filament-panels::components.topbar.database-notifications-trigger', ['unreadNotificationsCount' => 2])->render();
    $sinAvisos = (string) view('filament-panels::components.topbar.database-notifications-trigger', ['unreadNotificationsCount' => 0])->render();

    expect($conAvisos)->toContain('fi-topbar-database-notifications-btn')->toContain('fi-icon-btn-badge-ctn')
        ->and($sinAvisos)->toContain('fi-topbar-database-notifications-btn')->not->toContain('fi-icon-btn-badge-ctn');
});
