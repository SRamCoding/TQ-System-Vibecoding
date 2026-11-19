<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Impedimentos;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Estados\DocumentoQueImpide;
use App\Modules\Nucleo\Estados\RevisionAntesDeAnular;
use Illuminate\Database\Eloquent\Model;

/**
 * Con una cotización aceptada, la oportunidad no se anula (plan 2.22, D13): primero se anula la aceptación. No vale
 * para «anular ganada»: la aceptada es parte de lo ganado y queda como historia.
 */
final readonly class AceptadaImpideAnular implements RevisionAntesDeAnular
{
    public function documentosQueImpiden(Model $documento): array
    {
        if (app(OportunidadesService::class)->resumen((int) $documento->getKey())->etapa === 'ganada') {
            return [];
        }

        return self::de((int) $documento->getKey());
    }

    /** @return list<DocumentoQueImpide> */
    public static function de(int $oportunidadId): array
    {
        return Cotizacion::query()->where('oportunidad_id', $oportunidadId)->where('es_aceptada', true)->get()
            ->map(fn (Cotizacion $c): DocumentoQueImpide => new DocumentoQueImpide('la cotización', "{$c->numero} v{$c->numero_version}", 'aceptada: anula la aceptación primero'))
            ->all();
    }
}
