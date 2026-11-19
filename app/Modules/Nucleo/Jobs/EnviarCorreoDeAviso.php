<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Jobs;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\AvisoPorCorreo;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manda por correo un aviso ya guardado (plan 1.8, D7), después del commit y por cola, con el patrón de los efectos
 * externos (`codigo.md`; `EfectosExternosService`): una transacción corta reserva la fila, el envío corre fuera de toda
 * transacción y otra transacción corta lo confirma o libera la reserva. Si el worker muere a mitad, la reserva vence y
 * otro intento lo retoma: al menos una vez (el Message-ID estable ayuda a que el servidor reconozca el repetido).
 */
final class EnviarCorreoDeAviso implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Mayor que el timeout del job y menor que `retry_after` de la cola, como en los efectos externos. */
    public const int PLAZO_RESERVA = 75;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $notificacionId) {}

    public function handle(): void
    {
        $tomada = DB::transaction(function (): Notificacion|bool {
            $aviso = Notificacion::query()->lockForUpdate()->find($this->notificacionId);

            if ($aviso === null || ! $aviso->enviar_correo || $aviso->correo_enviado_at !== null) {
                return true;
            }

            if ($aviso->correo_reservado_hasta?->isFuture()) {
                return false;
            }

            $aviso->forceFill(['correo_reservado_hasta' => now()->addSeconds(self::PLAZO_RESERVA)])->save();

            return $aviso;
        });

        if ($tomada === false) {
            $this->release(self::PLAZO_RESERVA);

            return;
        }

        if ($tomada === true) {
            return;
        }

        $persona = User::query()->find($tomada->notifiable_id);

        if ($persona === null || ! $persona->es_activo) {
            $this->cerrar(['correo_reservado_hasta' => null]);

            return;
        }

        $datos = $tomada->data;
        $enlace = collect((array) ($datos['actions'] ?? []))->pluck('url')->filter()->first();

        try {
            $persona->notify(new AvisoPorCorreo($tomada->id, (string) ($datos['title'] ?? ''), isset($datos['body']) ? (string) $datos['body'] : null, is_string($enlace) ? $enlace : null));
        } catch (Throwable $error) {
            $this->cerrar(['correo_reservado_hasta' => null]);

            throw $error;
        }

        $this->cerrar(['correo_enviado_at' => now(), 'correo_reservado_hasta' => null]);
    }

    public function failed(?Throwable $error): void
    {
        // Solo el id: el texto del aviso no va a los registros (regla 10).
        Log::error('El correo de un aviso no se pudo enviar.', ['notificacion_id' => $this->notificacionId]);

        // Plan 1.10, D3: y avisa, como mucho una vez al día y solo en la campana (por correo también fallaría).
        try {
            $hoy = Calendario::ahora();
            app(AlertasService::class)->emitir(AlertasDelNucleo::CORREO_NO_ENVIADO, $hoy->toDateString(), ['fecha' => $hoy->format('d/m/Y')]);
        } catch (Throwable $aviso) {
            Log::error('No se pudo avisar que un correo de aviso no salió.', ['notificacion_id' => $this->notificacionId, 'error' => $aviso::class]);
        }
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function cerrar(array $cambios): void
    {
        DB::transaction(function () use ($cambios): void {
            Notificacion::query()->lockForUpdate()->findOrFail($this->notificacionId)->forceFill($cambios)->save();
        });
    }
}
