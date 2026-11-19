<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\MovimientoDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La ficha del tablero en el panel deslizante (plan 2.21c, paso 7; D6 y D8): la gema en grande, los datos, lo que exige
 * la transición siguiente (Cumple o Falta) y los botones Pasar, Ganar y Perder que la persona puede usar, por el mismo
 * camino que el arrastre. Una cerrada, su cierre sin botones; una anulada no abre. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-11 10:00');
    $this->ejecutivoFt = User::factory()->create(['name' => 'Rosa Inventada'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->cuentaFt = Cuenta::factory()->create([
        'ejecutivo_user_id' => $this->ejecutivoFt->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Fichas Inventadas S.A.C.'])->id,
    ]);
});

const FT_TODO = [
    'crm.oportunidades.editar',
    'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.enviar_propuesta', 'crm.oportunidades.oportunidad.negociar',
    'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder',
];

function ftEntrar(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function ftOportunidad(string $nombre = 'Luminarias del malecón'): Oportunidad
{
    return OportunidadesDePrueba::crear(test()->cuentaFt->id, ['nombre' => $nombre, 'ejecutivo_user_id' => test()->ejecutivoFt->id, 'monto_estimado' => '24500.00']);
}

/** La pone en un estado sin pasar por las transiciones: la ficha solo lee. */
function ftEnEstado(string $estado, string $nombre = 'Luminarias del malecón'): Oportunidad
{
    $oportunidad = ftOportunidad($nombre);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $estado)->value('id'),
        'fecha_cierre_real' => in_array($estado, ['ganada', 'perdida', 'anulada'], true) ? '2026-11-05' : null,
    ]);

    return $oportunidad->refresh();
}

/** El HTML del panel de la ficha abierta. */
function ftPanel(string $html): string
{
    preg_match('/<aside[^>]*class="tq-ficha".*?<\/aside>/s', $html, $panel);

    return $panel[0] ?? '';
}

test('tocar una tarjeta abre su ficha en el panel, sin salir del tablero: gema, datos y requisitos', function () {
    ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();
    $oportunidad->forceFill(['necesidad_confirmada' => true])->save();

    $pagina = Livewire::test(TableroOportunidades::class)
        ->assertDontSeeHtml('class="tq-ficha"')
        ->call('abrirFicha', $oportunidad->id)
        ->assertSet('ficha', $oportunidad->id)
        ->assertSet('versionDeLaFicha', $oportunidad->refresh()->version);

    $panel = ftPanel($pagina->html());

    expect($panel)->toContain('data-ficha="'.$oportunidad->id.'"')
        ->toContain('Luminarias del malecón')
        ->toContain($oportunidad->numero)
        ->toContain('Fichas Inventadas S.A.C.')
        ->toContain('Rosa Inventada')
        ->toContain('24,500.00')
        // D17: en la ficha, el monto completo; nada de «k» ni «M».
        ->not->toContain('24.5k')
        ->toContain('Prospecto')
        ->toContain('10 %')
        ->toContain('Cierre estimado')
        ->toContain('Días en la etapa')
        ->toContain('data-talla="en_bruto"')
        ->toContain('role="img"')
        ->toContain('Para pasar a Calificada')
        ->toMatch('/data-requisito="cumple">.*?Necesidad confirmada/s')
        ->toMatch('/data-requisito="falta">.*?Decisor identificado/s')
        ->toContain('href="'.e(VerOportunidad::getUrl(['record' => $oportunidad->id])).'"')
        // La próxima acción no sale hasta CRM-05 (D8).
        ->not->toContain('Próxima acción');
});

test('los botones de cada etapa: Pasar a la siguiente y Perder; en Negociación, Ganar y Perder', function () {
    ftEntrar(...FT_TODO);
    $prospecto = ftEnEstado('prospecto');
    $negociacion = ftEnEstado('negociacion', 'Pantallas del auditorio');

    $panel = ftPanel(Livewire::test(TableroOportunidades::class)->call('abrirFicha', $prospecto->id)->html());
    expect($panel)->toContain('data-hacia="calificada"')
        ->toContain('Pasar a Calificada')
        ->toContain('data-hacia="'.MovimientoDeOportunidad::PERDIDA.'"')
        ->not->toContain('data-hacia="'.MovimientoDeOportunidad::GANADA.'"')
        // Los botones llevan la versión leída al abrir la ficha.
        ->toContain('version: '.$prospecto->version);

    $panel = ftPanel(Livewire::test(TableroOportunidades::class)->call('abrirFicha', $negociacion->id)->html());
    expect($panel)->toContain('Para ganarla')
        ->toContain('data-hacia="'.MovimientoDeOportunidad::GANADA.'"')
        ->toContain('data-hacia="'.MovimientoDeOportunidad::PERDIDA.'"')
        ->not->toContain('Pasar a');
});

test('los botones van por el mismo camino que el arrastre: el diálogo de la acción y la acción real', function () {
    ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();

    $pagina = Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id);
    $version = $pagina->get('versionDeLaFicha');

    // Plan 2.21d, D8: la certificación ya estaba; la necesidad pide cómo se confirmó.
    $oportunidad->forceFill(['presupuesto_confirmado' => true])->save();
    $version = $oportunidad->refresh()->version;
    $pagina = Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id);
    $calificacion = [
        'necesidad_confirmada' => true, 'necesidad_detalle' => 'Correo inventado', 'presupuesto_confirmado' => true,
        'decisor_contacto_id' => PartyContacto::factory()->create(['party_id' => test()->cuentaFt->party_id])->id,
    ];

    // El botón abre el diálogo aunque falte la calificación: se completa ahí mismo y vuelve a la pantalla.
    $pagina->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $version)
        ->assertActionMounted('calificar')
        ->fillForm($calificacion)
        ->callMountedAction()
        ->assertDispatched('tq-oportunidad-dialogo-listo');

    $pagina->call('moverOportunidad', $oportunidad->id, 'prospecto', 'calificada', $version, $calificacion);

    $pagina->call('despuesDeMover', $oportunidad->id)
        ->assertSet('ficha', $oportunidad->id)
        ->assertSet('versionDeLaFicha', $oportunidad->refresh()->version);
    expect(ftPanel($pagina->html()))->toContain('data-etapa-ficha="calificada"')
        ->toContain('Para pasar a Propuesta enviada');
});

test('sin el permiso de la acción o con «propias» de otro ejecutivo, la ficha no ofrece ese botón', function () {
    ftEntrar('crm.oportunidades.editar', 'crm.oportunidades.oportunidad.perder');
    $oportunidad = ftOportunidad();

    $panel = ftPanel(Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id)->html());
    expect($panel)->not->toContain('data-hacia="calificada"')
        ->toContain('data-hacia="'.MovimientoDeOportunidad::PERDIDA.'"');

    ftEntrar('crm.oportunidades.editar-propias', 'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.perder');
    $panel = ftPanel(Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id)->html());
    expect($panel)->not->toContain('data-hacia=')
        ->toContain('No tienes permiso para mover esta oportunidad.');
});

test('una cerrada muestra su cierre, sin requisitos ni botones', function () {
    $quien = ftEntrar(...FT_TODO);
    $ganada = ftEnEstado('ganada', 'Domo del coliseo');

    $panel = ftPanel(Livewire::test(TableroOportunidades::class)->call('abrirFicha', $ganada->id)->html());

    expect($panel)->toContain('data-talla="brillante"')
        ->toContain('Ganada el')
        ->toContain('05/11/2026')
        ->not->toContain('data-requisitos')
        ->not->toContain('data-hacia=')
        ->not->toContain('Días en la etapa');
});

test('una anulada no abre: un aviso y el panel sigue cerrado', function () {
    ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();
    $pagina = Livewire::test(TableroOportunidades::class);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', 'anulada')->value('id'),
    ]);

    $pagina->call('abrirFicha', $oportunidad->id)
        ->assertSet('ficha', null)
        ->assertNotified(TableroOportunidades::FICHA_NO_ESTA)
        ->assertDontSeeHtml('class="tq-ficha"');

    // Ni con el enlace guardado.
    Livewire::withQueryParams(['ficha' => (string) $oportunidad->id])->test(TableroOportunidades::class)
        ->assertSet('ficha', null)
        ->assertDontSeeHtml('class="tq-ficha"');
});

test('el enlace guardado abre la ficha directo; cerrarla vuelve el foco a su tarjeta', function () {
    ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();

    Livewire::withQueryParams(['ficha' => (string) $oportunidad->id])->test(TableroOportunidades::class)
        ->assertSet('ficha', $oportunidad->id)
        ->assertSeeHtml('data-ficha="'.$oportunidad->id.'"')
        ->call('cerrarFicha')
        ->assertSet('ficha', null)
        ->assertSet('versionDeLaFicha', null)
        ->assertDontSeeHtml('class="tq-ficha"');
});

test('la ficha la ve solo quien ve oportunidades', function () {
    $sinPermiso = User::factory()->create()->givePermissionTo(CuentasServiceProvider::PERMISO_VER);
    test()->actingAs($sinPermiso);

    Livewire::test(TableroOportunidades::class)->assertForbidden();
    test()->get(TableroOportunidades::getUrl(['ficha' => ftOportunidad()->id]))->assertForbidden();
});

test('el panel de la ficha va encima del tablero, que queda atenuado, con su cierre por teclado', function () {
    ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();

    $html = Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id)->html();

    expect($html)->toContain('tq-tablero-detras')
        ->toContain('aria-labelledby="tq-ficha-titulo"')
        ->toContain('x-on:keydown.escape.window')
        ->toContain('Cerrar la ficha');
});

test('tras mover desde el tablero, si su ficha está abierta toma la versión nueva; las ejecuciones usan la tomada', function () {
    $quien = ftEntrar(...FT_TODO);
    $oportunidad = ftOportunidad();
    $pagina = Livewire::test(TableroOportunidades::class)->call('abrirFicha', $oportunidad->id);
    $tomada = $pagina->get('versionDeLaFicha');

    DB::table('oportunidades')->where('id', $oportunidad->id)->update(['presupuesto_confirmado' => true]);
    app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.'.calificar', [
        'oportunidad_id' => $oportunidad->id, 'version' => $tomada, 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba', 'presupuesto_confirmado' => true,
        'decisor_contacto_id' => PartyContacto::factory()->create(['party_id' => test()->cuentaFt->party_id])->id,
    ], $quien);

    // Otro la movió: el botón con la versión vieja da conflicto.
    $pagina->call('abrirMovimiento', $oportunidad->id, 'prospecto', 'calificada', $tomada)->assertActionNotMounted();

    $pagina->call('despuesDeMover', $oportunidad->id)
        ->assertSet('versionDeLaFicha', $oportunidad->refresh()->version);
});
