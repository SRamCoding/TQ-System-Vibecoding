<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\CapasDelDespiece;
use App\Modules\Crm\Oportunidades\Filament\DespieceDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\CompetidoresDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\EtapasDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\HistorialDeOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Pestanas\ComponentePestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\PestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La faja de vidrio, paso 6 (plan 2.21b; D10, D14; pedido B3): el despiece de una caja. Una capa por pestaña de la ficha
 * (las registradas por otros módulos incluidas), las que esperan su módulo vacías con su texto; el panel con el resumen,
 * los requisitos del escáner siguiente y los botones de pasar, ganar y perder según la etapa y los permisos; en una
 * cerrada, su cierre y ningún botón. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-10 10:00');
    $this->ejecutivoDe = User::factory()->create(['name' => 'Rosaura Inventada']);
    $this->cuentaDe = Cuenta::factory()->create([
        'ejecutivo_user_id' => $this->ejecutivoDe->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Vidrios Inventados S.A.C.'])->id,
    ]);
});

/** Una pestaña que otro módulo suma a la ficha, solo para estas pruebas. */
final class DespiecePestanaDePrueba extends ComponentePestanaOportunidad
{
    public function render(): string
    {
        return '<div>Cotizaciones de prueba de la oportunidad {{ $oportunidadId }}</div>';
    }
}

function deEntrar(string ...$permisos): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Crea una oportunidad y la pone en la etapa sin pasar por las transiciones: el despiece solo lee. */
function deEnEtapa(string $estado, string $nombre = 'Mamparas del hotel', string $desde = '2026-11-05'): Oportunidad
{
    $oportunidad = OportunidadesDePrueba::crear(test()->cuentaDe->id, [
        'nombre' => $nombre,
        'monto_estimado' => '24715.10',
        'ejecutivo_user_id' => test()->ejecutivoDe->id,
        'fecha_cierre_estimada' => '2026-12-15',
    ]);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $estado)->value('id'),
        'en_etapa_desde' => $desde,
        'fecha_cierre_real' => in_array($estado, ['ganada', 'perdida'], true) ? '2026-11-07' : null,
    ]);

    return $oportunidad->refresh();
}

/** @return list<string> */
function deClaves(): array
{
    return array_column(app(CapasDelDespiece::class)->para(auth()->user() instanceof User ? auth()->user() : null), 'clave');
}

/** Que los textos estén en el HTML, en ese orden (el HTML sin escapar, no la respuesta en JSON). */
function deEnOrden(string $html, array $textos): void
{
    $desde = 0;

    foreach ($textos as $texto) {
        $donde = mb_strpos($html, e($texto), $desde);
        expect($donde)->not->toBeFalse("Falta «{$texto}» en su lugar.");
        $desde = (int) $donde + mb_strlen(e($texto));
    }
}

test('las capas siguen las pestañas de la ficha y las que esperan su módulo salen vacías con su texto', function () {
    // Cotizaciones ya llegó (plan 2.22b): su capa es su pestaña y se ve con su permiso.
    Permission::findOrCreate('crm.cotizaciones.ver');
    deEntrar('crm.cotizaciones.ver');
    $oportunidad = deEnEtapa('prospecto');

    expect(deClaves())->toBe(['resumen', 'cotizaciones', 'presupuesto', 'actividades', 'competidores', 'documentos', 'etapas', 'historial']);

    $capas = collect(app(CapasDelDespiece::class)->para(auth()->user() instanceof User ? auth()->user() : null))->keyBy('clave');
    expect($capas->where('tipo', 'espera')->keys()->all())->toBe(['presupuesto', 'actividades', 'documentos'])
        ->and($capas['cotizaciones']['componente'])->toBe('oportunidad.cotizaciones')
        ->and($capas['actividades']['espera'])->toContain('CRM-05')
        ->and($capas['competidores']['componente'])->toBe(CompetidoresDeOportunidad::class)
        ->and($capas['etapas']['componente'])->toBe(EtapasDeOportunidad::class)
        ->and($capas['historial']['componente'])->toBe(HistorialDeOportunidad::class)
        ->and($capas['historial']['parametro'])->toBe('registroId');

    $despiece = Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id);
    $despiece->assertSeeHtml('data-capa-despiece="cotizaciones"');
    deEnOrden($despiece->html(), ['Resumen', 'Cotizaciones', 'Presupuesto', 'Actividades', 'Competidores', 'Documentos', 'Historial de etapas', 'Historial']);

    // Una capa vacía, elegida: su texto de espera y nada inventado.
    $despiece->set('capa', 'presupuesto')
        ->assertSeeHtml('data-capa-abierta="presupuesto"')
        ->assertSee(CapasDelDespiece::EN_ESPERA['presupuesto']['espera']);
});

test('una pestaña registrada por otro módulo reemplaza a su capa vacía, en su lugar, y solo la ve quien tiene su permiso', function () {
    // La de Cotizaciones la registra su módulo (plan 2.22b); la licitación, esta prueba.
    app(RegistroPestanasOportunidad::class)->registrar(new PestanaOportunidad('licitacion', 'Licitación de origen', null, DespiecePestanaDePrueba::class, 'heroicon-o-building-library'));
    $oportunidad = deEnEtapa('prospecto');

    // Sin el permiso de Cotizaciones: ni su capa ni la vacía. La de todos va antes del Historial.
    deEntrar();
    expect(deClaves())->toBe(['resumen', 'presupuesto', 'actividades', 'competidores', 'documentos', 'etapas', 'licitacion', 'historial']);

    Permission::findOrCreate('crm.cotizaciones.ver');
    deEntrar('crm.cotizaciones.ver');
    $capas = collect(app(CapasDelDespiece::class)->para(auth()->user() instanceof User ? auth()->user() : null))->keyBy('clave');
    expect($capas->keys()->all())->toBe(['resumen', 'cotizaciones', 'presupuesto', 'actividades', 'competidores', 'documentos', 'etapas', 'licitacion', 'historial'])
        ->and($capas['cotizaciones']['tipo'])->toBe('componente')
        ->and($capas['cotizaciones']['componente'])->toBe('oportunidad.cotizaciones')
        ->and($capas['cotizaciones']['espera'])->toBeNull();

    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->set('capa', 'cotizaciones')
        ->assertSeeHtml('data-capa-abierta="cotizaciones"')
        ->assertDontSee('cuando llegue su módulo (CRM-03)');

    Livewire::test(DespiecePestanaDePrueba::class, ['oportunidadId' => $oportunidad->id])
        ->assertSee("Cotizaciones de prueba de la oportunidad {$oportunidad->id}");
});

test('el resumen muestra los datos de la oportunidad, como la cabecera de la ficha', function () {
    deEntrar();
    $oportunidad = deEnEtapa('calificada', desde: '2026-11-05');

    $html = Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->assertSeeHtml('data-capa-abierta="resumen"')
        ->html();
    deEnOrden($html, [
        'Número', $oportunidad->numero,
        'Oportunidad', 'Mamparas del hotel',
        'Cuenta', 'Vidrios Inventados S.A.C.',
        'Monto', '24,715.10',
        'Ejecutivo', 'Rosaura Inventada',
        'Etapa', 'Calificada',
        'Probabilidad', '%',
        'Cierre estimado', '15/12/2026',
        'Días en la etapa', '5 días',
    ]);
});

test('el panel lista lo que exige el escáner siguiente, con lo que se cumple y lo que falta', function () {
    deEntrar();
    $oportunidad = deEnEtapa('prospecto');
    DB::table('oportunidades')->where('id', $oportunidad->id)->update(['necesidad_confirmada' => true, 'presupuesto_confirmado' => false]);

    $html = Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->assertSee('Escáner 1 · hacia Calificada')
        ->html();

    expect($html)->toMatch('/data-requisito="cumple">.*?Necesidad confirmada/s')
        ->and($html)->toMatch('/data-requisito="falta">.*?Presupuesto confirmado/s')
        ->and($html)->toMatch('/data-requisito="falta">.*?Decisor identificado/s');
});

test('los botones dependen de la etapa y de los permisos de cada acción', function () {
    $prospecto = deEnEtapa('prospecto', 'Ventanales del colegio');
    $negociacion = deEnEtapa('negociacion', 'Fachada del banco');

    deEntrar('crm.oportunidades.editar', 'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.perder');
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $prospecto->id)
        ->assertSeeHtml('data-hacia="calificada"')
        ->assertSeeHtml('data-hacia="'.FajaOportunidades::PERDIDA.'"')
        ->assertDontSeeHtml('data-hacia="'.FajaOportunidades::GANADA.'"')
        ->assertSee('Pasar a Calificada');
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $negociacion->id)
        ->assertSee('Escáner 4 · hacia la tubería de ganadas')
        ->assertSeeHtml('data-hacia="'.FajaOportunidades::GANADA.'"')
        ->assertSeeHtml('data-hacia="'.FajaOportunidades::PERDIDA.'"')
        ->assertDontSeeHtml('data-hacia="calificada"');

    // Sin el permiso de una acción, su botón no está; sin ninguno, se dice.
    deEntrar('crm.oportunidades.editar', 'crm.oportunidades.oportunidad.perder');
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $prospecto->id)
        ->assertDontSeeHtml('data-hacia="calificada"')
        ->assertSeeHtml('data-hacia="'.FajaOportunidades::PERDIDA.'"');
    deEntrar();
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $negociacion->id)
        ->assertDontSeeHtml('data-hacia=')
        ->assertSee('No tienes permiso para mover esta oportunidad.');
});

test('una oportunidad anulada después de cargar la faja no abre su despiece', function () {
    deEntrar();
    $oportunidad = deEnEtapa('prospecto');
    // Otra persona la anula mientras la faja está abierta (D11.2 de la 2.21: las anuladas no están en ningún lugar).
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', 'anulada')->value('id'),
    ]);

    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->assertNotFound()
        ->assertSet('oportunidadId', null);
});

test('una caja cerrada muestra su cierre y ningún botón ni escáner', function () {
    deEntrar(DefinicionOportunidad::CLAVE.'.perder', DefinicionOportunidad::CLAVE.'.ganar');
    $ganada = deEnEtapa('ganada', 'Domo del centro comercial');
    $perdida = deEnEtapa('perdida', 'Barandas del estadio');

    $html = Livewire::test(DespieceDeOportunidad::class)->call('abrir', $ganada->id)
        ->assertSeeHtml('data-cierre')
        ->assertDontSeeHtml('data-escaner-siguiente')
        ->assertDontSeeHtml('data-hacia=')
        ->html();
    deEnOrden($html, ['Cierre', 'Ganada el', '07/11/2026']);
    $html = Livewire::test(DespieceDeOportunidad::class)->call('abrir', $perdida->id)
        ->assertDontSeeHtml('data-hacia=')
        ->html();
    deEnOrden($html, ['Perdida el', '07/11/2026', 'Motivo', 'Ganador', 'Precio']);
});

test('cerrar vuelve a la faja y solo lo abre quien ve oportunidades', function () {
    deEntrar();
    $oportunidad = deEnEtapa('prospecto');

    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->assertSet('oportunidadId', $oportunidad->id)
        ->set('capa', 'competidores')
        ->call('cerrar')
        ->assertSet('oportunidadId', null)
        ->assertSet('capa', 'resumen')
        ->assertDontSeeHtml('data-panel');

    // Una capa que no existe vuelve al resumen.
    Livewire::test(DespieceDeOportunidad::class)->call('abrir', $oportunidad->id)
        ->set('capa', 'inventada')
        ->assertSeeHtml('data-capa-abierta="resumen"');

    test()->actingAs(User::factory()->create());
    Livewire::test(DespieceDeOportunidad::class)->assertForbidden();
});

test('la faja lleva el despiece, encima del visor', function () {
    deEntrar();
    deEnEtapa('prospecto');

    Livewire::test(FajaOportunidades::class)
        ->assertSeeHtml('x-on:tq-faja-mover.window="moverSinArrastre($event.detail)"')
        ->assertSeeLivewire(DespieceDeOportunidad::class);
});

test('las pestañas de la ficha que el despiece reutiliza se dibujan para la oportunidad', function () {
    deEntrar();
    $oportunidad = deEnEtapa('calificada');
    OportunidadCompetidor::query()->create([
        'oportunidad_id' => $oportunidad->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Cristales Rivales S.A.'])->id,
    ]);

    Livewire::test(CompetidoresDeOportunidad::class, ['oportunidadId' => $oportunidad->id])->assertSee('Cristales Rivales S.A.');
    Livewire::test(EtapasDeOportunidad::class, ['oportunidadId' => $oportunidad->id])->assertOk();
    Livewire::test(HistorialDeOportunidad::class, ['registroId' => $oportunidad->id])->assertOk();
});
