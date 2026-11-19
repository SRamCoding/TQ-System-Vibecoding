<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M5 · Monedas y tipo de cambio diario (DBML v2). Ajustes aprobados en el plan 1.2 (D3, D4): auditoría,
 * código único, un TC por moneda y día, origen obligatorio y CHECKs de valores imposibles. Plan 1.2b (E10):
 * `version` para el bloqueo optimista de la edición por pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monedas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre', 40);
            $table->string('simbolo', 5);
            $table->smallInteger('decimales');
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE monedas ADD CONSTRAINT monedas_decimales_check CHECK (decimales BETWEEN 0 AND 4)');
        DB::statement('ALTER TABLE monedas ADD CONSTRAINT monedas_version_check CHECK (version >= 1)');

        Schema::create('tipos_cambio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->date('fecha');
            $table->decimal('compra', 10, 4);
            $table->decimal('venta', 10, 4);
            $table->string('origen', 30);
            $table->auditoria();

            $table->unique(['moneda_id', 'fecha']);
            $table->index('fecha');
        });

        DB::statement('ALTER TABLE tipos_cambio ADD CONSTRAINT tipos_cambio_compra_check CHECK (compra > 0)');
        DB::statement('ALTER TABLE tipos_cambio ADD CONSTRAINT tipos_cambio_venta_check CHECK (venta > 0)');
        DB::statement("ALTER TABLE tipos_cambio ADD CONSTRAINT tipos_cambio_origen_check CHECK (origen IN ('sunat', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
        Schema::dropIfExists('monedas');
    }
};
