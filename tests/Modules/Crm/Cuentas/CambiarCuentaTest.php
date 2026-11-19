<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\AporteActividad;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\Alertas\CuentasConEjecutivoInactivo;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Events\EjecutivoDeCuentaCambiado;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\MotivosService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

/*
 * Editar, reasignar, desactivar y reactivar una cuenta, y la alerta de ejecutivo inactivo (plan 2.20, D7 y D9), por las
 * acciones registradas y sin pantalla.
 */

function hacerConCuenta(string $accion, User $usuario, array $datos): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($accion, $datos, $usuario);
}

function motivoDeCuenta(bool $otro = false): Motivo
{
    $familia = TipoMotivo::query()->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR)->firstOrFail();

    return Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', $otro)->orderBy('id')->firstOrFail();
}

function motivoCreadaPorError(): Motivo
{
    return Motivo::query()->where('codigo', CuentasServiceProvider::MOTIVO_CREADA_POR_ERROR)->firstOrFail();
}

function usuarioDeCuentasCon(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

function hacerPublica(Cuenta $cuenta): void
{
    PartyRol::factory()->create(['party_id' => $cuenta->party_id, 'rol_party_id' => RolParty::query()->where('codigo', 'entidad')->value('id')]);
}

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, PermisosSeeder::class, EstadosSeeder::class]);
    RolParty::factory()->create(['codigo' => 'cliente', 'nombre' => 'Cliente']);
    RolParty::factory()->create(['codigo' => 'entidad', 'nombre' => 'Entidad pública']);
    $this->origen = OrigenComercial::factory()->create();
    $this->nivel = NivelGobierno::factory()->create();
    $this->ejecutivo = usuarioDeCuentasCon('crm.cuentas.atender');
    $this->cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);
});

test('editar cambia origen y notas sin tocar el ejecutivo, y compara la versión leída', function () {
    $editor = usuarioDeCuentasCon('crm.cuentas.editar');

    $resumen = hacerConCuenta('crm.cuentas.editar', $editor, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id, 'notas' => 'Visitar en enero']);

    expect($this->cuenta->refresh()->origen_comercial_id)->toBe($this->origen->id)
        ->and($this->cuenta->notas)->toBe('Visitar en enero')
        ->and($this->cuenta->version)->toBe(2)
        ->and($resumen->ejecutivoUserId)->toBe($this->ejecutivo->id)
        ->and(fn () => hacerConCuenta('crm.cuentas.editar', $editor, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id]))->toThrow(VersionDesactualizada::class);
});

test('editar-propias solo deja editar las cuentas que uno atiende', function () {
    $this->ejecutivo->givePermissionTo('crm.cuentas.editar-propias');
    $otro = usuarioDeCuentasCon('crm.cuentas.editar-propias');
    $datos = ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id];

    expect(fn () => hacerConCuenta('crm.cuentas.editar-propias', $otro, $datos))->toThrow(CuentaRechazada::class, 'Solo puedes editar las cuentas que atiendes')
        ->and(hacerConCuenta('crm.cuentas.editar-propias', $this->ejecutivo, $datos)->id)->toBe($this->cuenta->id)
        ->and(fn () => hacerConCuenta('crm.cuentas.editar', $this->ejecutivo, [...$datos, 'version' => 2]))->toThrow(AccionRechazada::class);
});

test('en una pública el nivel es obligatorio; en una privada el guardado se conserva', function () {
    $editor = usuarioDeCuentasCon('crm.cuentas.editar');
    $privada = Cuenta::factory()->create(['nivel_gobierno_id' => $this->nivel->id]);
    hacerPublica($this->cuenta);

    expect(fn () => hacerConCuenta('crm.cuentas.editar', $editor, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id]))->toThrow(CuentaRechazada::class, 'necesita su nivel')
        ->and(hacerConCuenta('crm.cuentas.editar', $editor, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id, 'nivel_gobierno_id' => $this->nivel->id])->nivelGobiernoId)->toBe($this->nivel->id)
        ->and(hacerConCuenta('crm.cuentas.editar', $editor, ['cuenta_id' => $privada->id, 'version' => 1, 'origen_comercial_id' => $this->origen->id])->nivelGobiernoId)->toBe($this->nivel->id);
});

test('reasignar varias es todo o nada, salta las que ya son del nuevo, avisa una sola vez y dispara el evento por cada cambio', function () {
    Event::fake([EjecutivoDeCuentaCambiado::class]);
    $gerencia = usuarioDeCuentasCon('crm.cuentas.reasignar');
    $nuevo = usuarioDeCuentasCon('crm.cuentas.atender');
    $segunda = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);
    $yaSuya = Cuenta::factory()->create(['ejecutivo_user_id' => $nuevo->id]);

    $cambiadas = hacerConCuenta('crm.cuentas.reasignar', $gerencia, ['cuenta_ids' => [$this->cuenta->id, $segunda->id, $yaSuya->id], 'ejecutivo_user_id' => $nuevo->id, 'incluir_oportunidades_abiertas' => true]);

    expect($cambiadas)->toBe(2)
        ->and(Cuenta::query()->where('ejecutivo_user_id', $nuevo->id)->count())->toBe(3)
        ->and($yaSuya->refresh()->version)->toBe(1)
        ->and(Notificacion::query()->where('type', CuentasServiceProvider::AVISO_ASIGNADAS)->where('notifiable_id', $nuevo->id)->count())->toBe(1)
        ->and(Notificacion::query()->where('type', CuentasServiceProvider::AVISO_ASIGNADAS)->value('data')['title'] ?? null)->toBe('Se te asignaron 2 cuentas');
    Event::assertDispatchedTimes(EjecutivoDeCuentaCambiado::class, 2);
    Event::assertDispatched(fn (EjecutivoDeCuentaCambiado $e): bool => $e->cuentaId === $segunda->id && $e->ejecutivoAnteriorUserId === $this->ejecutivo->id && $e->incluyeOportunidadesAbiertas);
});

test('reasignar rechaza un ejecutivo que no puede atender y la versión con varias, sin cambiar nada', function () {
    $gerencia = usuarioDeCuentasCon('crm.cuentas.reasignar');
    $sinPermiso = User::factory()->create();
    $nuevo = usuarioDeCuentasCon('crm.cuentas.atender');
    $segunda = Cuenta::factory()->create();

    expect(fn () => hacerConCuenta('crm.cuentas.reasignar', $gerencia, ['cuenta_ids' => [$this->cuenta->id], 'ejecutivo_user_id' => $sinPermiso->id, 'incluir_oportunidades_abiertas' => false]))->toThrow(CuentaRechazada::class, 'no puede atender')
        ->and(fn () => hacerConCuenta('crm.cuentas.reasignar', $gerencia, ['cuenta_ids' => [$this->cuenta->id, $segunda->id], 'ejecutivo_user_id' => $nuevo->id, 'incluir_oportunidades_abiertas' => false, 'version' => 1]))->toThrow(CuentaRechazada::class, 'solo vale al reasignar una')
        ->and(fn () => hacerConCuenta('crm.cuentas.reasignar', $gerencia, ['cuenta_ids' => [$this->cuenta->id], 'ejecutivo_user_id' => $nuevo->id, 'incluir_oportunidades_abiertas' => false, 'version' => 7]))->toThrow(VersionDesactualizada::class)
        ->and($this->cuenta->refresh()->ejecutivo_user_id)->toBe($this->ejecutivo->id)
        ->and(hacerConCuenta('crm.cuentas.reasignar', $gerencia, ['cuenta_ids' => [$this->cuenta->id], 'ejecutivo_user_id' => $nuevo->id, 'incluir_oportunidades_abiertas' => false, 'version' => 1]))->toBe(1)
        ->and(Notificacion::query()->where('type', CuentasServiceProvider::AVISO_ASIGNADAS)->value('data')['title'] ?? '')->toStartWith('Se te asignó la cuenta ');
});

test('desactivar exige un motivo de su lista, con detalle solo en «Otro», y la cuenta deja de ser usable', function () {
    $gerencia = usuarioDeCuentasCon('crm.cuentas.desactivar');
    $base = ['cuenta_id' => $this->cuenta->id, 'version' => 1];
    $deOtraLista = Motivo::query()->whereNot('tipo_motivo_id', motivoDeCuenta()->tipo_motivo_id)->value('id') ?? 999_999;

    expect(fn () => hacerConCuenta('crm.cuentas.desactivar', $gerencia, [...$base, 'motivo_id' => $deOtraLista]))->toThrow(CuentaRechazada::class, 'Elige un motivo de la lista')
        ->and(fn () => hacerConCuenta('crm.cuentas.desactivar', $gerencia, [...$base, 'motivo_id' => motivoDeCuenta()->id, 'detalle' => 'sobra']))->toThrow(CuentaRechazada::class, 'solo se escribe cuando el motivo es «Otro»')
        ->and(fn () => hacerConCuenta('crm.cuentas.desactivar', $gerencia, [...$base, 'motivo_id' => motivoDeCuenta(otro: true)->id, 'detalle' => 'corto']))->toThrow(CuentaRechazada::class, 'al menos 10 caracteres');

    $hecho = hacerConCuenta('crm.cuentas.desactivar', $gerencia, [...$base, 'motivo_id' => motivoDeCuenta(otro: true)->id, 'detalle' => 'Se fusionó con otra empresa']);

    expect($hecho->cuenta->usable)->toBeFalse()
        ->and($this->cuenta->refresh()->motivo_inactiva_detalle)->toBe('Se fusionó con otra empresa')
        ->and(fn () => hacerConCuenta('crm.cuentas.desactivar', $gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 2, 'motivo_id' => motivoDeCuenta()->id]))->toThrow(CuentaRechazada::class, 'ya está inactiva')
        ->and(app(MotivosService::class)->detallesDeOtro(CuentasServiceProvider::FAMILIA_DESACTIVAR)[0]->texto ?? null)->toBe('Se fusionó con otra empresa');
});

test('no se desactiva con trabajo en curso: lo dicen los aportes, con su número', function () {
    app(ActividadDeCuentas::class)->registrar(new class implements AporteActividad
    {
        public function datos(): array
        {
            return [DatoActividad::Facturado];
        }

        public function leer(array $cuentaIds): array
        {
            return [];
        }

        public function impedimentosParaDesactivar(int $cuentaId): array
        {
            return ['2 oportunidades abiertas', '1 licitación en curso'];
        }
    });

    expect(fn () => hacerConCuenta('crm.cuentas.desactivar', usuarioDeCuentasCon('crm.cuentas.desactivar'), ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'motivo_id' => motivoDeCuenta()->id]))
        ->toThrow(CuentaRechazada::class, 'tiene 2 oportunidades abiertas, 1 licitación en curso')
        ->and($this->cuenta->refresh()->es_activa)->toBeTrue();
});

test('creada por error: desactiva también la ficha si solo es Cliente y hay permiso; si no, solo avisa', function () {
    $conPermiso = usuarioDeCuentasCon('crm.cuentas.desactivar', 'parties.desactivar');
    $sinPermiso = usuarioDeCuentasCon('crm.cuentas.desactivar');
    PartyRol::factory()->create(['party_id' => $this->cuenta->party_id, 'rol_party_id' => RolParty::query()->where('codigo', 'cliente')->value('id')]);
    $conOtroTipo = Cuenta::factory()->create();
    hacerPublica($conOtroTipo);
    $terceraCuenta = Cuenta::factory()->create();
    $datos = fn (Cuenta $c): array => ['cuenta_id' => $c->id, 'version' => 1, 'motivo_id' => motivoCreadaPorError()->id, 'desactivar_ficha' => true];

    // Con otro motivo, la ficha no se toca: la regla vive en el servicio, no solo en la pantalla (D9.2).
    expect(fn () => hacerConCuenta('crm.cuentas.desactivar', $conPermiso, [...$datos($this->cuenta), 'motivo_id' => motivoDeCuenta()->id]))
        ->toThrow(CuentaRechazada::class, 'solo se desactiva junto con la cuenta si el motivo es «Creada por error');

    $hecha = hacerConCuenta('crm.cuentas.desactivar', $conPermiso, $datos($this->cuenta));
    $conOtro = hacerConCuenta('crm.cuentas.desactivar', $conPermiso, $datos($conOtroTipo));
    $sin = hacerConCuenta('crm.cuentas.desactivar', $sinPermiso, $datos($terceraCuenta));

    expect($hecha->fichaDesactivada)->toBeTrue()
        ->and($this->cuenta->party->refresh()->es_activo)->toBeFalse()
        ->and($conOtro->fichaDesactivada)->toBeFalse()
        ->and($conOtro->avisoFicha)->toContain('tiene otros tipos')
        ->and($sin->fichaDesactivada)->toBeFalse()
        ->and($sin->avisoFicha)->toContain('pide a quien puede desactivar fichas')
        ->and($terceraCuenta->party->refresh()->es_activo)->toBeTrue();
});

test('reactivar limpia el motivo y exige la ficha activa', function () {
    $gerencia = usuarioDeCuentasCon('crm.cuentas.desactivar');
    hacerConCuenta('crm.cuentas.desactivar', $gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 1, 'motivo_id' => motivoDeCuenta()->id]);
    $deFichaInactiva = Cuenta::factory()->create(['es_activa' => false, 'motivo_inactiva_id' => motivoDeCuenta()->id]);
    $deFichaInactiva->party->forceFill(['es_activo' => false])->save();

    $resumen = hacerConCuenta('crm.cuentas.reactivar', $gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 2]);

    expect($resumen->usable)->toBeTrue()
        ->and($this->cuenta->refresh()->motivo_inactiva_id)->toBeNull()
        ->and(fn () => hacerConCuenta('crm.cuentas.reactivar', $gerencia, ['cuenta_id' => $this->cuenta->id, 'version' => 3]))->toThrow(CuentaRechazada::class, 'ya está activa')
        ->and(fn () => hacerConCuenta('crm.cuentas.reactivar', $gerencia, ['cuenta_id' => $deFichaInactiva->id, 'version' => 1]))->toThrow(CuentaRechazada::class, 'Esta ficha está inactiva');
});

test('la alerta cuenta solo las cuentas usables con ejecutivo inactivo, y no sale si no hay', function () {
    $fuente = app(CuentasConEjecutivoInactivo::class);
    $emitida = fn (string $dia): ?AlertaEmitida => AlertaEmitida::query()->where('clave', CuentasServiceProvider::ALERTA_EJECUTIVO_INACTIVO.':'.$dia)->first();

    $fuente->revisar(CarbonImmutable::parse('2026-11-04'));
    $this->ejecutivo->forceFill(['es_activo' => false])->save();
    Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id, 'es_activa' => false, 'motivo_inactiva_id' => motivoDeCuenta()->id]);
    $fuente->revisar(CarbonImmutable::parse('2026-11-05'));

    expect($emitida('2026-11-04'))->toBeNull()
        ->and($emitida('2026-11-05'))->not->toBeNull()
        ->and(app(CuentasService::class)->conEjecutivoInactivo(Cuenta::query())->pluck('id')->all())->toBe([$this->cuenta->id]);
});
