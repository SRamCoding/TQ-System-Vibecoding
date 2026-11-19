<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Erp\Presupuestos\Services\MaterialesSinProducto;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * Los materiales sin producto enlazado (plan 2.9, D13): el enlace es opcional; al ofertar y al traer al proyecto hay
 * aviso, sin bloquear, y se completa después aunque el presupuesto esté aprobado, sin cambiar ningún monto. Productos
 * inventados (regla 10).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

/** Enlaza el insumo a un producto nuevo con su misma unidad, con lo demás como está. */
function msEnlazar(string $codigo): void
{
    $insumo = Insumo::query()->where('codigo', $codigo)->firstOrFail();
    $producto = Producto::factory()->create(['codigo' => "P-{$codigo}", 'unidad_medida_id' => $insumo->unidad_medida_id]);

    app(InsumosService::class)->editar($insumo->id, new DatosInsumo($insumo->codigo, $insumo->nombre, $insumo->tipo, $insumo->unidad_medida_id, $insumo->precio_referencia, $producto->id), $insumo->version);
}

test('se oferta y se trae al proyecto con materiales sin producto, y el aviso los nombra con su porqué', function () {
    $p = PresupuestoDePrueba::flujo1();
    msEnlazar('CAM-DOMO');
    $aviso = app(ConsultaPresupuestos::class)->materialesSinProducto($p->presupuesto->id);

    // La mano de obra y el equipo no cuentan: solo los materiales.
    expect(array_column($aviso->materiales, 'codigo'))->toBe(['CAB-UTP6', 'NVR-16', 'POS-MET'])
        ->and($aviso->mensaje())->toBe('Materiales sin producto enlazado: CAB-UTP6 Cable UTP Cat6, NVR-16 NVR de 16 canales, POS-MET Poste metálico. '.MaterialesSinProducto::POR_QUE);

    $presupuesto = $p->fresco();
    app(TransicionesService::class)->transicionar($presupuesto, 'ofertar', $presupuesto->version, PresupuestoDePrueba::jefatura());
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, PresupuestoDePrueba::proyecto(), $p->fresco()->version, PresupuestoDePrueba::jefatura());

    expect(app(PresupuestosService::class)->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::APROBADO);
});

test('con todos los materiales enlazados no hay aviso', function () {
    $p = PresupuestoDePrueba::flujo1();

    foreach (['CAB-UTP6', 'CAM-DOMO', 'NVR-16', 'POS-MET'] as $codigo) {
        msEnlazar($codigo);
    }

    $aviso = app(ConsultaPresupuestos::class)->materialesSinProducto($p->presupuesto->id);

    expect([$aviso->hayAviso(), $aviso->mensaje()])->toBe([false, null]);
});

test('enlazar el producto de un insumo de un presupuesto aprobado está permitido y no cambia ningún monto', function () {
    $p = PresupuestoDePrueba::flujo1();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, PresupuestoDePrueba::proyecto(), $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $montos = fn (): array => [
        $p->fresco()->only(['costo_directo', 'gastos_generales', 'utilidad', 'igv', 'total']),
        PartidaDePresupuesto::query()->where('presupuesto_id', $p->presupuesto->id)->orderBy('codigo')->pluck('parcial', 'codigo')->all(),
        app(ConsultaPresupuestos::class)->listaDeInsumos($p->presupuesto->id)->total,
    ];
    $antes = $montos();

    msEnlazar('NVR-16');

    expect($montos())->toBe($antes)
        ->and($antes[0]['costo_directo'])->toBe('17750.00')
        ->and(array_column(app(ConsultaPresupuestos::class)->materialesSinProducto($p->presupuesto->id)->materiales, 'codigo'))->not->toContain('NVR-16');
});
