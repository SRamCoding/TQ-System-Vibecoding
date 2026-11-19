<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\MovimientoDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La rueda de tallado del tablero de gemas (plan 2.21c, paso 6; D4 y D5): soltar una tarjeta en la etapa siguiente
 * revisa en el servidor el permiso, «propias» y los requisitos de la etapa destino; si falta algo no abre nada y dice
 * qué; si está todo, abre el diálogo de la acción y la ejecuta por EjecutorAcciones con la versión tomada al agarrarla.
 * El mismo camino que la faja (`MueveOportunidades`). Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-11 10:00');
    $this->ejecutivoRt = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuentaRt = Cuenta::factory()->create([
        'ejecutivo_user_id' => $this->ejecutivoRt->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Talladores Inventados S.A.C.'])->id,
    ]);
});

/** Todo lo de mover en el embudo, como Gerencia Comercial en las pruebas. */
const RT_TODO = [
    'crm.oportunidades.editar',
    'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.enviar_propuesta', 'crm.oportunidades.oportunidad.negociar',
    'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder',
];

function rtEntrar(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function rtOportunidad(string $nombre = 'Vitrinas del museo'): Oportunidad
{
    return OportunidadesDePrueba::crear(test()->cuentaRt->id, ['nombre' => $nombre, 'ejecutivo_user_id' => test()->ejecutivoRt->id]);
}

function rtEtapa(Oportunidad $oportunidad): string
{
    return (string) Estado::query()->whereKey($oportunidad->refresh()->estado_id)->value('codigo');
}

function rtDecisor(): int
{
    return PartyContacto::factory()->create(['party_id' => test()->cuentaRt->party_id])->id;
}

/** Con la calificación completa: lo que exige pasar a Calificada. */
function rtCalificable(string $nombre = 'Vitrinas del museo'): Oportunidad
{
    $oportunidad = rtOportunidad($nombre);
    $oportunidad->forceFill(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true, 'decisor_contacto_id' => rtDecisor()])->save();

    return $oportunidad->refresh();
}

/** La lleva por las acciones reales hasta la etapa pedida. */
function rtHasta(Oportunidad $oportunidad, string $etapa, User $quien): Oportunidad
{
    $pasos = ['calificada' => 'calificar', 'propuesta_enviada' => 'enviar_propuesta', 'negociacion' => 'negociar'];

    foreach ($pasos as $destino => $codigo) {
        $datos = ['oportunidad_id' => $oportunidad->id, 'version' => $oportunidad->refresh()->version];

        if ($codigo === 'calificar') {
            // Plan 2.21d, D8: la certificación ya estaba; la necesidad dice cómo.
            DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
            $datos += ['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => rtDecisor()];
        }

        app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.".{$codigo}", $datos, $quien);

        if ($destino === $etapa) {
            break;
        }
    }

    return $oportunidad->refresh();
}

function rtMotivoPerder(): int
{
    return (int) Motivo::query()->where('es_otro', false)
        ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', DefinicionOportunidad::CLAVE.'.perder'))->value('id');
}

/**
 * Lo que devolvió el último método de la página.
 *
 * @return array<string, mixed>
 */
function rtDevuelto(Testable $pagina): array
{
    $devuelto = null;
    $pagina->assertReturned(function (mixed $valor) use (&$devuelto): bool {
        $devuelto = $valor;

        return is_array($valor);
    });

    return (array) $devuelto;
}

test('con los requisitos completos, la rueda deja pasar: abre el diálogo y la acción real la lleva a la etapa siguiente', function () {
    rtEntrar(...RT_TODO);
    $oportunidad = rtCalificable();
    $tomada = $oportunidad->version;

    $pagina = Livewire::test(TableroOportunidades::class)
        // Plan 2.21d (D5): la persona la tiene puesta en su tablero; su código viaja con ella.
        ->call('alternarEnTablero', $oportunidad->id, true)
        ->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $tomada, true);
    expect(rtDevuelto($pagina))->toBe(['ok' => true, 'conflicto' => false, 'mensaje' => null]);
    $pagina->assertActionMounted('calificar')
        ->assertMountedActionModalSee('Esta etapa exige')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('tq-oportunidad-dialogo-listo', fn (string $nombre, array $parametros): bool => $parametros['oportunidad'] === $oportunidad->id
            && ! array_key_exists('version', $parametros['datos']));
    expect(rtEtapa($oportunidad))->toBe('prospecto');

    $pagina->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $tomada, []);
    expect(rtDevuelto($pagina))->toMatchArray(['ok' => true, 'conflicto' => false, 'etapa' => 'calificada', 'mensaje' => '«Vitrinas del museo» pasó a Calificada · 25 %.'])
        ->and(rtEtapa($oportunidad))->toBe('calificada');

    // Tras moverla, el tablero se redibuja con su código en la columna nueva (2.21d, D5).
    expect($pagina->call('despuesDeMover', $oportunidad->id)->html())
        ->toMatch('/data-pila="calificada">(?:\s|<!--.*?-->)*<div\s+class="tq-botonera-codigo"\s+data-codigo="'.$oportunidad->id.'"/');
});

test('con requisitos incompletos, la rueda no deja pasar: dice qué falta, no abre el diálogo y no la mueve', function () {
    rtEntrar(...RT_TODO);
    $oportunidad = rtOportunidad();
    $oportunidad->forceFill(['necesidad_confirmada' => true])->save();

    $pagina = Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $oportunidad->refresh()->version, true)
        ->assertActionNotMounted();

    expect(rtDevuelto($pagina))->toBe([
        'ok' => false,
        'conflicto' => false,
        'mensaje' => 'Para pasar a Calificada falta: Decisor identificado, Presupuesto confirmado.',
        'faltan' => ['Decisor identificado', 'Presupuesto confirmado'],
    ])->and(rtEtapa($oportunidad))->toBe('prospecto');
});

test('Propuesta enviada → Negociación no exige nada propio: la rueda deja pasar con los acumulados (D7)', function () {
    $quien = rtEntrar(...RT_TODO);
    $oportunidad = rtHasta(rtOportunidad(), 'propuesta_enviada', $quien);

    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'propuesta_enviada', 'negociacion', $oportunidad->version, true)
        ->assertActionMounted('negociar');
});

test('no se saltan etapas ni se retrocede: se explica en una línea y no se llama a ninguna acción', function () {
    $quien = rtEntrar(...RT_TODO);
    $prospecto = rtCalificable();
    $calificada = rtHasta(rtOportunidad('Domo del parque'), 'calificada', $quien);
    $version = $calificada->version;
    $pagina = Livewire::test(TableroOportunidades::class);

    // Dos etapas adelante.
    $pagina->call('abrirMovimiento', $prospecto->id, 'prospecto', 'propuesta_enviada', $prospecto->version, true)->assertActionNotMounted();
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => MovimientoDeOportunidad::NO_SE_SALTA]);

    $pagina->call('moverOportunidad', $prospecto->id, 'prospecto', 'negociacion', $prospecto->version, []);
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => MovimientoDeOportunidad::NO_SE_SALTA]);

    $pagina->call('abrirMovimiento', $calificada->id, 'calificada', 'prospecto', $version, true)->assertActionNotMounted();
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => MovimientoDeOportunidad::NO_SE_RETROCEDE]);

    expect(rtEtapa($prospecto))->toBe('prospecto')
        ->and(rtEtapa($calificada))->toBe('calificada')
        ->and($calificada->refresh()->version)->toBe($version);
});

test('a Ganadas desde Propuesta enviada o Negociación (el origen de «ganar», plan 2.22b), no antes', function () {
    $quien = rtEntrar(...RT_TODO);
    $calificada = rtHasta(rtOportunidad('Domo del parque'), 'calificada', $quien);
    $negociacion = rtHasta(rtOportunidad(), 'negociacion', $quien);
    $pagina = Livewire::test(TableroOportunidades::class);

    $pagina->call('abrirMovimiento', $calificada->id, 'calificada', MovimientoDeOportunidad::GANADA, $calificada->version, true)->assertActionNotMounted();
    expect(rtDevuelto($pagina)['mensaje'])->toBe(MovimientoDeOportunidad::NO_SE_SALTA);
    $pagina->call('moverOportunidad', $calificada->id, 'calificada', MovimientoDeOportunidad::GANADA, $calificada->version, ['fecha_cierre_real' => '2026-11-10', 'monto_final' => '100']);
    expect(rtDevuelto($pagina)['ok'])->toBeFalse()
        ->and(rtEtapa($calificada))->toBe('calificada');

    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $negociacion->id, 'negociacion', MovimientoDeOportunidad::GANADA, $negociacion->version, true)
        ->assertActionMounted('ganar')
        ->assertFormFieldExists('fecha_cierre_real')
        ->assertFormFieldExists('monto_final');

    // Desde Propuesta enviada también (propuesta del desarrollador para «Embudo comercial», plan 2.22, P2).
    $propuesta = rtHasta(rtOportunidad('Domo del parque'), 'propuesta_enviada', $quien);
    expect(MovimientoDeOportunidad::ganaDesde())->toBe(['propuesta_enviada', 'negociacion']);
    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $propuesta->id, 'propuesta_enviada', MovimientoDeOportunidad::GANADA, $propuesta->version, true)
        ->assertActionMounted('ganar');

    $pagina = Livewire::test(TableroOportunidades::class)
        ->call('moverOportunidad', $negociacion->id, 'negociacion', MovimientoDeOportunidad::GANADA, $negociacion->version, ['fecha_cierre_real' => '2026-11-10', 'monto_final' => '15000']);
    expect(rtDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'ganada', 'mensaje' => '«Vitrinas del museo» ganada.'])
        ->and(rtEtapa($negociacion))->toBe('ganada');
});

test('a Perdidas desde cualquier etapa: motivo de la lista, ganador y precio', function () {
    $quien = rtEntrar(...RT_TODO);
    $oportunidad = rtHasta(rtOportunidad(), 'propuesta_enviada', $quien);
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Competencia Inventada S.A.C.']);
    $soles = Moneda::query()->where('codigo', 'PEN')->value('id');

    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'propuesta_enviada', MovimientoDeOportunidad::PERDIDA, $oportunidad->version, true)
        ->assertActionMounted('perder')
        ->assertFormFieldExists('motivo_id')
        ->assertFormFieldExists('ganador_conocido');

    $pagina = Livewire::test(TableroOportunidades::class)
        ->call('moverOportunidad', $oportunidad->id, 'propuesta_enviada', MovimientoDeOportunidad::PERDIDA, $oportunidad->version, [
            'motivo_id' => rtMotivoPerder(), 'ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio' => '8500', 'moneda_id' => $soles,
        ]);

    expect(rtDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'perdida', 'mensaje' => '«Vitrinas del museo» cayó a perdidas.'])
        ->and(rtEtapa($oportunidad))->toBe('perdida')
        ->and(OportunidadCompetidor::query()->where('oportunidad_id', $oportunidad->id)->sole()->only(['party_id', 'precio', 'es_ganador']))
        ->toBe(['party_id' => $rival->id, 'precio' => '8500.00', 'es_ganador' => true]);
});

test('si otro la movió después de tomar la tarjeta, vuelve el aviso de conflicto y no se mueve', function () {
    $quien = rtEntrar(...RT_TODO);
    $oportunidad = rtCalificable();
    $tomada = $oportunidad->version;
    rtHasta($oportunidad, 'calificada', $quien);

    $pagina = Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $tomada, true)
        ->assertActionNotMounted();
    expect(rtDevuelto($pagina))->toMatchArray(['ok' => false, 'conflicto' => true]);

    $pagina->call('moverOportunidad', $oportunidad->id, 'calificada', 'propuesta_enviada', $tomada, []);
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => true, 'mensaje' => 'Otra persona cambió la oportunidad mientras lo editabas. Vuelve a abrirlo para ver el valor actual.'])
        ->and(rtEtapa($oportunidad))->toBe('calificada');
});

test('con «editar las propias», la de otro ejecutivo se rechaza con el mensaje de la acción', function () {
    $quien = rtEntrar('crm.oportunidades.editar-propias', 'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.perder');
    $ajena = rtCalificable();
    $mensaje = 'Solo su ejecutivo, o quien puede editar todas las oportunidades, puede cambiarla.';

    $pagina = Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $ajena->id, 'prospecto', 'calificada', $ajena->version, true)
        ->assertActionNotMounted();
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => $mensaje]);

    $pagina->call('moverOportunidad', $ajena->id, 'prospecto', 'calificada', $ajena->version, []);
    expect(rtDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => $mensaje])
        ->and(rtEtapa($ajena))->toBe('prospecto');

    $suya = OportunidadesDePrueba::crear(test()->cuentaRt->id, ['nombre' => 'Atril propio', 'ejecutivo_user_id' => $quien->id]);
    $suya->forceFill(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true, 'decisor_contacto_id' => rtDecisor()])->save();
    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $suya->id, 'prospecto', 'calificada', $suya->refresh()->version, true)
        ->assertActionMounted('calificar');
});

test('plan 2.21d: los códigos de las etapas se arrastran con su etapa y su versión; los de Ganadas y Perdidas, no', function () {
    $quien = rtEntrar(...RT_TODO);
    $abierta = rtHasta(rtOportunidad(), 'calificada', $quien);
    $perdida = rtOportunidad('Mampara del hotel');
    app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.'.perder', [
        'oportunidad_id' => $perdida->id, 'version' => $perdida->version, 'motivo_id' => rtMotivoPerder(), 'ganador_conocido' => 'no_se_sabe',
    ], $quien);

    $pagina = Livewire::test(TableroOportunidades::class)->call('alternarEnTablero', $abierta->id, true)->call('alternarEnTablero', $perdida->id, true);
    $html = $pagina->html();
    $codigo = fn (Oportunidad $o): string => '/data-codigo="'.$o->id.'"[^>]*draggable="(true|false)"/';

    preg_match($codigo($abierta), $html, $a);
    preg_match($codigo($perdida), $html, $p);
    expect($a[1] ?? null)->toBe('true')->and($p[1] ?? null)->toBe('false')
        ->and($html)->toContain('x-on:dragstart="agarrar($event, ')
        ->toContain('tqBotonera(')
        ->toContain('tqMovimientoOportunidad(');
});
