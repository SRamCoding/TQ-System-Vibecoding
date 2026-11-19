<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Salud del sistema (plan 1.12, D1). Tabla técnica nueva, fuera del DBML: cada corrida de una tarea del programador
 * (latido, respaldos, huérfanos de adjuntos), para que la página de salud lea de la base y no de la caché. `datos`
 * lleva solo cantidades y tamaños, nunca contenido ni datos de personas (regla 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salud_ejecuciones', function (Blueprint $table): void {
            $table->id();
            $table->string('tarea', 60);
            $table->timestampTz('iniciada_at');
            $table->timestampTz('terminada_at')->nullable();
            $table->string('resultado', 15)->default('en_curso');
            $table->jsonb('datos')->default('{}');
            $table->auditoria();

            $table->index(['tarea', 'iniciada_at']);
        });

        DB::statement("ALTER TABLE salud_ejecuciones ADD CONSTRAINT salud_ejecuciones_tarea_check CHECK (tarea ~ '^[a-z][a-z0-9_.-]*$')");
        DB::statement("ALTER TABLE salud_ejecuciones ADD CONSTRAINT salud_ejecuciones_resultado_check CHECK (resultado IN ('en_curso', 'correcta', 'fallida'))");
        DB::statement("ALTER TABLE salud_ejecuciones ADD CONSTRAINT salud_ejecuciones_terminada_check CHECK ((resultado = 'en_curso') = (terminada_at IS NULL) AND (terminada_at IS NULL OR terminada_at >= iniciada_at))");
        DB::statement("ALTER TABLE salud_ejecuciones ADD CONSTRAINT salud_ejecuciones_datos_check CHECK (jsonb_typeof(datos) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('salud_ejecuciones');
    }
};
