<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Erp\Compras\Models\Herramienta;
use App\Modules\Erp\Compras\Services\HerramientasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** MV-028: las herramientas de la demo, unas en obra (una atrasada), otras en el almacén y una en mantenimiento. */
final class HerramientasSeeder extends Seeder
{
    public function run(): void
    {
        if (Herramienta::query()->exists()) {
            return;
        }

        $s = app(HerramientasService::class);
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $proyectos = DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('e.codigo', 'en_ejecucion')->orderBy('p.id')->pluck('p.id')->all();
        $gente = DB::table('colaboradores')->where('es_activo', true)->orderBy('id')->pluck('id')->all();

        $lista = [
            ['Taladro percutor', 'electrica', 'Bosch GSB 18V-50', 'BSH-22871', 0, -20, 10],
            ['Escalera telescópica 6 m', 'altura', 'Werner D1520', null, 1, -12, 18],
            ['Camioneta 4x4', 'vehiculo', 'Toyota Hilux 2023', 'BXQ-482', 0, -30, -3],
            ['Telurómetro', 'medicion', 'Fluke 1625-2', 'FLK-77120', 2, -8, 6],
            ['Multímetro de pinza', 'medicion', 'Fluke 376 FC', 'FLK-55410', 3, -5, 9],
            ['Certificadora de cableado', 'medicion', 'Fluke DSX-5000', 'FLK-90233', 1, -15, -2],
            ['Arnés y línea de vida', 'altura', 'MSA V-Form', null, null, 0, 0],
            ['Amoladora angular', 'electrica', 'Makita GA4530', 'MKT-30112', null, 0, 0],
            ['Escalera tijera 2,4 m', 'altura', 'Little Giant', null, null, 0, 0],
            ['Fusionadora de fibra', 'medicion', 'Fujikura 90S', 'FJK-11872', 'mantenimiento', -40, 0],
        ];

        foreach ($lista as $i => [$nombre, $tipo, $marca, $serie, $donde, $salio, $vuelve]) {
            $h = $s->registrar($nombre, $tipo, $marca, $serie);

            if (is_int($donde) && isset($proyectos[$donde % max(count($proyectos), 1)]) && $gente !== []) {
                $s->enviarAObra($h->id, $proyectos[$donde % count($proyectos)], $gente[$i % count($gente)], $hoy->addDays($salio), $hoy->addDays($vuelve), null, 'Para la obra');
            } elseif ($donde === 'mantenimiento' && $proyectos !== [] && $gente !== []) {
                $s->enviarAObra($h->id, $proyectos[0], $gente[0], $hoy->addDays($salio), $hoy->addDays($salio + 20), null);
                $s->devolver($h->id, $hoy->addDays(-3), true, null, 'Calibración del arco');
            }
        }
    }
}
