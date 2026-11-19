<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El avance del asistente de configuración inicial (plan 1.13, D3): qué pasos dio por listos el administrador. Una fila
 * por paso que se marcó alguna vez; reabrirlo la deja en falso, y el historial de cambios guarda quién y cuándo. Los
 * pasos los declara el código (`RegistroPasosConfiguracion`): aquí solo su clave. No está en el DBML: pedido del
 * desarrollador (2026-10-19, mapa 1.13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_pasos', function (Blueprint $table): void {
            $table->id();
            $table->string('paso', 80)->unique();
            $table->boolean('es_listo');
            $table->auditoria();
        });

        DB::statement("ALTER TABLE configuracion_pasos ADD CONSTRAINT configuracion_pasos_paso_check CHECK (paso ~ '^[a-z0-9_]+\\.[a-z0-9_.-]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_pasos');
    }
};
