<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Lo que la base garantiza de la cotización (plan 2.22, paso 3), escrito directo en las tablas: una aceptada por número
 * y por oportunidad (D7), lo congelado al emitir llega junto (D9, D10), la aceptada lleva fecha y respaldo (D5, P2), el
 * origen de cada línea (D6) y una sola línea del presupuesto (D8).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->usuarioBase = User::factory()->create();
    test()->oportunidadBase = OportunidadesDePrueba::crear(Cuenta::factory()->create()->id);
});

/** @param  array<string, mixed>  $datos */
function cbFila(array $datos = []): int
{
    $o = test()->oportunidadBase;

    return DB::table('cotizaciones')->insertGetId([
        'numero' => 'COT-2026-0184', 'numero_version' => 1, 'oportunidad_id' => $o->id, 'cuenta_id' => $o->cuenta_id,
        'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'dias_validez' => 15,
        'estado_id' => Estado::query()->value('id'), 'created_by' => test()->usuarioBase->id, ...$datos,
    ]);
}

/** @return array<string, mixed> */
function cbEmitida(): array
{
    return ['emitida_at' => now(), 'fecha_emision' => '2026-11-12', 'fecha_vencimiento' => '2026-11-27', 'tipo_cambio' => '3.7500', 'igv_porcentaje' => '18.00'];
}

/** @return array<string, mixed> */
function cbAceptada(): array
{
    return [...cbEmitida(), 'es_aceptada' => true, 'fecha_aceptacion' => '2026-11-14', 'respaldo_tipo' => 'orden_compra', 'respaldo_referencia' => 'OC-0099'];
}

/** @param  array<string, mixed>  $datos */
function cbLinea(int $cotizacionId, array $datos = []): int
{
    return DB::table('cotizacion_lineas')->insertGetId([
        'cotizacion_id' => $cotizacionId, 'origen' => 'libre', 'descripcion' => 'Instalación y puesta en marcha',
        'unidad_medida_id' => UnidadMedida::query()->value('id'), 'cantidad' => '1', 'precio_unitario' => '8000',
        'costo_unitario' => '7752.78', 'subtotal' => '8000.00', 'created_by' => test()->usuarioBase->id, ...$datos,
    ]);
}

function cbFalla(Closure $escribir): void
{
    expect(fn () => DB::transaction($escribir))->toThrow(QueryException::class);
}

test('número y versión son únicos juntos, y el número tiene el formato COT', function () {
    cbFila();
    cbFila(['numero_version' => 2]);

    cbFalla(fn () => cbFila(['numero_version' => 2]));
    cbFalla(fn () => cbFila(['numero' => 'PRE-2026-0001']));
});

test('una sola aceptada por número y por oportunidad (D7)', function () {
    cbFila(cbAceptada());

    cbFalla(fn () => cbFila(['numero_version' => 2, ...cbAceptada()]));
    cbFalla(fn () => cbFila(['numero' => 'COT-2026-0185', ...cbAceptada()]));
    expect(cbFila(['numero_version' => 3, ...cbEmitida()]))->toBeInt();
});

test('lo congelado al emitir llega junto, y la validez no es menor que la emisión (D9, D10)', function () {
    cbFalla(fn () => cbFila(['emitida_at' => now()]));
    cbFalla(fn () => cbFila([...cbEmitida(), 'tipo_cambio' => null]));
    cbFalla(fn () => cbFila([...cbEmitida(), 'fecha_vencimiento' => '2026-11-01']));
    cbFalla(fn () => cbFila(['dias_validez' => 0]));
});

test('la aceptada es emitida y lleva fecha y respaldo de la lista (D5, P2)', function () {
    cbFalla(fn () => cbFila([...cbAceptada(), 'respaldo_referencia' => ' ']));
    cbFalla(fn () => cbFila([...cbAceptada(), 'respaldo_referencia' => null]));
    cbFalla(fn () => cbFila([...cbAceptada(), 'respaldo_tipo' => 'llamada']));
    cbFalla(fn () => cbFila([...cbAceptada(), 'emitida_at' => null, 'fecha_emision' => null, 'fecha_vencimiento' => null, 'tipo_cambio' => null, 'igv_porcentaje' => null]));
});

test('cada línea dice su origen; solo la del catálogo lleva producto, y hay una sola del presupuesto (D6, D8)', function () {
    $id = cbFila();
    cbLinea($id, ['origen' => 'presupuesto']);

    cbFalla(fn () => cbLinea($id, ['origen' => 'presupuesto']));
    cbFalla(fn () => cbLinea($id, ['origen' => 'catalogo']));
    cbFalla(fn () => cbLinea($id, ['origen' => 'otro']));
    cbFalla(fn () => cbLinea($id, ['descuento_porcentaje' => '100.01']));
    cbFalla(fn () => cbLinea($id, ['cantidad' => '0']));
});
