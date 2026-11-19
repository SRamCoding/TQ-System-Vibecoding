<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Plan 2.22, D14: una regla de aprobación puede entrar «solo si el margen queda bajo el mínimo» (la cotización; v4
 * parte 7, «margen bajo el mínimo: Gerencia Comercial»). Como las otras condiciones, sin autoaprobación (la restricción
 * `reglas_aprobacion_condicion_sin_autoaprobacion_check` ya lo exige para todo lo que no es «ninguna»).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reglas_aprobacion DROP CONSTRAINT reglas_aprobacion_condicion_check');
        DB::statement("ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_condicion_check CHECK (condicion IN ('ninguna', 'exceso_partida', 'gasto_no_presupuestado', 'margen_bajo_minimo'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reglas_aprobacion DROP CONSTRAINT reglas_aprobacion_condicion_check');
        DB::statement("ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_condicion_check CHECK (condicion IN ('ninguna', 'exceso_partida', 'gasto_no_presupuestado'))");
    }
};
