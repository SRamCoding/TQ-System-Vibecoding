<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Lo que la 1.9 dejó anotado para la etapa 2 en `imputaciones` (plan 2.1, D6). Vive aquí y no en el núcleo porque
 * necesita `partidas_control`, y el núcleo no sabe de `Erp`.
 * - `proyecto_id` y `partida_control_id`; la llave compuesta impide una línea del proyecto A en una partida del B.
 * - Destino: partida o centro de costo, y con partida, proyecto. Un centro de costo con proyecto y sin partida queda
 *   permitido porque no estorba y ningún servicio llega ahí: es un conflicto con el v4 por decidir (plan 2.1,
 *   «Preparado para lo diferido», nota «Costos tempranos»).
 * - Sin `es_posible_adicional` (flujo 7) ni `tipo_compra_id` (Compras): llegan con sus tareas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imputaciones', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->nullable()->index()->constrained('proyectos');
            $table->unsignedBigInteger('partida_control_id')->nullable()->index();
            $table->foreign(['partida_control_id', 'proyecto_id'])->references(['id', 'proyecto_id'])->on('partidas_control');
        });

        DB::statement('ALTER TABLE imputaciones DROP CONSTRAINT imputaciones_destino_check');
        DB::statement('ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_destino_check CHECK ((partida_control_id IS NOT NULL OR centro_costo_id IS NOT NULL) AND (partida_control_id IS NULL OR proyecto_id IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE imputaciones DROP CONSTRAINT imputaciones_destino_check');
        DB::statement('ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_destino_check CHECK (centro_costo_id IS NOT NULL)');

        Schema::table('imputaciones', function (Blueprint $table): void {
            $table->dropForeign(['partida_control_id', 'proyecto_id']);
            $table->dropColumn(['partida_control_id', 'proyecto_id']);
        });
    }
};
