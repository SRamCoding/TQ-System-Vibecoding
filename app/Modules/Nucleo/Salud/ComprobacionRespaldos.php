<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Respaldos\RespaldosService;
use App\Modules\Nucleo\Respaldos\ValidadorPruebaRestauracion;
use Illuminate\Support\Number;

/**
 * ¿Hay respaldo, reciente y fuera del servidor? (plan 1.12, D3). Falla si el último falló o está vencido; con destino
 * local, nunca en verde: «Respaldo solo local: no protege ante pérdida del servidor». Muestra también la última
 * prueba de restauración y quién la registró.
 */
final readonly class ComprobacionRespaldos implements ComprobacionSalud
{
    public const string SOLO_LOCAL = 'Respaldo solo local: no protege ante pérdida del servidor.';

    public function __construct(private RespaldosService $respaldos) {}

    public function clave(): string
    {
        return 'nucleo.respaldos';
    }

    public function titulo(): string
    {
        return 'Respaldos';
    }

    public function icono(): string
    {
        return 'heroicon-o-archive-box';
    }

    public function revisar(): ResultadoComprobacion
    {
        $ultimo = $this->respaldos->ultimoCorrecto();
        $corrida = $this->respaldos->ultimaCorrida();
        $detalle = [
            'Frecuencia' => $this->respaldos->frecuencia()->value,
            'Destino' => $this->respaldos->disco().($this->respaldos->destinoEsLocal() ? ' (en este servidor)' : ''),
            'Último respaldo' => $ultimo === null ? 'ninguno' : $ultimo->iniciada_at->timezone('America/Lima')->format('d/m/Y H:i')
                .' · '.Number::fileSize((int) ($ultimo->datos['bytes'] ?? 0), precision: 1),
            'Prueba de restauración' => $this->pruebaRestauracion(),
        ];

        if ($corrida?->resultado === ResultadoEjecucion::Fallida) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'El último respaldo falló el '.$corrida->iniciada_at->timezone('America/Lima')->format('d/m/Y H:i').'.', $detalle);
        }

        if ($ultimo === null) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'Todavía no hay ningún respaldo.', $detalle);
        }

        if ($this->respaldos->estaVencido()) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'El último respaldo es más viejo que su frecuencia.', $detalle);
        }

        if ($this->respaldos->destinoEsLocal()) {
            return new ResultadoComprobacion(EstadoSalud::Aviso, self::SOLO_LOCAL, $detalle);
        }

        return new ResultadoComprobacion(EstadoSalud::Bien, 'Respaldo reciente fuera del servidor.', $detalle);
    }

    /** La fecha del parámetro y quién la registró, según la auditoría del parámetro (pedido del desarrollador). */
    private function pruebaRestauracion(): string
    {
        $fecha = $this->respaldos->ultimaPruebaRestauracion();

        if ($fecha === null) {
            return ValidadorPruebaRestauracion::SIN_PROBAR;
        }

        $parametro = Parametro::query()->where('grupo', 'respaldos')->where('clave', 'ultima_prueba_restauracion')->first();
        $quien = $parametro?->updated_by === null ? null : User::query()->find($parametro->updated_by);

        return $fecha->format('d/m/Y').($quien === null ? '' : ' · registrada por '.$quien->name);
    }
}
