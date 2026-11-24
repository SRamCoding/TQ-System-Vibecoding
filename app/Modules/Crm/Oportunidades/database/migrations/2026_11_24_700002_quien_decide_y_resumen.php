<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Observación 9.5 (8 y 10): quién decide en cada negocio (personas del cliente con su papel y su postura) y el resumen
 * con el siguiente paso sugerido (por la IA o, en la demo, ya sugerido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidad_decisores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oportunidad_id')->index()->constrained('oportunidades')->cascadeOnDelete();
            $table->foreignId('contacto_id')->nullable()->constrained('party_contactos');
            $table->string('nombre', 150)->nullable();
            $table->string('cargo', 120)->nullable();
            $table->string('papel', 10);
            $table->string('postura', 10);
            $table->string('nota', 300)->nullable();
            $table->auditoria();
        });
        DB::statement("ALTER TABLE oportunidad_decisores ADD CONSTRAINT oportunidad_decisores_papel_check CHECK (papel IN ('decide', 'influye', 'usa', 'bloquea'))");
        DB::statement("ALTER TABLE oportunidad_decisores ADD CONSTRAINT oportunidad_decisores_postura_check CHECK (postura IN ('a_favor', 'neutral', 'en_contra'))");
        DB::statement('ALTER TABLE oportunidad_decisores ADD CONSTRAINT oportunidad_decisores_quien_check CHECK (contacto_id IS NOT NULL OR btrim(coalesce(nombre, \'\')) <> \'\')');

        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->text('resumen_ia')->nullable();
            $table->string('siguiente_paso_ia', 300)->nullable();
            $table->timestampTz('resumen_ia_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', fn (Blueprint $t) => $t->dropColumn(['resumen_ia', 'siguiente_paso_ia', 'resumen_ia_at']));
        Schema::dropIfExists('oportunidad_decisores');
    }
};
