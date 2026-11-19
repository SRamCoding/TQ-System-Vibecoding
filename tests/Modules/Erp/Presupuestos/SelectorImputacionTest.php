<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Imputacion\DestinosDePresupuestos;
use App\Modules\Erp\Presupuestos\Services\EnMemoria\PartidasEnMemoria;
use App\Modules\Erp\Proyectos\Services\EnMemoria\ProyectosEnMemoria;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\Modules\Erp\Presupuestos\Fixtures\FormularioConSelector;

/*
 * El selector de imputación en un formulario (plan 2.2, D1, D3, D7 y D8), solo con las versiones en memoria de C1 y C2:
 * así lo prueba cualquier módulo que lo ponga en sus líneas, sin armar árboles. Proyecto inventado PRY con PC-01 Cable
 * (1,000.00), PC-02 Cámaras (5,000.00, con 4,000.00 ya comprometidos) y PC-03 Movilidad, desactivada.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());

    $proyectos = new ProyectosEnMemoria;
    $proyecto = $proyectos->agregar('Videovigilancia inventada');
    $partidas = new PartidasEnMemoria;
    test()->ids = [
        'proyecto' => $proyecto->id,
        'PC-01' => $partidas->agregar($proyecto->id, 'PC-01', 'Cable', presupuestado: '1000.00'),
        'PC-02' => $partidas->agregar($proyecto->id, 'PC-02', 'Cámaras', presupuestado: '5000.00'),
        'PC-03' => $partidas->agregar($proyecto->id, 'PC-03', 'Movilidad', activa: false),
    ];
    $partidas->imputar(test()->ids['PC-02'], '4000.00');
    FormularioConSelector::$fuente = new DestinosDePresupuestos($partidas, $proyectos);
});

afterEach(function () {
    FormularioConSelector::$fuente = null;
});

test('las tres opciones se ven y el gasto directo con proyecto está habilitado (plan 2.6)', function () {
    Livewire::test(FormularioConSelector::class)
        ->assertSee('Proyecto › partida')
        ->assertSee('Gasto directo sin proyecto')
        ->assertSee('Gasto directo con proyecto')
        ->assertDontSee('Llega con el gasto no presupuestado.');
});

test('con el gasto directo con proyecto pide el motivo de su lista, el comentario y un centro de referencia opcional', function () {
    Livewire::test(FormularioConSelector::class)
        ->set('data.imputacion_tipo', 'no_presupuestado')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->assertSee('Motivo del gasto no presupuestado')
        ->assertSee('¿Por qué no estaba en el presupuesto?')
        ->assertSee('Centro de costo de referencia')
        ->assertDontSee('Partida de control')
        ->assertDontSee('Motivo del exceso');
});

test('con una partida elegida muestra el saldo con la línea', function () {
    Livewire::test(FormularioConSelector::class)
        ->set('data.monto', '300')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->set('data.imputacion_partida_id', test()->ids['PC-01'])
        ->assertSee('Presupuestado S/ 1,000.00')
        ->assertSee('Comprometido con esta línea S/ 300.00')
        ->assertSee('Disponible S/ 700.00')
        ->assertSee('Solo si la partida se excede con esta línea o con las demás del documento.');
});

test('al 90 % o más avisa cómo queda la partida', function () {
    Livewire::test(FormularioConSelector::class)
        ->set('data.monto', '650')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->set('data.imputacion_partida_id', test()->ids['PC-02'])
        ->assertSee('La partida queda al 93.00 %.');
});

test('si excede, lo dice y pide el motivo y el comentario', function () {
    Livewire::test(FormularioConSelector::class)
        ->set('data.monto', '1048')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->set('data.imputacion_partida_id', test()->ids['PC-01'])
        ->assertSee('Excede en S/ 48.00')
        ->assertSee('Motivo del exceso')
        ->assertSee('¿Por qué se excede?');
});

test('una línea en dólares se convierte con el tipo de cambio de su fecha; sin él, lo avisa sin adivinar', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-12'), '3.7000', '3.8000', OrigenTipoCambio::Manual);

    Livewire::test(FormularioConSelector::class)
        ->set('data.monto', '100')
        ->set('data.moneda', 'USD')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->set('data.imputacion_partida_id', test()->ids['PC-01'])
        ->assertSee('Comprometido con esta línea S/ 380.00');

    Livewire::test(FormularioConSelector::class)
        ->set('data.monto', '100')
        ->set('data.moneda', 'USD')
        ->set('data.fecha', '2026-01-02')
        ->set('data.imputacion_tipo', 'partida')
        ->set('data.imputacion_proyecto_id', test()->ids['proyecto'])
        ->set('data.imputacion_partida_id', test()->ids['PC-01'])
        ->assertSee('Falta el tipo de cambio del día')
        ->assertSee('Comprometido con esta línea S/ 0.00');
});

test('una partida ya guardada que hoy está desactivada se sigue mostrando', function () {
    Livewire::test(FormularioConSelector::class, ['inicial' => [
        'imputacion_tipo' => 'partida',
        'imputacion_proyecto_id' => test()->ids['proyecto'],
        'imputacion_partida_id' => test()->ids['PC-03'],
    ]])->assertSee('PC-03 Movilidad (desactivada)');
});

test('con el gasto directo sin proyecto, se elige un centro de costo', function () {
    Livewire::test(FormularioConSelector::class)
        ->set('data.imputacion_tipo', 'centro_costo')
        ->assertSee('Centro de costo')
        ->assertSee('Administración')
        ->assertDontSee('Partida de control');
});
