<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Tiempo\Calendario;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Las corridas de las tareas del programador (plan 1.12, D1). Un módulo envuelve su tarea en `registrar()` y la página
 * de salud lee la última con `ultima()`. Si la tarea falla, la corrida queda fallida con la clase del error (nunca su
 * mensaje: regla 10) y el error sigue su camino.
 */
final class SaludService
{
    /** Ver la página de salud y Pulse (plan 1.12, D2 y D6). Nace con el administrador. */
    public const string PERMISO_VER = 'salud.ver';

    /** Una corrida «en curso» por más de esto murió a medias (proceso terminado, servidor reiniciado). */
    public const int HORAS_EN_CURSO = 6;

    public function __construct(private readonly ParametrosService $parametros) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $tarea  devuelve lo que se anota en `datos` si es un arreglo: solo cantidades y tamaños
     * @return T
     */
    public function registrar(string $nombre, Closure $tarea): mixed
    {
        $corrida = SaludEjecucion::query()->create(['tarea' => $nombre, 'iniciada_at' => Calendario::ahora()]);

        try {
            $resultado = $tarea();
        } catch (Throwable $error) {
            $corrida->update(['resultado' => ResultadoEjecucion::Fallida, 'terminada_at' => Calendario::ahora(), 'datos' => ['error' => $error::class]]);

            throw $error;
        }

        $corrida->update([
            'resultado' => ResultadoEjecucion::Correcta,
            'terminada_at' => Calendario::ahora(),
            'datos' => is_array($resultado) ? $resultado : [],
        ]);

        return $resultado;
    }

    /** La última corrida terminada o en curso de una tarea, o null si nunca corrió. */
    public function ultima(string $nombre, ?ResultadoEjecucion $resultado = null): ?SaludEjecucion
    {
        return SaludEjecucion::query()
            ->where('tarea', $nombre)
            ->when($resultado !== null, fn ($query) => $query->where('resultado', $resultado))
            ->orderByDesc('iniciada_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Revisión de arquitectura de la 1.12: una corrida que muere a medias quedaba «en curso» para siempre, la página la
     * tomaba como la última y la mostraba en verde, y la limpieza nunca la archivaba. El latido las cierra como fallidas.
     */
    public function cerrarColgadas(): int
    {
        return SaludEjecucion::query()
            ->where('resultado', ResultadoEjecucion::EnCurso)
            ->where('iniciada_at', '<', Calendario::ahora()->subHours(self::HORAS_EN_CURSO))
            ->update(['resultado' => ResultadoEjecucion::Fallida, 'terminada_at' => Calendario::ahora(), 'datos' => json_encode(['error' => 'interrumpida'])]);
    }

    /**
     * Para que el registro no crezca sin fin (pedido del desarrollador): las corridas de la tarea frecuente (el latido)
     * se guardan `$diasFrecuente` días; las demás (respaldos, huérfanos), `salud.dias_historial`, porque son pocas y
     * sirven para ver tendencias. Las que siguen en curso no se tocan. Las que salen pasan antes al archivo histórico.
     * El histórico llega como parámetro (AGENTS.md: una dependencia de un solo método no va en el constructor).
     */
    public function olvidarViejas(string $tareaFrecuente, int $diasFrecuente, ArchivoHistoricoService $historico): int
    {
        $ahora = Calendario::ahora();

        // Plan 1.4y, D6 («que se almacene como histórico todo»): salen de la base, pero quedan en el archivo histórico.
        return $historico->archivar('salud_ejecuciones', DB::table('salud_ejecuciones')
            ->where('tarea', $tareaFrecuente)
            ->where('iniciada_at', '<', $ahora->subDays($diasFrecuente)))
            + $historico->archivar('salud_ejecuciones', DB::table('salud_ejecuciones')
                ->where('tarea', '<>', $tareaFrecuente)
                ->where('resultado', '<>', ResultadoEjecucion::EnCurso->value)
                ->where('iniciada_at', '<', $ahora->subDays($this->parametros->entero('salud.dias_historial'))));
    }
}
