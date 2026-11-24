<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Mien\ComponenteTarjetaMien;
use App\Modules\Nucleo\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Observación 9.5 (2), lienzo `oportunidades-mejoras/Aprobacion.dc.html`: «Por aprobar» en Mien, con el descuento, el
 * margen, quién lo pide y su motivo, y Aprobar o Rechazar (con motivo) ahí mismo.
 */
final class TarjetaPorAprobarMargen extends ComponenteTarjetaMien
{
    public function aprobar(int $id, int $version): void
    {
        $this->resolver(fn (User $u) => app(CotizacionesService::class)->aprobarMargen($id, $version, $u), 'Descuento aprobado: ya se puede emitir.');
    }

    public function rechazar(int $id, int $version, string $motivo): void
    {
        $this->resolver(fn (User $u) => app(CotizacionesService::class)->rechazarMargen($id, $version, $motivo, $u), 'Descuento rechazado: la cotización vuelve a borrador.');
    }

    private function resolver(callable $hacer, string $ok): void
    {
        $u = auth()->user();
        abort_unless($u instanceof User, 403);

        try {
            $hacer($u);
            Notification::make()->success()->title($ok)->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    protected function contenido(): View
    {
        return view('cotizaciones::mien-por-aprobar', [
            'items' => app(CotizacionesService::class)->porAprobarMargen(),
            'url' => fn (int $id): string => VerCotizacion::getUrl(['record' => $id]),
        ]);
    }
}
