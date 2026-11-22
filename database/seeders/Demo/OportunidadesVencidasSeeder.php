<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** MV-060: dos oportunidades abiertas con el cierre esperado ya pasado, para ver «venció» y el separador de vencidas. */
final class OportunidadesVencidasSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $abiertas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->whereIn('e.codigo', ['calificada', 'propuesta_enviada'])->orderBy('o.id')->limit(2)->pluck('o.id')->all();

        if (DB::table('oportunidades')->whereIn('id', $abiertas)->where('fecha_cierre_estimada', '<', $hoy->toDateString())->exists()) {
            return;
        }

        foreach ($abiertas as $i => $id) {
            DB::table('oportunidades')->where('id', $id)->update(['fecha_cierre_estimada' => $hoy->subDays($i === 0 ? 12 : 4)->toDateString()]);
        }
    }
}
