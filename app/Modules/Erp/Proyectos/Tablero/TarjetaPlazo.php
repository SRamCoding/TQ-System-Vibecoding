<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Tablero;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\LineaResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;

/**
 * «¿Vamos a tiempo?» con lo que hoy hay (plan 2.61, D9): las fechas y cuánto del plazo pasó. El avance físico y su
 * comparación con el plazo los suma el módulo de fases con su propia tarjeta.
 */
final class TarjetaPlazo implements TarjetaResumen
{
    /** Desde cuántos días antes del fin previsto se avisa en ámbar. */
    public const int DIAS_DE_AVISO = 15;

    public function clave(): string
    {
        return 'plazo';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Plazo;
    }

    public function orden(): int
    {
        return 10;
    }

    public function exigeCostos(): bool
    {
        return false;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        $proyecto = Proyecto::query()->with('estado')->find($proyectoId);

        if ($proyecto === null) {
            return null;
        }

        $plazo = self::plazo($proyecto);
        $lineas = [
            new LineaResumen('Inicio', $proyecto->fecha_inicio->format('d/m/Y')),
            new LineaResumen('Fin previsto', $proyecto->fecha_fin_prevista->format('d/m/Y')),
        ];

        if (in_array($proyecto->estado->codigo, DefinicionProyecto::ACEPTAN_COSTOS, true)) {
            $lineas[] = new LineaResumen('Plazo transcurrido', $plazo['porcentaje'].' %');
            $lineas[] = new LineaResumen($plazo['dias'] < 0 ? 'Venció hace' : 'Faltan', abs($plazo['dias']).' '.(abs($plazo['dias']) === 1 ? 'día' : 'días'), $plazo['tono']);
        }

        return new ContenidoTarjeta('Plazo', $lineas);
    }

    /**
     * Cuánto del plazo pasó (0 a 100), cuántos días faltan (negativo si venció) y su tono: ámbar en los últimos
     * DIAS_DE_AVISO días, rojo vencido. Lo usa también la portada.
     *
     * @return array{porcentaje: int, dias: int, tono: ?string}
     */
    public static function plazo(Proyecto $proyecto): array
    {
        $hoy = Calendario::ahora()->startOfDay();
        $total = max(1, (int) $proyecto->fecha_inicio->diffInDays($proyecto->fecha_fin_prevista));
        $pasados = (int) $proyecto->fecha_inicio->diffInDays($hoy, false);
        $dias = (int) $hoy->diffInDays($proyecto->fecha_fin_prevista, false);

        return [
            'porcentaje' => (int) round(min(100, max(0, $pasados / $total * 100))),
            'dias' => $dias,
            'tono' => match (true) {
                $dias < 0 => 'danger',
                $dias <= self::DIAS_DE_AVISO => 'warning',
                default => null,
            },
        ];
    }
}
