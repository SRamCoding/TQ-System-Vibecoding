<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t2-presupuesto-empresa, plan 2.13): el presupuesto de lo que gasta la empresa fuera de los
 * proyectos, por ejercicio, sede (nula = «Empresa») y rubro, con un monto por mes; y sus traspasos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos_empresa', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('anio')->unique();
            $table->string('estado', 20)->default('borrador');
            $table->timestampTz('aprobado_at')->nullable();
            $table->auditoria();
        });

        Schema::create('presupuesto_empresa_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presupuesto_empresa_id')->index()->constrained('presupuestos_empresa')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('party_sedes');
            $table->string('rubro', 60);
            $table->string('nota', 200)->nullable();
            foreach (range(1, 12) as $m) {
                $table->decimal(sprintf('m%02d', $m), 14, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['presupuesto_empresa_id', 'sede_id', 'rubro']);
        });

        Schema::create('presupuesto_empresa_traspasos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('desde_linea_id')->constrained('presupuesto_empresa_lineas');
            $table->smallInteger('desde_mes');
            $table->foreignId('hacia_linea_id')->constrained('presupuesto_empresa_lineas');
            $table->smallInteger('hacia_mes');
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 300);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE presupuestos_empresa ADD CONSTRAINT presupuestos_empresa_estado_check CHECK (estado IN ('borrador', 'aprobado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_empresa_traspasos');
        Schema::dropIfExists('presupuesto_empresa_lineas');
        Schema::dropIfExists('presupuestos_empresa');
    }
};
