<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 8.3): lo que la persona deja puesto en la interfaz y no es una preferencia de «Mis
 * preferencias» (por ejemplo, qué grupos de filtros dejó abiertos). Clave libre, valor JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_interfaz_usuario', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('clave', 80);
            $table->json('valor');
            $table->timestampTz('updated_at')->nullable();
            $table->primary(['user_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_interfaz_usuario');
    }
};
