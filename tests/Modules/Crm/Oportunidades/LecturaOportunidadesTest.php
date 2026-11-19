<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Lo que Oportunidades ofrece para leer (plan 2.21, D8, D12, D13): los aportes a Cuentas, la etiqueta en su historial,
 * buscar para otros módulos y el embudo en moneda local. Empresas inventadas.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, CatalogosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    $this->travelTo('2026-11-06 10:00');
    $this->cuenta = Cuenta::factory()->create();
    $this->gerencia = User::factory()->create()->givePermissionTo(['crm.oportunidades.editar', DefinicionOportunidad::CLAVE.'.perder', DefinicionOportunidad::CLAVE.'.anular']);
});

function loPerder(Oportunidad $oportunidad, User $quien): void
{
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();
    $motivo = Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->value('id');
    app(TransicionesService::class)->transicionar($oportunidad, 'perder', (int) $oportunidad->fresh()?->version, $quien, new MotivoElegido($motivo));
}

function loMoneda(string $codigo): int
{
    return Moneda::query()->where('codigo', $codigo)->value('id') ?? Moneda::factory()->create(['codigo' => $codigo])->id;
}

test('Cuentas recibe las abiertas de cada cuenta en lote y las filtra con la subconsulta de Oportunidades', function () {
    $otra = Cuenta::factory()->create();
    $sinNada = Cuenta::factory()->create();
    OportunidadesDePrueba::crear($this->cuenta->id);
    OportunidadesDePrueba::crear($this->cuenta->id);
    loPerder(OportunidadesDePrueba::crear($otra->id), $this->gerencia);
    $actividad = app(ActividadDeCuentas::class);

    $datos = $actividad->datosDe([$this->cuenta->id, $otra->id, $sinNada->id]);
    $conAbiertas = Cuenta::query();
    $actividad->conOportunidadesAbiertas($conAbiertas);

    expect($actividad->tiene(DatoActividad::OportunidadesAbiertas))->toBeTrue()
        ->and($datos[$this->cuenta->id]->oportunidadesAbiertas)->toBe(2)
        ->and($datos[$otra->id]->oportunidadesAbiertas)->toBeNull()
        ->and($conAbiertas->pluck('id')->all())->toBe([$this->cuenta->id]);
});

test('una cuenta con oportunidades abiertas no se desactiva: el mensaje dice cuántas', function () {
    OportunidadesDePrueba::crear($this->cuenta->id);
    OportunidadesDePrueba::crear($this->cuenta->id);
    $familia = TipoMotivo::query()->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR)->firstOrFail();
    $motivo = (int) Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->value('id');

    expect(fn () => app(CuentasService::class)->desactivar($this->cuenta->id, $this->cuenta->refresh()->version, $motivo, null, false, $this->gerencia))
        ->toThrow(CuentaRechazada::class, '2 oportunidades abiertas');
});

test('el historial de la cuenta nombra la oportunidad con su número y nombre', function () {
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id, ['nombre' => 'Cableado del hospital']);

    expect(app(OportunidadEnlazable::class)->etiqueta($oportunidad->id))->toBe("{$oportunidad->numero} · Cableado del hospital")
        ->and(app(OportunidadEnlazable::class)->etiqueta(999999))->toBe('Oportunidad #999999');
});

test('buscar ofrece las abiertas por número o nombre sin tildes, de una cuenta, y la del registro aunque esté cerrada', function () {
    $hospital = OportunidadesDePrueba::crear($this->cuenta->id, ['nombre' => 'Cableado del Hospital Las Mercedes']);
    $otraCuenta = OportunidadesDePrueba::crear(Cuenta::factory()->create()->id, ['nombre' => 'Hospital de otra cuenta']);
    $cerrada = OportunidadesDePrueba::crear($this->cuenta->id, ['nombre' => 'Hospital cerrado']);
    loPerder($cerrada, $this->gerencia);
    $servicio = app(OportunidadesService::class);
    $ids = fn (array $r): array => array_map(fn (ResumenOportunidad $o): int => $o->id, $r);

    expect($ids($servicio->buscar('hospital las mercedes')))->toBe([$hospital->id])
        ->and($ids($servicio->buscar('HOSPITAL', $this->cuenta->id)))->toBe([$hospital->id])
        ->and($ids($servicio->buscar($hospital->numero)))->toBe([$hospital->id])
        ->and($ids($servicio->buscar('hospital', $this->cuenta->id, incluirId: $cerrada->id)))->toBe([$cerrada->id, $hospital->id])
        ->and($servicio->resumen($cerrada->id)->abierta)->toBeFalse()
        ->and($servicio->resumen($otraCuenta->id)->etiqueta())->toBe("{$otraCuenta->numero} · Hospital de otra cuenta");
});

test('el embudo suma en soles al T.C. de compra, pondera por etapa y deja aparte la moneda sin tipo de cambio', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-06'), '3.7500', '3.7600', OrigenTipoCambio::Manual);
    OportunidadesDePrueba::crear($this->cuenta->id, ['monto_estimado' => '10000.00', 'moneda_id' => loMoneda('PEN')]);
    OportunidadesDePrueba::crear($this->cuenta->id, ['monto_estimado' => '2000.00', 'moneda_id' => loMoneda('USD')]);
    OportunidadesDePrueba::crear($this->cuenta->id, ['monto_estimado' => '500.00', 'moneda_id' => loMoneda('EUR')]);
    loPerder(OportunidadesDePrueba::crear($this->cuenta->id, ['monto_estimado' => '99999.00']), $this->gerencia);

    $embudo = app(OportunidadesService::class)->embudo(Oportunidad::query());

    // 10 000 + 2 000 × 3.75 = 17 500; las dos en Prospecto (10 %): 1 750. Los 500 euros, aparte.
    expect($embudo->cantidad)->toBe(2)
        ->and($embudo->total)->toBe('17500.00')
        ->and($embudo->ponderado)->toBe('1750.00')
        ->and($embudo->convertido)->toBe(['USD' => ['monto' => '2000.00', 'tipoCambio' => '3.7500']])
        ->and($embudo->sinTipoCambio)->toBe(['EUR' => ['monto' => '500.00', 'cantidad' => 1]]);
});
