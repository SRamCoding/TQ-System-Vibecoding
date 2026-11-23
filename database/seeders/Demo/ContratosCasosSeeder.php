<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 6.11 (ficha t2-contratos): que `/contratos` muestre todos sus casos con la cartera de demo, ajustando
 * contratos que ya existen. Idempotente: cada ajuste se hace solo si el caso todavía no se ve.
 *
 * - Por completar: la historia clínica electrónica (su proyecto aún está en preparación) vuelve a borrador.
 * - Vencido con penalidad en riesgo: el mantenimiento de CCTV de la clínica venció hace 9 días.
 * - Al día: el sistema hospitalario recibe dos valorizaciones aprobadas (45 % y 40 % de cada partida).
 * - Atrasado y carta fianza a 30 días: la red de datos del gobierno regional (avance 50 % contra 72 % del plazo); si
 *   su carta ya no vence en 30 días, se acerca a 20.
 * - Culminados: los de 2024 y 2025 de la cartera.
 */
final class ContratosCasosSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $s = app(ContratosService::class);

        $borrador = $this->contrato('Historia clínica electrónica%');

        if ($borrador !== null && $borrador->estado === 'vigente' && ! DB::table('valorizaciones')->where('contrato_id', $borrador->id)->exists()) {
            $borrador->hitos()->delete();
            $borrador->update(['estado' => 'borrador', 'numero_entidad' => null, 'fecha_firma' => null]);
        }

        $vencido = $this->contrato('Mantenimiento de CCTV%');

        if ($vencido !== null && $vencido->estado === 'vigente' && $s->diasRestantes($vencido) >= 0) {
            $fin = $hoy->subDays(9);
            $plazo = (int) $vencido->fecha_inicio->diffInDays($fin) - (int) $vencido->adendas()->sum('dias_adicionales');
            $vencido->update(['plazo_dias' => $plazo]);
            $vencido->hitos()->where('nombre', 'Conformidad')->update(['fecha_prevista' => $fin->toDateString(), 'fecha_real' => null]);
            $vencido->hitos()->where('nombre', '!=', 'Conformidad')->where('fecha_prevista', '>', $fin->toDateString())->update(['fecha_prevista' => $fin->subDays(5)->toDateString()]);
        }

        $alDia = $this->contrato('Sistema de información hospitalaria%');

        if ($alDia !== null && $alDia->estado === 'vigente' && ! DB::table('valorizaciones')->where('contrato_id', $alDia->id)->exists()) {
            $v = app(ValorizacionesService::class);
            $partidas = array_column($v->partidasContractuales($alDia), 'codigo');

            foreach (['2026-09' => '45', '2026-10' => '40'] as $periodo => $pct) {
                $val = $v->armar($alDia->id, $periodo, array_fill_keys($partidas, $pct));
                $v->aprobar($val->id);
            }
        }

        $atrasado = $this->contrato('Red de datos%');
        $g = $atrasado === null ? null : $s->fianzaVigente($atrasado);

        if ($g !== null && ((int) $hoy->diffInDays($g->vencimiento, false) > 30 || $g->vencimiento->lt($hoy))) {
            $g->update(['vencimiento' => $hoy->addDays(20)->toDateString()]);
        }
    }

    private function contrato(string $objeto): ?Contrato
    {
        return Contrato::query()->where('objeto', 'like', $objeto)->orderBy('id')->first();
    }
}
