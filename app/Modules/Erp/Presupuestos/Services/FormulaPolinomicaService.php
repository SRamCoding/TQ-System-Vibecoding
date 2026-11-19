<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t2-formula-polinomica, plan 2.14): la fórmula polinómica de reajuste. Se define con el presupuesto
 * (monomios con su índice unificado y su coeficiente, que suman 1.000) y se aplica en cada valorización: K = Σ coef ×
 * (índice del mes ÷ índice base). «Es cálculo, no criterio» (v4 flujo 10).
 */
final readonly class FormulaPolinomicaService
{
    /** Hasta ocho monomios, como manda la norma de reajuste de obras. */
    public const int MAXIMO_MONOMIOS = 8;

    /** @return list<object{id: int, descripcion: string, indice_codigo: string, coeficiente: string, orden: int}> */
    public function monomios(int $presupuestoId): array
    {
        return DB::table('presupuesto_monomios')->where('presupuesto_id', $presupuestoId)->orderBy('orden')->get()->all();
    }

    public function suma(int $presupuestoId): string
    {
        return bcadd((string) (DB::table('presupuesto_monomios')->where('presupuesto_id', $presupuestoId)->sum('coeficiente') ?: '0'), '0', 3);
    }

    public function agregar(int $presupuestoId, string $descripcion, string $indice, string $coeficiente): void
    {
        $this->exigirEditable($presupuestoId);

        if (count($this->monomios($presupuestoId)) >= self::MAXIMO_MONOMIOS) {
            throw new RuntimeException('La fórmula admite hasta '.self::MAXIMO_MONOMIOS.' monomios.');
        }

        if (bccomp(bcadd($this->suma($presupuestoId), $coeficiente, 3), '1', 3) > 0) {
            throw new RuntimeException('Con ese coeficiente la suma pasa de 1.000.');
        }

        if (! DB::table('indices_unificados')->where('codigo', $indice)->exists()) {
            throw new RuntimeException("No hay índices cargados con el código {$indice}.");
        }

        DB::table('presupuesto_monomios')->insert([
            'presupuesto_id' => $presupuestoId, 'descripcion' => trim($descripcion), 'indice_codigo' => $indice, 'coeficiente' => $coeficiente,
            'orden' => (int) DB::table('presupuesto_monomios')->where('presupuesto_id', $presupuestoId)->max('orden') + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function quitar(int $monomioId): void
    {
        $presupuestoId = (int) DB::table('presupuesto_monomios')->where('id', $monomioId)->value('presupuesto_id');
        $this->exigirEditable($presupuestoId);
        DB::table('presupuesto_monomios')->where('id', $monomioId)->delete();
    }

    /**
     * El factor de reajuste entre dos meses, con el detalle por monomio.
     *
     * @return array{k: string, filas: list<array{descripcion: string, coeficiente: string, base: string, mes: string, termino: string}>}
     */
    public function factor(int $presupuestoId, string $periodoBase, string $periodoMes): array
    {
        $monomios = $this->monomios($presupuestoId);

        if ($monomios === [] || bccomp($this->suma($presupuestoId), '1', 3) !== 0) {
            throw new RuntimeException('La fórmula tiene que estar completa: sus coeficientes suman 1.000.');
        }

        $k = '0';
        $filas = [];

        foreach ($monomios as $m) {
            $base = DB::table('indices_unificados')->where('codigo', $m->indice_codigo)->where('periodo', $periodoBase)->value('valor');
            $mes = DB::table('indices_unificados')->where('codigo', $m->indice_codigo)->where('periodo', $periodoMes)->value('valor');

            if ($base === null || $mes === null) {
                throw new RuntimeException("Falta el índice {$m->indice_codigo} de ".($base === null ? $periodoBase : $periodoMes).'.');
            }

            $termino = bcdiv(bcmul((string) $m->coeficiente, (string) $mes, 8), (string) $base, 6);
            $k = bcadd($k, $termino, 6);
            $filas[] = ['descripcion' => $m->descripcion, 'coeficiente' => (string) $m->coeficiente, 'base' => (string) $base, 'mes' => (string) $mes, 'termino' => $termino];
        }

        return ['k' => bcadd($k, '0.00005', 4), 'filas' => $filas];
    }

    /** El reajuste de una valorización: monto × (K − 1), a dos decimales. */
    public function reajuste(string $monto, string $k): string
    {
        return bcadd(bcmul($monto, bcsub($k, '1', 4), 6), bccomp($k, '1', 4) >= 0 ? '0.005' : '-0.005', 2);
    }

    /** @return array<string, string> los periodos con índices cargados, del más nuevo al más viejo */
    public function periodos(): array
    {
        return DB::table('indices_unificados')->select('periodo')->distinct()->orderByDesc('periodo')->pluck('periodo', 'periodo')->all();
    }

    /** @return array<string, string> código => «47 · Mano de obra» */
    public function indices(): array
    {
        return DB::table('indices_unificados')->select('codigo', 'nombre')->distinct()->orderBy('codigo')->get()
            ->mapWithKeys(fn (object $i): array => [$i->codigo => "{$i->codigo} · {$i->nombre}"])->all();
    }

    private function exigirEditable(int $presupuestoId): void
    {
        $p = Presupuesto::query()->with('estado')->findOrFail($presupuestoId);

        if (! $p->estado->es_editable) {
            throw new RuntimeException("La fórmula de {$p->numero} v{$p->numero_version} ya no se cambia: está ".mb_strtolower($p->estado->nombre).'.');
        }
    }
}
