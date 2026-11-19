<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Lo mínimo de `proyectos` (plan 2.1, D2): la ficha de ejecución contra la que se imputa el costo. El área 4 sigue desde
 * aquí (fases, hitos, entregables). Desviaciones del DBML, a propósito:
 * - Sin `tipologia`: T1–T6 no están definidas; espera el pendiente «Tipologías de proyecto».
 * - Sin `monto_contratado`: el monto vive en el contrato, con sus adendas (C10).
 * - Sin `avance_porcentaje` ni `fecha_fin_real`: son de las fases y del cierre (área 4).
 * - `contrato_id` con índice y sin llave foránea: `contratos` no existe todavía; la llave la agrega la tarea de
 *   Contratos. Desde ya, un proyecto es interno o tiene contrato (v4).
 * - `version` para el bloqueo optimista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 200);
            $table->foreignId('party_id')->index()->constrained('parties');
            $table->boolean('es_interno')->default(false);
            $table->unsignedBigInteger('contrato_id')->nullable()->index();
            $table->foreignId('jefe_proyecto_user_id')->index()->constrained('users');
            $table->date('fecha_inicio');
            $table->date('fecha_fin_prevista');
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE proyectos ADD CONSTRAINT proyectos_textos_check CHECK (btrim(nombre) <> '' AND btrim(codigo) <> '')");
        DB::statement('ALTER TABLE proyectos ADD CONSTRAINT proyectos_fechas_check CHECK (fecha_fin_prevista >= fecha_inicio)');
        DB::statement('ALTER TABLE proyectos ADD CONSTRAINT proyectos_contrato_check CHECK (es_interno OR contrato_id IS NOT NULL)');
        DB::statement('ALTER TABLE proyectos ADD CONSTRAINT proyectos_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
