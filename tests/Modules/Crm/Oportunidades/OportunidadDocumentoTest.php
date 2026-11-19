<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Events\OportunidadGanada;
use App\Modules\Crm\Oportunidades\Events\OportunidadGanadaAnulada;
use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentoDeOportunidad;
use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentosDeOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitoDeEtapa;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Estados\DocumentoQueImpide;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\RevisionAntesDeAnular;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La oportunidad como documento con estados (plan 2.21, paso 8): D1 (etapas, transiciones, anular y anular_ganada),
 * D2 (en_etapa_desde), D3 (ganar: la marca de cliente y OportunidadGanada), D9 (requisitos acumulados), D10 («propias»)
 * y D12 (impedimentos). Personas y empresas inventadas.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    OportunidadesDePrueba::sinExigirCotizacion();
    $this->travelTo('2026-11-06 10:00');
    foreach (['crm.oportunidades.editar', 'crm.oportunidades.editar-propias'] as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }
    $this->cuenta = Cuenta::factory()->create();
    $this->gerencia = odPersona('crm.oportunidades.editar', ...odTransiciones());
});

/** @return list<string> */
function odTransiciones(): array
{
    return array_map(fn (string $c): string => DefinicionOportunidad::CLAVE.".{$c}", ['calificar', 'enviar_propuesta', 'negociar', 'retroceder', 'ganar', 'perder', 'anular', 'anular_ganada']);
}

function odPersona(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

function odEtapa(Oportunidad $oportunidad): string
{
    return Estado::query()->findOrFail($oportunidad->fresh()?->estado_id)->codigo;
}

function odMover(Oportunidad $oportunidad, string $transicion, User $quien, ?MotivoElegido $motivo = null): HistorialEstado
{
    return app(TransicionesService::class)->transicionar($oportunidad, $transicion, (int) $oportunidad->fresh()?->version, $quien, $motivo);
}

function odMotivo(string $transicion, bool $otro = false): MotivoElegido
{
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.".{$transicion}")->firstOrFail();
    $motivo = Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', $otro)->firstOrFail();

    return new MotivoElegido($motivo->id, $otro ? 'Detalle escrito por la persona' : null);
}

/** Cumple lo que pide calificar (D9): necesidad, presupuesto y un decisor de la ficha de la cuenta. */
function odCalificable(Oportunidad $oportunidad): Oportunidad
{
    $cuenta = Cuenta::query()->findOrFail($oportunidad->cuenta_id);
    $decisor = PartyContacto::factory()->create(['party_id' => $cuenta->party_id]);
    $oportunidad->forceFill(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true, 'decisor_contacto_id' => $decisor->id])->save();

    return $oportunidad;
}

/** Hasta Negociación, cumpliendo los requisitos, y gana con la fecha de cierre dada. */
function odGanar(Oportunidad $oportunidad, string $fechaCierre, User $quien): Oportunidad
{
    odCalificable($oportunidad);
    foreach (['calificar', 'enviar_propuesta', 'negociar'] as $paso) {
        odMover($oportunidad, $paso, $quien);
    }
    $oportunidad->refresh()->forceFill(['fecha_cierre_real' => $fechaCierre])->save();
    odMover($oportunidad, 'ganar', $quien);

    return $oportunidad->refresh();
}

/** D2: en_etapa_desde es la fecha (en Lima) de la última entrada al estado actual en el historial. */
function odCoherente(Oportunidad $oportunidad): void
{
    $fila = $oportunidad->fresh();
    $ultima = HistorialEstado::query()->where('documentable_type', 'oportunidad')->where('documentable_id', $oportunidad->id)
        ->where('hacia_estado_id', $fila?->estado_id)->latest('id')->firstOrFail();

    expect($fila?->en_etapa_desde->toDateString())->toBe($ultima->fecha->timezone('America/Lima')->toDateString());
}

function odClienteDesde(int $cuentaId): ?string
{
    return app(CuentasService::class)->resumen($cuentaId)->clienteDesde?->toDateString();
}

// ——— Nacer y avanzar ———

test('nace en Prospecto con su número OP-AAAA-NNNN y en la etapa desde hoy', function () {
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id);

    expect(odEtapa($oportunidad))->toBe('prospecto')
        ->and($oportunidad->numero)->toBe('OP-2026-0001')
        ->and($oportunidad->en_etapa_desde->toDateString())->toBe('2026-11-06')
        ->and(fn () => $oportunidad->forceFill(['cuenta_id' => Cuenta::factory()->create()->id])->save())->toThrow(LogicException::class);
});

test('calificar sin necesidad, decisor ni presupuesto falla y nombra lo que falta (escenario del v4)', function () {
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id);

    expect(fn () => odMover($oportunidad, 'calificar', $this->gerencia))
        ->toThrow(TransicionRechazada::class, 'Para avanzar falta: Necesidad confirmada, Decisor identificado, Presupuesto confirmado.');

    odMover(odCalificable($oportunidad), 'calificar', $this->gerencia);

    expect(odEtapa($oportunidad))->toBe('calificada');
    odCoherente($oportunidad);
});

test('no se saltan etapas hacia adelante', function () {
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));

    expect(fn () => odMover($oportunidad, 'negociar', $this->gerencia))->toThrow(TransicionRechazada::class)
        ->and(fn () => odMover($oportunidad, 'ganar', $this->gerencia))->toThrow(TransicionRechazada::class);
});

test('los requisitos se acumulan hasta la etapa destino; uno nuevo deja detenida a la que no lo cumple, sin moverla', function () {
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));
    odMover($oportunidad, 'calificar', $this->gerencia);

    // Como el que registrará Cotizaciones (CRM-03): se exige desde Propuesta enviada.
    app(RequisitosDeEtapa::class)->registrar(new class implements RequisitoDeEtapa
    {
        public function exigidoAlEntrarEn(): string
        {
            return 'propuesta_enviada';
        }

        public function texto(): string
        {
            return 'Una cotización emitida';
        }

        public function cumple(int $oportunidadId): bool
        {
            return false;
        }
    });
    $oportunidad->refresh()->forceFill(['necesidad_confirmada' => false])->save();

    expect(fn () => odMover($oportunidad, 'enviar_propuesta', $this->gerencia))
        ->toThrow(TransicionRechazada::class, 'Para avanzar falta: Necesidad confirmada, Una cotización emitida.')
        ->and(odEtapa($oportunidad))->toBe('calificada')
        ->and(app(RequisitosDeEtapa::class)->revisar($oportunidad->id, 'propuesta_enviada'))->toHaveCount(4);
});

test('retroceder pide motivo y reinicia el contador: en_etapa_desde es siempre la última entrada al estado actual', function () {
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));
    $entradaACalificada = function () use ($oportunidad): int {
        return (int) HistorialEstado::query()->where('documentable_type', 'oportunidad')->where('documentable_id', $oportunidad->id)
            ->where('hacia_estado_id', $oportunidad->fresh()?->estado_id)->latest('id')->value('id');
    };

    odMover($oportunidad, 'calificar', $this->gerencia);
    odCoherente($oportunidad);
    $primera = $entradaACalificada();
    odMover($oportunidad, 'enviar_propuesta', $this->gerencia);
    odCoherente($oportunidad);

    expect(fn () => odMover($oportunidad, 'retroceder', $this->gerencia))->toThrow(TransicionRechazada::class);

    odMover($oportunidad, 'retroceder', $this->gerencia, odMotivo('retroceder', otro: true));

    expect(odEtapa($oportunidad))->toBe('calificada')
        ->and($entradaACalificada())->toBeGreaterThan($primera);
    odCoherente($oportunidad);
});

// ——— Ganar y la marca de cliente (D3) ———

test('ganar marca la cuenta como cliente con la fecha de cierre real y avisa al ERP sin cotización', function () {
    Event::fake([OportunidadGanada::class]);
    $oportunidad = odGanar(OportunidadesDePrueba::crear($this->cuenta->id, ['monto_estimado' => '24715.10']), '2026-11-05', $this->gerencia);

    expect(odEtapa($oportunidad))->toBe('ganada')
        ->and(odClienteDesde($this->cuenta->id))->toBe('2026-11-05');
    Event::assertDispatched(OportunidadGanada::class, fn (OportunidadGanada $e): bool => $e->oportunidadId === $oportunidad->id
        && $e->cuentaId === $this->cuenta->id && $e->partyId === $this->cuenta->party_id && $e->monto === '24715.10'
        && $e->moneda === 'PEN' && $e->fechaCierreReal === '2026-11-05' && $e->cotizacionId === null && $e->convocatoriaId === null);
});

test('ganar exige la cuenta usable', function () {
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));
    foreach (['calificar', 'enviar_propuesta', 'negociar'] as $paso) {
        odMover($oportunidad, $paso, $this->gerencia);
    }
    $oportunidad->refresh()->forceFill(['fecha_cierre_real' => '2026-11-05'])->save();
    DB::table('cuentas')->where('id', $this->cuenta->id)->update(['es_activa' => false, 'motivo_inactiva_id' => Motivo::query()->value('id')]);

    expect(fn () => odMover($oportunidad, 'ganar', $this->gerencia))->toThrow(TransicionRechazada::class, 'La cuenta está inactiva')
        ->and(odEtapa($oportunidad))->toBe('negociacion');
});

test('las salidas disponibles de una en Negociación se leen sin bloquear la cuenta ni fallar fuera de una transacción', function () {
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));
    foreach (['calificar', 'enviar_propuesta', 'negociar'] as $paso) {
        odMover($oportunidad, $paso, $this->gerencia);
    }

    $salidas = app(TransicionesService::class)->salidasDisponibles($oportunidad->refresh(), $this->gerencia);

    expect($salidas)->not->toBeEmpty();
});

test('marca con varias oportunidades: ganada en marzo y perdida en noviembre sigue cliente desde marzo', function () {
    odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-03-15', $this->gerencia);
    $perdida = OportunidadesDePrueba::crear($this->cuenta->id);
    odMover($perdida, 'perder', $this->gerencia, odMotivo('perder'));

    expect(odEtapa($perdida))->toBe('perdida')
        ->and(odClienteDesde($this->cuenta->id))->toBe('2026-03-15');
});

test('marca con varias oportunidades: ganadas en marzo y agosto, se anula la de agosto y sigue desde marzo; anuladas las dos, vuelve a prospecto', function () {
    $marzo = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-03-15', $this->gerencia);
    $agosto = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-08-20', $this->gerencia);

    odMover($agosto, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada'));
    expect(odClienteDesde($this->cuenta->id))->toBe('2026-03-15');

    odMover($marzo, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada'));
    expect(odClienteDesde($this->cuenta->id))->toBeNull()
        ->and(odEtapa($marzo))->toBe('anulada');
});

test('marca con varias oportunidades: ganada en agosto y luego una tardía de marzo deja cliente desde marzo', function () {
    odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-08-20', $this->gerencia);
    odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-03-15', $this->gerencia);

    expect(odClienteDesde($this->cuenta->id))->toBe('2026-03-15');
});

// ——— Anular (D1, D10) ———

test('anular una ganada llama a Contratos en la misma transacción y avisa después de confirmar', function () {
    Event::fake([OportunidadGanadaAnulada::class]);
    $contratos = new class implements ContratosDeOportunidad
    {
        /** @var list<array{int, string}> */
        public array $llamadas = [];

        public function anularBorrador(int $oportunidadId, string $motivo): void
        {
            $this->llamadas[] = [$oportunidadId, $motivo];
        }

        public function etiquetasDeContratos(array $oportunidadIds): array
        {
            return [];
        }

        public function tieneDatos(): bool
        {
            return true;
        }
    };
    app()->instance(ContratosDeOportunidad::class, $contratos);
    $ganada = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-11-05', $this->gerencia);

    odMover($ganada, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada'));

    expect($contratos->llamadas)->toBe([[$ganada->id, 'Ganada por equivocación']]);
    Event::assertDispatched(OportunidadGanadaAnulada::class, fn (OportunidadGanadaAnulada $e): bool => $e->oportunidadId === $ganada->id && $e->motivo === 'Ganada por equivocación');
});

/**
 * Un Contratos de prueba (C3): su oyente crea el borrador solo si `exigirGanada` dice que sigue ganada, y
 * `anularBorrador` anula el que encuentre.
 */
function odContratosDePrueba(): ContratosDeOportunidad
{
    return new class implements ContratosDeOportunidad
    {
        /** @var array<int, string> estado del contrato por oportunidad */
        public array $contratos = [];

        public function crearDesdeLaCola(int $oportunidadId): void
        {
            DB::transaction(function () use ($oportunidadId): void {
                if (app(OportunidadesService::class)->exigirGanada($oportunidadId)) {
                    $this->contratos[$oportunidadId] = 'borrador';
                }
            });
        }

        public function anularBorrador(int $oportunidadId, string $motivo): void
        {
            if (isset($this->contratos[$oportunidadId])) {
                $this->contratos[$oportunidadId] = 'anulado';
            }
        }

        public function etiquetasDeContratos(array $oportunidadIds): array
        {
            return [];
        }

        public function tieneDatos(): bool
        {
            return true;
        }
    };
}

test('C3: si la ganada se anula antes de que Contratos cree el contrato, no se crea', function () {
    $contratos = odContratosDePrueba();
    app()->instance(ContratosDeOportunidad::class, $contratos);
    $ganada = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-11-05', $this->gerencia);

    odMover($ganada, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada'));
    $contratos->crearDesdeLaCola($ganada->id);

    expect($contratos->contratos)->toBe([]);
});

test('C3: si Contratos crea el contrato antes de la anulación, anularBorrador lo encuentra y lo anula', function () {
    $contratos = odContratosDePrueba();
    app()->instance(ContratosDeOportunidad::class, $contratos);
    $ganada = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-11-05', $this->gerencia);

    $contratos->crearDesdeLaCola($ganada->id);
    odMover($ganada, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada'));

    expect($contratos->contratos)->toBe([$ganada->id => 'anulado'])
        ->and(DB::transaction(fn (): bool => app(OportunidadesService::class)->exigirGanada($ganada->id)))->toBeFalse();
});

test('si el contrato ya avanzó, anular la ganada se rechaza y no cambia nada', function () {
    app()->instance(ContratosDeOportunidad::class, new class implements ContratosDeOportunidad
    {
        public function anularBorrador(int $oportunidadId, string $motivo): void
        {
            throw new class('Primero anula el contrato CT-2026-018.') extends ErrorDeNegocio {};
        }

        public function etiquetasDeContratos(array $oportunidadIds): array
        {
            return [];
        }

        public function tieneDatos(): bool
        {
            return true;
        }
    });
    $ganada = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-11-05', $this->gerencia);

    expect(fn () => odMover($ganada, 'anular_ganada', $this->gerencia, odMotivo('anular_ganada')))->toThrow(ErrorDeNegocio::class, 'Primero anula el contrato CT-2026-018.')
        ->and(odEtapa($ganada))->toBe('ganada')
        ->and(odClienteDesde($this->cuenta->id))->toBe('2026-11-05');
});

test('anular sale de las abiertas y de perdida; de ganada solo anular_ganada', function () {
    $abierta = OportunidadesDePrueba::crear($this->cuenta->id);
    $perdida = OportunidadesDePrueba::crear($this->cuenta->id);
    odMover($perdida, 'perder', $this->gerencia, odMotivo('perder'));
    $ganada = odGanar(OportunidadesDePrueba::crear($this->cuenta->id), '2026-11-05', $this->gerencia);

    odMover($abierta, 'anular', $this->gerencia, odMotivo('anular'));
    odMover($perdida, 'anular', $this->gerencia, odMotivo('anular'));

    expect(odEtapa($abierta))->toBe('anulada')
        ->and(odEtapa($perdida))->toBe('anulada')
        ->and(fn () => odMover($ganada, 'anular', $this->gerencia, odMotivo('anular')))->toThrow(TransicionRechazada::class);
});

test('un documento vivo de otro módulo impide anular, con el mecanismo del núcleo', function () {
    $revision = new class implements RevisionAntesDeAnular
    {
        public function documentosQueImpiden(Model $documento): array
        {
            return [new DocumentoQueImpide('la cotización', 'COT-2026-0007', 'Aceptada')];
        }
    };
    app()->instance($revision::class, $revision);
    app(RegistroDocumentos::class)->revisionAntesDeAnular(DefinicionOportunidad::CLAVE, $revision::class);
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id);

    expect(fn () => odMover($oportunidad, 'anular', $this->gerencia, odMotivo('anular')))->toThrow(TransicionRechazada::class, 'la cotización COT-2026-0007 (Aceptada)');
});

// ——— Perder, retroceder, «propias» ———

test('perder nace con sus siete motivos de partida y su «Otro»', function () {
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();

    expect(Motivo::query()->where('tipo_motivo_id', $familia->id)->orderBy('id')->pluck('nombre')->all())
        ->toBe(['Otro', 'Precio', 'Plazo', 'Especificaciones', 'Presupuesto cancelado', 'No respondió', 'Decisión interna', 'Proceso desierto']);
});

test('un impedimento de otro módulo detiene perder y retroceder con su mensaje', function () {
    app(ImpedimentosDeOportunidad::class)->registrar(new class implements ImpedimentoDeOportunidad
    {
        public function transiciones(): array
        {
            return ['perder', 'retroceder'];
        }

        public function impiden(int $oportunidadId): array
        {
            return [new DocumentoQueImpide('la cotización', 'COT-2026-0009', 'Aceptada')];
        }
    });
    $oportunidad = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));
    odMover($oportunidad, 'calificar', $this->gerencia);

    expect(fn () => odMover($oportunidad, 'perder', $this->gerencia, odMotivo('perder')))->toThrow(TransicionRechazada::class, 'la cotización COT-2026-0009 (Aceptada)')
        ->and(fn () => odMover($oportunidad, 'retroceder', $this->gerencia, odMotivo('retroceder', otro: true)))->toThrow(TransicionRechazada::class, 'COT-2026-0009');
});

test('«propias»: con editar-propias se mueven las propias, no las ajenas; anular_ganada no tiene variante propia', function () {
    $ejecutivo = odPersona('crm.oportunidades.editar-propias', ...odTransiciones());
    $propia = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id, ['ejecutivo_user_id' => $ejecutivo->id]));
    $ajena = odCalificable(OportunidadesDePrueba::crear($this->cuenta->id));

    odMover($propia, 'calificar', $ejecutivo);

    expect(odEtapa($propia))->toBe('calificada')
        ->and(fn () => odMover($ajena, 'calificar', $ejecutivo))->toThrow(TransicionRechazada::class, 'Solo su ejecutivo')
        ->and(fn () => odMover($ajena, 'anular', $ejecutivo, odMotivo('anular')))->toThrow(TransicionRechazada::class, 'Solo su ejecutivo');
});

// ——— La base (D4) ———

test('la base cuida el precio del competidor: con su moneda, «desconocido» solo del ganador, un ganador por oportunidad', function () {
    $oportunidad = OportunidadesDePrueba::crear($this->cuenta->id);
    // Cada intento en su punto de guardado: en PostgreSQL un error aborta la transacción entera.
    $fila = fn (array $datos): bool => DB::transaction(fn (): bool => DB::table('oportunidad_competidores')->insert([
        'oportunidad_id' => $oportunidad->id, 'party_id' => Cuenta::factory()->create()->party_id, 'created_by' => $this->gerencia->id, ...$datos,
    ]));
    $soles = DB::table('monedas')->where('codigo', 'PEN')->value('id');

    expect(fn () => $fila(['precio' => '100.00']))->toThrow(QueryException::class, 'oportunidad_competidores_moneda_check')
        ->and(fn () => $fila(['precio' => '0', 'moneda_id' => $soles]))->toThrow(QueryException::class, 'oportunidad_competidores_precio_check')
        ->and(fn () => $fila(['precio_desconocido' => true]))->toThrow(QueryException::class, 'oportunidad_competidores_desconocido_check')
        ->and(fn () => $fila(['es_ganador' => true]))->toThrow(QueryException::class, 'oportunidad_competidores_ganador_precio_check')
        ->and($fila(['es_ganador' => true, 'precio_desconocido' => true]))->toBeTrue()
        ->and(fn () => $fila(['es_ganador' => true, 'precio' => '90.00', 'moneda_id' => $soles]))->toThrow(QueryException::class, 'oportunidad_competidores_un_ganador_unique')
        ->and(fn () => DB::transaction(fn () => DB::table('oportunidades')->where('id', $oportunidad->id)->update(['monto_estimado' => 0])))->toThrow(QueryException::class, 'oportunidades_monto_check');
});
