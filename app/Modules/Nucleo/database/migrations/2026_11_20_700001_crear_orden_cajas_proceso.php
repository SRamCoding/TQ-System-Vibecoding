<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MV-035: el orden de las cajas de cada proceso, por persona. Sin fila, el orden original del sistema. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_cajas_proceso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('proceso', 40);
            $table->json('claves');
            $table->timestamps();
            $table->unique(['user_id', 'proceso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_cajas_proceso');
    }
};
