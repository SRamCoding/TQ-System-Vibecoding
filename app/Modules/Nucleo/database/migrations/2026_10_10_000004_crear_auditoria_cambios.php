<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M4 · Bitácora campo por campo de las tablas sensibles (DBML v2). Ajustes aprobados en el plan 1.1:
 * valores en jsonb (el DBML dice text) y user_id NOT NULL (nunca hay cambio sin autor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_cambios', function (Blueprint $table): void {
            // harness:sin-auditoria es la bitácora misma: user_id y fecha son su auditoría; solo se inserta.
            $table->id();
            $table->string('auditable_type', 120);
            $table->bigInteger('auditable_id');
            $table->string('evento', 20);
            $table->foreignId('user_id')->index()->constrained('users');
            $table->jsonb('valores_anteriores')->nullable();
            $table->jsonb('valores_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agente', 255)->nullable();
            $table->timestampTz('fecha');

            $table->index(['auditable_type', 'auditable_id', 'fecha']);
            $table->index('fecha');
        });

        DB::statement("ALTER TABLE auditoria_cambios ADD CONSTRAINT auditoria_cambios_evento_check CHECK (evento IN ('creado', 'actualizado', 'eliminado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_cambios');
    }
};
