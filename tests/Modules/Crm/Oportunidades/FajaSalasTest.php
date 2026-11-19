<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Filament\FajaDeVidrio;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\FajaOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Filament\TablaDeLaFaja;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La faja de vidrio, paso 5 (plan 2.21b; pedido B5 y B6): los terminales de cada lugar, la ventana con la tabla de una
 * zona o sala (`TablaDeLaFaja`, solo desde `tablaDeLaFaja`), sus columnas (próxima acción oculta hasta CRM-05; contrato
 * solo con datos reales de Contratos), tocar una fila, y las salas de ganadas y perdidas con el límite de cajas.
 * Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-10 10:00');
    $this->cuentaSalas = Cuenta::factory()->create([
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Ferretería Inventada S.A.C.'])->id,
    ]);
});

function fsEntrar(): User
{
    $usuario = User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    test()->actingAs($usuario);

    return $usuario;
}

/** Crea una oportunidad y la pone en el estado sin pasar por las transiciones: la pantalla solo lee. */
function fsEnEstado(string $estado, string $nombre, string $monto = '10000.00', string $desde = '2026-11-10', string $cierreReal = '2026-11-05'): Oportunidad
{
    $oportunidad = OportunidadesDePrueba::crear(test()->cuentaSalas->id, ['nombre' => $nombre, 'monto_estimado' => $monto]);
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $estado)->value('id'),
        'en_etapa_desde' => $desde,
        'fecha_cierre_real' => in_array($estado, ['ganada', 'perdida', 'anulada'], true) ? $cierreReal : null,
    ]);

    return $oportunidad->refresh();
}

function fsPagina(): string
{
    return Livewire::test(FajaOportunidades::class)->html();
}

/**
 * Los grabados de las cajas de un lugar, en el orden en que se dibujan.
 *
 * @return list<string>
 */
function fsGrabados(string $html, string $lugar): array
{
    preg_match_all('/data-lugar="'.$lugar.'".*?<div class="tq-faja-grabado">(.*?)<\/div>/s', $html, $coincidencias);

    return array_map(fn (string $grabado): string => trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $grabado)))), $coincidencias[1]);
}

/** El texto de un terminal o letrero (`data-terminal` o `data-terminal-sala`). */
function fsTerminal(string $html, string $atributo, string $lugar): string
{
    expect(preg_match('/'.$atributo.'="'.$lugar.'"[^>]*>(.*?)<\/span>/s', $html, $coincidencia))->toBe(1);

    return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('/<small[^>]*>/', ' · ', $coincidencia[1]))));
}

/**
 * Los nombres de las filas de la tabla, en su orden.
 *
 * @return list<string>
 */
function fsFilas(mixed $tabla): array
{
    return array_values(array_map(fn (array $fila): string => $fila['nombre'], $tabla->instance()->getTableRecords()->all()));
}

/** Una versión de Contratos con datos reales: la del módulo cuando exista. */
function fsContratosConDatos(int $oportunidadId): ContratosDeOportunidad
{
    return new class($oportunidadId) implements ContratosDeOportunidad
    {
        public function __construct(private int $conContrato) {}

        public function anularBorrador(int $oportunidadId, string $motivo): void {}

        public function etiquetasDeContratos(array $oportunidadIds): array
        {
            return in_array($this->conContrato, $oportunidadIds, true) ? [$this->conContrato => 'CT-2026-0042'] : [];
        }

        public function tieneDatos(): bool
        {
            return true;
        }
    };
}

// ——— Terminales y barra superior ———

test('cada terminal dice cuántas hay y su total en soles, como totalesDeLaFaja; la barra lleva ganadas y perdidas', function () {
    fsEntrar();
    fsEnEstado('prospecto', 'Red del colegio', '1000.00');
    fsEnEstado('prospecto', 'Cámaras del mercado', '2500.50');
    fsEnEstado('ganada', 'Fibra del hospital', '8000.00');
    fsEnEstado('perdida', 'Radioenlace', '3000.00');
    fsEnEstado('perdida', 'Telefonía IP', '500.00');
    fsEnEstado('anulada', 'Anulada que no cuenta', '99999.00');

    $html = fsPagina();
    $totales = app(OportunidadesService::class)->totalesDeLaFaja();

    expect(fsTerminal($html, 'data-terminal', 'prospecto'))->toBe('Ver las 2 de Prospecto · 2 · S/ 3.5k')
        ->and(fsTerminal($html, 'data-terminal', 'calificada'))->toBe('Ver las 0 de Calificada · 0 · S/ 0')
        ->and(fsTerminal($html, 'data-terminal', 'negociacion'))->toBe('Ver las 0 de Negociación · 0 · S/ 0')
        ->and(fsTerminal($html, 'data-terminal-sala', 'ganadas'))->toBe('Ver ganadas · 1 · S/ 8k')
        ->and(fsTerminal($html, 'data-terminal-sala', 'perdidas'))->toBe('↓ Ver perdidas · 2 · S/ 3.5k')
        ->and($totales['perdidas']->cantidad)->toBe(2)
        ->and(substr_count($html, 'data-terminal="'))->toBe(4)
        ->and(preg_match('/data-boton="ganadas"[^>]*>Ver ganadas · 1</', $html))->toBe(1)
        ->and(preg_match('/data-boton="perdidas"[^>]*>Ver perdidas · 2</', $html))->toBe(1)
        // Las viñetas de las salas, solo con el número.
        ->and(preg_match('/data-vineta="ganadas"[^>]*><b>1<\/b>/', $html))->toBe(1)
        ->and(preg_match('/data-vineta="perdidas"[^>]*><b>2<\/b>/', $html))->toBe(1);
});

test('la tubería tiene su boca con brida y una cortina de diez tiras; la faja abre la tabla desde la pila y las filas', function () {
    fsEntrar();

    $html = fsPagina();

    expect($html)->toContain('data-hueco="tuberia"')->toContain('tq-faja-boca')->toContain('data-cortina')
        ->and(substr_count($html, '<i class="tq-faja-tira" data-tira'))->toBe(10)
        ->and($html)->toContain('x-on:tq-faja-abrir-lugar="$event.preventDefault(); verLugar($event.detail.lugar)"')
        ->toContain('x-on:tq-faja-abrir-fila.window="abrirFila($event.detail)"')
        ->toContain("this.\$wire.mountAction('verLugar', { lugar })")
        // Reducir animaciones (D12): sin vuelos, sin física de la cortina y sin fundido.
        ->toContain('if (this.sala === null && ! this.sinMovimiento())')
        ->toContain('html.tq-sin-animaciones .tq-faja *, ')
        ->toContain('.tq-faja-fundido');
});

// ——— La ventana con la tabla ———

test('verLugar abre la ventana del lugar con su nombre y su total; un lugar que no existe no abre', function () {
    fsEntrar();
    fsEnEstado('calificada', 'Fibra del puerto', '1200.00');
    fsEnEstado('calificada', 'Cámaras del puerto', '800.00');

    Livewire::test(FajaOportunidades::class)
        ->call('mountAction', 'verLugar', ['lugar' => 'calificada'])
        ->assertActionMounted('verLugar')
        ->assertMountedActionModalSee(['Calificada', '2 · S/ 2,000.00', 'Cerrar']);

    Livewire::test(FajaOportunidades::class)
        ->call('mountAction', 'verLugar', ['lugar' => 'ganadas'])
        ->assertMountedActionModalSee(['Ganadas', '0 · S/ 0.00 · del período de cierre']);

    Livewire::test(FajaOportunidades::class)->call('mountAction', 'verLugar', ['lugar' => 'cualquiera'])->assertNotFound();
});

test('la tabla de una zona trae solo esa zona, sin anuladas, con búsqueda, orden y páginas', function () {
    fsEntrar();
    foreach (range(1, 11) as $n) {
        fsEnEstado('calificada', sprintf('Calificada %02d', $n), (string) (1000 * $n));
    }
    fsEnEstado('calificada', 'Fibra óptica del hospital', '500.00');
    fsEnEstado('prospecto', 'En otra zona');
    fsEnEstado('anulada', 'Anulada escondida');

    $tabla = Livewire::test(TablaDeLaFaja::class, ['lugar' => 'calificada']);

    // Sin orden elegido, el de las cajas: mayor monto primero; 10 por página.
    expect(fsFilas($tabla))->toHaveCount(10)
        ->and(fsFilas($tabla)[0])->toBe('Calificada 11')
        ->and($tabla->instance()->getTableRecords()->total())->toBe(12);
    $tabla->assertDontSee('En otra zona')->assertDontSee('Anulada escondida');

    $tabla->call('gotoPage', 2);
    expect(fsFilas($tabla))->toBe(['Calificada 01', 'Fibra óptica del hospital']);

    $tabla->sortTable('nombre', 'desc')->call('gotoPage', 1);
    expect(fsFilas($tabla)[0])->toBe('Fibra óptica del hospital');

    $tabla->sortTable('monto', 'asc');
    expect(fsFilas($tabla)[0])->toBe('Fibra óptica del hospital')
        ->and(fsFilas($tabla)[1])->toBe('Calificada 01');

    $tabla->searchTable('FIBRA OPTICA');
    expect(fsFilas($tabla))->toBe(['Fibra óptica del hospital']);
});

test('columnas de una zona: días en la etapa en ámbar si está atascada, cierre estimado y sin próxima acción hasta CRM-05', function () {
    fsEntrar();
    // Calificada tiene 14 días de máximo en los parámetros iniciales.
    $atascada = fsEnEstado('calificada', 'Olvidada hace meses', desde: '2026-08-01');
    fsEnEstado('calificada', 'Al día');

    $tabla = Livewire::test(TablaDeLaFaja::class, ['lugar' => 'calificada']);
    $filas = $tabla->instance()->getTableRecords();

    expect(FajaDeVidrio::conProximaAccion())->toBeFalse()
        ->and($filas['o'.$atascada->id]['atascada'])->toBeTrue()
        ->and($filas['o'.$atascada->id]['dias'])->toBe('101')
        ->and($filas['o'.$atascada->id]['codigo'])->toBe($atascada->numero)
        ->and($filas['o'.$atascada->id]['cuenta'])->toContain('Ferretería Inventada S.A.C.')
        ->and($filas['o'.$atascada->id]['monto'])->toBe('S/ 10,000.00');
    $tabla->assertTableColumnVisible('codigo')
        ->assertTableColumnVisible('nombre')
        ->assertTableColumnVisible('cuenta')
        ->assertTableColumnVisible('monto')
        ->assertTableColumnVisible('ejecutivo')
        ->assertTableColumnVisible('dias')
        ->assertTableColumnVisible('cierre')
        ->assertTableColumnHidden('proxima')
        ->assertTableColumnDoesNotExist('contrato')
        ->assertTableColumnDoesNotExist('motivo')
        ->assertSee('Cierre estimado')
        ->assertDontSee('Próxima acción')
        ->assertSee('Atascada: pasa los días máximos de la etapa.');
});

test('la tabla de ganadas cambia las columnas finales: sin contrato mientras Contratos sea la versión de fábrica', function () {
    fsEntrar();
    fsEnEstado('ganada', 'Ganada sin contrato');

    expect(app(ContratosDeOportunidad::class)->tieneDatos())->toBeFalse();
    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'ganadas'])
        ->assertSee('Ganada sin contrato')
        ->assertTableColumnVisible('cierre')
        ->assertTableColumnHidden('contrato')
        ->assertTableColumnDoesNotExist('dias')
        ->assertTableColumnDoesNotExist('proxima')
        ->assertTableColumnDoesNotExist('motivo')
        ->assertSee('Ganada el')
        ->assertDontSee('Contrato');
});

test('con una versión de Contratos que tiene datos, la columna «Contrato» aparece sola con su etiqueta', function () {
    fsEntrar();
    $conContrato = fsEnEstado('ganada', 'Ganada con contrato');
    fsEnEstado('ganada', 'Ganada aún sin contrato');
    app()->instance(ContratosDeOportunidad::class, fsContratosConDatos($conContrato->id));

    $tabla = Livewire::test(TablaDeLaFaja::class, ['lugar' => 'ganadas'])
        ->assertTableColumnVisible('contrato')
        ->assertSee('Contrato')
        ->assertSee('CT-2026-0042');

    expect($tabla->instance()->getTableRecords()['o'.$conContrato->id]['contrato'])->toBe('CT-2026-0042');
});

test('la tabla de perdidas cambia las columnas finales por motivo, ganador y precio', function () {
    $gerencia = fsEntrar()->givePermissionTo(['crm.oportunidades.editar', DefinicionOportunidad::CLAVE.'.perder']);
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();
    $motivo = Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->firstOrFail();
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Competencia Inventada S.A.C.']);
    $perdida = OportunidadesDePrueba::crear($this->cuentaSalas->id, ['nombre' => 'Perdida por precio']);
    $pen = Moneda::query()->where('codigo', 'PEN')->value('id');
    app(OportunidadesService::class)->perder($perdida->id, $perdida->version, new MotivoElegido($motivo->id), GanadorConocido::Si, $rival->id, '18500.00', $pen, false, null, $gerencia);

    $tabla = Livewire::test(TablaDeLaFaja::class, ['lugar' => 'perdidas'])
        ->assertTableColumnVisible('motivo')
        ->assertTableColumnVisible('ganador')
        ->assertTableColumnVisible('precio')
        ->assertTableColumnDoesNotExist('dias')
        ->assertTableColumnDoesNotExist('contrato')
        ->assertSee('Perdida el')
        ->assertSee($motivo->nombre)
        ->assertSee('Competencia Inventada S.A.C.')
        ->assertSee('S/ 18,500.00');

    expect($tabla->instance()->getTableRecords()['o'.$perdida->id]['cierre'])->toBe('10/11/2026');
});

test('tocar una fila pide a la faja abrir esa caja, con la ficha como respaldo', function () {
    fsEntrar();
    $oportunidad = fsEnEstado('negociacion', 'Centro de datos');

    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'negociacion'])
        ->assertSeeHtml('wire:click.prevent.stop="abrirFila(&#039;o'.$oportunidad->id.'&#039;)"')
        ->call('abrirFila', 'o'.$oportunidad->id)
        ->assertDispatched(TablaDeLaFaja::ABRIR_FILA, id: $oportunidad->id, url: VerOportunidad::getUrl(['record' => $oportunidad->id]));
});

test('sin ver oportunidades la tabla no se abre, y un lugar inventado tampoco', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'prospecto'])->assertForbidden();

    fsEntrar();
    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'cualquiera'])->assertNotFound();
});

// ——— Las salas ———

test('la sala de ganadas: más de 12, las 11 más recientes sobre pallets con su código y una pila «+N más»', function () {
    fsEntrar();
    $viejas = [fsEnEstado('ganada', 'Ganada vieja 1', cierreReal: '2026-11-01'), fsEnEstado('ganada', 'Ganada vieja 2', cierreReal: '2026-11-02')];
    $recientes = [];
    foreach (range(1, 11) as $n) {
        $recientes[] = fsEnEstado('ganada', "Ganada reciente {$n}", cierreReal: '2026-11-0'.min(9, 2 + $n));
    }

    $html = fsPagina();
    $grabados = fsGrabados($html, 'ganadas');

    expect($grabados)->toHaveCount(11)
        ->and($html)->toContain('data-pila="ganadas"')->toContain('+2 más')
        // Las más recientes primero: la del 9 de noviembre de mayor id va adelante.
        ->and($grabados[0])->toBe(end($recientes)->numero)
        ->and($html)->toContain('tq-faja-ganada')
        ->and($html)->not->toContain('data-caja="'.$viejas[0]->id.'"')
        ->and($html)->not->toContain('data-caja="'.$viejas[1]->id.'"');
});

test('el sótano de perdidas: hasta 6, cajas completas caídas, las más recientes primero', function () {
    fsEntrar();
    fsEnEstado('perdida', 'Perdida del 2', '1000.00', cierreReal: '2026-11-02');
    fsEnEstado('perdida', 'Perdida del 7', '2000.00', cierreReal: '2026-11-07');
    fsEnEstado('perdida', 'Perdida del 4', '3000.00', cierreReal: '2026-11-04');

    $grabados = fsGrabados(fsPagina(), 'perdidas');

    expect(array_map(fn (string $g): string => explode(' S/', $g)[0], $grabados))->toBe(['Perdida del 7', 'Perdida del 4', 'Perdida del 2'])
        ->and($grabados[0])->toContain('S/ 2k')->toContain('Ferretería Inventada S.A.C.');
});

test('las anuladas no aparecen en ninguna sala ni en sus tablas', function () {
    fsEntrar();
    fsEnEstado('ganada', 'Ganada visible');
    fsEnEstado('perdida', 'Perdida visible');
    fsEnEstado('anulada', 'Anulada escondida');

    $html = fsPagina();

    expect(fsGrabados($html, 'ganadas'))->toHaveCount(1)
        ->and(fsGrabados($html, 'perdidas'))->toHaveCount(1)
        ->and($html)->not->toContain('Anulada escondida');
    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'ganadas'])->assertSee('Ganada visible')->assertDontSee('Anulada escondida');
    Livewire::test(TablaDeLaFaja::class, ['lugar' => 'perdidas'])->assertSee('Perdida visible')->assertDontSee('Anulada escondida');
});
