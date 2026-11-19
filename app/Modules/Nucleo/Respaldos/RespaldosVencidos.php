<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Alertas\FuenteAlertas;
use App\Modules\Nucleo\Services\AlertasService;
use Carbon\CarbonImmutable;

/**
 * La parte de respaldos de la revisión diaria (plan 1.12, D3): si el último respaldo correcto es más viejo que la
 * frecuencia más un día, una alerta por día hasta que vuelva a haber uno.
 */
final readonly class RespaldosVencidos implements FuenteAlertas
{
    public function __construct(
        private RespaldosService $respaldos,
        private AlertasService $alertas,
    ) {}

    public function revisar(CarbonImmutable $hoy): void
    {
        if ($this->respaldos->estaVencido()) {
            $ultimo = $this->respaldos->ultimoCorrecto();

            $this->alertas->emitir(AlertasDelNucleo::RESPALDO_VENCIDO, $hoy->toDateString(), [
                'ultimo' => $ultimo?->iniciada_at->timezone('America/Lima')->format('d/m/Y') ?? 'ninguno',
            ]);
        }
    }
}
