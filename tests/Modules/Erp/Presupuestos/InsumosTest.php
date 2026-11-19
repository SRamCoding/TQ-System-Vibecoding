<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Erp\Presupuestos\Services\PrecioSugeridoManoObra;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El catálogo de insumos del APU (plan 2.9, D8 y D10) y el precio sugerido de la mano de obra (D1). Productos e
 * insumos inventados (regla 10).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function inServicio(): InsumosService
{
    return app(InsumosService::class);
}

function inUnidad(string $codigo): int
{
    return (int) UnidadMedida::query()->where('codigo', $codigo)->value('id');
}

test('el código va en mayúsculas y es único; el tipo es uno de los cuatro', function () {
    $insumo = inServicio()->crear(new DatosInsumo(' cab-utp6 ', 'Cable UTP', Insumo::MATERIAL, inUnidad('MTR'), '2.4'));

    expect([$insumo->codigo, $insumo->precio_referencia, $insumo->es_activo])->toBe(['CAB-UTP6', '2.4000', true])
        ->and(fn () => inServicio()->crear(new DatosInsumo('CAB-UTP6', 'Otro', Insumo::MATERIAL, inUnidad('MTR'))))->toThrow(PresupuestoRechazado::class, 'Ya hay un insumo con el código CAB-UTP6')
        ->and(fn () => inServicio()->crear(new DatosInsumo('X1', 'Otro', 'herramienta', inUnidad('NIU'))))->toThrow(PresupuestoRechazado::class, 'material, mano de obra, equipo o subcontrato')
        ->and(fn () => DB::table('insumos')->where('id', $insumo->id)->update(['tipo' => 'herramienta']))->toThrow(QueryException::class, 'insumos_tipo_check');
});

test('D8: un material se enlaza a un producto de C4, uno a uno y con su misma unidad', function () {
    $producto = Producto::factory()->create(['codigo' => 'CAM-D4M', 'unidad_medida_id' => inUnidad('NIU')]);
    $datos = fn (string $codigo, string $unidad, string $tipo = Insumo::MATERIAL) => new DatosInsumo($codigo, 'Cámara domo', $tipo, inUnidad($unidad), productoId: $producto->id);

    expect(fn () => inServicio()->crear($datos('CAM-1', 'MTR')))->toThrow(PresupuestoRechazado::class, 'El producto CAM-D4M se mide en')
        ->and(fn () => inServicio()->crear($datos('CAM-1', 'NIU', Insumo::EQUIPO)))->toThrow(PresupuestoRechazado::class, 'Solo un material');

    inServicio()->crear($datos('CAM-1', 'NIU'));

    expect(fn () => inServicio()->crear($datos('CAM-2', 'NIU')))->toThrow(PresupuestoRechazado::class, 'Ese producto ya tiene su insumo')
        ->and(fn () => inServicio()->crear(new DatosInsumo('X2', 'Sin producto', Insumo::MATERIAL, inUnidad('NIU'), productoId: 999999)))->toThrow(PresupuestoRechazado::class, 'no existe en el catálogo');
});

test('editar no cambia el código ni el tipo, y pide la versión leída; desactivar lo saca de los APU nuevos', function () {
    $insumo = inServicio()->crear(new DatosInsumo('MO-TEC', 'Técnico', Insumo::MANO_OBRA, inUnidad('HUR'), '18'));

    expect(fn () => inServicio()->editar($insumo->id, new DatosInsumo('MO-TEC', 'Técnico', Insumo::EQUIPO, inUnidad('HUR')), $insumo->version))->toThrow(PresupuestoRechazado::class, 'no cambian');

    $editado = inServicio()->editar($insumo->id, new DatosInsumo('mo-tec', 'Técnico electricista', Insumo::MANO_OBRA, inUnidad('HUR'), '19.5', categoriaCostoHoraId: 7), $insumo->version);

    expect([$editado->nombre, $editado->precio_referencia, $editado->categoria_costo_hora_id])->toBe(['Técnico electricista', '19.5000', 7])
        ->and(fn () => inServicio()->desactivar($insumo->id, $insumo->version))->toThrow(VersionDesactualizada::class)
        ->and(inServicio()->desactivar($insumo->id, $editado->version)->es_activo)->toBeFalse();
});

test('D10: «Precio sin detallar» es uno solo, del sistema, y nadie lo edita ni lo desactiva', function () {
    $sin = inServicio()->sinDetallar();

    expect([$sin->codigo, $sin->tipo, $sin->es_sistema, $sin->unidadMedida->codigo])->toBe(['SIN-DETALLE', Insumo::SUBCONTRATO, true, 'GLB'])
        ->and(inServicio()->sinDetallar()->id)->toBe($sin->id)
        ->and(fn () => inServicio()->desactivar($sin->id, $sin->version))->toThrow(PresupuestoRechazado::class, 'es del sistema')
        ->and(fn () => inServicio()->crear(new DatosInsumo('SIN-DETALLE', 'Copia', Insumo::SUBCONTRATO, inUnidad('GLB'))))->toThrow(PresupuestoRechazado::class, 'insumo del sistema');
});

test('D1: el precio inicial de la mano de obra es la sugerencia de C5; si no hay, el de referencia', function () {
    $conCategoria = inServicio()->crear(new DatosInsumo('MO-A', 'Técnico', Insumo::MANO_OBRA, inUnidad('HUR'), '18', categoriaCostoHoraId: 3));
    $fecha = CarbonImmutable::parse('2026-11-07');

    // Hoy no existe C5: no hay sugerencia y queda el de referencia.
    expect(inServicio()->precioInicial($conCategoria, $fecha))->toBe('18.0000');

    app()->instance(PrecioSugeridoManoObra::class, new class implements PrecioSugeridoManoObra
    {
        public function vigente(int $categoriaId, CarbonImmutable $fecha): ?string
        {
            return $categoriaId === 3 ? '21.5000' : null;
        }
    });

    expect(inServicio()->precioInicial($conCategoria, $fecha))->toBe('21.5000');
});

test('la unidad de un insumo que ya está en un APU no cambia', function () {
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('01', 'CABLEADO');
    $cable = PresupuestoDePrueba::insumo('CAB-U', 'Cable', Insumo::MATERIAL, 'MTR');
    $p->partida('01', '01.01', 'Cableado', 'MTR', '10', [new DatosLineaApu($cable, cantidadNeta: '1', precio: '2')]);
    $insumo = Insumo::query()->findOrFail($cable);

    expect(fn () => inServicio()->editar($cable, new DatosInsumo('CAB-U', 'Cable', Insumo::MATERIAL, inUnidad('RO')), $insumo->version))
        ->toThrow(PresupuestoRechazado::class, 'su unidad no cambia')
        ->and(inServicio()->editar($cable, new DatosInsumo('CAB-U', 'Cable UTP', Insumo::MATERIAL, inUnidad('MTR')), $insumo->version)->nombre)->toBe('Cable UTP');
});
