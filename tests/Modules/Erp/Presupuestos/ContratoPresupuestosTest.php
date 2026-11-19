<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\EnMemoria\PresupuestosEnMemoria;
use App\Modules\Erp\Presupuestos\Services\PartidaEnResumen;
use App\Modules\Erp\Presupuestos\Services\PresupuestoNoEncontrado;
use App\Modules\Erp\Presupuestos\Services\PresupuestosEnBase;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Tests\Costuras\PresupuestoDePrueba;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Contrato de C8 (plan 2.9, D7): las mismas pruebas contra la versión real y la falsa, para que la falsa que use
 * Comercial no se aleje de la real. La real se arma con el servicio; a la falsa se le da el resumen que la real dio.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

/** @return array{PresupuestosService, int} */
function c8Version(string $cual): array
{
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('01', 'CÁMARAS');
    $p->partida('01', '01.01', 'Instalación de cámara domo IP', 'NIU', '10', [
        new DatosLineaApu(PresupuestoDePrueba::insumo('CAM-K', 'Cámara domo', Insumo::MATERIAL, 'NIU'), cantidadNeta: '1', precio: '583'),
        new DatosLineaApu(PresupuestoDePrueba::insumo('MO-K', 'Técnico', Insumo::MANO_OBRA, 'HUR'), rendimiento: '0.5', unidadRendimiento: 'hora', precio: '30'),
    ]);
    $real = app(PresupuestosEnBase::class);

    if ($cual === 'falsa') {
        $falsa = new PresupuestosEnMemoria;
        $falsa->agregar($real->resumen($p->presupuesto->id));

        return [$falsa, $p->presupuesto->id];
    }

    return [$real, $p->presupuesto->id];
}

dataset('versiones de C8', ['real', 'falsa']);

test('C8 trae el encabezado con porcentajes y montos, y las partidas a costo directo', function (string $cual) {
    [$servicio, $id] = c8Version($cual);
    $r = $servicio->resumen($id);

    expect($r)->toBeInstanceOf(ResumenPresupuesto::class)
        ->and([$r->numeroVersion, $r->estado, $r->monedaCodigo, $r->usaApu, $r->esVigente, $r->proyectoId])->toBe([1, 'borrador', 'PEN', true, false, null])
        ->and([$r->gastosGeneralesPct, $r->utilidadPct, $r->igvPct])->toBe(['10.00', '8.00', '18.00'])
        ->and([$r->costoDirecto, $r->gastosGenerales, $r->utilidad, $r->igv, $r->total, $r->sinDetallar])->toBe(['6430.00', '643.00', '514.40', '1365.73', '8953.13', '0.00'])
        ->and($r->partidas)->toHaveCount(1)
        ->and($r->partidas[0])->toEqual(new PartidaEnResumen('01.01', '01', 'CÁMARAS', 'Instalación de cámara domo IP', 'und', '10.000', '643.00', '6430.00'));
})->with('versiones de C8');

test('C8 avisa si el presupuesto no existe', function (string $cual) {
    [$servicio] = c8Version($cual);

    expect(fn () => $servicio->resumen(999999))->toThrow(PresupuestoNoEncontrado::class, '#999999');
})->with('versiones de C8');

test('la real es la que da el contenedor', function () {
    expect(app(PresupuestosService::class))->toBeInstanceOf(PresupuestosEnBase::class);
});

/*
 * Plan 2.22, D8: `deOportunidad()` y `ofertar()`. La real se arma por el servicio; la falsa recibe los resúmenes que la
 * real dio antes de cualquier cambio (y lo que le falta a cada uno), y debe terminar igual.
 */

/** Un presupuesto chico y completo de la oportunidad. */
function c8Chico(?int $oportunidadId, bool $completo = true): PresupuestoDePrueba
{
    $p = PresupuestoDePrueba::nuevo(oportunidadId: $oportunidadId);

    if ($completo) {
        $p->titulo('01', 'SUMINISTRO');
        $p->partida('01', '01.01', 'Cámara', 'NIU', '2', [
            new DatosLineaApu(PresupuestoDePrueba::insumo('CAM-C8', 'Cámara de prueba', Insumo::MATERIAL, 'NIU'), cantidadNeta: '1', precio: '100'),
        ]);
    }

    return $p;
}

/**
 * La versión pedida de C8, con estos presupuestos ya armados en la real.
 *
 * @param  list<int>  $ids
 */
function c8Con(string $cual, array $ids): PresupuestosService
{
    $real = app(PresupuestosEnBase::class);

    if ($cual === 'real') {
        return $real;
    }

    $falsa = new PresupuestosEnMemoria;

    foreach ($ids as $id) {
        $falsa->agregar($real->resumen($id), app(ConsultaPresupuestos::class)->faltas(Presupuesto::query()->findOrFail($id)));
    }

    return $falsa;
}

function c8Oportunidad(): int
{
    return OportunidadesDePrueba::id(Cuenta::factory()->create()->id);
}

test('C8 oferta un borrador, y ofertar otra vez no falla ni cambia nada', function (string $cual) {
    $p = c8Chico(c8Oportunidad());
    $servicio = c8Con($cual, [$p->presupuesto->id]);
    $quien = User::factory()->create();

    $primera = $servicio->ofertar($p->presupuesto->id, $quien);
    $segunda = $servicio->ofertar($p->presupuesto->id, $quien);

    expect([$primera->estado, $segunda->estado])->toBe([DefinicionPresupuesto::OFERTADO, DefinicionPresupuesto::OFERTADO])
        ->and($servicio->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::OFERTADO);
})->with('versiones de C8');

test('C8 no oferta un presupuesto incompleto, y lo deja en borrador', function (string $cual) {
    $p = c8Chico(c8Oportunidad(), completo: false);
    $servicio = c8Con($cual, [$p->presupuesto->id]);

    expect(fn () => $servicio->ofertar($p->presupuesto->id, User::factory()->create()))
        ->toThrow(PresupuestoRechazado::class, 'El presupuesto está incompleto: ')
        ->and($servicio->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::BORRADOR);
})->with('versiones de C8');

test('C8 lista los presupuestos de la oportunidad, la versión más reciente primero; ofertar la nueva reemplaza la ofertada', function (string $cual) {
    $oportunidad = c8Oportunidad();
    $p = c8Chico($oportunidad);
    app(PresupuestosEnBase::class)->ofertar($p->presupuesto->id, $p->usuario);
    // Otros costos para la cotización: versión nueva del presupuesto ofertado.
    $v2 = app(ArmadoPresupuestoService::class)->nuevaVersion($p->presupuesto->id, $p->usuario);
    $otraOportunidad = c8Chico(c8Oportunidad());
    $servicio = c8Con($cual, [$p->presupuesto->id, $v2->id, $otraOportunidad->presupuesto->id]);

    expect(array_map(fn ($r) => [$r->id, $r->numeroVersion, $r->estado], $servicio->deOportunidad($oportunidad)))
        ->toBe([[$v2->id, 2, DefinicionPresupuesto::BORRADOR], [$p->presupuesto->id, 1, DefinicionPresupuesto::OFERTADO]]);

    $servicio->ofertar($v2->id, $p->usuario);

    expect($servicio->resumen($p->presupuesto->id)->estado)->toBe(DefinicionPresupuesto::REEMPLAZADO)
        ->and(array_map(fn ($r) => $r->id, $servicio->deOportunidad($oportunidad)))->toBe([$v2->id])
        ->and($servicio->deOportunidad(999999))->toBe([]);
})->with('versiones de C8');

test('C8 no oferta uno anulado (ni reemplazado: la misma rama)', function (string $cual) {
    $p = c8Chico(c8Oportunidad());
    $presupuesto = $p->fresco();
    $motivo = new MotivoElegido((int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', 'erp.presupuestos.presupuesto.anular'))->where('es_otro', true)->value('id'), 'Se desistió de la oferta');
    app(TransicionesService::class)->transicionar($presupuesto, 'anular', $presupuesto->version, PresupuestoDePrueba::jefatura(), $motivo);
    $servicio = c8Con($cual, [$p->presupuesto->id]);

    expect(fn () => $servicio->ofertar($p->presupuesto->id, User::factory()->create()))
        ->toThrow(PresupuestoRechazado::class, 'está anulado: no se oferta.');
})->with('versiones de C8');

test('la real oferta a nombre de quien emite, aunque no tenga el permiso de ofertar a mano', function () {
    $p = c8Chico(c8Oportunidad());
    $ejecutivo = User::factory()->create();

    app(PresupuestosService::class)->ofertar($p->presupuesto->id, $ejecutivo);

    $ultimo = HistorialEstado::query()->where('documentable_type', 'presupuesto')->where('documentable_id', $p->presupuesto->id)->latest('id')->firstOrFail();
    expect($ultimo->originado_por_user_id)->toBe($ejecutivo->id)
        ->and($ejecutivo->can('erp.presupuestos.presupuesto.ofertar'))->toBeFalse();
});
