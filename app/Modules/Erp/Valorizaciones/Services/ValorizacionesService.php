<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Services;

use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Presupuestos\Services\FormulaPolinomicaService;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Valorizaciones\Models\Subcontrato;
use App\Modules\Erp\Valorizaciones\Models\SubcontratoValorizacion;
use App\Modules\Erp\Valorizaciones\Models\Valorizacion;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * MVP rápido (ficha t4-valorizaciones, flujo 10): la valorización se arma desde el avance por partida: metrado ejecutado
 * por precio contractual, más el reajuste con los índices del mes, menos la amortización de los adelantos, menos la
 * retención si la hay, menos las penalidades del periodo. El subcontrato es lo mismo en la dirección contraria.
 */
final readonly class ValorizacionesService
{
    public const string PERMISO_VER = 'erp.valorizaciones.ver';

    public const string PERMISO_GESTIONAR = 'erp.valorizaciones.gestionar';

    /** El precio contractual (sin IGV) de cada partida del presupuesto vigente: su parcial × (venta ÷ costo directo). */
    public function partidasContractuales(Contrato $c): array
    {
        $pre = DB::table('presupuestos')->where('proyecto_id', $c->proyecto_id)->where('es_vigente', true)->first();

        if ($pre === null || bccomp((string) $pre->costo_directo, '0', 2) === 0) {
            return [];
        }

        $venta = bcdiv(app(ContratosService::class)->montoVigente($c), '1.18', 6);
        $factor = bcdiv($venta, (string) $pre->costo_directo, 8);

        return DB::table('presupuesto_partidas')->where('presupuesto_id', $pre->id)->orderBy('codigo')->get(['codigo', 'descripcion', 'parcial'])
            ->map(fn (object $p): array => ['codigo' => $p->codigo, 'descripcion' => $p->descripcion, 'total' => bcadd(bcmul((string) $p->parcial, $factor, 4), '0.005', 2)])->all();
    }

    /** Lo ya valorizado de cada partida (porcentaje acumulado de las valorizaciones aprobadas). */
    public function acumulado(int $contratoId): array
    {
        return DB::table('valorizacion_lineas as l')->join('valorizaciones as v', 'v.id', '=', 'l.valorizacion_id')->where('v.contrato_id', $contratoId)->where('v.estado', 'aprobada')
            ->groupBy('l.codigo')->selectRaw('l.codigo, SUM(l.porcentaje) AS pct')->pluck('pct', 'codigo')->map(fn ($v) => (string) $v)->all();
    }

    public function registrarAdelanto(int $contratoId, string $tipo, string $monto, string $porcentaje, string $fecha): void
    {
        DB::table('contrato_adelantos')->insert(['contrato_id' => $contratoId, 'tipo' => $tipo, 'monto' => $monto, 'porcentaje' => $porcentaje, 'fecha' => $fecha, 'created_at' => now()]);
    }

    /** Cuánto queda por amortizar de los adelantos de un tipo. */
    public function porAmortizar(int $contratoId, string $tipo): string
    {
        $dado = (string) (DB::table('contrato_adelantos')->where('contrato_id', $contratoId)->where('tipo', $tipo)->sum('monto') ?: '0');
        $col = $tipo === 'directo' ? 'amortizacion_directo' : 'amortizacion_materiales';
        $amortizado = (string) (DB::table('valorizaciones')->where('contrato_id', $contratoId)->where('estado', 'aprobada')->sum($col) ?: '0');

        return bcsub($dado, $amortizado, 2);
    }

    /**
     * Arma la valorización del periodo con el avance de cada partida (porcentaje del periodo).
     *
     * @param  array<string, string>  $avances  código de partida => porcentaje del periodo
     */
    public function armar(int $contratoId, string $periodo, array $avances, string $amortizacionMateriales = '0', string $retencionPct = '0'): Valorizacion
    {
        return DB::transaction(function () use ($contratoId, $periodo, $avances, $amortizacionMateriales, $retencionPct): Valorizacion {
            $c = Contrato::query()->lockForUpdate()->findOrFail($contratoId);

            if (DB::table('valorizaciones')->where('contrato_id', $c->id)->where('estado', 'borrador')->exists()) {
                throw new RuntimeException('Ya hay una valorización en borrador: apruébala antes de armar otra.');
            }

            $acumulado = $this->acumulado($c->id);
            $lineas = [];
            $avance = '0';

            foreach ($this->partidasContractuales($c) as $p) {
                $pct = (string) ($avances[$p['codigo']] ?? '0');

                if (bccomp($pct, '0', 2) <= 0) {
                    continue;
                }

                if (bccomp(bcadd($acumulado[$p['codigo']] ?? '0', $pct, 2), '100', 2) > 0) {
                    throw new RuntimeException("La partida {$p['codigo']} pasaría el 100 % valorizado.");
                }

                $monto = bcadd(bcdiv(bcmul($p['total'], $pct, 6), '100', 6), '0.005', 2);
                $lineas[] = [...$p, 'porcentaje' => $pct, 'monto' => $monto];
                $avance = bcadd($avance, $monto, 2);
            }

            $pre = DB::table('presupuestos')->where('proyecto_id', $c->proyecto_id)->where('es_vigente', true)->value('id');
            $base = $c->fecha_firma?->subMonth()->format('Y-m') ?? $c->fecha_inicio->subMonth()->format('Y-m');

            try {
                $factor = app(FormulaPolinomicaService::class)->factor((int) $pre, $base, $periodo)['k'];
            } catch (Throwable) {
                $factor = '1.0000';
            }

            $reajuste = app(FormulaPolinomicaService::class)->reajuste($avance, $factor);
            $pctDirecto = (string) (DB::table('contrato_adelantos')->where('contrato_id', $c->id)->where('tipo', 'directo')->value('porcentaje') ?? '0');
            $directo = bcadd(bcdiv(bcmul($avance, $pctDirecto, 6), '100', 6), '0.005', 2);
            $directo = bccomp($directo, $this->porAmortizar($c->id, 'directo'), 2) > 0 ? $this->porAmortizar($c->id, 'directo') : $directo;
            $materiales = bccomp($amortizacionMateriales, $this->porAmortizar($c->id, 'materiales'), 2) > 0 ? $this->porAmortizar($c->id, 'materiales') : $amortizacionMateriales;
            $retencion = bcadd(bcdiv(bcmul($avance, $retencionPct, 6), '100', 6), '0.005', 2);
            [$y, $m] = explode('-', $periodo);
            $penalidad = (string) (DB::table('contrato_penalidades')->where('contrato_id', $c->id)->whereYear('fecha', (int) $y)->whereMonth('fecha', (int) $m)->sum('monto') ?: '0');
            $neto = bcsub(bcsub(bcsub(bcsub(bcadd($avance, $reajuste, 2), $directo, 2), $materiales, 2), $retencion, 2), $penalidad, 2);

            $v = Valorizacion::query()->create([
                'contrato_id' => $c->id, 'numero' => (int) DB::table('valorizaciones')->where('contrato_id', $c->id)->max('numero') + 1, 'periodo' => $periodo, 'periodo_base' => $base,
                'avance' => $avance, 'factor' => $factor, 'reajuste' => $reajuste, 'amortizacion_directo' => $directo, 'amortizacion_materiales' => $materiales,
                'retencion' => $retencion, 'penalidad' => bcadd($penalidad, '0', 2), 'neto' => $neto, 'estado' => 'borrador',
            ]);

            foreach ($lineas as $l) {
                $v->lineas()->create(['codigo' => $l['codigo'], 'descripcion' => mb_substr($l['descripcion'], 0, 250), 'total' => $l['total'], 'porcentaje' => $l['porcentaje'], 'monto' => $l['monto']]);
            }

            return $v;
        });
    }

    /** Aprueba: queda lista para su comprobante por el neto (flujo 4, tramo 5). */
    public function aprobar(int $valorizacionId): void
    {
        Valorizacion::query()->whereKey($valorizacionId)->where('estado', 'borrador')->update(['estado' => 'aprobada', 'aprobada_at' => Calendario::ahora()]);
    }

    /** @param  array<int, string>  $alcance  partida => monto */
    public function crearSubcontrato(int $proyectoId, string $subcontratista, string $objeto, string $adelantoPct, string $retencionPct, array $alcance): Subcontrato
    {
        return DB::transaction(function () use ($proyectoId, $subcontratista, $objeto, $adelantoPct, $retencionPct, $alcance): Subcontrato {
            $total = array_reduce($alcance, fn (string $s, string $m): string => bcadd($s, $m, 2), '0');
            $sc = Subcontrato::query()->create([
                'codigo' => app(NumeracionService::class)->siguiente('subcontrato', new FormatoNumeracion('SC'), Calendario::ahora()), 'proyecto_id' => $proyectoId,
                'subcontratista' => $subcontratista, 'objeto' => $objeto, 'monto' => $total, 'adelanto_pct' => $adelantoPct, 'retencion_pct' => $retencionPct,
            ]);
            $imputaciones = app(ImputacionService::class);

            foreach ($alcance as $partidaId => $monto) {
                $a = $sc->alcance()->create(['partida_control_id' => $partidaId, 'monto' => $monto]);
                $imputaciones->destinar($a, app(PartidasService::class)->destino((int) $partidaId), Calendario::ahora(), 'PEN');
                $imputaciones->mover($a, MomentoImputacion::Comprometido, $monto);
            }

            return $sc;
        });
    }

    /** La valorización del subcontratista por su avance del periodo: amortiza el adelanto y retiene la garantía. */
    public function valorizarSubcontrato(int $subcontratoId, string $periodo, string $avancePct): SubcontratoValorizacion
    {
        $sc = Subcontrato::query()->findOrFail($subcontratoId);
        $acumulado = (string) ($sc->valorizaciones()->sum('avance_pct') ?: '0');

        if (bccomp(bcadd($acumulado, $avancePct, 2), '100', 2) > 0) {
            throw new RuntimeException('El avance pasaría el 100 %.');
        }

        $monto = bcadd(bcdiv(bcmul((string) $sc->monto, $avancePct, 6), '100', 6), '0.005', 2);
        $amort = bcadd(bcdiv(bcmul($monto, (string) $sc->adelanto_pct, 6), '100', 6), '0.005', 2);
        $ret = bcadd(bcdiv(bcmul($monto, (string) $sc->retencion_pct, 6), '100', 6), '0.005', 2);

        return $sc->valorizaciones()->create(['numero' => $sc->valorizaciones()->count() + 1, 'periodo' => $periodo, 'avance_pct' => $avancePct, 'monto' => $monto,
            'amortizacion' => $amort, 'retencion' => $ret, 'neto' => bcsub(bcsub($monto, $amort, 2), $ret, 2), 'estado' => 'por_aprobar']);
    }

    /** Aprobada, pasa a ejecutado en las partidas del alcance (en proporción) y a cuentas por pagar por el neto. */
    public function aprobarSubcontrato(int $valorizacionId): void
    {
        DB::transaction(function () use ($valorizacionId): void {
            $v = SubcontratoValorizacion::query()->lockForUpdate()->findOrFail($valorizacionId);

            if ($v->estado !== 'por_aprobar') {
                throw new RuntimeException('Esa valorización ya se aprobó.');
            }

            $sc = Subcontrato::query()->with('alcance')->findOrFail($v->subcontrato_id);

            foreach ($sc->alcance as $a) {
                app(ImputacionService::class)->mover($a, MomentoImputacion::Ejecutado, bcadd(bcdiv(bcmul((string) $a->monto, (string) $v->avance_pct, 6), '100', 6), '0.005', 2));
            }

            $v->update(['estado' => 'aprobada']);
        });
    }

    public function retencionAcumulada(int $subcontratoId): string
    {
        return bcadd((string) (SubcontratoValorizacion::query()->where('subcontrato_id', $subcontratoId)->whereIn('estado', ['aprobada', 'pagada'])->sum('retencion') ?: '0'), '0', 2);
    }
}
