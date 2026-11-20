<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* MVP rápido (ficha op-asistente, plan 1.14): los tickets que el asistente propone y la persona confirma. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets_ayuda', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('pantalla', 200);
            $table->string('resumen', 300);
            $table->text('conversacion')->nullable();
            $table->string('estado', 20)->default('abierto'); // abierto | en_curso | resuelto
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets_ayuda');
    }
};
