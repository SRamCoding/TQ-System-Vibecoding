<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

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
}
