<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 1.4e: los saludos y frases para fuera del horario de la empresa. Nula, una frase del horario normal; si no, para
 * qué situación es: temprano (antes de la hora de inicio), noche (desde la de fin), fin de semana, feriado o cualquiera
 * de las cuatro. Así «Qué temprano» no sale de noche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frases_saludo', function (Blueprint $table): void {
            $table->string('fuera_de_horario', 15)->nullable();
        });

        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_fuera_de_horario_check CHECK (fuera_de_horario IS NULL OR fuera_de_horario IN ('temprano', 'noche', 'fin_de_semana', 'feriado', 'cualquiera'))");
        // Una especial de fuera de horario no es agradecimiento ni lleva fecha especial ni momento: nunca saldría.
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_fuera_de_horario_solo_check CHECK (fuera_de_horario IS NULL OR (tipo <> 'agradecimiento' AND ocasion IS NULL AND momento IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_fuera_de_horario_solo_check');
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_fuera_de_horario_check');

        Schema::table('frases_saludo', function (Blueprint $table): void {
            $table->dropColumn('fuera_de_horario');
        });
    }
};
