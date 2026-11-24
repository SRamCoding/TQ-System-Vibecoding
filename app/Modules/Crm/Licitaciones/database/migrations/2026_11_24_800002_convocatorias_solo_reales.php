<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Observación 9.7 (MV-101): Convocatorias del SEACE solo con lo leído del OECE. Se van las convocatorias inventadas de
 * la bandeja y sus avisos; los procesos anteriores ficticios (ganadores de entidades «Valle Norte») se quedan marcados
 * `es_demo`: solo los usan las licitaciones de demo de Oportunidades (nunca la bandeja ni «Buscar en todo el SEACE»).
 * Cada lectura guarda además cuántas quedaron fuera y por qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->boolean('es_demo')->default(false)->index();
        });
        Schema::table('seace_lecturas', function (Blueprint $table): void {
            $table->json('fuera')->nullable();
        });

        // Las inventadas: su código no es del SEACE v3 (los reales son «ocds-dgv273-seacev3-…»).
        $inventadas = DB::table('convocatorias_seace')->where('ocid', 'like', 'ocds-dgv273-%')->where('ocid', 'not like', 'ocds-dgv273-seacev3-%');
        (clone $inventadas)->where('es_historico', true)->update(['es_demo' => true, 'en_bandeja' => false]);
        $borrar = (clone $inventadas)->where('es_historico', false)->pluck('id');
        DB::table('convocatoria_avisos')->whereIn('convocatoria_id', $borrar)->delete();
        DB::table('convocatorias_seace')->whereIn('id', $borrar)->delete();
        // Las lecturas inventadas por la demo (la «programada» de 9 convocatorias) no son lecturas del OECE.
        DB::table('seace_lecturas')->where('origen', 'programada')->where('leidas', 9)->where('nuevas', 9)->delete();
    }

    public function down(): void
    {
        Schema::table('seace_lecturas', fn (Blueprint $t) => $t->dropColumn('fuera'));
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn('es_demo'));
    }
};
