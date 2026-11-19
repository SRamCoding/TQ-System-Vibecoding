<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\DibujoDeLaBotonera;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadRechazada;
use App\Modules\Crm\Oportunidades\Services\TableroDeBotonera;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D3–D6: el tablero de botonera. La gema solo en el título y sin montos; la botonera con la cantidad abre la
 * tabla de la etapa; hasta 5 códigos puestos por etapa, recordados por persona; «Avanzar a …» con una sola puesta.
 * Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-16 10:00');
    $ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    test()->cuenta = Cuenta::factory()->create([
        'ejecutivo_user_id' => $ejecutivo->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Colegio Botonera Inventado S.A.C.'])->id,
    ]);
    test()->ejecutivoId = $ejecutivo->id;
    test()->yo = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    test()->actingAs(test()->yo);
});

function opBotonera(string $nombre, string $etapa = 'prospecto'): Oportunidad
{
    $o = OportunidadesDePrueba::crear(test()->cuenta->id, ['ejecutivo_user_id' => test()->ejecutivoId, 'nombre' => $nombre, 'monto_estimado' => '12345.00']);
    Oportunidad::query()->whereKey($o->id)->update(['estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $etapa)->value('id')]);

    return $o->refresh();
}

test('sin ver oportunidades el tablero no abre', function () {
    test()->actingAs(User::factory()->create())->get(TableroOportunidades::getUrl())->assertForbidden();
});

test('seis columnas con su gema solo en el título y la botonera con la cantidad; sin montos', function () {
    opBotonera('Una en prospecto inventada');
    opBotonera('Otra en prospecto inventada');

    $pagina = Livewire::test(TableroOportunidades::class);
    $html = $pagina->html();

    expect(preg_match_all('/data-gema-titulo="[a-z_]+"/', $html))->toBe(6)
        ->and(substr_count($html, 'data-botonera='))->toBe(6)
        ->and($html)->toMatch('/data-botonera="prospecto">\s*<b>2<\/b>/')
        ->and(app(DibujoDeLaBotonera::class)->columnas(test()->yo, null)[0])->not->toHaveKey('total');

    // El tablero no lleva los montos de las oportunidades (D3): solo la tabla de la etapa, al abrirla.
    $pagina->assertDontSee('S/ 12,345.00')->call('abrirEtapa', 'prospecto')->assertSee('S/ 12,345.00')->assertSee('0 de 5 en el tablero');
});

test('marcar en la tabla la pone en el tablero solo con su código; con 5, las demás no se pueden marcar', function () {
    $ops = array_map(fn (int $i): Oportunidad => opBotonera("Prospecto {$i} inventado"), range(1, 6));
    $pagina = Livewire::test(TableroOportunidades::class)->call('abrirEtapa', 'prospecto');

    foreach (array_slice($ops, 0, 5) as $o) {
        $pagina->call('alternarEnTablero', $o->id, true);
    }

    $pagina->assertSee('5 de 5 en el tablero')
        ->assertSeeHtml('data-codigo="'.$ops[0]->id.'"')
        ->call('alternarEnTablero', $ops[5]->id, true)
        ->assertNotified('En Prospecto ya tienes 5 en el tablero: quita una para poner otra.');

    expect($pagina->html())->toMatch('/disabled="disabled"[^>]*data-marcar="'.$ops[5]->id.'"|data-marcar="'.$ops[5]->id.'"[^>]*disabled/')
        ->and(app(TableroDeBotonera::class)->puestas(test()->yo)['prospecto'])->toHaveCount(5);

    // Con su ×, se quita y libera el lugar.
    $pagina->call('alternarEnTablero', $ops[0]->id, false)->assertDontSeeHtml('data-codigo="'.$ops[0]->id.'"')->assertSee('4 de 5 en el tablero');
});

test('el tope de 5 por etapa lo exige el servicio, también sin pantalla', function () {
    $ops = array_map(fn (int $i): Oportunidad => opBotonera("Calificada {$i} inventada", 'calificada'), range(1, 6));

    foreach (array_slice($ops, 0, 5) as $o) {
        app(EjecutorAcciones::class)->ejecutar(TableroDeBotonera::FIJAR, ['oportunidad_id' => $o->id], test()->yo);
    }

    expect(fn () => app(EjecutorAcciones::class)->ejecutar(TableroDeBotonera::FIJAR, ['oportunidad_id' => $ops[5]->id], test()->yo))
        ->toThrow(OportunidadRechazada::class, 'ya tienes 5');

    // En otra etapa sí cabe.
    $otra = opBotonera('Negociación inventada', 'negociacion');
    app(EjecutorAcciones::class)->ejecutar(TableroDeBotonera::FIJAR, ['oportunidad_id' => $otra->id], test()->yo);
    expect(app(TableroDeBotonera::class)->puestas(test()->yo)['negociacion'])->toBe([$otra->id]);
});

test('lo puesto se recuerda para esa persona y no para otra', function () {
    $o = opBotonera('Recordada inventada');
    Livewire::test(TableroOportunidades::class)->call('alternarEnTablero', $o->id, true);

    Livewire::test(TableroOportunidades::class)->assertSeeHtml('data-codigo="'.$o->id.'"');

    test()->actingAs(User::factory()->create()->givePermissionTo(OportunidadesServiceProvider::PERMISO_VER));
    Livewire::test(TableroOportunidades::class)->assertDontSeeHtml('data-codigo="'.$o->id.'"');
});

test('«Avanzar a …» se activa solo con una puesta en el tablero de esa etapa; en Ganadas y Perdidas no hay', function () {
    $a = opBotonera('Primera inventada');
    $b = opBotonera('Segunda inventada');
    $avanzar = fn ($p): bool => str_contains($p->html(), 'data-avanzar="no"');

    $pagina = Livewire::test(TableroOportunidades::class)->call('abrirEtapa', 'prospecto');
    expect($avanzar($pagina))->toBeTrue();

    $pagina->call('alternarEnTablero', $a->id, true)->assertSee('Avanzar a Calificada');
    expect($avanzar($pagina))->toBeFalse();

    $pagina->call('alternarEnTablero', $b->id, true);
    expect($avanzar($pagina))->toBeTrue();

    $pagina->call('abrirEtapa', 'ganadas')->assertDontSeeHtml('data-avanzar');
    $pagina->call('abrirEtapa', 'negociacion')->assertSee('Avanzar a Ganadas');
});

test('D9: al avanzar, la gema vuela y llega convertida; con reducir animaciones, sin vuelo', function () {
    $html = Livewire::test(TableroOportunidades::class)->html();

    expect($html)->toContain('async volar(desde, hacia)')
        ->toContain('gema.innerHTML = destino.innerHTML;')
        ->toContain('if (m && Number(detalle.id) === m.id && ! this.quieto()) await this.volar(m.desde, m.hacia);')
        ->toContain("document.documentElement.classList.contains('tq-sin-animaciones')")
        ->toContain('html.tq-sin-animaciones .tq-botonera-titulo .tq-gema-titulo.tq-salta { animation: none; }');
});
