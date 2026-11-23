<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Erp\Personal\Models\TareoLinea;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Erp\Personal\Services\TareoService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observaciones 6.9 y 6.10 (MV-069, MV-070): todos los casos del tareo nuevo. La jornada de cada persona; en la Plaza de
 * Armas, la semana cerrada y la pasada sin cerrar del tramo 4 y la de esta semana rellenada con la jornada y corregida
 * (horas extra, un «¿faltó?», un SCTR vencido sin tarear, una partida que pasa su presupuesto de mano de obra); en la Red
 * de datos, esta semana con líneas «de la jornada» y una falta, y la del 7 de diciembre con sus dos feriados. Marco
 * queda en dos proyectos y pasa su jornada. Idempotente: cada semana se arma solo si no existe.
 */
final class TareoCasosSeeder extends Seeder
{
    public function run(): void
    {
        $this->jornadas();
        $plaza = (int) DB::table('proyectos')->where('codigo', 'PRY-2026-0003')->value('id');
        $red = (int) DB::table('proyectos')->where('codigo', 'PRY-2026-0001')->value('id');

        if ($plaza === 0 || $red === 0) {
            return;
        }

        $id = fn (string $nombre): int => (int) DB::table('colaboradores')->where('nombres', 'like', $nombre.'%')->value('id');
        $partida = fn (int $proyecto, string $codigo): int => (int) DB::table('partidas_control')->where('proyecto_id', $proyecto)->where('codigo', $codigo)->value('id');
        $lunes = CarbonImmutable::parse(Calendario::hoy())->startOfWeek();
        $s = app(PersonalService::class);
        $t = app(TareoService::class);

        // Tomás (SCTR vencido) sigue asignado a la Plaza: aparece en coral, sin rellenar.
        if (! DB::table('asignaciones')->where('colaborador_id', $id('Tomás'))->where('proyecto_id', $plaza)->where('desde', $lunes->toDateString())->exists()) {
            DB::table('asignaciones')->insert(['colaborador_id' => $id('Tomás'), 'proyecto_id' => $plaza, 'porcentaje' => 100, 'desde' => $lunes->toDateString(), 'hasta' => $lunes->addDays(13)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }

        // Plaza de Armas, esta semana: rellenada y corregida.
        if (! DB::table('tareos')->where('proyecto_id', $plaza)->where('semana', $lunes->toDateString())->exists()) {
            $tareo = $s->semana($plaza, $lunes)->id;
            $linea = fn (string $nombre): int => (int) TareoLinea::query()->where('tareo_id', $tareo)->where('colaborador_id', $id($nombre))->value('id');
            $t->celda($linea('Julio'), 'lu', '10'); // horas extra
            $t->cambiarPartida($linea('Marco'), $partida($plaza, 'PC-01.02')); // pasa el presupuesto de esa partida
            $t->celda($linea('Marco'), 'lu', '0'); // ¿faltó?
        }

        // Red de datos, esta semana: líneas «de la jornada» (con la partida que tendrían de su semana anterior) y una falta.
        if (! DB::table('tareos')->where('proyecto_id', $red)->where('semana', $lunes->toDateString())->exists()) {
            $tareo = $s->semana($red, $lunes)->id;
            $ponerPartida = fn (string $nombre, string $codigo) => TareoLinea::query()->where('tareo_id', $tareo)->where('colaborador_id', $id($nombre))->update(['partida_control_id' => $partida($red, $codigo)]);
            $ponerPartida('Carla', 'PC-03.03');
            $ponerPartida('Milagros', 'PC-01.01');
            $ponerPartida('Marco', 'PC-03.03');
            $t->asistencia($tareo, $id('Carla'), 'lu', false);
        }

        // Red de datos, semana del 7 de diciembre: el 8 y el 9 son feriados y van en 0.
        $diciembre = CarbonImmutable::parse('2026-12-07');

        if ($diciembre->gt($lunes) && ! DB::table('tareos')->where('proyecto_id', $red)->where('semana', $diciembre->toDateString())->exists()) {
            $tareo = $s->semana($red, $diciembre)->id;
            TareoLinea::query()->where('tareo_id', $tareo)->whereNull('partida_control_id')->update(['partida_control_id' => $partida($red, 'PC-03.03')]);
        }
    }

    private function jornadas(): void
    {
        $propias = ['Julio' => ['lu' => '8', 'ma' => '8', 'mi' => '8', 'ju' => '8', 'vi' => '8', 'sa' => '8', 'do' => '0'], 'Diana' => TareoService::jornadaPropuesta('eventual')];
        $eventual = ['lu' => '8', 'ma' => '8', 'mi' => '8', 'ju' => '8', 'vi' => '8', 'sa' => '0', 'do' => '0'];

        foreach (DB::table('colaboradores')->whereNull('jornada')->get(['id', 'nombres', 'vinculo']) as $c) {
            $j = $propias[explode(' ', $c->nombres)[0]] ?? ($c->vinculo === 'planilla' ? TareoService::jornadaPropuesta('planilla') : $eventual);
            DB::table('colaboradores')->where('id', $c->id)->update(['jornada' => json_encode($j)]);
        }
    }
}
