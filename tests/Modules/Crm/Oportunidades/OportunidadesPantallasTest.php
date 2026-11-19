<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\VerCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\OportunidadesDeCuenta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\CompetidoresDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\EtapasDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\HistorialDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\ListarOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las pantallas de Oportunidades (plan 2.21, D11): la portada de la lista, el tablero, Nueva oportunidad, la ficha con
 * sus pestañas, los paneles Avanzar y Cerrar (con la versión tomada al abrir) y la pestaña y el botón en la cuenta.
 * Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-06 10:00');
    $this->ejecutivo = User::factory()->create(['name' => 'Marco Ejecutivo Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuenta = Cuenta::factory()->create([
        'ejecutivo_user_id' => $this->ejecutivo->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Constructora Embudo S.A.C.'])->id,
    ]);
});

/** Todas las transiciones, editar y los competidores: lo de Gerencia Comercial en las pruebas. */
const OP_PANTALLAS_TODO = [
    'crm.oportunidades.crear', 'crm.oportunidades.editar', 'crm.oportunidades.reasignar',
    'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.enviar_propuesta', 'crm.oportunidades.oportunidad.negociar',
    'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder', 'crm.oportunidades.oportunidad.retroceder',
    'crm.oportunidades.oportunidad.anular', 'crm.oportunidades.oportunidad.anular_ganada',
];

function entrarEnOportunidades(string ...$permisos): User
{
    $usuario = User::factory()->create(['name' => 'Elena Prueba Ríos'])->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** @param  array<string, mixed>  $datos */
function oportunidadEnPantalla(array $datos = [], ?int $cuentaId = null): Oportunidad
{
    return OportunidadesDePrueba::crear($cuentaId ?? test()->cuenta->id, ['ejecutivo_user_id' => test()->ejecutivo->id, ...$datos]);
}

/** Pone la etapa en la base, sin el modelo (para la portada y el tablero: no prueban las transiciones). */
function opEnEtapa(Oportunidad $oportunidad, string $codigo, array $mas = []): Oportunidad
{
    Oportunidad::query()->whereKey($oportunidad->id)->update(['estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $codigo)->value('id'), ...$mas]);

    return $oportunidad->refresh();
}

function opEtapa(Oportunidad $oportunidad): string
{
    return (string) Estado::query()->whereKey($oportunidad->refresh()->estado_id)->value('codigo');
}

function opMotivoPerder(): int
{
    return (int) Motivo::query()->where('es_otro', false)
        ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', DefinicionOportunidad::CLAVE.'.perder'))->value('id');
}

/** Calificada, con la calificación completa. */
function opCalificada(Oportunidad $oportunidad, User $quien): Oportunidad
{
    // Plan 2.21d, D8: la certificación ya estaba; la necesidad dice cómo.
    DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
    app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.'.calificar', [
        'oportunidad_id' => $oportunidad->id, 'version' => $oportunidad->refresh()->version, 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true,
        'decisor_contacto_id' => PartyContacto::factory()->create(['party_id' => test()->cuenta->party_id])->id,
    ], $quien);

    return $oportunidad->refresh();
}

function opEnNegociacion(Oportunidad $oportunidad, User $quien): Oportunidad
{
    opCalificada($oportunidad, $quien);
    foreach (['enviar_propuesta', 'negociar'] as $codigo) {
        app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.".{$codigo}", ['oportunidad_id' => $oportunidad->id, 'version' => $oportunidad->refresh()->version], $quien);
    }

    return $oportunidad->refresh();
}

// ——— Acceso y portada ———

test('sin ver oportunidades no se entra; con el permiso, la lista y el tablero abren, y «Nueva oportunidad» solo a quien crea', function () {
    $this->actingAs(User::factory()->create())->get('/oportunidades')->assertForbidden();
    $this->get('/oportunidades/lista')->assertForbidden();

    entrarEnOportunidades();
    $this->get('/oportunidades')->assertOk();
    $this->get('/oportunidades/lista')->assertOk();
    Livewire::withQueryParams([])->test(ListarOportunidades::class)->assertActionHidden('crmOportunidadesCrear');

    entrarEnOportunidades('crm.oportunidades.crear');
    Livewire::withQueryParams([])->test(ListarOportunidades::class)->assertActionVisible('crmOportunidadesCrear');
});

test('las pestañas cuentan Abiertas, Mías, Estancadas, Ganadas, Perdidas, Anuladas y Todas; las anuladas, solo en la suya y en Todas', function () {
    $yo = entrarEnOportunidades();
    $mia = oportunidadEnPantalla(['ejecutivo_user_id' => $yo->id]);
    $abierta = oportunidadEnPantalla();
    $estancada = opEnEtapa(oportunidadEnPantalla(), 'calificada', ['en_etapa_desde' => '2026-10-01']);
    opEnEtapa(oportunidadEnPantalla(), 'ganada', ['fecha_cierre_real' => '2026-11-02']);
    opEnEtapa(oportunidadEnPantalla(), 'perdida', ['fecha_cierre_real' => '2026-11-03']);
    $anulada = opEnEtapa(oportunidadEnPantalla(['ejecutivo_user_id' => $yo->id]), 'anulada');

    $pestanas = Livewire::withQueryParams([])->test(ListarOportunidades::class)->instance()->getTabs();

    expect(array_keys($pestanas))->toBe(['abiertas', 'mias', 'estancadas', 'ganadas', 'perdidas', 'anuladas', 'todas'])
        ->and($pestanas['abiertas']->getBadge())->toEqual(3)
        ->and($pestanas['mias']->getBadge())->toEqual(1)
        ->and($pestanas['estancadas']->getBadge())->toEqual(1)
        ->and($pestanas['ganadas']->getBadge())->toEqual(1)
        ->and($pestanas['perdidas']->getBadge())->toEqual(1)
        ->and($pestanas['anuladas']->getBadge())->toEqual(1)
        ->and($pestanas['todas']->getBadge())->toEqual(6);

    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->assertCanSeeTableRecords([$mia, $abierta, $estancada])->assertCanNotSeeTableRecords([$anulada])
        ->set('activeTab', 'todas')->assertCanSeeTableRecords([$anulada])->assertSee('Anulada');
});

test('las tarjetas suman solo las abiertas en soles, con la nota de lo convertido y lo que no tiene T.C.', function () {
    entrarEnOportunidades();
    oportunidadEnPantalla(['monto_estimado' => '10000.00', 'fecha_cierre_estimada' => '2026-11-20']);
    opEnEtapa(oportunidadEnPantalla(['monto_estimado' => '20000.00']), 'calificada');
    opEnEtapa(oportunidadEnPantalla(['monto_estimado' => '99999.00']), 'anulada');
    opEnEtapa(oportunidadEnPantalla(['monto_estimado' => '88888.00']), 'ganada', ['fecha_cierre_real' => '2026-11-02']);
    $dolar = Moneda::query()->where('codigo', 'USD')->value('id');
    oportunidadEnPantalla(['monto_estimado' => '500.00', 'moneda_id' => $dolar]);

    // Sin T.C. del dólar cargado: los US$ 500 van aparte. Ponderado: 10 000 × 10 % + 20 000 × 25 % = 6 000.
    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->assertSee('Embudo abierto')->assertSee('S/ 30,000.00')
        ->assertSee('Ponderado')->assertSee('S/ 6,000.00')
        ->assertSee('Cierran este mes')->assertSee('S/ 10,000.00')
        ->assertSee('Aparte, sin T.C.: US$ 500.00 (1 oportunidad)')
        ->assertDontSee('99,999.00')
        ->assertDontSee('Sin próxima acción');
});

test('los avisos dicen cuántas revisar y «Ver cuáles» filtra la tabla', function () {
    entrarEnOportunidades();
    $saliente = User::factory()->create(['name' => 'Julio Saliente Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $huerfana = oportunidadEnPantalla(['ejecutivo_user_id' => $saliente->id]);
    $saliente->forceFill(['es_activo' => false])->save();
    $estancada = opEnEtapa(oportunidadEnPantalla(), 'calificada', ['en_etapa_desde' => '2026-10-01']);
    $normal = oportunidadEnPantalla();

    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->assertSee('1 oportunidad estancada')
        ->assertSee('1 oportunidad abierta con ejecutivo inactivo')
        ->call('verAviso', 'estancadas')
        ->assertCanSeeTableRecords([$estancada])->assertCanNotSeeTableRecords([$huerfana, $normal])
        ->assertSee('Ver todas')
        ->call('verAviso', 'ejecutivo-inactivo')
        ->assertCanSeeTableRecords([$huerfana])->assertCanNotSeeTableRecords([$estancada, $normal]);
});

test('el filtro «Etapa» deja ver una o varias etapas a la vez', function () {
    entrarEnOportunidades();
    $negociando = opEnEtapa(oportunidadEnPantalla(['nombre' => 'Enlace del almacén']), 'negociacion');
    $propuesta = opEnEtapa(oportunidadEnPantalla(['nombre' => 'Alarmas del taller']), 'propuesta_enviada');
    $prospecto = oportunidadEnPantalla(['nombre' => 'Cámaras del patio']);
    $etapa = fn (string $codigo): int => (int) Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $codigo)->value('id');

    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->filterTable('estado_id', [$etapa('negociacion')])
        ->assertCanSeeTableRecords([$negociando])->assertCanNotSeeTableRecords([$propuesta, $prospecto])
        ->filterTable('estado_id', [$etapa('negociacion'), $etapa('propuesta_enviada')])
        ->assertCanSeeTableRecords([$negociando, $propuesta])->assertCanNotSeeTableRecords([$prospecto]);
});

test('el buscador encuentra por número o nombre sin tildes; reasignar en bloque pasa las marcadas', function () {
    entrarEnOportunidades('crm.oportunidades.reasignar');
    $cableado = oportunidadEnPantalla(['nombre' => 'Cableado del hospital']);
    $camaras = oportunidadEnPantalla(['nombre' => 'Cámaras del colegio']);
    $nuevo = User::factory()->create(['name' => 'Nora Nueva Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);

    Livewire::withQueryParams([])->test(ListarOportunidades::class)->searchTable('CAMARAS')
        ->assertCanSeeTableRecords([$camaras])->assertCanNotSeeTableRecords([$cableado]);

    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->callTableBulkAction('reasignar', [$cableado, $camaras], ['ejecutivo_user_id' => $nuevo->id])
        ->assertNotified('Se reasignaron 2 oportunidades.');

    expect(Oportunidad::query()->where('ejecutivo_user_id', $nuevo->id)->count())->toBe(2);
});

// ——— Nueva oportunidad ———

test('Nueva oportunidad en el panel: elegir la cuenta trae su origen y su ejecutivo, y nace en Prospecto', function () {
    entrarEnOportunidades('crm.oportunidades.crear');

    Livewire::withQueryParams([])->test(ListarOportunidades::class)
        ->mountAction('crmOportunidadesCrear')
        ->assertMountedActionModalSee('Etapa inicial: Prospecto · 10 %')
        ->fillForm(['cuenta_id' => $this->cuenta->id])
        ->assertSchemaStateSet(['origen_comercial_id' => $this->cuenta->origen_comercial_id, 'ejecutivo_user_id' => $this->ejecutivo->id], 'mountedActionSchema0')
        ->fillForm([
            'nombre' => 'Red del hospital', 'monto_estimado' => '15000', 'fecha_cierre_estimada' => '2026-12-20',
            'linea_solucion_id' => LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Oportunidad creada.');

    $creada = Oportunidad::query()->sole();
    expect($creada->nombre)->toBe('Red del hospital')
        ->and(opEtapa($creada))->toBe('prospecto')
        ->and($creada->ejecutivo_user_id)->toBe($this->ejecutivo->id);
});

// ——— La ficha ———

test('la ficha muestra la cabecera y sus botones según la etapa y el permiso', function () {
    $oportunidad = oportunidadEnPantalla(['nombre' => 'Red del hospital', 'monto_estimado' => '24715.10']);

    entrarEnOportunidades();
    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->assertSee('Red del hospital')
        ->assertSee('Constructora Embudo S.A.C.')
        ->assertSeeHtml('href="'.e(url("/cuentas/{$this->cuenta->id}")).'"')
        ->assertSee('S/ 24,715.10')
        ->assertSee('Prospecto · 10 %')
        ->assertSee('Ponderado S/ 2,471.51')
        ->assertActionHidden('calificar')
        ->assertActionHidden('perder');

    entrarEnOportunidades(...OP_PANTALLAS_TODO);
    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->assertActionVisible('calificar')
        ->assertActionHidden('enviarPropuesta')
        ->assertActionHidden('ganar')
        ->assertActionVisible('perder')
        ->assertActionHidden('crmOportunidadesOportunidadRetroceder')
        ->assertActionVisible('crmOportunidadesOportunidadAnular')
        ->assertActionHidden('crmOportunidadesOportunidadAnularGanada');
});

test('Avanzar muestra lo que exige la etapa; con algo faltante no avanza y lo nombra; completándolo ahí mismo, avanza', function () {
    $oportunidad = oportunidadEnPantalla(['monto_estimado' => '10000.00']);
    entrarEnOportunidades(...OP_PANTALLAS_TODO);

    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->mountAction('calificar')
        ->assertMountedActionModalSee(['Esta etapa exige', 'Falta', 'Necesidad confirmada', 'Qué cambia al avanzar', 'S/ 2,500.00'])
        ->fillForm(['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión con el área usuaria'])
        ->callMountedAction()
        ->assertNotified('Para avanzar falta: Decisor identificado, Presupuesto confirmado.');

    expect(opEtapa($oportunidad))->toBe('prospecto');

    $decisor = PartyContacto::factory()->create(['party_id' => $this->cuenta->party_id, 'nombres' => 'Rosa', 'apellidos' => 'Decisora Prueba']);
    // La certificación ya se adjuntó (plan 2.21d, D8; su subida está en CompuertaRellenaTest): no se vuelve a pedir.
    Oportunidad::query()->whereKey($oportunidad->id)->update(['presupuesto_confirmado' => true]);
    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->callAction('calificar', ['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión con el área usuaria', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => $decisor->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect(opEtapa($oportunidad))->toBe('calificada')
        ->and($oportunidad->refresh()->decisor_contacto_id)->toBe($decisor->id);
});

test('Cerrar como ganada desde la ficha: fecha de hoy y monto por defecto; con más de 90 días pide confirmarla', function () {
    $quien = entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $oportunidad = opEnNegociacion(oportunidadEnPantalla(['monto_estimado' => '10000.00']), $quien);

    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->mountAction('ganar')
        ->assertSchemaStateSet(['fecha_cierre_real' => '2026-11-06', 'monto_final' => '10000.00'], 'mountedActionSchema0')
        ->fillForm(['fecha_cierre_real' => '2026-07-01'])
        ->callMountedAction()
        ->assertHasFormErrors(['confirmo_fecha_antigua']);

    expect(opEtapa($oportunidad))->toBe('negociacion');

    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->callAction('ganar', ['fecha_cierre_real' => '2026-07-01', 'confirmo_fecha_antigua' => true, 'monto_final' => '9500'])
        ->assertHasNoActionErrors()
        ->assertNotified('Guardado.');

    expect(opEtapa($oportunidad))->toBe('ganada')
        ->and($oportunidad->refresh()->fecha_cierre_real->toDateString())->toBe('2026-07-01');
});

test('Cerrar como perdida: motivo de su lista y, si la ganó otra empresa, cuál y a qué precio', function () {
    entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $oportunidad = oportunidadEnPantalla();
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Rival Inventado S.A.C.']);

    Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])
        ->mountAction('perder')
        ->fillForm(['ganador_conocido' => 'no'])
        ->assertFormFieldHidden('ganador_party_id')
        ->fillForm(['ganador_conocido' => 'si'])
        ->assertFormFieldVisible('ganador_party_id')
        ->fillForm(['motivo_id' => opMotivoPerder(), 'ganador_party_id' => $rival->id, 'precio' => '9000', 'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id')])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    expect(opEtapa($oportunidad))->toBe('perdida')
        ->and(OportunidadCompetidor::query()->where('oportunidad_id', $oportunidad->id)->sole()->only(['party_id', 'precio', 'es_ganador']))
        ->toBe(['party_id' => $rival->id, 'precio' => '9000.00', 'es_ganador' => true]);
});

test('bloqueo optimista: dos personas abren Avanzar y Cerrar; la primera guarda y la segunda recibe el aviso', function () {
    $quien = entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $oportunidad = opCalificada(oportunidadEnPantalla(), $quien);

    $primera = Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])->mountAction('enviarPropuesta');
    $segunda = Livewire::test(VerOportunidad::class, ['record' => $oportunidad->id])->mountAction('perder')
        ->fillForm(['motivo_id' => opMotivoPerder(), 'ganador_conocido' => 'no_se_sabe']);

    $primera->callMountedAction()->assertNotified('Guardado.');
    $segunda->callMountedAction()->assertNotified('Otra persona cambió la oportunidad mientras lo editabas. Vuelve a abrirlo para ver el valor actual.');

    expect(opEtapa($oportunidad))->toBe('propuesta_enviada');
});

test('Más: Retroceder solo después de Prospecto, Anular ganada solo en una ganada y con su permiso', function () {
    $quien = entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $calificada = opCalificada(oportunidadEnPantalla(), $quien);
    $ganada = opEnEtapa(oportunidadEnPantalla(), 'ganada', ['fecha_cierre_real' => '2026-11-02']);

    Livewire::test(VerOportunidad::class, ['record' => $calificada->id])
        ->assertActionVisible('crmOportunidadesOportunidadRetroceder')
        ->assertActionHidden('crmOportunidadesOportunidadAnularGanada');
    Livewire::test(VerOportunidad::class, ['record' => $ganada->id])
        ->assertActionHidden('crmOportunidadesOportunidadRetroceder')
        ->assertActionHidden('crmOportunidadesOportunidadAnular')
        ->assertActionVisible('crmOportunidadesOportunidadAnularGanada');

    entrarEnOportunidades('crm.oportunidades.oportunidad.anular');
    Livewire::test(VerOportunidad::class, ['record' => $ganada->id])->assertActionHidden('crmOportunidadesOportunidadAnularGanada');
});

test('las pestañas de la ficha: competidores, historial de etapas con los días e historial de la bitácora', function () {
    $quien = entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $oportunidad = opCalificada(oportunidadEnPantalla(), $quien);
    // La fecha del historial la pone el reloj de la base (`useCurrent`), no el de la app: `travelTo` no la mueve y en
    // una prueba las dos filas nacen en la misma transacción, con la misma hora. Solo se inserta: se fija a mano.
    $filas = DB::table('historial_estados')->where('documentable_type', $oportunidad->getMorphClass())
        ->where('documentable_id', $oportunidad->id)->orderBy('id')->pluck('id');
    DB::table('historial_estados')->where('id', $filas[0])->update(['fecha' => '2026-11-01 10:00:00-05']);
    DB::table('historial_estados')->where('id', $filas[1])->update(['fecha' => '2026-11-06 10:00:00-05']);
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Competencia Inventada S.A.C.']);

    Livewire::test(CompetidoresDeOportunidad::class, ['oportunidadId' => $oportunidad->id])
        ->assertSee('Sin competidores')
        ->callTableAction('agregar', data: ['party_id' => $rival->id, 'precio' => '8000', 'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id')])
        ->assertNotified('Guardado.')
        ->assertSee('Competencia Inventada S.A.C.')
        ->assertSee('S/ 8,000.00');

    $etapas = Livewire::test(EtapasDeOportunidad::class, ['oportunidadId' => $oportunidad->id])
        ->assertSeeInOrder(['06/11/2026 10:00', 'Calificar: de «Prospecto» a «Calificada»', '01/11/2026 10:00', 'Creada en «Prospecto»']);
    // Los días van en su columna, fila por fila (un `assertSee('5')` lo encontraba en cualquier parte de la página).
    expect(collect($etapas->instance()->getTableRecords())->map(fn (array $fila): array => [$fila['cambio'], $fila['dias']])->values()->all())
        ->toBe([['Calificar: de «Prospecto» a «Calificada»', '5'], ['Creada en «Prospecto»', null]]);

    Livewire::test(HistorialDeOportunidad::class, ['registroId' => $oportunidad->id])->assertSee('Oportunidad');
});

// ——— Tablero ———

test('la faja de vidrio: una zona por etapa con su %, sin anuladas ni cerradas; el panel Avanzar sigue en la página', function () {
    entrarEnOportunidades(...OP_PANTALLAS_TODO);
    $prospecto = oportunidadEnPantalla(['nombre' => 'Red del hospital']);
    opEnEtapa(oportunidadEnPantalla(['nombre' => 'Anulada de prueba']), 'anulada');
    $ganada = opEnEtapa(oportunidadEnPantalla(['nombre' => 'Ganada este mes']), 'ganada', ['fecha_cierre_real' => '2026-11-02']);
    opEnEtapa(oportunidadEnPantalla(['nombre' => 'Perdida en octubre']), 'perdida', ['fecha_cierre_real' => '2026-10-15']);

    // Las ganadas y perdidas van a sus salas (paso 5 de la 2.21b), no a las zonas; las de otro período, a ninguna.
    Livewire::test(FajaOportunidades::class)
        ->assertSee('1 · PROSPECTO')->assertSee('10 %')
        ->assertSee('2 · CALIFICADA')->assertSee('25 %')
        ->assertSee('Red del hospital')
        ->assertSeeHtml('data-caja="'.$ganada->id.'" data-lugar="ganadas"')
        ->assertDontSee('Perdida en octubre')
        ->assertDontSee('Anulada de prueba')
        ->mountAction('calificar', ['oportunidad' => $prospecto->id])
        ->assertMountedActionModalSee('Esta etapa exige');
});

// ——— En la cuenta ———

test('la cuenta suma la pestaña «Oportunidades» y el botón «Nueva oportunidad» con la cuenta fija', function () {
    $pestana = collect(app(RegistroPestanasCuenta::class)->pestanasPara(entrarEnOportunidades()))->firstWhere('clave', 'oportunidades');
    expect($pestana->permiso)->toBe(OportunidadesServiceProvider::PERMISO_VER)
        ->and($pestana->icono)->toBe('heroicon-o-presentation-chart-line');

    $suya = oportunidadEnPantalla(['nombre' => 'Red del hospital']);
    $otraCuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);
    $ajena = oportunidadEnPantalla(['nombre' => 'De otra cuenta'], $otraCuenta->id);

    Livewire::test(OportunidadesDeCuenta::class, ['cuentaId' => $this->cuenta->id])
        ->assertCanSeeTableRecords([$suya])->assertCanNotSeeTableRecords([$ajena]);

    entrarEnOportunidades();
    Livewire::test(VerCuenta::class, ['record' => $this->cuenta->id])->assertActionDoesNotExist('nueva-oportunidad');

    entrarEnOportunidades('crm.oportunidades.crear');
    Livewire::test(VerCuenta::class, ['record' => $this->cuenta->id])
        ->assertActionVisible('nueva-oportunidad')
        ->callAction('nueva-oportunidad', [
            'nombre' => 'Cámaras del almacén', 'monto_estimado' => '5000', 'fecha_cierre_estimada' => '2026-12-01',
            'linea_solucion_id' => LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Oportunidad creada.');

    expect(Oportunidad::query()->where('nombre', 'Cámaras del almacén')->sole()->cuenta_id)->toBe($this->cuenta->id);
});

test('sin ver oportunidades, la pestaña de la cuenta no se abre', function () {
    $this->actingAs(User::factory()->create()->givePermissionTo(CuentasServiceProvider::PERMISO_VER));

    Livewire::test(OportunidadesDeCuenta::class, ['cuentaId' => $this->cuenta->id])->assertForbidden();
});
