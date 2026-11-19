<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * De dónde salió el árbol de partidas (plan 2.9b, D6; DBML v2: `partida_arboles.presupuesto_id`,
 * `partidas_control.origen_titulo_id` y `origen_partida_id`, que la 2.1 dejó para esta tarea).
 * - El árbol guarda el presupuesto aprobado del que salió su propuesta, del mismo proyecto (llave compuesta a
 *   `presupuestos (id, proyecto_id)`); nulo si se armó a mano o desde una plantilla.
 * - Cada partida guarda, como máximo, un origen: un título o una partida del presupuesto. La de gastos generales y la
 *   PC-99 no llevan ninguno (el de gastos generales se deduce de su marca y del presupuesto del árbol).
 * - El origen es del presupuesto de su árbol, y eso lo garantiza la base (revisión de arquitectura de la 2.9b):
 *   `origen_presupuesto_id` (no está en el DBML) va con el título o la partida por llaves compuestas a
 *   `presupuesto_titulos (id, presupuesto_id)` y `presupuesto_partidas (id, presupuesto_id)`, y con el árbol por
 *   `partida_arboles (id, presupuesto_id)`. Así una partida no puede salir de otro presupuesto, y el presupuesto del
 *   árbol no se cambia mientras alguna partida venga de él.
 * - El `CHECK` «presupuesto o plantilla, no los dos» lo agrega la 2.11 junto con `plantilla_version_id`.
 * - El número 000002 evita chocar con `2026_11_12_000001_crear_plantillas` del núcleo (2.11): la tabla `migrations`
 *   guarda solo el nombre del archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table): void {
            // Destino de la llave compuesta del árbol.
            $table->unique(['id', 'proyecto_id']);
        });

        Schema::table('presupuesto_partidas', function (Blueprint $table): void {
            // Destino de la llave compuesta del origen.
            $table->unique(['id', 'presupuesto_id']);
        });

        Schema::table('partida_arboles', function (Blueprint $table): void {
            $table->unsignedBigInteger('presupuesto_id')->nullable()->index();
            $table->foreign(['presupuesto_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('presupuestos');
            // Destino de la llave compuesta del origen de sus partidas.
            $table->unique(['id', 'presupuesto_id']);
        });

        Schema::table('partidas_control', function (Blueprint $table): void {
            $table->foreignId('origen_titulo_id')->nullable()->index()->constrained('presupuesto_titulos');
            $table->foreignId('origen_partida_id')->nullable()->index()->constrained('presupuesto_partidas');
            $table->foreignId('origen_presupuesto_id')->nullable()->index()->constrained('presupuestos');
            $table->foreign(['origen_titulo_id', 'origen_presupuesto_id'], 'partidas_control_origen_titulo_foreign')->references(['id', 'presupuesto_id'])->on('presupuesto_titulos');
            $table->foreign(['origen_partida_id', 'origen_presupuesto_id'], 'partidas_control_origen_partida_foreign')->references(['id', 'presupuesto_id'])->on('presupuesto_partidas');
            // Nombres cortos: PostgreSQL corta los identificadores a 63 caracteres.
            $table->foreign(['arbol_alta_id', 'origen_presupuesto_id'], 'partidas_control_origen_arbol_foreign')->references(['id', 'presupuesto_id'])->on('partida_arboles');
        });

        DB::statement('ALTER TABLE partidas_control ADD CONSTRAINT partidas_control_origen_check CHECK ((origen_titulo_id IS NULL OR origen_partida_id IS NULL) AND (origen_presupuesto_id IS NULL) = (origen_titulo_id IS NULL AND origen_partida_id IS NULL))');
        DB::statement('ALTER TABLE partidas_control ADD CONSTRAINT partidas_control_origen_sistema_check CHECK (NOT (es_gastos_generales OR es_no_presupuestado) OR (origen_titulo_id IS NULL AND origen_partida_id IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE partidas_control DROP CONSTRAINT partidas_control_origen_sistema_check');
        DB::statement('ALTER TABLE partidas_control DROP CONSTRAINT partidas_control_origen_check');

        Schema::table('partidas_control', function (Blueprint $table): void {
            $table->dropForeign('partidas_control_origen_arbol_foreign');
            $table->dropForeign('partidas_control_origen_partida_foreign');
            $table->dropForeign('partidas_control_origen_titulo_foreign');
            $table->dropConstrainedForeignId('origen_presupuesto_id');
            $table->dropConstrainedForeignId('origen_partida_id');
            $table->dropConstrainedForeignId('origen_titulo_id');
        });

        Schema::table('partida_arboles', function (Blueprint $table): void {
            $table->dropUnique(['id', 'presupuesto_id']);
            $table->dropForeign(['presupuesto_id', 'proyecto_id']);
            $table->dropIndex(['presupuesto_id']);
            $table->dropColumn('presupuesto_id');
        });

        Schema::table('presupuesto_partidas', function (Blueprint $table): void {
            $table->dropUnique(['id', 'presupuesto_id']);
        });

        Schema::table('presupuestos', function (Blueprint $table): void {
            $table->dropUnique(['id', 'proyecto_id']);
        });
    }
};
