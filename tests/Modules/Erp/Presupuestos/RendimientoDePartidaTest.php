<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Costuras\PresupuestoDePrueba;

/*
 * El rendimiento va en la partida y una línea puede llevar uno propio (plan 2.9, D12). Cantidad efectiva = cuadrilla ×
 * horas de la unidad ÷ rendimiento (el de la línea si lo tiene; si no, el de la partida).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->actingAs(User::factory()->create());
});

function rdLinea(int $partidaId, string $insumo): LineaApu
{
    return LineaApu::query()->where('presupuesto_partida_id', $partidaId)->whereHas('insumo', fn ($q) => $q->where('codigo', $insumo))->firstOrFail();
}

/** Edita solo el rendimiento de la partida, con lo demás como está. */
function rdCambiarRendimiento(int $partidaId, ?string $rendimiento, ?string $unidad): PartidaDePresupuesto
{
    $partida = PartidaDePresupuesto::query()->findOrFail($partidaId);

    return app(ArmadoPresupuestoService::class)->editarPartida($partida->id, $partida->descripcion, $partida->unidad_medida_id, $partida->metrado, null, $partida->version, $rendimiento, $unidad);
}

test('todas las líneas de mano de obra usan el rendimiento de la partida: 20 m/h → 0.05 h por metro', function () {
    $p = PresupuestoDePrueba::flujo1();
    $partida = PartidaDePresupuesto::query()->findOrFail($p->partidas['02.01']);

    expect([$partida->rendimiento, $partida->unidad_rendimiento])->toBe(['20.0000', 'hora']);

    foreach (['MO-TEC', 'MO-AYU'] as $codigo) {
        $linea = rdLinea($partida->id, $codigo);

        expect([$linea->rendimiento_propio, $linea->rendimiento, $linea->unidad_rendimiento, $linea->cantidad])->toBe([false, '20.0000', 'hora', '0.05000']);
    }
});

test('una línea con rendimiento propio en otra unidad: partida a 20 m/h y la línea a 400 m/jornada → 0.02 h por metro', function () {
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('01', 'CABLEADO');
    $tecnico = PresupuestoDePrueba::insumo('MO-R1', 'Técnico', Insumo::MANO_OBRA, 'HUR');
    $equipo = PresupuestoDePrueba::insumo('EQ-R1', 'Tendedora de cable', Insumo::EQUIPO, 'HUR');
    $id = $p->partida('01', '01.01', 'Tendido de cable', 'MTR', '100', [
        new DatosLineaApu($tecnico, precio: '18'),
        new DatosLineaApu($equipo, rendimiento: '400', unidadRendimiento: 'jornada', precio: '40'),
    ], rendimiento: ['20', 'hora']);

    $propia = rdLinea($id, 'EQ-R1');

    expect([$propia->rendimiento_propio, $propia->rendimiento, $propia->unidad_rendimiento, $propia->horas_jornada, $propia->cantidad])
        ->toBe([true, '400.0000', 'jornada', '8.00', '0.02000'])
        ->and([rdLinea($id, 'MO-R1')->rendimiento_propio, rdLinea($id, 'MO-R1')->cantidad])->toBe([false, '0.05000']);
});

test('cambiar el rendimiento de la partida recalcula solo las líneas que lo usan', function () {
    $p = PresupuestoDePrueba::flujo1();
    $id = $p->partidas['03.01'];
    $ayudante = rdLinea($id, 'MO-AYU');

    // Técnico: 1 h ÷ 0.125 = 8 h; ayudante, con el suyo: 1 ÷ 0.25 = 4 h.
    expect([rdLinea($id, 'MO-TEC')->cantidad, $ayudante->cantidad, $ayudante->rendimiento_propio])->toBe(['8.00000', '4.00000', true]);

    $partida = rdCambiarRendimiento($id, '0.5', 'hora');

    expect(rdLinea($id, 'MO-TEC')->only(['cantidad', 'parcial', 'rendimiento']))->toBe(['cantidad' => '2.00000', 'parcial' => '36.0000', 'rendimiento' => '0.5000'])
        ->and(rdLinea($id, 'MO-AYU')->only(['cantidad', 'version']))->toBe(['cantidad' => '4.00000', 'version' => $ayudante->version])
        // NVR 4116 + técnico 36 + ayudante 48 = 4200.
        ->and([$partida->costo_unitario, $partida->parcial])->toBe(['4200.0000', '4200.00'])
        ->and($p->fresco()->costo_directo)->toBe('17642.00');
});

test('sin rendimiento propio ni de la partida, la línea no se guarda; y no se quita el de la partida mientras alguien lo use', function () {
    $p = PresupuestoDePrueba::nuevo();
    $p->titulo('01', 'MONTAJE');
    $tecnico = PresupuestoDePrueba::insumo('MO-R2', 'Técnico', Insumo::MANO_OBRA, 'HUR');
    $sin = $p->partida('01', '01.01', 'Montaje sin rendimiento', 'NIU', '1');

    expect(fn () => app(ArmadoPresupuestoService::class)->agregarLineaApu($sin, new DatosLineaApu($tecnico, precio: '18'), PartidaDePresupuesto::query()->findOrFail($sin)->version))
        ->toThrow(PresupuestoRechazado::class, 'La partida 01.01 no tiene rendimiento')
        ->and(fn () => app(ArmadoPresupuestoService::class)->agregarLineaApu($sin, new DatosLineaApu($tecnico, rendimiento: '2', precio: '18'), PartidaDePresupuesto::query()->findOrFail($sin)->version))
        ->toThrow(PresupuestoRechazado::class, 'por hora o por jornada');

    $con = $p->partida('01', '01.02', 'Montaje con rendimiento', 'NIU', '1', [new DatosLineaApu($tecnico, precio: '18')], rendimiento: ['2', 'hora']);

    $linea = rdLinea($con, 'MO-R2')->id;

    // Cada escritura en su transacción: un rechazo de la base no aborta la de la prueba.
    expect(fn () => rdCambiarRendimiento($con, null, null))->toThrow(PresupuestoRechazado::class, 'usan su rendimiento')
        ->and(fn () => DB::transaction(fn () => DB::table('presupuesto_partidas')->where('id', $con)->update(['unidad_rendimiento' => null])))->toThrow(QueryException::class, 'presupuesto_partidas_rendimiento_check')
        ->and(fn () => DB::transaction(fn () => DB::table('apu_insumos')->where('id', $linea)->update(['tipo_cantidad' => 'cantidad', 'cantidad_neta' => 1, 'rendimiento' => null, 'unidad_rendimiento' => null, 'cuadrilla' => null, 'rendimiento_propio' => true])))->toThrow(QueryException::class, 'apu_insumos_escrito_check');
});

test('la versión nueva copia el rendimiento de la partida y cuáles líneas llevan el suyo', function () {
    $p = PresupuestoDePrueba::flujo1();
    $proyecto = PresupuestoDePrueba::proyecto();
    app(ArmadoPresupuestoService::class)->traerAlProyecto($p->presupuesto->id, $proyecto, $p->fresco()->version, PresupuestoDePrueba::jefatura());
    $nueva = app(ArmadoPresupuestoService::class)->nuevaVersion($p->presupuesto->id, $p->usuario);
    $copia = PartidaDePresupuesto::query()->where('presupuesto_id', $nueva->id)->where('codigo', '03.01')->firstOrFail();

    expect([$copia->rendimiento, $copia->unidad_rendimiento])->toBe(['0.1250', 'hora'])
        ->and([rdLinea($copia->id, 'MO-TEC')->rendimiento_propio, rdLinea($copia->id, 'MO-AYU')->rendimiento_propio])->toBe([false, true]);
});
