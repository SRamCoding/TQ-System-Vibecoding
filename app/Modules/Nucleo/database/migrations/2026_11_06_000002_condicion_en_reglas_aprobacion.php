<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.2, D5: una regla de aprobación puede entrar solo con una condición, además de su rango de monto. Hoy, «exceso
 * de partida» (v4 parte 7: «Cualquier línea que exceda su partida: siempre Jefatura de Proyectos; sobre U1 también
 * Gerencia de Operaciones»). Plan 2.6, D2: «gasto no presupuestado», si alguna línea va a la PC-99 (v4 parte 7:
 * «siempre, cualquier monto»). Los valores salen de `CondicionRegla`. Ninguna de las dos admite autoaprobación
 * (desarrollador, 2026-11-06): las firma siempre otra persona, como «nadie aprueba su propio incremento» del fondo del
 * residente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglas_aprobacion', function (Blueprint $table): void {
            $table->string('condicion', 30)->default('ninguna');
        });

        DB::statement("ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_condicion_check CHECK (condicion IN ('ninguna', 'exceso_partida', 'gasto_no_presupuestado'))");
        DB::statement("ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_condicion_sin_autoaprobacion_check CHECK (condicion = 'ninguna' OR tope_autoaprobacion IS NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reglas_aprobacion DROP CONSTRAINT reglas_aprobacion_condicion_sin_autoaprobacion_check');
        DB::statement('ALTER TABLE reglas_aprobacion DROP CONSTRAINT reglas_aprobacion_condicion_check');

        Schema::table('reglas_aprobacion', function (Blueprint $table): void {
            $table->dropColumn('condicion');
        });
    }
};
