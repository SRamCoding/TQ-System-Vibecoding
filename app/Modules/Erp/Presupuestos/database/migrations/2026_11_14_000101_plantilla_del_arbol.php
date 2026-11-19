<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * De qué plantilla salió el árbol (plan 2.11, D3 y paso 6; ADR 0022): `partida_arboles.plantilla_version_id`, la
 * versión que se aplicó (nula si se armó a mano o desde el presupuesto). No está en el DBML: nace del ADR 0022.
 * - Apunta a `plantilla_versiones` del núcleo, que no se borran ni se editan: el árbol dice siempre de dónde salió.
 * - Un árbol sale del presupuesto o de una plantilla, no de los dos (plan 2.9b, D6): `CHECK`.
 * - El número 000101 evita chocar con migraciones del mismo día de otras copias: la tabla `migrations` guarda solo el
 *   nombre del archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partida_arboles', function (Blueprint $table): void {
            $table->foreignId('plantilla_version_id')->nullable()->index()->constrained('plantilla_versiones');
        });

        DB::statement('ALTER TABLE partida_arboles ADD CONSTRAINT partida_arboles_un_origen_check CHECK (presupuesto_id IS NULL OR plantilla_version_id IS NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE partida_arboles DROP CONSTRAINT partida_arboles_un_origen_check');

        Schema::table('partida_arboles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plantilla_version_id');
        });
    }
};
