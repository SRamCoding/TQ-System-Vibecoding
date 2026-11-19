<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M5 · Unidades de medida (códigos del catálogo 03 de SUNAT) y CUBSO (DBML v2). CUBSO se crea sin
 * carga hasta que Licitaciones confirme que la necesita (reconciliación con el v4, D3). Plan 1.2b (E10):
 * `version` para el bloqueo optimista de la edición por pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 60);
            $table->string('abreviatura', 10);
            $table->boolean('permite_decimales')->default(false);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('cubso', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 500);
            $table->foreignId('unidad_medida_id')->nullable()->index()->constrained('unidades_medida');
            $table->string('version_catalogo', 20)->nullable();
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        foreach (['unidades_medida', 'cubso'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_version_check CHECK (version >= 1)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cubso');
        Schema::dropIfExists('unidades_medida');
    }
};
