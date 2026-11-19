<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\DibujoDelCronograma;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\LineaDeTiempoOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D11 (arreglo): la línea de tiempo como el prototipo aprobado. Una fila por oportunidad con una barra por
 * etapa desde el historial, lo que falta hasta el cierre esperado y el hito del cierre. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-01 10:00');
    $ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    test()->cuentaCr = Cuenta::factory()->create(['ejecutivo_user_id' => $ejecutivo->id, 'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Universidad Cronograma Inventada'])->id]);
    test()->ejecutivoCr = $ejecutivo;
    test()->gerenciaCr = User::factory()->create()->givePermissionTo([
        OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, 'crm.oportunidades.editar', 'crm.oportunidades.oportunidad.calificar',
    ]);
});

function opCronograma(string $nombre, string $cierre): Oportunidad
{
    return OportunidadesDePrueba::crear(test()->cuentaCr->id, ['ejecutivo_user_id' => test()->ejecutivoCr->id, 'nombre' => $nombre, 'fecha_cierre_estimada' => $cierre]);
}

test('cada oportunidad es una fila: una barra por etapa desde su historial, lo que falta rayado y el rombo del cierre esperado', function () {
    $o = opCronograma('Fibra del campus inventado', '2026-12-20');
    test()->travelTo('2026-11-10 10:00');
    DB::table('oportunidades')->where('id', $o->id)->update(['presupuesto_confirmado' => true]);
    app(EjecutorAcciones::class)->ejecutar(DefinicionOportunidad::CLAVE.'.calificar', [
        'oportunidad_id' => $o->id, 'version' => $o->refresh()->version, 'necesidad_confirmada' => true, 'necesidad_detalle' => 'Reunión de prueba',
        'decisor_contacto_id' => PartyContacto::factory()->create(['party_id' => test()->cuentaCr->party_id])->id,
    ], test()->gerenciaCr);
    // La fecha del historial la pone la base (hora real): se fija la del ejemplo.
    DB::table('historial_estados')->where('documentable_type', 'oportunidad')->where('documentable_id', $o->id)->whereNotNull('desde_estado_id')->update(['fecha' => '2026-11-10 10:00:00']);
    DB::table('historial_estados')->where('documentable_type', 'oportunidad')->where('documentable_id', $o->id)->whereNull('desde_estado_id')->update(['fecha' => '2026-11-01 10:00:00']);
    test()->travelTo('2026-11-16 10:00');

    $fila = collect(app(DibujoDelCronograma::class)->datos()['filas'])->firstWhere('id', $o->id);

    expect(array_column($fila['tramos'], 'etapa'))->toBe(['prospecto', 'calificada'])
        ->and($fila['tramos'][0]['titulo'])->toBe('Prospecto: 01/11/2026 → 10/11/2026')
        ->and($fila['tramos'][1]['titulo'])->toBe('Calificada: 10/11/2026 → 16/11/2026')
        ->and($fila['falta'])->not->toBeNull()
        ->and($fila['hito']['tipo'])->toBe('esperado')->and($fila['hito']['titulo'])->toBe('Cierre esperado: 20/12/2026');
});

test('una cerrada termina en ✓ o ✕ en su fecha de cierre, sin rayado', function () {
    $o = opCronograma('Ganada inventada', '2026-11-30');
    DB::table('oportunidades')->where('id', $o->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', 'ganada')->value('id'),
        'fecha_cierre_real' => '2026-11-12',
    ]);
    test()->travelTo('2026-11-16 10:00');

    $fila = collect(app(DibujoDelCronograma::class)->datos()['filas'])->firstWhere('id', $o->id);

    expect($fila['hito']['tipo'])->toBe('ganada')->and($fila['hito']['titulo'])->toBe('Ganada el 12/11/2026')->and($fila['falta'])->toBeNull();
});

test('la pantalla: meses, la línea de hoy, la leyenda, y tocar una fila abre su ficha', function () {
    $o = opCronograma('Antena del cerro inventada', '2027-01-15');
    test()->travelTo('2026-11-16 10:00');
    test()->actingAs(test()->gerenciaCr);

    Livewire::test(LineaDeTiempoOportunidades::class)
        ->assertSeeHtml('data-cronograma')->assertSeeHtml('data-hoy')->assertSeeHtml('data-oportunidad="'.$o->id.'"')
        ->assertSeeHtml('data-hito="esperado"')->assertSee('Lo que falta hasta el cierre esperado')->assertSee('Negociación')
        ->call('abrirFicha', $o->id)->assertSet('ficha', $o->id);
});

test('muestra 10 por página y se avanza 1, 2, 3', function () {
    foreach (range(1, 23) as $i) {
        opCronograma("Oportunidad {$i} inventada", CarbonImmutable::parse('2026-12-01')->addDays($i)->toDateString());
    }
    test()->travelTo('2026-11-16 10:00');
    test()->actingAs(test()->gerenciaCr);
    $filas = fn (Testable $p): int => preg_match_all('/class="tq-crono-fila"[^>]*data-oportunidad=/', $p->html());

    $pagina = Livewire::withQueryParams([])->test(LineaDeTiempoOportunidades::class);
    expect($filas($pagina))->toBe(10)->and($pagina->html())->toContain('Oportunidad 1 inventada')->not->toContain('Oportunidad 11 inventada');

    $pagina->call('gotoPage', 3);
    expect($filas($pagina))->toBe(3)->and($pagina->html())->toContain('Oportunidad 23 inventada');
});

test('el buscador sube arriba la que mejor coincide mientras se escribe y vuelve a la página 1', function () {
    foreach (range(1, 15) as $i) {
        opCronograma("Cableado {$i} inventado", CarbonImmutable::parse('2026-12-01')->addDays($i)->toDateString());
    }
    $buscada = opCronograma('Videowall del auditorio inventado', '2027-02-01');
    test()->travelTo('2026-11-16 10:00');
    test()->actingAs(test()->gerenciaCr);
    $primera = fn (Testable $p): int => (int) (preg_match('/class="tq-crono-fila"[^>]*?data-oportunidad="(\d+)"/', $p->html(), $m) ? $m[1] : 0);

    $pagina = Livewire::withQueryParams([])->test(LineaDeTiempoOportunidades::class)->call('gotoPage', 2);
    expect($primera($pagina))->not->toBe($buscada->id);

    // Letra por letra: en cuanto coincide, queda primera y se marca; se vuelve a la página 1.
    $pagina->set('buscar', 'vid');
    expect($primera($pagina))->toBe($buscada->id)->and($pagina->html())->toContain('data-primera')->toContain('1 coincide');
    $pagina->set('buscar', 'Vídeowall aud');
    expect($primera($pagina))->toBe($buscada->id);
    $pagina->set('buscar', 'zzz');
    expect($pagina->html())->toContain('Ninguna coincide');

    expect(collect(app(DibujoDelCronograma::class)->datos('cableado 1')['filas'])->take(2)->pluck('nombre')->all())
        ->toBe(['Cableado 1 inventado', 'Cableado 10 inventado']);
});
