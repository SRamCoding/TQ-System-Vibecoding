<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.5 (ficha r1-oportunidades-vistas, MV-099): que se vea cada caso de las diez mejoras de Oportunidades.
 * Idempotente: cada parte revisa si ya está.
 */
final class MejorasOportunidadesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->propuestaSinRespuesta();
            $this->esperandoAprobacion();
        });
    }

    /** (6) «CCTV perimetral de la planta concentradora» lleva 9 días en Propuesta enviada sin ninguna actividad. */
    private function propuestaSinRespuesta(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $o = DB::table('oportunidades')->where('nombre', 'CCTV perimetral de la planta concentradora')->first(['id', 'en_etapa_desde']);

        if ($o === null) {
            return;
        }

        $desde = $hoy->subDays(9)->setTime(10, 0);
        DB::table('oportunidades')->where('id', $o->id)->update(['en_etapa_desde' => $desde]);
        DB::table('actividades')->where('oportunidad_id', $o->id)->where('created_at', '>=', $desde)->update(['created_at' => $desde->subDay()]);
    }

    /**
     * (2) La cotización bajo el mínimo que espera aprobación, pedida por el camino de verdad (abre su ronda) con su motivo:
     * sale en Mien → «Por aprobar».
     */
    private function esperandoAprobacion(): void
    {
        $c = Cotizacion::query()->whereHas('estado', fn ($q) => $q->where('codigo', 'por_aprobar'))->first();

        if ($c === null || DB::table('rondas_aprobacion')->where('aprobable_type', 'cotizacion')->where('aprobable_id', $c->id)->where('estado', 'abierta')->exists()) {
            return;
        }

        $usuario = User::query()->where('email', 'dev@techquk.test')->first() ?? User::query()->where('es_sistema', false)->firstOrFail();
        auth()->setUser($usuario);
        $borrador = (int) DB::table('estados')->where('documento_type', 'cotizacion')->where('codigo', 'borrador')->value('id');
        DB::table('cotizaciones')->where('id', $c->id)->update(['estado_id' => $borrador]);
        $c->refresh();
        app(CotizacionesService::class)->solicitarAprobacion($c->id, $c->version,
            'Competencia fuerte: Redes Andinas ofertó 10 % menos en el proceso anterior de esta municipalidad.', $usuario);
    }
}
