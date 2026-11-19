<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las redes sociales de una party o de uno de sus contactos (plan 1.4o, D2 y D3). No está en el DBML: es pedido del
 * desarrollador (2026-10-27).
 * - `party_id` siempre; con `contacto_id`, la red es de ese contacto, que debe ser de la misma party (llave compuesta,
 *   como en `party_sedes`).
 * - `es_privada` (D3): solo la ve la propia persona; si no, todo el equipo. Un contacto no la usa.
 * - El enlace se guarda normalizado (esquema y dominio en minúsculas) y no se repite en el mismo dueño.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_redes_sociales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->index()->constrained('parties');
            // Llave compuesta (party_id, contacto_id) más abajo; este índice la sirve.
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->foreignId('red_social_id')->index()->constrained('redes_sociales');
            $table->string('enlace', 500);
            $table->boolean('es_privada')->default(false);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['contacto_id', 'party_id']);
        });

        DB::statement('ALTER TABLE party_redes_sociales ADD CONSTRAINT party_redes_sociales_contacto_foreign FOREIGN KEY (party_id, contacto_id) REFERENCES party_contactos (party_id, id)');
        DB::statement("ALTER TABLE party_redes_sociales ADD CONSTRAINT party_redes_sociales_enlace_check CHECK (enlace ~ '^https?://[a-z0-9.-]+\\.[a-z0-9-]+([/?#:]\\S*)?$')");
        DB::statement('ALTER TABLE party_redes_sociales ADD CONSTRAINT party_redes_sociales_privada_check CHECK (NOT es_privada OR contacto_id IS NULL)');
        DB::statement('ALTER TABLE party_redes_sociales ADD CONSTRAINT party_redes_sociales_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX party_redes_sociales_enlace_unique ON party_redes_sociales (party_id, contacto_id, enlace) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('party_redes_sociales');
    }
};
