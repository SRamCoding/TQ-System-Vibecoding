<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Events\EjecutivoDeCuentaCambiado;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Listeners\TraspasaOportunidadesAlReasignarCuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\AlertasSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Al reasignar una cuenta con «pasar también sus oportunidades abiertas» (plan 2.21, D5): pasan solo las abiertas del
 * ejecutivo anterior, en la cola, y repetirlo no cambia nada. Si agota sus reintentos, salta una alerta.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    $this->anterior = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->nuevo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->otro = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->anterior->id]);
});

function toEjecutivo(int $id): int
{
    return Oportunidad::query()->findOrFail($id)->ejecutivo_user_id;
}

test('pasan solo las abiertas del ejecutivo anterior; las de otro y las cerradas se quedan; repetirlo no cambia nada', function () {
    $suya = OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $this->anterior->id]);
    $deOtro = OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $this->otro->id]);
    $cerrada = OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $this->anterior->id]);
    $gerencia = User::factory()->create()->givePermissionTo(['crm.oportunidades.editar', DefinicionOportunidad::CLAVE.'.perder']);
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();
    app(TransicionesService::class)->transicionar($cerrada, 'perder', 1, $gerencia, new MotivoElegido(Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->value('id')));
    $servicio = app(OportunidadesService::class);
    DB::table('cuentas')->where('id', $this->cuenta->id)->update(['ejecutivo_user_id' => $this->nuevo->id]);

    expect($servicio->traspasarDeEjecutivo($this->cuenta->id, $this->anterior->id))->toBe(1)
        ->and(toEjecutivo($suya->id))->toBe($this->nuevo->id)
        ->and(toEjecutivo($deOtro->id))->toBe($this->otro->id)
        ->and(toEjecutivo($cerrada->id))->toBe($this->anterior->id)
        ->and($servicio->traspasarDeEjecutivo($this->cuenta->id, $this->anterior->id))->toBe(0);
});

test('de punta a punta: reasignar la cuenta con la casilla pasa sus abiertas; sin la casilla, no', function () {
    $conCasilla = OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $this->anterior->id]);
    $otraCuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->anterior->id]);
    $sinCasilla = OportunidadesDePrueba::crear($otraCuenta->id, ['ejecutivo_user_id' => $this->anterior->id]);

    app(CuentasService::class)->reasignar([$this->cuenta->id], $this->nuevo->id, true);
    app(CuentasService::class)->reasignar([$otraCuenta->id], $this->nuevo->id, false);

    expect(toEjecutivo($conCasilla->id))->toBe($this->nuevo->id)
        ->and(toEjecutivo($sinCasilla->id))->toBe($this->anterior->id)
        ->and(new TraspasaOportunidadesAlReasignarCuenta)->toBeInstanceOf(ShouldQueue::class);
});

test('si el traspaso agota sus reintentos, sale la alerta a Gerencia con la cuenta y los dos ejecutivos', function () {
    $this->seed(AlertasSeeder::class);
    $evento = new EjecutivoDeCuentaCambiado($this->cuenta->id, $this->anterior->id, $this->nuevo->id, true);

    (new TraspasaOportunidadesAlReasignarCuenta)->failed($evento, new RuntimeException('La base no respondió'));

    expect(AlertaEmitida::query()->where('clave', OportunidadesServiceProvider::ALERTA_TRASPASO_FALLIDO.":cuenta:{$this->cuenta->id}:de:{$this->anterior->id}:a:{$this->nuevo->id}")->exists())->toBeTrue();
});

test('dos reasignaciones seguidas procesadas fuera de orden: las oportunidades terminan con el ejecutivo vigente de la cuenta', function () {
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $this->anterior->id]);
    $listener = new TraspasaOportunidadesAlReasignarCuenta;
    // La cuenta pasó de «anterior» a «nuevo» y enseguida de «nuevo» a «otro».
    DB::table('cuentas')->where('id', $this->cuenta->id)->update(['ejecutivo_user_id' => $this->otro->id]);

    // El segundo evento corre primero (el primero falló y espera su reintento): no encuentra nada de «nuevo».
    $listener->handle(new EjecutivoDeCuentaCambiado($this->cuenta->id, $this->nuevo->id, $this->otro->id, true));
    // El reintento del primero: pasa al ejecutivo vigente, no al «nuevo» del evento.
    $listener->handle(new EjecutivoDeCuentaCambiado($this->cuenta->id, $this->anterior->id, $this->nuevo->id, true));

    expect(toEjecutivo($oportunidad->id))->toBe($this->otro->id);
});
