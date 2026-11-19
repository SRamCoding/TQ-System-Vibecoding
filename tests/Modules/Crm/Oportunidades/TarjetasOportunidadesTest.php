<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\DibujoDeLinea;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FiltrosOportunidades;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D1 y D11: la vista «Tarjetas» con la foto o el dibujo de su línea, sin barra de etapas, y los filtros con
 * su cantidad. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-16 10:00');
    test()->ejecutivo = User::factory()->create(['name' => 'Valeria Ejecutiva Prueba'])->givePermissionTo(CuentasService::PERMISO_ATENDER);
    test()->cuenta = Cuenta::factory()->create([
        'ejecutivo_user_id' => test()->ejecutivo->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Clínica Vitrina Inventada S.A.C.'])->id,
    ]);
    test()->actingAs(User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]));
});

/** @param  array<string, mixed>  $datos */
function opVitrina(array $datos = [], ?string $etapa = null): Oportunidad
{
    $o = OportunidadesDePrueba::crear(test()->cuenta->id, ['ejecutivo_user_id' => test()->ejecutivo->id, ...$datos]);

    if ($etapa !== null) {
        Oportunidad::query()->whereKey($o->id)->update(['estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $etapa)->value('id')]);
    }

    return $o->refresh();
}

/** @return list<int> */
function enLasTarjetas(Testable $vista): array
{
    preg_match_all('/data-oportunidad="(\d+)"/', $vista->html(), $ids);

    return array_map(intval(...), $ids[1]);
}

test('la tarjeta lleva el dibujo de su línea con la línea y la etapa, el anillo y el monto; sin barra de etapas', function () {
    $video = LineaSolucion::query()->where('codigo', 'VIDEOVIGILANCIA')->firstOrFail();
    $o = opVitrina(['linea_solucion_id' => $video->id, 'nombre' => 'Cámaras del pabellón inventado']);

    Livewire::withQueryParams([])->test(TarjetasOportunidades::class)
        ->assertSee('Cámaras del pabellón inventado')->assertSee('Videovigilancia')->assertSee('Clínica Vitrina Inventada')
        ->assertSeeHtml('<linearGradient id="tq-dl-'.$o->id.'"')->assertSeeHtml('stop-color="#C7D2FE"')
        ->assertSeeHtml('class="tq-op-anillo"')
        ->assertDontSeeHtml('tq-etapas-barra');

    // Una línea sin dibujo propio toma el genérico.
    expect(DibujoDeLinea::svg(1, 'SOFTWARE', 4))->toContain('M200 30l52 22-52 22-52-22z');
});

test('los filtros cuentan sin su propio grupo y filtran; el orden por monto', function () {
    $chica = opVitrina(['monto_estimado' => '1000.00', 'nombre' => 'Chica inventada']);
    $grande = opVitrina(['monto_estimado' => '9000.00', 'nombre' => 'Grande inventada'], 'calificada');

    expect(enLasTarjetas(Livewire::withQueryParams([])->test(TarjetasOportunidades::class)))->toBe([$grande->id, $chica->id])
        ->and(enLasTarjetas(Livewire::withQueryParams([])->test(TarjetasOportunidades::class)->set('etapas', ['calificada'])))->toBe([$grande->id])
        ->and(enLasTarjetas(Livewire::withQueryParams([])->test(TarjetasOportunidades::class)->set('buscar', 'chica')))->toBe([$chica->id]);

    $opciones = app(PortadaOportunidades::class)->opcionesVitrina(new FiltrosOportunidades(etapas: ['calificada']));
    expect($opciones['etapas']['prospecto']['cantidad'])->toBe(1)->and($opciones['etapas']['calificada']['cantidad'])->toBe(1)
        ->and($opciones['ejecutivos'][test()->ejecutivo->id]['cantidad'])->toBe(1)
        ->and(array_keys($opciones['clientes']))->toBe(['privado']);
});

test('«Atascadas» deja solo las que pasaron el plazo de su etapa', function () {
    // Calificada tiene 14 días de plazo; Prospecto, ninguno.
    $vieja = opVitrina(['nombre' => 'Vieja inventada'], 'calificada');
    Oportunidad::query()->whereKey($vieja->id)->update(['en_etapa_desde' => '2026-09-01']);
    opVitrina(['nombre' => 'Nueva inventada'], 'calificada');
    $sinPlazo = opVitrina(['nombre' => 'Prospecto viejo inventado']);
    Oportunidad::query()->whereKey($sinPlazo->id)->update(['en_etapa_desde' => '2026-09-01']);

    Livewire::withQueryParams([])->test(TarjetasOportunidades::class)->set('atencion', ['atascadas'])
        ->assertSee('atascada')
        ->tap(fn (Testable $v) => expect(enLasTarjetas($v))->toBe([$vieja->id]));
});

test('tocar una tarjeta lleva a la ficha completa, no al panel lateral', function () {
    $o = opVitrina();

    Livewire::withQueryParams([])->test(TarjetasOportunidades::class)
        ->assertSeeHtml('href="'.e(VerOportunidad::getUrl(['record' => $o->id])).'" wire:navigate class="tq-op-card"')
        ->assertDontSeeHtml('wire:click="abrirFicha('.$o->id.')"');
});
