<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M5 · Catálogos sin relaciones entre sí (DBML v2): ubigeos (códigos INEI), tipos de documento
 * (catálogo 01 de SUNAT), formas de pago, líneas de solución, tipos de penalidad y tipos de trabajo.
 * Ajustes aprobados en el plan 1.2 (D3, D4): auditoría, código único, CHECKs de signo y días.
 * Plan 1.2b: `version` para el bloqueo optimista (E10) y `ubigeos.origen` (E7), para que la actualización
 * desde el INEI no desactive los distritos agregados a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 6)->unique();
            $table->string('departamento', 60);
            $table->string('provincia', 60);
            $table->string('distrito', 60);
            $table->boolean('es_activo')->default(true);
            $table->string('origen', 10);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE ubigeos ADD CONSTRAINT ubigeos_origen_check CHECK (origen IN ('inei', 'manual'))");

        Schema::create('tipos_documento', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre', 80);
            $table->boolean('es_electronico')->default(false);
            $table->boolean('afecta_cuenta_corriente')->default(false);
            $table->smallInteger('signo');
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE tipos_documento ADD CONSTRAINT tipos_documento_signo_check CHECK (signo IN (1, -1))');

        Schema::create('formas_pago', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            $table->smallInteger('dias')->nullable();
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE formas_pago ADD CONSTRAINT formas_pago_dias_check CHECK (dias >= 0)');

        Schema::create('lineas_solucion', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('tipos_penalidad', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('tipos_trabajo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->boolean('es_facturable_default')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        foreach (['ubigeos', 'tipos_documento', 'formas_pago', 'lineas_solucion', 'tipos_penalidad', 'tipos_trabajo'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_version_check CHECK (version >= 1)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_trabajo');
        Schema::dropIfExists('tipos_penalidad');
        Schema::dropIfExists('lineas_solucion');
        Schema::dropIfExists('formas_pago');
        Schema::dropIfExists('tipos_documento');
        Schema::dropIfExists('ubigeos');
    }
};
