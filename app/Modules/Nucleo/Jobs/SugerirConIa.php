<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Jobs;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Ia\SinCupoIa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\IaService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pide la sugerencia al modelo (plan 1.11, D8), en la cola y después del commit: la llamada corre fuera de toda
 * transacción. Como quien la pidió, para la auditoría. Sin cupo en el proveedor, vuelve a la cola con espera.
 *
 * Un reintento nunca paga dos veces la misma llamada (`codigo.md`, «Jobs idempotentes»; revisión de arquitectura):
 * - **Único** por proceso, origen y adjunto mientras espera: un doble clic no encola dos pedidos.
 * - **Reservado mientras corre** (`WithoutOverlapping`, sin devolverlo a la cola): si la cola lo vuelve a entregar
 *   porque tarda más que su `retry_after`, la segunda copia se descarta. La reserva vence si el worker muere.
 * - **Una sola excepción** (`maxExceptions`): un fallo después de la llamada no la repite. Los reintentos son solo por
 *   falta de cupo, que no lanzan.
 * - Si falla o agota la espera de cupo, avisa al responsable (`failed()`).
 * - Va en la cola `ia` (plan 1.11c), cuyo `retry_after` (960 s) es mayor que su `timeout`.
 */
final class SugerirConIa implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    /** Los reintentos son por falta de cupo; los del proveedor los decide IaService y quedan en `ia_llamadas`. */
    public int $tries = 30;

    public int $maxExceptions = 1;

    public int $timeout = 600;

    /** Lo que dura como máximo el pedido único y su reserva: más que el job con sus esperas. */
    public const int RESERVA = 900;

    public int $uniqueFor = self::RESERVA;

    public function __construct(
        public readonly string $proceso,
        public readonly string $origenType,
        public readonly int $origenId,
        public readonly ?int $adjuntoId,
        public readonly int $pedidoPor,
    ) {
        // Plan 1.11c: en su propia cola, con su worker; los correos y el tipo de cambio no esperan detrás.
        $this->onConnection(config('nucleo.ia.cola'))->onQueue('ia');
    }

    public function uniqueId(): string
    {
        return "{$this->proceso}:{$this->origenType}:{$this->origenId}:".($this->adjuntoId ?? '-');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ia-pedido:'.$this->uniqueId()))->dontRelease()->expireAfter(self::RESERVA)];
    }

    public function handle(IaService $ia, ContextoAuditoria $auditoria): void
    {
        $tarea = fn (): mixed => $ia->ejecutar($this->proceso, $this->origenType, $this->origenId, $this->adjuntoId);
        $persona = User::query()->find($this->pedidoPor);

        try {
            $persona instanceof User ? $auditoria->comoUsuario($persona, $tarea) : $tarea();
        } catch (SinCupoIa) {
            $this->release(IaService::ESPERA_SIN_CUPO);
        }
    }

    public function failed(?Throwable $error): void
    {
        // Sin datos del origen ni del documento (regla 10): el proceso y el tipo del error.
        Log::error('Una sugerencia de IA no se pudo pedir.', ['proceso' => $this->proceso, 'error' => $error === null ? null : $error::class]);

        try {
            $nombre = app(RegistroProcesosIa::class)->procesos()[$this->proceso]->nombre ?? $this->proceso;
            app(AlertasService::class)->emitir(
                AlertasDelNucleo::IA_PEDIDO_FALLIDO,
                "proceso:{$this->proceso}:".Calendario::ahora()->toDateString(),
                ['proceso' => $nombre],
            );
        } catch (Throwable $aviso) {
            Log::error('No se pudo avisar que una sugerencia de IA falló.', ['proceso' => $this->proceso, 'error' => $aviso::class]);
        }
    }
}
