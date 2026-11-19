<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Cuentas\Actividad\OportunidadSinModulo;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Prospecto o cliente (plan 2.20, D8): marcas por oportunidad ganada (para CRM-02) y a mano, y la coherencia de
 * `cliente_desde` con la marca vigente más antigua.
 */

function marcarConAccion(string $accion, User $usuario, array $datos): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($accion, $datos, $usuario);
}

/** `cliente_desde` debe ser siempre la fecha vigente más antigua (o nula sin marcas). */
function esperarCoherencia(Cuenta $cuenta): void
{
    $masAntigua = CuentaMarcaCliente::query()->where('cuenta_id', $cuenta->id)->whereNull('quitada_en')->min('fecha');

    expect($cuenta->refresh()->cliente_desde?->toDateString())->toBe($masAntigua === null ? null : CarbonImmutable::parse($masAntigua)->toDateString());
}

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, PermisosSeeder::class, EstadosSeeder::class]);
    $this->travelTo('2026-11-06 10:00');
    $this->servicio = app(CuentasService::class);
    $this->cuenta = Cuenta::factory()->create();
    $this->gerencia = User::factory()->create()->givePermissionTo(['crm.cuentas.marcar-cliente', 'crm.cuentas.quitar-marca-cliente']);
});

test('coherencia: cliente desde es la marca vigente más antigua tras marcar, revertir, marcar a mano y quitar', function () {
    [$o101, $o102] = [OportunidadesDePrueba::id($this->cuenta->id), OportunidadesDePrueba::id($this->cuenta->id)];
    $this->servicio->marcarCliente($this->cuenta->id, CarbonImmutable::parse('2026-06-10'), $o101);
    esperarCoherencia($this->cuenta);

    // Una ganada cargada después con cierre anterior corrige la fecha; repetir una marca no la duplica.
    $this->servicio->marcarCliente($this->cuenta->id, CarbonImmutable::parse('2026-03-02'), $o102);
    $this->servicio->marcarCliente($this->cuenta->id, CarbonImmutable::parse('2026-03-02'), $o102);
    expect($this->cuenta->marcasCliente()->count())->toBe(2);
    esperarCoherencia($this->cuenta);

    marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => $this->cuenta->refresh()->version, 'fecha' => '2025-01-15', 'comentario' => 'Cliente desde antes del sistema; contrato 2025']);
    esperarCoherencia($this->cuenta);
    expect($this->cuenta->cliente_desde?->toDateString())->toBe('2025-01-15');

    $this->servicio->revertirCliente($this->cuenta->id, $o102);
    esperarCoherencia($this->cuenta);
    $this->servicio->revertirCliente($this->cuenta->id, $o101);
    esperarCoherencia($this->cuenta);
    expect($this->cuenta->cliente_desde?->toDateString())->toBe('2025-01-15');

    marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => $this->cuenta->refresh()->version, 'motivo' => 'Se marcó la cuenta equivocada']);
    esperarCoherencia($this->cuenta);
    expect($this->cuenta->cliente_desde)->toBeNull();

    // Revertir sin marca de esa oportunidad no hace nada; una manual nueva puede volver a ponerse.
    $this->servicio->revertirCliente($this->cuenta->id, 999);
    marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => $this->cuenta->refresh()->version, 'fecha' => '2026-02-01', 'comentario' => 'Contrato firmado en febrero']);
    esperarCoherencia($this->cuenta);
    expect($this->cuenta->cliente_desde?->toDateString())->toBe('2026-02-01');
});

test('marcar a mano: fecha no futura, comentario obligatorio, una sola vigente y en una cuenta activa', function () {
    $datos = ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'fecha' => '2026-11-07', 'comentario' => 'Contrato 2024'];

    expect(fn () => marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, $datos))->toThrow(CuentaRechazada::class, 'no puede ser futura')
        ->and(fn () => marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, [...$datos, 'fecha' => '2026-11-06', 'comentario' => '   ']))->toThrow(ValidationException::class)
        ->and(fn () => $this->servicio->marcarClienteManual($this->cuenta->id, 1, CarbonImmutable::parse('2026-11-06'), '  '))->toThrow(CuentaRechazada::class, 'Cuenta por qué es cliente');

    $resumen = marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, [...$datos, 'fecha' => '2026-11-06']);

    expect($resumen->esCliente())->toBeTrue()
        ->and(fn () => marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, [...$datos, 'version' => 2, 'fecha' => '2026-01-01']))->toThrow(CuentaRechazada::class, 'ya está marcada como cliente a mano')
        ->and(fn () => marcarConAccion('crm.cuentas.marcar-cliente', User::factory()->create(), $datos))->toThrow(AccionRechazada::class);
});

test('quitar la marca: solo la manual y sin ganadas, con motivo escrito; la marca queda anulada, no borrada', function () {
    marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'fecha' => '2026-01-10', 'comentario' => 'Contrato 2025']);
    $datos = ['cuenta_id' => $this->cuenta->id, 'version' => 2, 'motivo' => 'Se marcó por error'];

    expect(fn () => marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, [...$datos, 'motivo' => 'error']))->toThrow(CuentaRechazada::class, 'al menos 10 caracteres');

    $o300 = OportunidadesDePrueba::id($this->cuenta->id);
    $this->servicio->marcarCliente($this->cuenta->id, CarbonImmutable::parse('2026-05-05'), $o300);
    // Una ganada más reciente no cambia «cliente desde» ni la versión.
    expect(fn () => marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, $datos))->toThrow(CuentaRechazada::class, 'por oportunidades ganadas');

    $this->servicio->revertirCliente($this->cuenta->id, $o300);
    $resumen = marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, [...$datos, 'version' => $this->cuenta->refresh()->version]);
    $anulada = CuentaMarcaCliente::query()->where('cuenta_id', $this->cuenta->id)->sole();

    expect($resumen->esCliente())->toBeFalse()
        ->and($anulada->motivo_quitada)->toBe('Se marcó por error')
        ->and($anulada->quitada_por_user_id)->toBe($this->gerencia->id)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'cuenta_marca_cliente')->where('auditable_id', $anulada->id)->where('evento', 'actualizado')->exists())->toBeTrue()
        ->and(fn () => marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, [...$datos, 'version' => $this->cuenta->refresh()->version]))->toThrow(CuentaRechazada::class, 'no tiene una marca de cliente puesta a mano');
});

test('una cuenta inactiva no se marca ni se le quita la marca hasta reactivarla', function () {
    marcarConAccion('crm.cuentas.marcar-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'fecha' => '2026-01-10', 'comentario' => 'Contrato 2025']);
    $this->cuenta->refresh()->forceFill(['es_activa' => false, 'motivo_inactiva_id' => Motivo::query()->where('es_otro', false)->value('id')])->save();

    expect(fn () => marcarConAccion('crm.cuentas.quitar-marca-cliente', $this->gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => $this->cuenta->version, 'motivo' => 'Se marcó por error']))
        ->toThrow(CuentaRechazada::class, 'reactívala antes de editarla');
});

test('la base no deja anular una marca a medias ni la de una oportunidad', function () {
    $manual = CuentaMarcaCliente::factory()->create(['cuenta_id' => $this->cuenta->id]);
    $ganada = CuentaMarcaCliente::factory()->deOportunidad(OportunidadesDePrueba::id($this->cuenta->id))->create(['cuenta_id' => $this->cuenta->id]);

    expect(fn () => $manual->forceFill(['quitada_en' => now()])->save())->toThrow(QueryException::class, 'cuenta_marcas_cliente_quitada_check')
        ->and(fn () => $ganada->forceFill(['quitada_en' => now(), 'quitada_por_user_id' => $this->gerencia->id, 'motivo_quitada' => 'No vale'])->save())->toThrow(QueryException::class, 'cuenta_marcas_cliente_quitada_check');
});

test('el historial nombra la oportunidad de la marca con la etiqueta que registre Oportunidades (plan 2.21, D13.3)', function () {
    $id = OportunidadesDePrueba::id($this->cuenta->id);
    $this->servicio->marcarCliente($this->cuenta->id, CarbonImmutable::parse('2026-06-10'), $id);
    $marca = CuentaMarcaCliente::query()->where('oportunidad_id', $id)->firstOrFail();
    app()->bind(OportunidadEnlazable::class, OportunidadSinModulo::class);

    expect($marca->resumenCreacionBitacora(['fecha' => '2026-06-10', 'origen' => 'oportunidad', 'oportunidad_id' => $id]))
        ->toBe("Cliente desde 10/06/2026 (Oportunidad #{$id})");

    app()->bind(OportunidadEnlazable::class, fn (): OportunidadEnlazable => new class implements OportunidadEnlazable
    {
        public function etiqueta(int $oportunidadId): string
        {
            return "OP-2026-{$oportunidadId} · Cableado del hospital";
        }
    });

    expect($marca->valorLegibleBitacora('oportunidad_id', $id))->toBe("OP-2026-{$id} · Cableado del hospital");
});
