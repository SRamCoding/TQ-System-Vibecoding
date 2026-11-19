<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Lo que se lee de un presupuesto dentro del área 1 (plan 2.9): qué le falta para estar completo (D9), la lista de
 * insumos (D5, D10), los materiales sin producto (D13) y los parámetros del cálculo. Lo que lee otra área va por C8 (`PresupuestosService`).
 */
final readonly class ConsultaPresupuestos
{
    public const string PARAMETRO_DECIMALES = 'presupuestos.decimales_precio_unitario';

    public const string PARAMETRO_JORNADA = 'presupuestos.horas_jornada';

    /** A cuántos decimales va el P.U. de una partida (D5; pendiente con quienes presupuestan). De 0 a 4. */
    public static function decimalesPrecioUnitario(): int
    {
        return max(0, min(4, app(ParametrosService::class)->entero(self::PARAMETRO_DECIMALES)));
    }

    /** Las horas de una jornada para el rendimiento por día (D4). */
    public static function horasJornada(): string
    {
        $horas = app(ParametrosService::class)->decimal(self::PARAMETRO_JORNADA);

        if (bccomp($horas, '0', 2) <= 0 || bccomp($horas, '24', 2) > 0) {
            throw PresupuestoRechazado::conMotivo('El parámetro de horas de la jornada debe estar entre 0 y 24.');
        }

        return bcadd($horas, '0', 2);
    }

    /**
     * Lo que le falta para ofertarse o traerse al proyecto (D9): al menos una partida; cada una con metrado y P.U.
     * mayores que cero; con APU, cada una con su análisis. Los títulos y los códigos los garantiza el armado.
     *
     * @return list<string>
     */
    public function faltas(Presupuesto $presupuesto): array
    {
        $partidas = PartidaDePresupuesto::query()->where('presupuesto_id', $presupuesto->id)->withCount('lineasApu')->orderBy('codigo')->get();

        if ($partidas->isEmpty()) {
            return ['no tiene partidas'];
        }

        $faltas = [];

        foreach ($partidas as $partida) {
            if (bccomp($partida->metrado, '0', 3) <= 0) {
                $faltas[] = "la partida {$partida->codigo} no tiene metrado";
            }

            if ($presupuesto->usa_apu && $partida->lineas_apu_count === 0) {
                $faltas[] = "la partida {$partida->codigo} no tiene análisis de precios unitarios";
            } elseif (bccomp($partida->costo_unitario, '0', 4) <= 0) {
                $faltas[] = "la partida {$partida->codigo} no tiene precio unitario";
            }
        }

        return $faltas;
    }

    /** La lista de insumos (D5, D10). Sin APU no hay lista: vacía, con el costo directo como total. */
    public function listaDeInsumos(int $presupuestoId): ListaDeInsumos
    {
        $partidas = PartidaDePresupuesto::query()
            ->where('presupuesto_id', $presupuestoId)
            ->with(['unidadMedida', 'lineasApu.insumo.unidadMedida'])
            ->orderBy('codigo')
            ->get();

        return ListaDeInsumos::desde($partidas->map(fn (PartidaDePresupuesto $p): PartidaParaLista => new PartidaParaLista(
            $p->codigo,
            $p->descripcion,
            $p->metrado,
            $p->unidadMedida->abreviatura,
            $p->parcial,
            $p->lineasApu->map(fn (LineaApu $l): LineaParaLista => new LineaParaLista(
                $l->insumo_id,
                $l->insumo->codigo,
                $l->insumo->nombre,
                $l->insumo->tipo,
                $l->insumo->unidadMedida->abreviatura,
                $l->insumo->es_sistema,
                $l->cantidad,
                $l->parcial,
            ))->values()->all(),
        ))->values()->all());
    }

    /** Los materiales de su APU sin producto enlazado, por código (D13): avisan, no bloquean. */
    public function materialesSinProducto(int $presupuestoId): MaterialesSinProducto
    {
        $insumos = Insumo::query()
            ->where('tipo', Insumo::MATERIAL)
            ->whereNull('producto_id')
            ->whereIn('id', LineaApu::query()
                ->select('insumo_id')
                ->whereIn('presupuesto_partida_id', PartidaDePresupuesto::query()->select('id')->where('presupuesto_id', $presupuestoId)))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return new MaterialesSinProducto($insumos->map(fn (Insumo $i): array => ['insumoId' => $i->id, 'codigo' => $i->codigo, 'nombre' => $i->nombre])->values()->all());
    }

    /**
     * Títulos y partidas en orden de código (D4 de la 2.10): «01», «01.01», «02»… Un título suma los parciales de todas
     * las partidas bajo su código.
     *
     * @return list<FilaPresupuesto>
     */
    public function filas(int $presupuestoId): array
    {
        $partidas = PartidaDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->with('unidadMedida')->withCount('lineasApu')->get();
        $titulos = TituloDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->get();
        $filas = [];

        foreach ($titulos as $t) {
            $suma = $partidas->filter(fn (PartidaDePresupuesto $p): bool => str_starts_with($p->codigo, $t->codigo.'.'))
                ->reduce(fn (string $s, PartidaDePresupuesto $p): string => bcadd($s, $p->parcial, 2), '0.00');
            $filas[] = new FilaPresupuesto(true, $t->id, $t->padre_id, $t->codigo, $t->nombre, substr_count($t->codigo, '.'), null, null, null, $suma, 0, $t->version);
        }

        foreach ($partidas as $p) {
            $filas[] = new FilaPresupuesto(false, $p->id, $p->titulo_id, $p->codigo, $p->descripcion, substr_count($p->codigo, '.'), $p->unidadMedida->codigo, $p->metrado, $p->costo_unitario, $p->parcial, (int) $p->lineas_apu_count, $p->version);
        }

        usort($filas, fn (FilaPresupuesto $a, FilaPresupuesto $b): int => strcmp($a->codigo, $b->codigo));

        return $filas;
    }

    /**
     * El código libre siguiente bajo un título (para un subtítulo o una partida, que comparten numeración) o en la
     * raíz: «03», «01.04». Solo una sugerencia: el armado valida.
     */
    public function codigoSiguiente(int $presupuestoId, ?int $tituloId): string
    {
        $prefijo = $tituloId === null ? '' : TituloDePresupuesto::query()->whereKey($tituloId)->value('codigo').'.';
        $patron = '/^'.preg_quote($prefijo, '/').'(\d{2})$/';
        $usados = TituloDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->pluck('codigo')
            ->merge(PartidaDePresupuesto::query()->where('presupuesto_id', $presupuestoId)->pluck('codigo'))
            ->map(fn (string $c): int => preg_match($patron, $c, $m) === 1 ? (int) $m[1] : 0);

        return $prefijo.str_pad((string) min(((int) $usados->max()) + 1, 99), 2, '0', STR_PAD_LEFT);
    }

    /** Cuánto del costo directo va con «Precio sin detallar» (D10). */
    public function sinDetallar(int $presupuestoId): string
    {
        return $this->listaDeInsumos($presupuestoId)->sinDetallar;
    }
}
