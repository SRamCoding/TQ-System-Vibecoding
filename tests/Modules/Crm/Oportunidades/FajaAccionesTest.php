<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\DespieceDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Arrastrar cajas en la faja de vidrio (plan 2.21b, paso 4; D3–D6): soltar en la zona siguiente abre el diálogo de la
 * acción de esa transición y, al llegar al escáner, la ejecuta de verdad por EjecutorAcciones con la versión tomada al
 * agarrar la caja. Saltar o retroceder no llama a nada; ganar y perder, desde el final y la canaleta. Empresas
 * inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-10 10:00');
    $this->ejecutivoFa = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuentaFa = Cuenta::factory()->create([
        'ejecutivo_user_id' => $this->ejecutivoFa->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Arrastres Inventados S.A.C.'])->id,
    ]);
});

/** Todo lo de mover en el embudo, como Gerencia Comercial en las pruebas. */
const FA_TODO = [
    'crm.oportunidades.editar',
    'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.enviar_propuesta', 'crm.oportunidades.oportunidad.negociar',
    'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder',
];

function faEntrar(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function faOportunidad(string $nombre = 'Red del almacén'): Oportunidad
{
    return OportunidadesDePrueba::crear(test()->cuentaFa->id, ['nombre' => $nombre, 'ejecutivo_user_id' => test()->ejecutivoFa->id]);
}

function faEtapa(Oportunidad $oportunidad): string
{
    return (string) Estado::query()->whereKey($oportunidad->refresh()->estado_id)->value('codigo');
}

function faDecisor(): int
{
    return PartyContacto::factory()->create(['party_id' => test()->cuentaFa->party_id])->id;
}

/** La lleva por las acciones reales hasta la etapa pedida. */
function faHasta(Oportunidad $oportunidad, string $etapa, User $quien): Oportunidad
{
    $pasos = ['calificada' => 'calificar', 'propuesta_enviada' => 'enviar_propuesta', 'negociacion' => 'negociar'];

    foreach ($pasos as $destino => $codigo) {
        $datos = ['oportunidad_id' => $oportunidad->id, 'version' => $oportunidad->refresh()->version];

        if ($codigo === 'calificar') {
            // Plan 2.21d, D8: la certificación ya estaba (se prueba en CompuertaRellenaTest); la necesidad dice cómo.
            Illuminate\Support\Facades\DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
            $datos += ['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => faDecisor()];
        }

        app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.".{$codigo}", $datos, $quien);

        if ($destino === $etapa) {
            break;
        }
    }

    return $oportunidad->refresh();
}

function faMotivoPerder(): int
{
    return (int) Motivo::query()->where('es_otro', false)
        ->whereIn('tipo_motivo_id', TipoMotivo::query()->select('id')->where('codigo', DefinicionOportunidad::CLAVE.'.perder'))->value('id');
}

/**
 * Lo que devolvió el último método de la página.
 *
 * @return array<string, mixed>
 */
function faDevuelto(Testable $pagina): array
{
    $devuelto = null;
    $pagina->assertReturned(function (mixed $valor) use (&$devuelto): bool {
        $devuelto = $valor;

        return is_array($valor);
    });

    return (array) $devuelto;
}

test('soltar en la zona siguiente abre el diálogo de la acción de cada etapa, con sus campos', function () {
    $quien = faEntrar(...FA_TODO);
    $prospecto = faOportunidad();
    $calificada = faHasta(faOportunidad('Cámaras del puerto'), 'calificada', $quien);
    $propuesta = faHasta(faOportunidad('Fibra del campus'), 'propuesta_enviada', $quien);

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $prospecto->id, 'prospecto', 'calificada', $prospecto->version);
    expect(faDevuelto($pagina))->toBe(['ok' => true, 'conflicto' => false, 'mensaje' => null]);
    $pagina->assertActionMounted('calificar')
        ->assertMountedActionModalSee('Esta etapa exige')
        ->assertFormFieldExists('necesidad_confirmada')
        ->assertFormFieldExists('decisor_contacto_id');

    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $calificada->id, 'calificada', 'propuesta_enviada', $calificada->version)
        ->assertActionMounted('enviarPropuesta');

    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $propuesta->id, 'propuesta_enviada', 'negociacion', $propuesta->version)
        ->assertActionMounted('negociar');
});

test('el diálogo no ejecuta: devuelve lo llenado a la faja, sin la versión leída al abrir', function () {
    faEntrar(...FA_TODO);
    $prospecto = faOportunidad();
    $decisor = faDecisor();
    // Plan 2.21d, D8: la certificación ya estaba; la necesidad pide cómo se confirmó.
    DB::table('oportunidades')->where('id', $prospecto->id)->update(['presupuesto_confirmado' => true]);

    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $prospecto->id, 'prospecto', 'calificada', $prospecto->refresh()->version)
        ->fillForm(['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión inventada', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => $decisor])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('tq-oportunidad-dialogo-listo', fn (string $nombre, array $parametros): bool => $parametros['oportunidad'] === $prospecto->id
            && $parametros['datos']['decisor_contacto_id'] === $decisor
            && ! array_key_exists('version', $parametros['datos']));

    expect(faEtapa($prospecto))->toBe('prospecto');
});

test('al llegar al escáner se ejecuta la acción real de cada etapa y la oportunidad pasa', function () {
    faEntrar(...FA_TODO);
    $oportunidad = faOportunidad();
    $pagina = Livewire::test(FajaOportunidades::class);

    Illuminate\Support\Facades\DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
    $pagina->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $oportunidad->version, [
        'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => faDecisor(),
    ]);
    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'conflicto' => false, 'etapa' => 'calificada', 'mensaje' => '«Red del almacén» pasó a Calificada · 25 %.'])
        ->and(faEtapa($oportunidad))->toBe('calificada');

    $pagina->call('moverOportunidad', $oportunidad->id, 'calificada', 'propuesta_enviada', $oportunidad->version, []);
    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'propuesta_enviada', 'version' => $oportunidad->refresh()->version])
        ->and(faEtapa($oportunidad))->toBe('propuesta_enviada');

    $pagina->call('moverOportunidad', $oportunidad->id, 'propuesta_enviada', 'negociacion', $oportunidad->version, []);
    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'negociacion'])
        ->and(faEtapa($oportunidad))->toBe('negociacion');
});

test('si falta un requisito de la etapa, vuelve el mensaje de la acción y la oportunidad no se mueve', function () {
    faEntrar(...FA_TODO);
    $oportunidad = faOportunidad();

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $oportunidad->version, ['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba']);

    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => 'Para avanzar falta: Decisor identificado, Presupuesto confirmado.'])
        ->and(faEtapa($oportunidad))->toBe('prospecto')
        ->and($oportunidad->necesidad_confirmada)->toBeFalse();
});

test('saltar etapas o retroceder se explica en una línea sin llamar a ninguna acción', function () {
    $quien = faEntrar(...FA_TODO);
    $prospecto = faOportunidad();
    $calificada = faHasta(faOportunidad('Cámaras del puerto'), 'calificada', $quien);
    $version = $calificada->version;
    $pagina = Livewire::test(FajaOportunidades::class);

    $pagina->call('abrirMovimiento', $prospecto->id, 'prospecto', 'propuesta_enviada', $prospecto->version)->assertActionNotMounted();
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => FajaOportunidades::NO_SE_SALTA]);

    $pagina->call('moverOportunidad', $calificada->id, 'calificada', 'prospecto', $version, []);
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => FajaOportunidades::NO_SE_RETROCEDE]);

    $pagina->call('moverOportunidad', $prospecto->id, 'prospecto', 'ganada', $prospecto->version, ['fecha_cierre_real' => '2026-11-10', 'monto_final' => '100']);
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => FajaOportunidades::NO_SE_SALTA]);

    expect(faEtapa($prospecto))->toBe('prospecto')
        ->and(faEtapa($calificada))->toBe('calificada')
        ->and($calificada->version)->toBe($version);
});

test('ganar desde Negociación soltando la caja al final de la faja', function () {
    $quien = faEntrar(...FA_TODO);
    $oportunidad = faHasta(faOportunidad(), 'negociacion', $quien);

    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'negociacion', FajaOportunidades::GANADA, $oportunidad->version)
        ->assertActionMounted('ganar')
        ->assertFormFieldExists('fecha_cierre_real')
        ->assertFormFieldExists('monto_final');

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('moverOportunidad', $oportunidad->id, 'negociacion', FajaOportunidades::GANADA, $oportunidad->version, ['fecha_cierre_real' => '2026-11-09', 'monto_final' => '12000']);

    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'ganada', 'mensaje' => '«Red del almacén» ganada.'])
        ->and(faEtapa($oportunidad))->toBe('ganada')
        ->and($oportunidad->fecha_cierre_real->toDateString())->toBe('2026-11-09');
});

test('perder soltando la caja en la canaleta: motivo, ganador y precio, o precio desconocido', function () {
    $quien = faEntrar(...FA_TODO);
    $conPrecio = faHasta(faOportunidad('Cámaras del puerto'), 'calificada', $quien);
    $sinPrecio = faOportunidad('Fibra del campus');
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Rival Inventado S.A.C.']);
    $soles = Moneda::query()->where('codigo', 'PEN')->value('id');

    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $conPrecio->id, 'calificada', FajaOportunidades::PERDIDA, $conPrecio->version)
        ->assertActionMounted('perder')
        ->assertFormFieldExists('motivo_id')
        ->assertFormFieldExists('ganador_conocido');

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('moverOportunidad', $conPrecio->id, 'calificada', FajaOportunidades::PERDIDA, $conPrecio->version, [
            'motivo_id' => faMotivoPerder(), 'ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio' => '9000', 'moneda_id' => $soles,
        ]);
    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'perdida', 'mensaje' => '«Cámaras del puerto» cayó a perdidas.'])
        ->and(faEtapa($conPrecio))->toBe('perdida')
        ->and(OportunidadCompetidor::query()->where('oportunidad_id', $conPrecio->id)->sole()->only(['party_id', 'precio', 'es_ganador']))
        ->toBe(['party_id' => $rival->id, 'precio' => '9000.00', 'es_ganador' => true]);

    $pagina->call('moverOportunidad', $sinPrecio->id, 'prospecto', FajaOportunidades::PERDIDA, $sinPrecio->version, [
        'motivo_id' => faMotivoPerder(), 'ganador_conocido' => 'si', 'ganador_party_id' => $rival->id, 'precio_desconocido' => true,
    ]);
    expect(faDevuelto($pagina))->toMatchArray(['ok' => true, 'etapa' => 'perdida'])
        ->and(OportunidadCompetidor::query()->where('oportunidad_id', $sinPrecio->id)->sole()->only(['precio', 'precio_desconocido', 'es_ganador']))
        ->toBe(['precio' => null, 'precio_desconocido' => true, 'es_ganador' => true]);
});

test('perder sin motivo devuelve el mensaje de validación de la acción', function () {
    faEntrar(...FA_TODO);
    $oportunidad = faOportunidad();

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('moverOportunidad', $oportunidad->id, 'prospecto', FajaOportunidades::PERDIDA, $oportunidad->version, ['ganador_conocido' => 'no_se_sabe']);

    expect(faDevuelto($pagina))->toMatchArray(['ok' => false, 'conflicto' => false])
        ->and(faDevuelto($pagina)['mensaje'])->toContain('Motivo')
        ->and(faEtapa($oportunidad))->toBe('prospecto');
});

test('si otro la movió después de tomar la caja, vuelve el aviso de conflicto', function () {
    $quien = faEntrar(...FA_TODO);
    $oportunidad = faOportunidad();
    $tomada = $oportunidad->version;
    faHasta($oportunidad, 'calificada', $quien);

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $tomada, ['necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => faDecisor()]);
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => true, 'mensaje' => 'Otra persona cambió la oportunidad mientras lo editabas. Vuelve a abrirlo para ver el valor actual.']);

    $pagina->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $tomada)->assertActionNotMounted();
    expect(faDevuelto($pagina)['conflicto'])->toBeTrue()
        ->and(faEtapa($oportunidad))->toBe('calificada');
});

test('sin el permiso de la acción se rechaza con el mensaje de la acción', function () {
    faEntrar('crm.oportunidades.editar', 'crm.oportunidades.oportunidad.perder');
    $oportunidad = faOportunidad();

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $oportunidad->version)
        ->assertActionNotMounted();
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => 'No tienes permiso para «Calificar oportunidad».']);

    Illuminate\Support\Facades\DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
    $pagina->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $oportunidad->version, [
        'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => faDecisor(),
    ]);
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => 'No tienes permiso para «Calificar oportunidad».'])
        ->and(faEtapa($oportunidad))->toBe('prospecto');
});

test('con «editar las propias», la caja de otro ejecutivo vuelve con el mensaje y su despiece no ofrece botones', function () {
    $propias = ['crm.oportunidades.editar-propias', 'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.perder'];
    $quien = faEntrar(...$propias);
    $ajena = faOportunidad();
    $mensaje = 'Solo su ejecutivo, o quien puede editar todas las oportunidades, puede cambiarla.';

    $pagina = Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $ajena->id, 'prospecto', 'calificada', $ajena->version)
        ->assertActionNotMounted();
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => $mensaje]);

    // Aunque la faja llame igual al escáner, la acción la rechaza con el mismo mensaje.
    Illuminate\Support\Facades\DB::table('oportunidades')->where('id', $ajena->id)->update(['presupuesto_confirmado' => true]);
    $pagina->call('moverOportunidad', $ajena->id, 'prospecto', 'calificada', $ajena->version, [
        'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true, 'decisor_contacto_id' => faDecisor(),
    ]);
    expect(faDevuelto($pagina))->toBe(['ok' => false, 'conflicto' => false, 'mensaje' => $mensaje])
        ->and(faEtapa($ajena))->toBe('prospecto');

    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $ajena->id)
        ->assertDontSeeHtml('data-hacia=')
        ->assertSee('No tienes permiso para mover esta oportunidad.');

    // La suya sí: se abre el diálogo y el despiece ofrece pasar y perder.
    $suya = OportunidadesDePrueba::crear(test()->cuentaFa->id, ['nombre' => 'Antena propia', 'ejecutivo_user_id' => $quien->id]);
    Livewire::test(FajaOportunidades::class)
        ->call('abrirMovimiento', $suya->id, 'prospecto', 'calificada', $suya->version)
        ->assertActionMounted('calificar');
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $suya->id)
        ->assertSeeHtml('data-hacia="calificada"')
        ->assertSeeHtml('data-hacia="'.FajaOportunidades::PERDIDA.'"');
});

test('cada caja lleva su versión y su lugar para el arrastre; Enter la sigue abriendo (D14)', function () {
    faEntrar(...FA_TODO);
    $oportunidad = faOportunidad();

    $html = Livewire::test(FajaOportunidades::class)->html();

    expect($html)->toContain('data-caja="'.$oportunidad->id.'" data-lugar="prospecto" data-version="'.$oportunidad->version.'"')
        ->toContain('wire:key="faja-caja-'.$oportunidad->id.'"')
        ->toContain('data-desplazo')
        ->toContain('x-on:keydown.enter.prevent="abrirCaja('.$oportunidad->id.')"');
});

test('tras mover una caja, la faja pone al día la fila de cifras de arriba (el mismo camino del tablero)', function () {
    $quien = faEntrar(...FA_TODO);
    $oportunidad = faHasta(faOportunidad(), 'negociacion', $quien);

    $pagina = Livewire::test(FajaOportunidades::class);
    $antes = $pagina->get('cifras')['embudo']['detalle'];

    $pagina->call('moverOportunidad', $oportunidad->id, 'negociacion', FajaOportunidades::GANADA, $oportunidad->version, ['fecha_cierre_real' => '2026-11-09', 'monto_final' => '12000']);
    // `moverOportunidad` no redibuja: las cifras siguen las de antes hasta que la faja recarga.
    expect($pagina->get('cifras')['embudo']['detalle'])->toBe($antes)->toBe('1 oportunidad abierta');

    $pagina->call('actualizarCifras');
    expect($pagina->get('cifras')['embudo']['detalle'])->toBe('0 oportunidades abiertas')
        ->and(file_get_contents(base_path('app/Modules/Crm/Oportunidades/resources/views/faja/camara.blade.php')))
        ->toContain("async recargar() {\n            await this.\$wire.actualizarCifras();");
});
