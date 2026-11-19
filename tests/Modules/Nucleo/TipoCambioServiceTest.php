<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\CatalogoNoEncontrado;
use App\Modules\Nucleo\Excepciones\TipoCambioFaltante;
use App\Modules\Nucleo\Excepciones\TipoCambioInvalido;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    Moneda::factory()->create(['codigo' => 'PEN', 'nombre' => 'Sol', 'simbolo' => 'S/']);
    Moneda::factory()->create(['codigo' => 'USD', 'nombre' => 'Dólar estadounidense', 'simbolo' => 'US$']);
});

function tc(): TipoCambioService
{
    return app(TipoCambioService::class);
}

function dia(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha);
}

test('registra el TC del día y lo devuelve con compra y venta exactas', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);

    $delDia = tc()->del('USD', dia('2026-10-09'));

    expect($delDia->compra)->toBe('3.7480')
        ->and($delDia->venta)->toBe('3.7560')
        ->and($delDia->fecha->toDateString())->toBe('2026-10-09')
        ->and($delDia->origen)->toBe(OrigenTipoCambio::Manual);
});

test('corregir el TC del día lo reemplaza y la bitácora guarda el anterior', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.9560', OrigenTipoCambio::Manual);

    $fila = TipoCambio::query()->sole();
    $bitacora = AuditoriaCambio::query()->where('auditable_type', $fila->getMorphClass())->where('auditable_id', $fila->id)->latest('id')->firstOrFail();

    expect($fila->venta)->toBe('3.9560')
        ->and($bitacora->valores_anteriores)->toBe(['venta' => '3.7560']);
});

test('el margen inicial es de 4 días (ADR 0012)', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);

    expect(tc()->del('USD', dia('2026-10-13'))->fecha->toDateString())->toBe('2026-10-09')
        ->and(fn () => tc()->del('USD', dia('2026-10-14')))->toThrow(TipoCambioFaltante::class);
});

test('con margen 0, sin TC ese día es un error', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);
    app(ParametrosService::class)->cambiarDeDinero('tipo_cambio.dias_tolerancia', 0, versionLeida: 1);

    expect(fn () => tc()->del('USD', dia('2026-10-11')))
        ->toThrow(TipoCambioFaltante::class, 'No hay tipo de cambio de USD para el 11/10/2026');
});

test('con tolerancia, usa el último registrado dentro de esos días y no más atrás', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);
    app(ParametrosService::class)->cambiarDeDinero('tipo_cambio.dias_tolerancia', 3, versionLeida: 1);

    // Domingo 11: usa el del viernes 9. Martes 13: ya pasó la tolerancia.
    expect(tc()->del('USD', dia('2026-10-11'))->fecha->toDateString())->toBe('2026-10-09')
        ->and(fn () => tc()->del('USD', dia('2026-10-13')))->toThrow(TipoCambioFaltante::class);
});

test('nunca usa un TC posterior a la fecha pedida', function () {
    tc()->registrar('USD', dia('2026-10-10'), '3.7480', '3.7560', OrigenTipoCambio::Manual);
    app(ParametrosService::class)->cambiarDeDinero('tipo_cambio.dias_tolerancia', 3, versionLeida: 1);

    expect(fn () => tc()->del('USD', dia('2026-10-09')))->toThrow(TipoCambioFaltante::class);
});

test('la moneda local vale 1 y no se registra', function () {
    expect(tc()->del('PEN', dia('2026-10-09'))->venta)->toBe('1.0000')
        ->and(fn () => tc()->registrar('PEN', dia('2026-10-09'), '1', '1', OrigenTipoCambio::Manual))
        ->toThrow(TipoCambioInvalido::class, 'PEN es la moneda local');
});

test('rechaza valores imposibles antes de llegar a la base', function (string $compra, string $venta, string $campo) {
    expect(fn () => tc()->registrar('USD', dia('2026-10-09'), $compra, $venta, OrigenTipoCambio::Manual))
        ->toThrow(TipoCambioInvalido::class, "tipo de cambio de {$campo}");
})->with([
    'compra en cero' => ['0', '3.7560', 'compra'],
    'venta negativa' => ['3.7480', '-3.7560', 'venta'],
    'cinco decimales' => ['3.74801', '3.7560', 'compra'],
    'con coma' => ['3,7480', '3.7560', 'compra'],
]);

test('una moneda que no está en el catálogo o está desactivada no recibe TC', function () {
    Moneda::query()->where('codigo', 'USD')->update(['es_activa' => false]);

    expect(fn () => tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual))->toThrow(CatalogoNoEncontrado::class)
        ->and(fn () => tc()->registrar('EUR', dia('2026-10-09'), '4.1000', '4.2000', OrigenTipoCambio::Manual))->toThrow(CatalogoNoEncontrado::class);
});

test('la carga automática futura usa la misma puerta, con su origen', function () {
    tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Sunat);

    expect(tc()->del('USD', dia('2026-10-09'))->origen)->toBe(OrigenTipoCambio::Sunat)
        ->and(app(ParametrosService::class)->monedaLocal())->toBe('PEN');
});

test('dos registros simultáneos del mismo día no terminan en error: se reintenta', function () {
    // Simula otro proceso que crea el TC del día justo antes de que este guarde. Una sola vez: dentro de
    // la transacción de la prueba su fila se deshace con el intento fallido (en la realidad, otra conexión
    // la deja confirmada y el reintento la corrige con la fila bloqueada).
    $choco = false;
    TipoCambio::creating(function (TipoCambio $nuevo) use (&$choco): void {
        if (! $choco) {
            $choco = true;
            DB::table('tipos_cambio')->insert([
                'moneda_id' => $nuevo->moneda_id, 'fecha' => $nuevo->fecha->toDateString(), 'compra' => '3.7000',
                'venta' => '3.7100', 'origen' => 'sunat', 'created_by' => app(UsuarioSistema::class)->id(),
            ]);
        }
    });

    $resultado = tc()->registrar('USD', dia('2026-10-09'), '3.7480', '3.7560', OrigenTipoCambio::Manual);

    expect($choco)->toBeTrue()
        ->and($resultado->venta)->toBe('3.7560')
        ->and(TipoCambio::query()->count())->toBe(1)
        ->and(TipoCambio::query()->sole()->origen)->toBe(OrigenTipoCambio::Manual);
});
