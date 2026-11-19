<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El árbol de partidas de control (plan 2.1, D5): contra sus hojas se carga cada costo real. Desviaciones del DBML:
 * - `partida_arboles.numero_version` en lugar de `version`: en el sistema, `version` es el bloqueo optimista que
 *   `TransicionesService` sube en cada transición; los dos no pueden ser la misma columna.
 * - Sin `partida_arboles.presupuesto_id` ni `partidas_control.origen_titulo_id` / `origen_partida_id`: `presupuestos`
 *   llega con la tarea del presupuesto con APU, que las agrega.
 * - Sin `aprobado_por` / `aprobado_at`: quién aprobó y cuándo ya queda en `historial_estados`.
 * - `partidas_control.version` para el bloqueo optimista de sus cambios.
 * - `partida_presupuestos.proyecto_id`, para que la base amarre la partida y el árbol al mismo proyecto.
 * - Supuesto ligado al pendiente «Árbol de partidas»: el código es `PC-NN` o `PC-NN.NN…` y `PC-99` es la partida de no
 *   presupuestados, que crea el sistema. Que el código de un hijo empiece con el de su padre lo revisa el servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partida_arboles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->smallInteger('numero_version')->default(1);
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->decimal('costo_presupuestado', 14, 2);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['proyecto_id', 'numero_version']);
            // Para amarrar cada partida a un árbol de su mismo proyecto.
            $table->unique(['id', 'proyecto_id']);
        });

        DB::statement('ALTER TABLE partida_arboles ADD CONSTRAINT partida_arboles_montos_check CHECK (costo_presupuestado >= 0 AND numero_version >= 1 AND version >= 1)');

        Schema::create('partidas_control', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->unsignedBigInteger('padre_id')->nullable()->index();
            $table->unsignedBigInteger('arbol_alta_id')->index();
            $table->string('codigo', 30);
            $table->string('nombre', 200);
            $table->boolean('es_hoja')->default(true);
            $table->foreignId('responsable_user_id')->index()->constrained('users');
            $table->boolean('es_gastos_generales')->default(false);
            $table->boolean('es_no_presupuestado')->default(false);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['proyecto_id', 'codigo']);
            // Para que el padre y la imputación (D6) apunten a una partida del mismo proyecto.
            $table->unique(['id', 'proyecto_id']);
            $table->foreign(['padre_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('partidas_control');
            $table->foreign(['arbol_alta_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('partida_arboles');
        });

        DB::statement("ALTER TABLE partidas_control ADD CONSTRAINT partidas_control_codigo_check CHECK (codigo ~ '^PC-[0-9]{2}(\\.[0-9]{2})*$' AND btrim(nombre) <> '')");
        // La PC-99 es la de no presupuestados y nada más; va en la raíz y nunca tiene hijos.
        DB::statement("ALTER TABLE partidas_control ADD CONSTRAINT partidas_control_pc99_check CHECK ((codigo = 'PC-99') = es_no_presupuestado AND (NOT es_no_presupuestado OR (padre_id IS NULL AND es_hoja)))");
        DB::statement('ALTER TABLE partidas_control ADD CONSTRAINT partidas_control_version_check CHECK (version >= 1)');

        Schema::create('partida_presupuestos', function (Blueprint $table): void {
            $table->id();
            // `proyecto_id` (no está en el DBML) amarra la partida y el árbol al mismo proyecto, como en las otras dos.
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->unsignedBigInteger('partida_id')->index();
            $table->unsignedBigInteger('arbol_id')->index();
            $table->decimal('monto', 14, 2);
            $table->auditoria();

            $table->unique(['partida_id', 'arbol_id']);
            $table->foreign(['partida_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('partidas_control');
            $table->foreign(['arbol_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('partida_arboles');
        });

        DB::statement('ALTER TABLE partida_presupuestos ADD CONSTRAINT partida_presupuestos_monto_check CHECK (monto >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partida_presupuestos');
        Schema::dropIfExists('partidas_control');
        Schema::dropIfExists('partida_arboles');
    }
};
