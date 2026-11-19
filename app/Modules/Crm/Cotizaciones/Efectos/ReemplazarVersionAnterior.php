<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\Eloquent\Model;

/** Al emitirse una versión, las anteriores que ya salieron al cliente (emitida, rechazada o vencida) pasan a reemplazadas (D5). */
final readonly class ReemplazarVersionAnterior implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Cotizacion) {
            return [];
        }

        $anteriores = Cotizacion::query()
            ->where('numero', $documento->numero)
            ->where('numero_version', '<', $documento->numero_version)
            ->whereHas('estado', fn ($q) => $q->whereIn('codigo', DefinicionCotizacion::SALE_VERSION_NUEVA))
            ->lockForUpdate()
            ->get();

        foreach ($anteriores as $anterior) {
            app(TransicionesService::class)->transicionarComoSistema($anterior, DefinicionCotizacion::REEMPLAZAR);
        }

        return ['reemplazadas' => $anteriores->modelKeys()];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Reemplazada es final y la emitida no se anula: nada que deshacer.
    }
}
