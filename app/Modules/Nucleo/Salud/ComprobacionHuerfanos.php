<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Services\HuerfanosAdjuntosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

/**
 * La última revisión de huérfanos de adjuntos (plan 1.12, D4): cuántos pasaron a cuarentena y cuántos se borraron,
 * cuánto ocupa la cuarentena y cuándo corrió. Detenida por el tope o más de dos semanas sin correr: falla.
 */
final readonly class ComprobacionHuerfanos implements ComprobacionSalud
{
    public function __construct(
        private SaludService $salud,
        private ParametrosService $parametros,
    ) {}

    public function clave(): string
    {
        return 'nucleo.huerfanos-adjuntos';
    }

    public function titulo(): string
    {
        return 'Archivos huérfanos';
    }

    public function icono(): string
    {
        return 'heroicon-o-document-minus';
    }

    public function revisar(): ResultadoComprobacion
    {
        if (! $this->parametros->booleano('adjuntos.revision_huerfanos_activa')) {
            return $this->pausada();
        }

        $ultima = $this->salud->ultima(HuerfanosAdjuntosService::TAREA);

        if ($ultima === null) {
            return new ResultadoComprobacion(EstadoSalud::SinDatos, 'Todavía no corrió la revisión semanal.');
        }

        $datos = $ultima->datos;
        $detalle = [
            'Última revisión' => $ultima->iniciada_at->timezone('America/Lima')->format('d/m/Y H:i'),
            'A cuarentena' => (string) ($datos['movidos'] ?? 0),
            'Borrados de la cuarentena' => (string) ($datos['borrados'] ?? 0),
            'Ocupa la cuarentena' => Number::fileSize((int) ($datos['bytes_cuarentena'] ?? 0), precision: 1),
        ];

        if ($ultima->resultado === ResultadoEjecucion::Fallida) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'La última revisión falló.', $detalle);
        }

        // En curso todavía no tiene cantidades: no es «sin pendientes». Si muere a medias, el latido la cierra como fallida.
        if ($ultima->resultado === ResultadoEjecucion::EnCurso) {
            return new ResultadoComprobacion(EstadoSalud::Aviso, 'La revisión está corriendo ahora.', ['Empezó' => $ultima->iniciada_at->timezone('America/Lima')->format('d/m/Y H:i')]);
        }

        if ($datos['detenida'] ?? false) {
            return new ResultadoComprobacion(EstadoSalud::Falla, "Detenida: {$datos['encontrados']} huérfanos pasan el tope. Revisa antes de moverlos.", $detalle);
        }

        if ($ultima->iniciada_at->lt(Calendario::ahora()->subDays(15))) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'La revisión semanal no corre desde '.$ultima->iniciada_at->diffForHumans().'.', $detalle);
        }

        return new ResultadoComprobacion(EstadoSalud::Bien, 'Sin pendientes.', $detalle);
    }

    /**
     * Pausada se ve en amarillo; si la pausa pasa de `adjuntos.dias_pausa_huerfanos`, en rojo: una pausa olvidada tras
     * una restauración no queda así para siempre (pedido del desarrollador). Desde cuándo: el último cambio del parámetro.
     */
    private function pausada(): ResultadoComprobacion
    {
        $desde = Parametro::query()->where('grupo', 'adjuntos')->where('clave', 'revision_huerfanos_activa')->value('updated_at');
        $desde = $desde === null ? null : CarbonImmutable::parse($desde);
        $detalle = $desde === null ? [] : ['Pausada desde' => $desde->timezone('America/Lima')->format('d/m/Y H:i')];

        if ($desde !== null && $desde->lt(Calendario::ahora()->subDays($this->parametros->entero('adjuntos.dias_pausa_huerfanos')))) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'La revisión de huérfanos lleva pausada desde '.$desde->diffForHumans().': enciéndela si ya terminó la restauración.', $detalle);
        }

        return new ResultadoComprobacion(EstadoSalud::Aviso, 'La revisión de huérfanos está pausada.', $detalle);
    }
}
