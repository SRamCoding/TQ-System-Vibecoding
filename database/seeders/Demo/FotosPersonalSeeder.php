<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 6.7 (MV-067): todas las personas con su sexo y más o menos la mitad con una foto de perfil, para ver los
 * dos casos (foto y avatar por defecto) en hombres y mujeres. Las fotos son ilustraciones inventadas que dibuja este
 * seeder, nunca fotos de personas reales. Suma dos colaboradoras (una con foto, otra sin asignar y sin SCTR). Idempotente.
 */
final class FotosPersonalSeeder extends Seeder
{
    private const array MUJERES = ['Carla', 'Milagros', 'Diana', 'Rosa', 'María', 'Ana', 'Lucía', 'Patricia', 'Sofía', 'Gabriela'];

    /** dni => [fondo, piel, pelo, peinado, ropa, lentes] */
    private const array RETRATOS = [
        '40112233' => ['#BFE3D9', '#C98E62', '#2B211C', 'corto', '#2F6F8F', false],
        '40334455' => ['#F3D9B1', '#E2B48C', '#4A3326', 'lado', '#5B6D4E', true],
        '40445566' => ['#D8CCEF', '#D9A67C', '#1F1A17', 'largo', '#B34E5A', false],
        '41005566' => ['#F6C9C0', '#B97A52', '#3B2A20', 'mono', '#2E5E7E', true],
        '41007788' => ['#CFE0F5', '#8E5B3E', '#151210', 'rapado', '#C77B2B', false],
    ];

    public function run(): void
    {
        $this->colaboradoras();

        foreach (DB::table('colaboradores')->whereNull('sexo')->get(['id', 'nombres']) as $c) {
            $nombre = explode(' ', $c->nombres)[0];
            DB::table('colaboradores')->where('id', $c->id)->update(['sexo' => in_array($nombre, self::MUJERES, true) ? 'mujer' : 'hombre']);
        }

        // Raúl (del tramo 4, sin DNI fijo en este seeder) también lleva foto: se le da la del retrato «rapado».
        $raul = DB::table('colaboradores')->where('nombres', 'like', 'Raúl Mendoza%')->value('dni');
        $retratos = self::RETRATOS;

        if ($raul !== null) {
            $retratos[$raul] = $retratos['41007788'];
            unset($retratos['41007788']);
        }

        foreach ($retratos as $dni => $r) {
            $c = DB::table('colaboradores')->where('dni', $dni)->first(['id', 'foto_ruta']);

            if ($c === null || $c->foto_ruta !== null) {
                continue;
            }

            app(PersonalService::class)->cambiarFoto((int) $c->id, self::retrato(...$r), 'svg');
        }
    }

    private function colaboradoras(): void
    {
        $cat = fn (string $c): int => (int) DB::table('categorias_personal')->where('codigo', $c)->value('id');

        if (! DB::table('colaboradores')->where('dni', '41005566')->exists()) {
            $id = Colaborador::query()->create(['nombres' => 'Milagros Torres Alva', 'dni' => '41005566', 'categoria_id' => $cat('ESP'), 'vinculo' => 'planilla', 'sexo' => 'mujer',
                'habilitado_hasta' => '2027-05-31', 'remuneracion' => '4200.00', 'regimen' => 'afp', 'ingreso' => '2025-08-01'])->id;
            $proyecto = (int) DB::table('proyectos')->where('nombre', 'like', 'Red de datos y enlaces%')->value('id');

            if ($proyecto > 0) {
                app(PersonalService::class)->asignar($id, $proyecto, 60, CarbonImmutable::parse(Calendario::hoy())->startOfWeek(), null);
            }
        }

        if (! DB::table('colaboradores')->where('dni', '41006677')->exists()) {
            Colaborador::query()->create(['nombres' => 'Diana Paz Carrión', 'dni' => '41006677', 'categoria_id' => $cat('TEC'), 'vinculo' => 'eventual', 'sexo' => 'mujer']);
        }
    }

    /** Un retrato ilustrado y plano (busto con cara, pelo, ropa y, a veces, lentes). */
    private static function retrato(string $fondo, string $piel, string $pelo, string $peinado, string $ropa, bool $lentes): string
    {
        $atras = match ($peinado) {
            'largo' => "<path fill='{$pelo}' d='M58 92c-6-44 14-62 42-62s48 18 42 62c-2 26 8 44-2 60H60c-10-16 0-34-2-60Z'/>",
            'mono' => "<circle fill='{$pelo}' cx='100' cy='34' r='17'/><path fill='{$pelo}' d='M62 90c-2-34 16-52 38-52s40 18 38 52Z'/>",
            default => '',
        };
        $frente = match ($peinado) {
            'corto' => "<path fill='{$pelo}' d='M64 86c-4-32 14-48 36-48s40 16 36 48c-6-14-16-22-36-22s-30 8-36 22Z'/>",
            'lado' => "<path fill='{$pelo}' d='M63 90c-5-34 15-52 39-50 22 2 37 18 33 48-10-18-30-24-56-16-6 4-12 10-16 18Z'/>",
            'largo' => "<path fill='{$pelo}' d='M66 84c2-26 16-38 34-38s32 12 34 38c-10-14-24-20-40-18-12 2-22 8-28 18Z'/>",
            'mono' => "<path fill='{$pelo}' d='M66 84c2-26 16-38 34-38s32 12 34 38c-8-12-20-18-34-18s-26 6-34 18Z'/>",
            default => "<path fill='{$pelo}' opacity='.55' d='M66 82c2-24 16-36 34-36s32 12 34 36c-8-10-20-15-34-15s-26 5-34 15Z'/>",
        };
        $gafas = $lentes ? "<g fill='none' stroke='#2A2A2A' stroke-width='3'><circle cx='86' cy='96' r='10'/><circle cx='114' cy='96' r='10'/><path d='M96 96h8'/></g>" : '';

        return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'><rect width='200' height='200' fill='{$fondo}'/>{$atras}"
            ."<path fill='{$ropa}' d='M30 200c0-38 30-58 70-58s70 20 70 58Z'/><path fill='{$piel}' d='M86 122h28v24c0 8-6 13-14 13s-14-5-14-13Z'/>"
            ."<path fill='#000' opacity='.12' d='M86 134c8 5 20 5 28 0v-8H86Z'/>"
            ."<ellipse fill='{$piel}' cx='64' cy='100' rx='7' ry='10'/><ellipse fill='{$piel}' cx='136' cy='100' rx='7' ry='10'/>"
            ."<ellipse fill='{$piel}' cx='100' cy='94' rx='35' ry='41'/>{$frente}"
            ."<g fill='#2A1E18'><ellipse cx='86' cy='97' rx='4' ry='5'/><ellipse cx='114' cy='97' rx='4' ry='5'/></g>"
            ."<g fill='#fff'><circle cx='87.5' cy='95' r='1.4'/><circle cx='115.5' cy='95' r='1.4'/></g>"
            ."<g fill='none' stroke='{$pelo}' stroke-width='3' stroke-linecap='round'><path d='M78 84q8-5 16 0'/><path d='M106 84q8-5 16 0'/></g>"
            ."<path d='M100 100q-4 12 2 14' fill='none' stroke='#000' stroke-opacity='.25' stroke-width='2.5' stroke-linecap='round'/>"
            ."<path d='M88 120q12 10 24 0' fill='none' stroke='#7A3B2E' stroke-width='3' stroke-linecap='round'/>"
            ."<g fill='#E26D5A' opacity='.18'><circle cx='76' cy='112' r='7'/><circle cx='124' cy='112' r='7'/></g>{$gafas}</svg>";
    }
}
