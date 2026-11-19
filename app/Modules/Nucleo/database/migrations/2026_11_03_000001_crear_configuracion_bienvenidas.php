<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Quién apagó la bienvenida de la configuración inicial (plan 1.13b, D1): «Seguir después desde Configuración» la apaga
 * para esa persona, y desde la página de Configuración inicial se vuelve a encender. Una fila por persona que la apagó
 * alguna vez; encenderla la deja en falso, y el historial de cambios guarda quién y cuándo. No está en el DBML: pedido
 * del desarrollador (2026-11-03, mapa 1.13b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_bienvenidas', function (Blueprint $table): void {
            $table->id();
            // El índice único es también el de la llave foránea.
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->boolean('es_apagada');
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_bienvenidas');
    }
};
