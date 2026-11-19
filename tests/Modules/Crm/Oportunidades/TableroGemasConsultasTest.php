<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\ColumnaTablero;
use App\Modules\Crm\Oportunidades\Services\EmbudoEnSoles;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\MarcaLineaDeTiempo;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\TableroDeGemas;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las lecturas del tablero de gemas (plan 2.21c, paso 2; D1, D2 y D9): las cuatro que se ven por columna (atascadas
 * primero, luego monto en soles), los totales de cada etapa y del embudo ponderado, las salas del período, la fila de
 * cifras y la línea de tiempo. Sin anuladas. Empresas y ejecutivos inventados.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, CatalogosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    $this->travelTo('2026-11-10 10:00');
    $this->cuenta = Cuenta::factory()->create();
    $this->tablero = app(TableroDeGemas::class);
});

function tgMoneda(string $codigo): int
{
    return Moneda::query()->where('codigo', $codigo)->value('id') ?? Moneda::factory()->create(['codigo' => $codigo])->id;
}

/** @param  array<string, mixed>  $datos */
function tgCrear(int $cuentaId, string $nombre, string $monto, array $datos = []): Oportunidad
{
    return OportunidadesDePrueba::crear($cuentaId, ['nombre' => $nombre, 'monto_estimado' => $monto, ...$datos]);
}

/** Pone una oportunidad en un estado sin pasar por las transiciones: aquí solo importa lo que se lee. */
function tgEnEstado(Oportunidad $oportunidad, string $codigo, string $desde = '2026-11-10', ?string $cierreReal = null): Oportunidad
{
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $codigo)->value('id'),
        'en_etapa_desde' => $desde,
        'fecha_cierre_real' => $cierreReal,
    ]);

    return $oportunidad->refresh();
}

/** @return list<string> */
function tgNombres(ColumnaTablero $columna): array
{
    return array_map(fn (FilaFaja $f): string => $f->nombre, $columna->visibles);
}

/** @return array{0: int, 1: string, 2: string} */
function tgCifra(EmbudoEnSoles $cifra): array
{
    return [$cifra->cantidad, $cifra->total, $cifra->ponderado];
}

function tgUsd(): void
{
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-10'), '3.7500', '3.7600', OrigenTipoCambio::Manual);
}

// ——— Las cuatro que se ven (D2) ———

test('una etapa muestra primero las atascadas y luego las de mayor monto en soles; dice cuántas no se ven', function () {
    tgUsd();
    foreach (['1000.00', '2000.00', '3000.00', '4000.00', '5000.00'] as $i => $monto) {
        tgEnEstado(tgCrear($this->cuenta->id, 'Fresca '.($i + 1), $monto), 'calificada', '2026-11-05');
    }
    // 2 000 USD × 3.75 = 7 500 soles: la de mayor monto, aunque el número sea menor.
    tgEnEstado(tgCrear($this->cuenta->id, 'En dólares', '2000.00', ['moneda_id' => tgMoneda('USD')]), 'calificada', '2026-11-05');
    // Calificada tiene 14 días de máximo: 21 y 16 días las atascan; 14 justos, no.
    tgEnEstado(tgCrear($this->cuenta->id, 'Atascada chica', '100.00'), 'calificada', '2026-10-20');
    tgEnEstado(tgCrear($this->cuenta->id, 'Atascada mediana', '500.00'), 'calificada', '2026-10-25');
    tgEnEstado(tgCrear($this->cuenta->id, 'En el límite', '50.00'), 'calificada', '2026-10-27');
    tgEnEstado(tgCrear($this->cuenta->id, 'Anulada enorme', '999999.00'), 'anulada', '2026-10-01');

    $columna = $this->tablero->columna(LugarFaja::Calificada);

    // 1 000 + 2 000 + 3 000 + 4 000 + 5 000 + 7 500 + 100 + 500 + 50 = 23 150; al 25 %, 5 787,50.
    expect(tgNombres($columna))->toBe(['Atascada mediana', 'Atascada chica', 'En dólares', 'Fresca 5'])
        ->and($columna->visibles[0]->atascada)->toBeTrue()
        ->and($columna->visibles[2]->atascada)->toBeFalse()
        ->and($columna->visibles[2]->montoEnSoles)->toBe('7500.00')
        ->and($columna->cantidad)->toBe(9)
        ->and($columna->ocultas())->toBe(5)
        ->and($columna->totalEnSoles)->toBe('23150.00')
        ->and($columna->sinTipoCambio)->toBe(0)
        ->and($columna->probabilidad)->toBe(25)
        ->and($columna->ponderadoEnSoles)->toBe('5787.50')
        ->and(TableroDeGemas::VISIBLES)->toBe(4);
});

test('con menos de cuatro se ven todas, y una etapa vacía no trae ninguna', function () {
    tgCrear($this->cuenta->id, 'Chica', '800.00');
    tgCrear($this->cuenta->id, 'Grande', '9000.00');

    $prospecto = $this->tablero->columna(LugarFaja::Prospecto);
    $negociacion = $this->tablero->columna(LugarFaja::Negociacion);

    expect(tgNombres($prospecto))->toBe(['Grande', 'Chica'])
        ->and($prospecto->ocultas())->toBe(0)
        ->and([$prospecto->totalEnSoles, $prospecto->probabilidad, $prospecto->ponderadoEnSoles])->toBe(['9800.00', 10, '980.00'])
        ->and($negociacion->visibles)->toBe([])
        ->and([$negociacion->cantidad, $negociacion->totalEnSoles, $negociacion->ponderadoEnSoles])->toBe([0, '0.00', '0.00'])
        // Ordenar por monto un lugar vacío no rompe la consulta (tampoco en la tabla de la faja).
        ->and(app(OportunidadesService::class)->tablaDeLaFaja(LugarFaja::Negociacion, orden: 'monto', direccion: 'desc')->total)->toBe(0);
});

test('una etapa sin máximo de días no atasca a ninguna: manda el monto', function () {
    tgEnEstado(tgCrear($this->cuenta->id, 'Vieja chica', '100.00'), 'negociacion', '2026-01-10');
    tgEnEstado(tgCrear($this->cuenta->id, 'Nueva grande', '7000.00'), 'negociacion');

    $columna = $this->tablero->columna(LugarFaja::Negociacion);

    expect(tgNombres($columna))->toBe(['Nueva grande', 'Vieja chica'])
        ->and($columna->visibles[1]->atascada)->toBeFalse();
});

test('la probabilidad y el máximo de días salen de los parámetros, no del código', function () {
    $parametros = app(ParametrosService::class);
    $cambiar = fn (string $nombre, int $valor) => $parametros->cambiar($nombre, $valor, $parametros->ficha($nombre)['version']);
    $cambiar(EtapaAbierta::Negociacion->parametroProbabilidad(), 80);
    $cambiar(EtapaAbierta::Negociacion->parametroDiasMaximos(), 30);
    tgEnEstado(tgCrear($this->cuenta->id, 'Vieja chica', '100.00'), 'negociacion', '2026-10-01');
    tgEnEstado(tgCrear($this->cuenta->id, 'Nueva grande', '7000.00'), 'negociacion');

    $columna = $this->tablero->columna(LugarFaja::Negociacion);

    // 7 100 al 80 % = 5 680; 40 días pasan los 30 de máximo.
    expect(tgNombres($columna))->toBe(['Vieja chica', 'Nueva grande'])
        ->and([$columna->probabilidad, $columna->ponderadoEnSoles])->toBe([80, '5680.00']);
});

// ——— Ganadas y Perdidas del período ———

test('las salas muestran por defecto las cuatro más recientes del período (D15), con su cantidad y total, sin probabilidad', function () {
    foreach (['1000.00', '6000.00', '3000.00', '4000.00', '2000.00'] as $i => $monto) {
        tgEnEstado(tgCrear($this->cuenta->id, 'Ganada '.($i + 1), $monto), 'ganada', '2026-11-0'.($i + 1), '2026-11-0'.($i + 1));
    }
    tgEnEstado(tgCrear($this->cuenta->id, 'Ganada en octubre', '90000.00'), 'ganada', '2026-10-20', '2026-10-20');
    tgEnEstado(tgCrear($this->cuenta->id, 'Perdida', '2500.00'), 'perdida', '2026-11-04', '2026-11-04');
    tgEnEstado(tgCrear($this->cuenta->id, 'Anulada', '50000.00'), 'anulada', '2026-11-04', '2026-11-04');

    $columnas = $this->tablero->columnas();
    $ganadas = $columnas['ganadas'];

    expect(array_keys($columnas))->toBe(array_map(fn (LugarFaja $l): string => $l->value, LugarFaja::cases()))
        ->and(tgNombres($ganadas))->toBe(['Ganada 5', 'Ganada 4', 'Ganada 3', 'Ganada 2'])
        ->and([$ganadas->cantidad, $ganadas->ocultas(), $ganadas->totalEnSoles])->toBe([5, 1, '16000.00'])
        ->and([$ganadas->probabilidad, $ganadas->ponderadoEnSoles])->toBe([null, null])
        ->and(tgNombres($columnas['perdidas']))->toBe(['Perdida'])
        ->and($columnas['perdidas']->totalEnSoles)->toBe('2500.00');
});

// ——— La fila de cifras (D1) ———

test('las cifras: embudo abierto y ponderado, cierran este mes, atascadas y cierre vencido, sin cerradas ni anuladas', function () {
    tgCrear($this->cuenta->id, 'Prospecto', '1000.00', ['fecha_cierre_estimada' => '2026-11-25']);
    tgEnEstado(tgCrear($this->cuenta->id, 'Calificada atascada', '2000.00', ['fecha_cierre_estimada' => '2026-11-05']), 'calificada', '2026-10-01');
    tgEnEstado(tgCrear($this->cuenta->id, 'Propuesta', '4000.00', ['fecha_cierre_estimada' => '2026-12-15']), 'propuesta_enviada');
    tgEnEstado(tgCrear($this->cuenta->id, 'Negociación', '8000.00', ['fecha_cierre_estimada' => '2026-10-31']), 'negociacion');
    tgEnEstado(tgCrear($this->cuenta->id, 'Ganada', '30000.00', ['fecha_cierre_estimada' => '2026-11-01']), 'ganada', '2026-11-02', '2026-11-02');
    tgEnEstado(tgCrear($this->cuenta->id, 'Anulada', '50000.00', ['fecha_cierre_estimada' => '2026-11-01']), 'anulada', '2026-10-01');

    $cifras = $this->tablero->cifras();

    // Ponderado: 1 000 × 10 % + 2 000 × 25 % + 4 000 × 50 % + 8 000 × 75 % = 100 + 500 + 2 000 + 6 000 = 8 600.
    expect(tgCifra($cifras->embudo))->toBe([4, '15000.00', '8600.00'])
        ->and(tgCifra($cifras->esteMes))->toBe([2, '3000.00', '600.00'])
        ->and(tgCifra($cifras->atascadas))->toBe([1, '2000.00', '500.00'])
        ->and(tgCifra($cifras->vencidas))->toBe([2, '10000.00', '6500.00']);
});

// ——— La línea de tiempo (D9) ———

test('la línea de tiempo trae las abiertas por cierre estimado, con las vencidas marcadas', function () {
    $tarde = tgCrear($this->cuenta->id, 'Cierra en enero', '1000.00', ['fecha_cierre_estimada' => '2027-01-20']);
    $vencida = tgEnEstado(tgCrear($this->cuenta->id, 'Ya debió cerrar', '2000.00', ['fecha_cierre_estimada' => '2026-11-03']), 'negociacion');
    tgCrear($this->cuenta->id, 'Cierra hoy', '3000.00', ['fecha_cierre_estimada' => '2026-11-10']);
    tgEnEstado(tgCrear($this->cuenta->id, 'Ganada', '9000.00', ['fecha_cierre_estimada' => '2026-11-01']), 'ganada', '2026-11-02', '2026-11-02');
    tgEnEstado(tgCrear($this->cuenta->id, 'Perdida', '9000.00', ['fecha_cierre_estimada' => '2026-11-01']), 'perdida', '2026-11-02', '2026-11-02');
    tgEnEstado(tgCrear($this->cuenta->id, 'Anulada', '9000.00', ['fecha_cierre_estimada' => '2026-11-01']), 'anulada');

    $marcas = $this->tablero->lineaDeTiempo();
    $resumen = array_map(fn (MarcaLineaDeTiempo $m): array => [$m->oportunidad->nombre, $m->oportunidad->etapa, $m->vencida], $marcas);

    expect($resumen)->toBe([
        ['Ya debió cerrar', 'negociacion', true],
        ['Cierra hoy', 'prospecto', false],
        ['Cierra en enero', 'prospecto', false],
    ])
        ->and($marcas[0]->oportunidad->id)->toBe($vencida->id)
        ->and($marcas[0]->oportunidad->montoEnSoles)->toBe('2000.00')
        ->and($marcas[2]->oportunidad->cierreEstimado->toDateString())->toBe($tarde->fecha_cierre_estimada->toDateString());
});
