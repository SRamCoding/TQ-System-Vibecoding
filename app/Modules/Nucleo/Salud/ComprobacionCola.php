<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * ¿Avanza la cola de trabajos? (plan 1.12; `monitoreo.md`: jobs fallidos y en cola). Por cada cola (la de siempre y la
 * de la IA, 1.11c): cuántos esperan y desde cuándo el más viejo; y los que fallaron en la última semana. Un trabajo que
 * espera más de 15 minutos dice que su worker no corre. Solo cuenta: nunca muestra el contenido de un trabajo (regla 10).
 */
final readonly class ComprobacionCola implements ComprobacionSalud
{
    public const int MINUTOS_ESPERA = 15;

    public function clave(): string
    {
        return 'nucleo.cola';
    }

    public function titulo(): string
    {
        return 'Cola de trabajos';
    }

    public function icono(): string
    {
        return 'heroicon-o-arrow-path-rounded-square';
    }

    public function revisar(): ResultadoComprobacion
    {
        // Lee `jobs`: con otra cola (Redis, SQS) esa tabla queda vacía y saldría en verde sin razón.
        if (config('queue.default') !== 'database') {
            return new ResultadoComprobacion(EstadoSalud::SinDatos, 'La cola no es de base de datos («'.config('queue.default').'»): esta página no la puede revisar.');
        }

        $ahora = Calendario::ahora();
        $esperando = DB::table('jobs')->whereNull('reserved_at')
            ->selectRaw('queue, count(*) as cantidad, min(available_at) as mas_viejo')
            ->groupBy('queue')->orderBy('queue')->get();
        $fallidosSemana = DB::table('failed_jobs')->where('failed_at', '>=', $ahora->subDays(7))->count();
        $fallidosDia = DB::table('failed_jobs')->where('failed_at', '>=', $ahora->subDay())->count();

        $detalle = [];
        $atascada = false;

        foreach ($esperando as $cola) {
            $minutos = (int) floor(($ahora->getTimestamp() - (int) $cola->mas_viejo) / 60);
            $atascada = $atascada || $minutos > self::MINUTOS_ESPERA;
            $desde = CarbonImmutable::createFromTimestamp((int) $cola->mas_viejo, 'America/Lima');
            $detalle['En espera ('.$cola->queue.')'] = $cola->cantidad.($minutos > 0 ? ' · el más viejo, '.$desde->diffForHumans($ahora, CarbonInterface::DIFF_RELATIVE_TO_NOW) : '');
        }

        $detalle['Fallidos en 7 días'] = (string) $fallidosSemana;

        return match (true) {
            $atascada => new ResultadoComprobacion(EstadoSalud::Falla, 'Hay trabajos esperando más de '.self::MINUTOS_ESPERA.' minutos: revisa que los workers corran.', $detalle),
            $fallidosDia > 0 => new ResultadoComprobacion(EstadoSalud::Falla, "Fallaron {$fallidosDia} trabajos en las últimas 24 horas.", $detalle),
            $fallidosSemana > 0 => new ResultadoComprobacion(EstadoSalud::Aviso, "Fallaron {$fallidosSemana} trabajos en la última semana.", $detalle),
            default => new ResultadoComprobacion(EstadoSalud::Bien, 'La cola avanza y no hay trabajos fallidos.', $detalle),
        };
    }
}
