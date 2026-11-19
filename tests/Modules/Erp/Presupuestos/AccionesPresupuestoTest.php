<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Regla 7 (plan 2.9): el presupuesto se arma de punta a punta sin la interfaz, con las acciones registradas y sus
 * permisos. El detalle de cada regla está en PresupuestoTest e InsumosTest.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

test('el presupuesto se arma, se oferta y se trae al proyecto con las acciones, cada una con su permiso', function () {
    $acciones = app(EjecutorAcciones::class);
    $sin = User::factory()->create();
    $con = User::factory()->create()->givePermissionTo([
        'erp.presupuestos.insumos.editar',
        'erp.presupuestos.presupuesto.editar',
        'erp.presupuestos.presupuesto.ofertar',
        'erp.presupuestos.presupuesto.traer_al_proyecto',
    ]);
    $encabezado = ['nombre' => 'Cámaras de un local (prueba)', 'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'gastos_generales_pct' => '10', 'utilidad_pct' => '8'];

    expect(fn () => $acciones->ejecutar('erp.presupuestos.crear-presupuesto', $encabezado, $sin))->toThrow(AccionRechazada::class);

    /** @var Presupuesto $presupuesto */
    $presupuesto = $acciones->ejecutar('erp.presupuestos.crear-presupuesto', $encabezado, $con);
    $insumo = $acciones->ejecutar('erp.presupuestos.crear-insumo', ['codigo' => 'CAM-A', 'tipo' => 'material', 'nombre' => 'Cámara', 'unidad_medida_id' => PresupuestoDePrueba::unidad('NIU'), 'precio_referencia' => '150'], $con);
    $titulo = $acciones->ejecutar('erp.presupuestos.agregar-titulo-presupuesto', ['presupuesto_id' => $presupuesto->id, 'codigo' => '01', 'nombre' => 'CÁMARAS'], $con);
    $partida = $acciones->ejecutar('erp.presupuestos.agregar-partida-presupuesto', ['presupuesto_id' => $presupuesto->id, 'titulo_id' => $titulo->id, 'codigo' => '01.01', 'descripcion' => 'Cámara', 'unidad_medida_id' => PresupuestoDePrueba::unidad('NIU'), 'metrado' => '4'], $con);
    $acciones->ejecutar('erp.presupuestos.agregar-insumo-apu', ['partida_id' => $partida->id, 'insumo_id' => $insumo->id, 'cantidad_neta' => '1', 'version' => $partida->version], $con);

    $presupuesto->refresh();
    $acciones->ejecutar('erp.presupuestos.presupuesto.ofertar', ['documento_id' => $presupuesto->id, 'version' => $presupuesto->version], $con);
    $presupuesto->refresh();
    $acciones->ejecutar('erp.presupuestos.traer-presupuesto-al-proyecto', ['presupuesto_id' => $presupuesto->id, 'proyecto_id' => PresupuestoDePrueba::proyecto(), 'version' => $presupuesto->version], $con);

    expect($presupuesto->refresh()->estado->codigo)->toBe(DefinicionPresupuesto::APROBADO)
        ->and($presupuesto->costo_directo)->toBe('600.00');
});
