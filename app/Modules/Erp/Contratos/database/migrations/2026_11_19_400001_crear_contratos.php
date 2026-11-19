<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t2-contratos): contratos (ERP-10, flujo 1) con hitos, garantías (cartas fianza), adendas y
 * penalidades. Nace en borrador al ganar la oportunidad; Administración de Contratos lo completa y lo activa. Estado de
 * contrato y de garantía como texto (atajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('numero_entidad', 80)->nullable();
            $table->foreignId('proyecto_id')->unique()->constrained('proyectos');
            $table->foreignId('oportunidad_id')->nullable()->unique()->constrained('oportunidades');
            $table->foreignId('cliente_party_id')->constrained('parties');
            $table->string('objeto', 300);
            $table->boolean('es_publico')->default(true);
            $table->decimal('monto', 14, 2);
            $table->decimal('igv_pct', 5, 2)->default(18);
            $table->date('fecha_firma')->nullable();
            $table->date('fecha_inicio');
            $table->smallInteger('plazo_dias');
            $table->string('forma_pago', 200)->nullable();
            $table->smallInteger('garantia_meses')->nullable();
            $table->decimal('penalidad_factor', 4, 2)->default(0.40);
            $table->decimal('penalidad_tope_pct', 5, 2)->default(10);
            $table->string('estado', 20)->index();
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('contrato_hitos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->date('fecha_prevista');
            $table->date('fecha_real')->nullable();
            $table->smallInteger('orden');
            $table->timestamps();
        });

        Schema::create('contrato_garantias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('banco', 120);
            $table->string('numero', 60)->nullable();
            $table->decimal('monto', 14, 2);
            $table->date('emision');
            $table->date('vencimiento');
            $table->decimal('comision', 14, 2)->nullable();
            $table->unsignedBigInteger('gasto_id')->nullable();
            $table->string('estado', 20);
            $table->timestamps();
        });

        Schema::create('contrato_adendas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->date('fecha');
            $table->string('motivo', 300);
            $table->decimal('monto_adicional', 14, 2)->default(0);
            $table->smallInteger('dias_adicionales')->default(0);
            $table->timestamps();
        });

        Schema::create('contrato_penalidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos')->cascadeOnDelete();
            $table->date('fecha');
            $table->smallInteger('dias_atraso');
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 300);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE contratos ADD CONSTRAINT contratos_estado_check CHECK (estado IN ('borrador', 'vigente', 'culminado', 'resuelto', 'anulado'))");
        DB::statement('ALTER TABLE contratos ADD CONSTRAINT contratos_montos_check CHECK (monto > 0 AND plazo_dias > 0 AND penalidad_factor > 0)');
        DB::statement("ALTER TABLE contrato_garantias ADD CONSTRAINT contrato_garantias_estado_check CHECK (estado IN ('solicitada', 'vigente', 'renovada', 'liberada', 'devuelta'))");
    }

    public function down(): void
    {
        foreach (['contrato_penalidades', 'contrato_adendas', 'contrato_garantias', 'contrato_hitos', 'contratos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
