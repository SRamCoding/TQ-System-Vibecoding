<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentaRechazada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\Secciones\SeccionDeOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadRechazada;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las acciones de Oportunidades sin interfaz (plan 2.21, paso 10): cada una llama a OportunidadesService, que guarda lo
 * que cada paso pide y deja los cambios de etapa a TransicionesService. Empresas y personas inventadas.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, CatalogosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    OportunidadesDePrueba::sinExigirCotizacion();
    $this->travelTo('2026-11-06 10:00');
    $this->ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);
    $this->gerencia = User::factory()->create()->givePermissionTo([
        'crm.oportunidades.crear', 'crm.oportunidades.editar', 'crm.oportunidades.reasignar',
        ...array_map(fn (string $c): string => DefinicionOportunidad::CLAVE.".{$c}", ['calificar', 'enviar_propuesta', 'negociar', 'ganar', 'perder', 'anular']),
    ]);
});

function aoEjecutar(string $clave, array $datos, User $quien): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($clave, $datos, $quien);
}

/** @return array<string, mixed> */
function aoDatos(int $cuentaId, array $mas = []): array
{
    return [
        'cuenta_id' => $cuentaId, 'nombre' => 'Cableado del hospital', 'monto_estimado' => '24715.10',
        'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'fecha_cierre_estimada' => '2026-12-15',
        'linea_solucion_id' => LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id, ...$mas,
    ];
}

function aoCrear(int $cuentaId, User $quien, array $mas = []): ResumenOportunidad
{
    return aoEjecutar('crm.oportunidades.crear', aoDatos($cuentaId, $mas), $quien);
}

function aoEtapa(int $id): string
{
    return Estado::query()->findOrFail(Oportunidad::query()->findOrFail($id)->estado_id)->codigo;
}

function aoVersion(int $id): int
{
    return Oportunidad::query()->findOrFail($id)->version;
}

function aoDecisor(Cuenta $cuenta): int
{
    return PartyContacto::factory()->create(['party_id' => $cuenta->party_id])->id;
}

/** Calificada, en Propuesta enviada y en Negociación, completando la calificación en el primer panel. */
function aoANegociacion(int $id, Cuenta $cuenta, User $quien): void
{
    // Plan 2.21d, D8: la certificación ya estaba; la necesidad dice cómo.
    DB::table('oportunidades')->where('id', $id)->update(['presupuesto_confirmado' => true]);
    aoEjecutar(DefinicionOportunidad::CLAVE.'.calificar', ['oportunidad_id' => $id, 'version' => aoVersion($id), 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => aoDecisor($cuenta)], $quien);
    aoEjecutar(DefinicionOportunidad::CLAVE.'.enviar_propuesta', ['oportunidad_id' => $id, 'version' => aoVersion($id)], $quien);
    aoEjecutar(DefinicionOportunidad::CLAVE.'.negociar', ['oportunidad_id' => $id, 'version' => aoVersion($id)], $quien);
}

function aoMotivoPerder(bool $otro = false): int
{
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();

    return Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', $otro)->value('id');
}

test('el módulo registra sus acciones, y las de avanzar, ganar y perder llevan la clave de su transición', function () {
    $claves = array_keys(app(RegistroAcciones::class)->todas());

    expect($claves)->toContain('crm.oportunidades.crear', 'crm.oportunidades.editar', 'crm.oportunidades.editar-propias', 'crm.oportunidades.reasignar',
        'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder',
        'crm.oportunidades.guardar-competidor', 'crm.oportunidades.quitar-competidor-propias', 'crm.oportunidades.oportunidad.anular');
});

// ——— Crear ———

test('crear toma el ejecutivo y el origen de la cuenta y nace en Prospecto', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);

    expect($creada->etapa)->toBe('prospecto')
        ->and($creada->abierta)->toBeTrue()
        ->and($creada->ejecutivoUserId)->toBe($this->ejecutivo->id)
        ->and($creada->monto)->toBe('24715.10')
        ->and(Oportunidad::query()->findOrFail($creada->id)->origen_comercial_id)->toBe($this->cuenta->origen_comercial_id);
});

test('crear se rechaza sin permiso, en una cuenta inactiva, con un ejecutivo que no atiende, con monto cero o un decisor ajeno', function () {
    $inactiva = Cuenta::factory()->create(['es_activa' => false, 'motivo_inactiva_id' => Motivo::query()->value('id')]);
    $otraFicha = PartyContacto::factory()->create(['party_id' => Party::factory()->juridica()->create()->id])->id;

    expect(fn () => aoCrear($this->cuenta->id, User::factory()->create()))->toThrow(AccionRechazada::class)
        ->and(fn () => aoCrear($inactiva->id, $this->gerencia))->toThrow(CuentaRechazada::class, 'inactiva')
        ->and(fn () => aoCrear($this->cuenta->id, $this->gerencia, ['ejecutivo_user_id' => User::factory()->create()->id]))->toThrow(OportunidadRechazada::class, 'no puede atender')
        ->and(fn () => aoCrear($this->cuenta->id, $this->gerencia, ['monto_estimado' => '0']))->toThrow(OportunidadRechazada::class, 'mayor que cero')
        ->and(fn () => aoCrear($this->cuenta->id, $this->gerencia, ['decisor_contacto_id' => $otraFicha]))->toThrow(OportunidadRechazada::class, 'contacto de la empresa')
        ->and(fn () => aoCrear($this->cuenta->id, $this->gerencia, ['moneda_id' => null]))->toThrow(ValidationException::class)
        ->and(Oportunidad::query()->count())->toBe(0);
});

test('una sección de otro módulo suma sus campos a crear y guarda lo suyo en la misma transacción', function () {
    $seccion = new class implements SeccionDeOportunidad
    {
        public array $guardados = [];

        public function clave(): string
        {
            return 'proxima_accion';
        }

        public function titulo(): string
        {
            return 'Próxima acción';
        }

        public function formularios(): array
        {
            return [self::AL_CREAR];
        }

        public function campos(): array
        {
            return [Campo::fecha('fecha', 'Fecha')];
        }

        public function guardar(int $oportunidadId, string $formulario, array $datos, User $usuario): void
        {
            $this->guardados[] = [$oportunidadId, $datos['fecha']->toDateString()];
        }
    };
    app(SeccionesDeOportunidad::class)->registrar($seccion);

    expect(fn () => aoCrear($this->cuenta->id, $this->gerencia))->toThrow(ValidationException::class);

    $creada = aoCrear($this->cuenta->id, $this->gerencia, ['proxima_accion__fecha' => '2026-11-20']);

    expect($seccion->guardados)->toBe([[$creada->id, '2026-11-20']]);
});

// ——— Editar y reasignar ———

test('editar: con editar-propias solo las propias; una cerrada no se edita', function () {
    $propias = User::factory()->create()->givePermissionTo(['crm.oportunidades.editar-propias', CuentasService::PERMISO_ATENDER]);
    $propia = aoCrear($this->cuenta->id, $this->gerencia, ['ejecutivo_user_id' => $propias->id]);
    $ajena = aoCrear($this->cuenta->id, $this->gerencia);
    $datos = fn (ResumenOportunidad $o): array => ['oportunidad_id' => $o->id, 'version' => aoVersion($o->id), ...array_diff_key(aoDatos($this->cuenta->id, ['monto_estimado' => '30000.00']), ['cuenta_id' => 0])];

    $editada = aoEjecutar('crm.oportunidades.editar-propias', $datos($propia), $propias);

    expect($editada->monto)->toBe('30000.00')
        ->and($editada->version)->toBe(2)
        ->and(fn () => aoEjecutar('crm.oportunidades.editar-propias', $datos($ajena), $propias))->toThrow(OportunidadRechazada::class, 'Solo su ejecutivo');

    aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $ajena->id, 'version' => aoVersion($ajena->id), 'motivo_id' => aoMotivoPerder(), 'ganador_conocido' => 'no'], $this->gerencia);

    expect(fn () => aoEjecutar('crm.oportunidades.editar', $datos($ajena), $this->gerencia))->toThrow(OportunidadRechazada::class, 'ya está cerrada');
});

test('reasignar es todo o nada: si una está cerrada, ninguna cambia de ejecutivo', function () {
    $nuevo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $abierta = aoCrear($this->cuenta->id, $this->gerencia);
    $cerrada = aoCrear($this->cuenta->id, $this->gerencia);
    aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $cerrada->id, 'version' => aoVersion($cerrada->id), 'motivo_id' => aoMotivoPerder(), 'ganador_conocido' => 'no_se_sabe'], $this->gerencia);

    expect(fn () => aoEjecutar('crm.oportunidades.reasignar', ['oportunidad_ids' => [$abierta->id, $cerrada->id], 'ejecutivo_user_id' => $nuevo->id], $this->gerencia))
        ->toThrow(OportunidadRechazada::class, 'ya está cerrada')
        ->and(Oportunidad::query()->findOrFail($abierta->id)->ejecutivo_user_id)->toBe($this->ejecutivo->id)
        ->and(aoEjecutar('crm.oportunidades.reasignar', ['oportunidad_ids' => [$abierta->id], 'ejecutivo_user_id' => $nuevo->id], $this->gerencia))->toBe(1);
});

// ——— Avanzar y ganar ———

test('avanzar completa la calificación en el mismo panel; si falta algo, no guarda nada', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $calificar = DefinicionOportunidad::CLAVE.'.calificar';

    expect(fn () => aoEjecutar($calificar, ['oportunidad_id' => $creada->id, 'version' => 1, 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba'], $this->gerencia))
        ->toThrow(TransicionRechazada::class, 'Para avanzar falta: Decisor identificado, Presupuesto confirmado.')
        ->and(Oportunidad::query()->findOrFail($creada->id)->necesidad_confirmada)->toBeFalse();

    DB::table('oportunidades')->where('id', $creada->id)->update(['presupuesto_confirmado' => true]);
    aoEjecutar($calificar, ['oportunidad_id' => $creada->id, 'version' => 1, 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => aoDecisor($this->cuenta)], $this->gerencia);

    expect(aoEtapa($creada->id))->toBe('calificada');
});

test('ganar: la fecha nunca futura, puede ser anterior a la creación, y confirma el monto final', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    aoANegociacion($creada->id, $this->cuenta, $this->gerencia);
    $ganar = fn (string $fecha): mixed => aoEjecutar(DefinicionOportunidad::CLAVE.'.ganar', ['oportunidad_id' => $creada->id, 'version' => aoVersion($creada->id), 'fecha_cierre_real' => $fecha, 'monto_final' => '23000.00'], $this->gerencia);

    expect(fn () => $ganar('2026-11-07'))->toThrow(OportunidadRechazada::class, 'no puede ser futura');

    $ganar('2026-06-30');
    $ganada = Oportunidad::query()->findOrFail($creada->id);

    expect(aoEtapa($creada->id))->toBe('ganada')
        ->and($ganada->fecha_cierre_real?->toDateString())->toBe('2026-06-30')
        ->and((string) $ganada->monto_estimado)->toBe('23000.00')
        ->and($this->cuenta->refresh()->cliente_desde?->toDateString())->toBe('2026-06-30');
});

// ——— Perder (D4) ———

test('perder valida «¿Lo ganó otra empresa?»: con «Sí», empresa y precio o «precio desconocido»; con «No», nada', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $rival = Party::factory()->juridica()->create();
    $perder = fn (array $mas): mixed => aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $creada->id, 'version' => aoVersion($creada->id), 'motivo_id' => aoMotivoPerder(), ...$mas], $this->gerencia);
    $soles = Moneda::query()->where('codigo', 'PEN')->value('id');

    expect(fn () => $perder(['ganador_conocido' => 'si']))->toThrow(OportunidadRechazada::class, 'elige cuál')
        ->and(fn () => $perder(['ganador_conocido' => 'si', 'ganador_party_id' => $rival->id]))->toThrow(OportunidadRechazada::class, 'precio desconocido')
        ->and(fn () => $perder(['ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio' => '20000.00', 'moneda_id' => $soles, 'precio_desconocido' => true]))->toThrow(OportunidadRechazada::class, 'deja solo uno')
        ->and(fn () => $perder(['ganador_conocido' => 'no', 'ganador_party_id' => $rival->id]))->toThrow(OportunidadRechazada::class, 'no se elige competidor')
        ->and(fn () => $perder(['ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio' => '20000.00', 'moneda_id' => $soles, 'motivo_id' => aoMotivoPerder(otro: true)]))->toThrow(TransicionRechazada::class)
        ->and(aoEtapa($creada->id))->toBe('prospecto');

    $perder(['ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio' => '20000.00', 'moneda_id' => $soles]);
    $ganador = OportunidadCompetidor::query()->where('oportunidad_id', $creada->id)->sole();

    expect(aoEtapa($creada->id))->toBe('perdida')
        ->and(Oportunidad::query()->findOrFail($creada->id)->ganador_conocido)->toBe(GanadorConocido::Si)
        ->and($ganador->es_ganador)->toBeTrue()
        ->and((string) $ganador->precio)->toBe('20000.00')
        ->and(app(PartiesService::class)->tieneRol($rival->id, 'competidor'))->toBeTrue();
});

test('perder con «precio desconocido» guarda el ganador sin precio, y la ficha que ya era competidor no falla', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $rival = Party::factory()->juridica()->create();
    app(PartiesService::class)->asignarRol($rival->id, 'competidor', now()->toImmutable());

    aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $creada->id, 'version' => 1, 'motivo_id' => aoMotivoPerder(), 'ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio_desconocido' => true], $this->gerencia);
    $ganador = OportunidadCompetidor::query()->where('oportunidad_id', $creada->id)->sole();

    expect($ganador->precio)->toBeNull()
        ->and($ganador->precio_desconocido)->toBeTrue();
});

// ——— Competidores ———

test('competidores: se agregan con su ficha y precio con moneda, no se repiten y se quitan; en una cerrada, no', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $rival = Party::factory()->juridica()->create();
    $soles = Moneda::query()->where('codigo', 'PEN')->value('id');

    $competidor = aoEjecutar('crm.oportunidades.guardar-competidor', ['oportunidad_id' => $creada->id, 'party_id' => $rival->id, 'precio' => '21000.00', 'moneda_id' => $soles, 'fortalezas' => 'Precio bajo'], $this->gerencia);

    expect($competidor->es_ganador)->toBeFalse()
        ->and(app(PartiesService::class)->tieneRol($rival->id, 'competidor'))->toBeTrue()
        ->and(fn () => aoEjecutar('crm.oportunidades.guardar-competidor', ['oportunidad_id' => $creada->id, 'party_id' => $rival->id], $this->gerencia))->toThrow(OportunidadRechazada::class, 'ya está en la lista')
        ->and(fn () => aoEjecutar('crm.oportunidades.guardar-competidor', ['oportunidad_id' => $creada->id, 'party_id' => Party::factory()->juridica()->create()->id, 'precio' => '5.00'], $this->gerencia))->toThrow(OportunidadRechazada::class, 'con su moneda');

    aoEjecutar('crm.oportunidades.quitar-competidor', ['competidor_id' => $competidor->id, 'version' => $competidor->version], $this->gerencia);
    expect(OportunidadCompetidor::query()->where('oportunidad_id', $creada->id)->count())->toBe(0);

    aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $creada->id, 'version' => aoVersion($creada->id), 'motivo_id' => aoMotivoPerder(), 'ganador_conocido' => 'no'], $this->gerencia);

    expect(fn () => aoEjecutar('crm.oportunidades.guardar-competidor', ['oportunidad_id' => $creada->id, 'party_id' => $rival->id], $this->gerencia))->toThrow(OportunidadRechazada::class, 'ya está cerrada');
});

test('una ficha inactiva no se registra como competidor', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $inactiva = Party::factory()->juridica()->create(['es_activo' => false]);

    expect(fn () => aoEjecutar('crm.oportunidades.guardar-competidor', ['oportunidad_id' => $creada->id, 'party_id' => $inactiva->id], $this->gerencia))->toThrow(OportunidadRechazada::class, 'inactiva');
});

test('perder y ganar sin el permiso de su transición se rechazan', function () {
    $creada = aoCrear($this->cuenta->id, $this->gerencia);
    $sinPermiso = User::factory()->create()->givePermissionTo('crm.oportunidades.editar');

    expect(fn () => aoEjecutar(DefinicionOportunidad::CLAVE.'.perder', ['oportunidad_id' => $creada->id, 'version' => 1, 'motivo_id' => aoMotivoPerder(), 'ganador_conocido' => 'no'], $sinPermiso))->toThrow(AccionRechazada::class)
        ->and(OrigenComercial::query()->count())->toBeGreaterThan(0);
});
