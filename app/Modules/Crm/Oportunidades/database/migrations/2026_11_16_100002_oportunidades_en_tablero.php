<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.21d, D5: las oportunidades que cada persona puso en su tablero (hasta 5 por etapa), recordadas por persona en
 * cualquier equipo, como el «Mías» de la 2.21c. Una fila por oportunidad y persona, única en la base. El tope de 5 por
 * etapa lo revisa `TableroDeBotonera` con bloqueo: la etapa de cada una cambia al avanzar, así que no cabe en un CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades_en_tablero', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('oportunidad_id')->index()->constrained('oportunidades')->cascadeOnDelete();
            $table->auditoria();

            $table->unique(['user_id', 'oportunidad_id'], 'oportunidades_en_tablero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidades_en_tablero');
    }
};
