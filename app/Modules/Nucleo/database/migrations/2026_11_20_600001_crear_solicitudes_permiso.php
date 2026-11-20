<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* MVP rápido (ficha op-solicitudes-permiso, plan 2.0b): quien no tiene el permiso de una acción lo pide con un clic. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_permiso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('permiso', 150);
            $table->string('accion', 150);
            $table->string('motivo', 300)->nullable();
            $table->string('estado', 20)->default('pendiente')->index(); // pendiente | concedida | rechazada
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users');
            $table->string('comentario', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_permiso');
    }
};
