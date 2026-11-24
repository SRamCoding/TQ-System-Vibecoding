<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.6 (ficha t1-licitaciones, MV-100): en la demo, las sugerencias de palabras ya hechas (no se llama a la
 * IA), sacadas de los proyectos que Techquk ganó. Idempotente.
 */
final class BusquedaSeaceSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('seace_sugerencias')->exists()) {
            return;
        }

        foreach ([
            ['videoconferencia', 'Telecomunicaciones', 'Ganaste la sala de videoconferencia de Agroindustrial Norteña; ¿agrego la palabra?'],
            ['computadoras', 'Centro de datos', 'Ganaste el suministro de 60 computadoras de Los Laureles; ¿agrego la palabra?'],
            ['historia clínica', 'Software', 'Ganaste la historia clínica electrónica del Hospital Regional; ¿agrego la palabra?'],
        ] as [$palabra, $rubro, $motivo]) {
            DB::table('seace_sugerencias')->insert(['palabra' => $palabra, 'rubro' => $rubro, 'motivo' => $motivo, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
