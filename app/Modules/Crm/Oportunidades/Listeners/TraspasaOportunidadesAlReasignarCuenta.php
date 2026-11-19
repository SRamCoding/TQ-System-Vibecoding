<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Listeners;

use App\Modules\Crm\Cuentas\Events\EjecutivoDeCuentaCambiado;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AlertasService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Cuando Cuentas reasigna una cuenta con «pasar también sus oportunidades abiertas» (plan 2.21, D5). Va en la cola con
 * reintentos: el evento sale después de confirmar, así que no es atómico con la reasignación de la cuenta. Si agota
 * los reintentos, además del monitoreo técnico, salta una alerta para que alguien lo corrija con «Reasignar».
 */
final class TraspasaOportunidadesAlReasignarCuenta implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * En la cola actúa el usuario de sistema (ConAuditoria): el historial lo registra como hecho por el sistema. Un oyente
     * en cola recibe solo el evento en `handle()` (CallQueuedListener no inyecta más): el servicio se pide con `app()`.
     */
    public function handle(EjecutivoDeCuentaCambiado $evento): void
    {
        if ($evento->incluyeOportunidadesAbiertas) {
            app(OportunidadesService::class)->traspasarDeEjecutivo($evento->cuentaId, $evento->ejecutivoAnteriorUserId);
        }
    }

    public function failed(EjecutivoDeCuentaCambiado $evento, Throwable $error): void
    {
        $nombre = fn (int $id): string => (string) (User::query()->whereKey($id)->value('name') ?? "#{$id}");

        app(AlertasService::class)->emitir(
            OportunidadesServiceProvider::ALERTA_TRASPASO_FALLIDO,
            "cuenta:{$evento->cuentaId}:de:{$evento->ejecutivoAnteriorUserId}:a:{$evento->ejecutivoNuevoUserId}",
            [
                'cuenta' => app(CuentasService::class)->resumen($evento->cuentaId)->etiqueta,
                'anterior' => $nombre($evento->ejecutivoAnteriorUserId),
                'nuevo' => $nombre($evento->ejecutivoNuevoUserId),
            ],
            enlace: "/cuentas/{$evento->cuentaId}",
        );
    }
}
