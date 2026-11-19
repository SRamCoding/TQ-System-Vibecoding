<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Jobs;

use App\Modules\Nucleo\Services\EfectosExternosService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Revierte un efecto externo después del commit de la anulación (plan 1.5, D3), como EjecutarEfectoExterno: fuera
 * de toda transacción, con reserva, y solo si salió o pudo salir.
 */
final class RevertirEfectoExterno implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /** Menor que la reserva (EfectosExternosService::PLAZO_RESERVA), que es menor que `retry_after` de la cola. */
    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $efectoAplicadoId) {}

    public function handle(EfectosExternosService $efectos): void
    {
        if (! $efectos->revertir($this->efectoAplicadoId)) {
            $this->release(EfectosExternosService::PLAZO_RESERVA);
        }
    }

    public function failed(?Throwable $error): void
    {
        // Solo ids: los datos del efecto no van a los registros (regla 10).
        Log::error('Un efecto externo no se pudo revertir.', ['efecto_aplicado_id' => $this->efectoAplicadoId]);

        // Plan 1.10, D3: y avisa. Si el aviso mismo falla, queda en el registro: nunca tapa el fallo del efecto.
        try {
            app(EfectosExternosService::class)->avisarQueFallo($this->efectoAplicadoId, alRevertir: true);
        } catch (Throwable $aviso) {
            Log::error('No se pudo avisar que un efecto externo falló.', ['efecto_aplicado_id' => $this->efectoAplicadoId, 'error' => $aviso::class]);
        }
    }
}
