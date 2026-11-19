<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Lo que la base garantiza de `cuentas` y `cuenta_marcas_cliente` (plan 2.20, D1, D8 y D9), aunque alguien se salte
 * CuentasService.
 */

function motivoParaDesactivarCuenta(): int
{
    test()->seed(EstadosSeeder::class);
    $familia = TipoMotivo::query()->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR)->firstOrFail();

    return Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->value('id');
}

test('una ficha tiene a lo más una cuenta', function () {
    $cuenta = Cuenta::factory()->create();

    expect(fn () => Cuenta::factory()->create(['party_id' => $cuenta->party_id]))->toThrow(QueryException::class, 'cuentas_party_id_unique');
});

test('inactiva si y solo si tiene motivo, y sin motivo no hay detalle', function () {
    $motivo = motivoParaDesactivarCuenta();

    expect(Cuenta::factory()->create(['es_activa' => false, 'motivo_inactiva_id' => $motivo, 'motivo_inactiva_detalle' => 'Cerró en marzo'])->es_activa)->toBeFalse()
        ->and(fn () => Cuenta::factory()->create(['es_activa' => false]))->toThrow(QueryException::class, 'cuentas_motivo_inactiva_check')
        ->and(fn () => Cuenta::factory()->create(['motivo_inactiva_id' => $motivo]))->toThrow(QueryException::class, 'cuentas_motivo_inactiva_check')
        ->and(fn () => Cuenta::factory()->create(['motivo_inactiva_detalle' => 'Sin motivo']))->toThrow(QueryException::class, 'cuentas_motivo_inactiva_check')
        ->and(fn () => Cuenta::factory()->create(['es_activa' => false, 'motivo_inactiva_id' => $motivo, 'motivo_inactiva_detalle' => '  ']))->toThrow(QueryException::class, 'cuentas_textos_check');
});

test('la versión empieza en 1 y la cuenta queda en la bitácora con su alias', function () {
    $cuenta = Cuenta::factory()->create();

    expect($cuenta->refresh()->version)->toBe(1)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'cuenta')->where('auditable_id', $cuenta->id)->exists())->toBeTrue();
});

test('la marca de una oportunidad lleva su id y la manual su comentario', function () {
    $cuenta = Cuenta::factory()->create();
    $oportunidad = OportunidadesDePrueba::id($cuenta->id);

    expect(fn () => CuentaMarcaCliente::factory()->create(['cuenta_id' => $cuenta->id, 'comentario' => ' ']))->toThrow(QueryException::class, 'cuenta_marcas_cliente_comentario_check')
        ->and(fn () => CuentaMarcaCliente::factory()->create(['cuenta_id' => $cuenta->id, 'oportunidad_id' => $oportunidad]))->toThrow(QueryException::class, 'cuenta_marcas_cliente_oportunidad_check')
        ->and(fn () => CuentaMarcaCliente::factory()->deOportunidad($oportunidad)->create(['cuenta_id' => $cuenta->id, 'oportunidad_id' => null]))->toThrow(QueryException::class, 'cuenta_marcas_cliente_oportunidad_check')
        ->and(fn () => DB::table('cuenta_marcas_cliente')->insert(['cuenta_id' => $cuenta->id, 'origen' => 'regalo', 'fecha' => '2026-01-15', 'comentario' => 'x', 'created_by' => $cuenta->created_by]))
        ->toThrow(QueryException::class, 'cuenta_marcas_cliente_origen_check');
});

test('una marca por oportunidad y una sola manual por cuenta', function () {
    $cuenta = Cuenta::factory()->create();
    [$oportunidad, $otra] = [OportunidadesDePrueba::id($cuenta->id), OportunidadesDePrueba::id($cuenta->id)];
    CuentaMarcaCliente::factory()->create(['cuenta_id' => $cuenta->id]);
    CuentaMarcaCliente::factory()->deOportunidad($oportunidad)->create(['cuenta_id' => $cuenta->id]);
    CuentaMarcaCliente::factory()->deOportunidad($otra)->create(['cuenta_id' => $cuenta->id]);

    expect($cuenta->marcasCliente()->count())->toBe(3)
        ->and(fn () => CuentaMarcaCliente::factory()->create(['cuenta_id' => $cuenta->id]))->toThrow(QueryException::class, 'cuenta_marcas_cliente_una_manual_unique')
        ->and(fn () => CuentaMarcaCliente::factory()->deOportunidad($oportunidad)->create(['cuenta_id' => $cuenta->id]))->toThrow(QueryException::class, 'cuenta_marcas_cliente_oportunidad_unique')
        // Una oportunidad pertenece a una sola cuenta: tampoco marca otra.
        ->and(fn () => CuentaMarcaCliente::factory()->deOportunidad($oportunidad)->create())->toThrow(QueryException::class, 'cuenta_marcas_cliente_oportunidad_unique');
});
