<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Filament\Desarrollo\ProbarSelectorImputacion;
use App\Modules\Erp\Presupuestos\Services\PartidasEnBase;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Proyectos\Services\ProyectosEnBase;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * La página «Probar el selector de imputación» (plan 2.2, D8): solo con el modo desarrollo activo; con datos en memoria
 * que no salen de la página (el contenedor sigue con C1 y C2 reales).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create());
});

function psModoDesarrollo(): void
{
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => true, 'nucleo.modo_desarrollo' => true]);
    session()->put('nucleo.modo_desarrollo.activo', true);
}

test('fuera del modo desarrollo la página no existe', function () {
    test()->get(ProbarSelectorImputacion::getUrl())->assertNotFound();
});

test('con el modo desarrollo responde y muestra el selector con sus tres opciones', function () {
    psModoDesarrollo();

    test()->get(ProbarSelectorImputacion::getUrl())->assertOk()
        ->assertSee('Probar el selector de imputación')
        ->assertSee('Proyecto › partida')
        ->assertSee('Gasto directo con proyecto');
});

test('muestra el saldo, el aviso del 90 % y el exceso con su motivo, y la partida desactivada ya guardada', function () {
    psModoDesarrollo();
    $pagina = Livewire::test(ProbarSelectorImputacion::class)
        ->set('data.monto', '40')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', 1)
        ->set('data.imputacion_partida_id', 3)
        ->assertSee('La partida queda al 100.00 %.')
        ->set('data.monto', '60')
        ->assertSee('Excede en S/ 20.00')
        ->assertSee('Motivo del exceso');

    $pagina->callAction('lineaGuardada')->assertSee('PC-03 Movilidad (desactivada)');
});

test('con la página abierta, el contenedor sigue dando las versiones reales de C1 y C2', function () {
    psModoDesarrollo();
    Livewire::test(ProbarSelectorImputacion::class);

    expect(app(ProyectosService::class))->toBeInstanceOf(ProyectosEnBase::class)
        ->and(app(PartidasService::class))->toBeInstanceOf(PartidasEnBase::class);
});
