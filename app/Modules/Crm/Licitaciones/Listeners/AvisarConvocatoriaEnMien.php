<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Listeners;

use App\Modules\Crm\Licitaciones\Events\ConvocatoriaCambio;
use App\Modules\Crm\Licitaciones\Events\ConvocatoriaLlego;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Observación 7.4, reacción «Avisar en Mien»: «Nueva convocatoria: objeto · entidad · S/ …» para las que entran a la
 * bandeja; y si una ya registrada cambió su fecha de presentación, «La convocatoria de [entidad] cambió su fecha de
 * presentación al …». Punto de extensión: el mismo evento puede avisar por WhatsApp.
 */
final class AvisarConvocatoriaEnMien
{
    public function handle(ConvocatoriaLlego|ConvocatoriaCambio $evento): void
    {
        $c = DB::table('convocatorias_seace')->find($evento->convocatoriaId);

        if ($c === null) {
            return;
        }

        if ($evento instanceof ConvocatoriaLlego) {
            if ($c->en_bandeja) {
                $monto = $c->valor_referencial === null ? '' : ' · S/ '.number_format((float) $c->valor_referencial, 2);
                $this->avisar($c->id, 'nueva', "Nueva convocatoria: {$c->objeto} · {$c->entidad}{$monto}");
            }

            return;
        }

        if ($c->estado === 'registrada' && isset($evento->cambios['presentacion'][1])) {
            $fecha = CarbonImmutable::parse($evento->cambios['presentacion'][1])->format('d/m/Y');
            $this->avisar($c->id, 'cambio', "La convocatoria de {$c->entidad} cambió su fecha de presentación al {$fecha}");
        }
    }

    private function avisar(int $id, string $tipo, string $texto): void
    {
        DB::table('convocatoria_avisos')->insert(['convocatoria_id' => $id, 'tipo' => $tipo, 'texto' => mb_substr($texto, 0, 400), 'created_at' => now()]);
    }
}
