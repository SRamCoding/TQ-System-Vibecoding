<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t4-valorizaciones, flujo 10): adelantos del contrato, valorizaciones del avance con su reajuste por
 * fórmula polinómica, amortizaciones, retención y penalidad; y subcontratos con su alcance por partida, adelanto,
 * retención y valorizaciones del subcontratista (cada una aprobada pasa a ejecutado en las partidas del alcance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_adelantos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos');
            $table->string('tipo', 20); // directo | materiales
            $table->decimal('monto', 14, 2);
            $table->decimal('porcentaje', 5, 2);
            $table->date('fecha');
            $table->timestamps();
        });

        Schema::create('valorizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->index()->constrained('contratos');
            $table->smallInteger('numero');
            $table->char('periodo', 7);
            $table->char('periodo_base', 7);
            $table->decimal('avance', 14, 2)->default(0);
            $table->decimal('factor', 6, 4)->default(1);
            $table->decimal('reajuste', 14, 2)->default(0);
            $table->decimal('amortizacion_directo', 14, 2)->default(0);
            $table->decimal('amortizacion_materiales', 14, 2)->default(0);
            $table->decimal('retencion', 14, 2)->default(0);
            $table->decimal('penalidad', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);
            $table->string('estado', 20)->default('borrador'); // borrador | aprobada
            $table->timestampTz('aprobada_at')->nullable();
            $table->timestamps();
            $table->unique(['contrato_id', 'numero']);
        });

        Schema::create('valorizacion_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('valorizacion_id')->index()->constrained('valorizaciones')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('descripcion', 250);
            $table->decimal('total', 14, 2);
            $table->decimal('porcentaje', 6, 2);
            $table->decimal('monto', 14, 2);
        });

        Schema::create('subcontratos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->string('subcontratista', 200);
            $table->string('objeto', 250);
            $table->decimal('monto', 14, 2);
            $table->decimal('adelanto_pct', 5, 2)->default(0);
            $table->decimal('retencion_pct', 5, 2)->default(0);
            $table->string('estado', 20)->default('en_ejecucion');
            $table->timestamps();
        });

        Schema::create('subcontrato_alcance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subcontrato_id')->index()->constrained('subcontratos')->cascadeOnDelete();
            $table->foreignId('partida_control_id')->constrained('partidas_control');
            $table->decimal('monto', 14, 2);
        });

        Schema::create('subcontrato_valorizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subcontrato_id')->index()->constrained('subcontratos')->cascadeOnDelete();
            $table->smallInteger('numero');
            $table->char('periodo', 7);
            $table->decimal('avance_pct', 6, 2);
            $table->decimal('monto', 14, 2);
            $table->decimal('amortizacion', 14, 2);
            $table->decimal('retencion', 14, 2);
            $table->decimal('neto', 14, 2);
            $table->string('estado', 20)->default('por_aprobar'); // por_aprobar | aprobada | pagada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['subcontrato_valorizaciones', 'subcontrato_alcance', 'subcontratos', 'valorizacion_lineas', 'valorizaciones', 'contrato_adelantos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
