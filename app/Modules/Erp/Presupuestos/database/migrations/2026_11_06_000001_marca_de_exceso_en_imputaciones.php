<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La marca de exceso de partida en `imputaciones` (plan 2.2, D4). Vive aquí y no en el núcleo porque su regla nombra
 * `partida_control_id`, que agrega Presupuestos. Una línea marcada va a una partida y lleva su motivo y su comentario;
 * que nunca sea la PC-99 lo revisa `ExcesoDePartidaService` (la base no sabe cuál es).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imputaciones', function (Blueprint $table): void {
            $table->boolean('excede_partida')->default(false);
        });

        DB::statement("ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_exceso_check CHECK (NOT excede_partida OR (partida_control_id IS NOT NULL AND motivo_id IS NOT NULL AND comentario IS NOT NULL AND btrim(comentario) <> ''))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE imputaciones DROP CONSTRAINT imputaciones_exceso_check');

        Schema::table('imputaciones', function (Blueprint $table): void {
            $table->dropColumn('excede_partida');
        });
    }
};
