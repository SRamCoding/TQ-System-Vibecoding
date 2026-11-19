<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Filament\Facades\Filament;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * Configuración → Numeración (plan 1.6, D5): solo lectura, con el último número de cada serie.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
    app(SincronizadorEstados::class)->sincronizar();
    Filament::setCurrentPanel('app');
});

test('con permiso para ver muestra cada serie con su último número; sin él, no abre', function () {
    foreach (['2026-03-01', '2026-04-01'] as $fecha) {
        app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido', 'fecha' => $fecha]), User::factory()->create());
    }

    test()->actingAs(User::factory()->create());
    test()->get('/configuracion/numeracion')->assertForbidden();

    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.ver'));
    test()->get('/configuracion/numeracion')->assertOk()
        ->assertSee('Documento de prueba')
        ->assertSee('DPR-2026-0002')
        ->assertSee('Toda la empresa')
        ->assertSee('Cada año');
});

test('sin números emitidos, lo dice', function () {
    test()->actingAs(User::factory()->create()->givePermissionTo('catalogos.ver'));

    test()->get('/configuracion/numeracion')->assertOk()->assertSee('Todavía no se emitió ningún número');
});
