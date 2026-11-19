<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4aa, D6 a D8: el saludo en cinco idiomas, según la sesión.
 * - `frases_saludo.idioma` acepta portugués y alemán.
 * - `frases_saludo.sesion`: nula, un saludo de cualquier sesión; si no, solo en esa clase de sesión (primera del día,
 *   vuelve, vuelve después de trabajar). Solo saludos, sin fecha especial ni fuera de horario: esos mandan antes.
 * - `saludos_mostrados`: una fila por persona y sesión (la semilla de `SemillaSesion`), con el idioma en que se la
 *   saludó. Da el idioma anterior (nunca dos sesiones seguidas en otro idioma, como mucho una al día) y si ya entró hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->valores("'es', 'en', 'fr', 'pt', 'de'");

        Schema::table('frases_saludo', function (Blueprint $table): void {
            $table->string('sesion', 25)->nullable();
        });

        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_sesion_check CHECK (sesion IS NULL OR sesion IN ('primera_del_dia', 'vuelve', 'vuelve_con_actividad'))");
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_sesion_solo_check CHECK (sesion IS NULL OR (tipo = 'saludo' AND ocasion IS NULL AND fuera_de_horario IS NULL))");

        Schema::create('saludos_mostrados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('idioma', 2);
            $table->string('sesion', 64);
            $table->timestampTz('mostrado_at');
            $table->auditoria();

            $table->unique(['user_id', 'sesion'], 'saludos_mostrados_unique');
            $table->index(['user_id', 'mostrado_at'], 'saludos_mostrados_user_fecha_index');
        });

        DB::statement("ALTER TABLE saludos_mostrados ADD CONSTRAINT saludos_mostrados_valores_check CHECK (idioma IN ('es', 'en', 'fr', 'pt', 'de') AND btrim(sesion) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('saludos_mostrados');

        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_sesion_solo_check');
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_sesion_check');

        Schema::table('frases_saludo', function (Blueprint $table): void {
            $table->dropColumn('sesion');
        });

        // Sin los idiomas, sus filas no caben en el CHECK anterior.
        DB::table('frases_saludo')->whereIn('idioma', ['pt', 'de'])->delete();
        $this->valores("'es', 'en', 'fr'");
    }

    private function valores(string $idiomas): void
    {
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_valores_check');
        DB::statement(<<<SQL
            ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_valores_check CHECK (
                tipo IN ('saludo', 'frase', 'agradecimiento')
                AND idioma IN ({$idiomas})
                AND (momento IS NULL OR momento IN ('manana', 'tarde', 'noche'))
                AND (dia_semana IS NULL OR dia_semana BETWEEN 1 AND 7)
                AND (area IS NULL OR area IN ('crm', 'erp'))
                AND (ocasion IS NULL OR ocasion IN ('anio_nuevo', 'semana_santa', 'dia_madre', 'dia_padre', 'fiestas_patrias', 'navidad', 'cumpleanos', 'aniversario'))
            )
            SQL);
    }
};
