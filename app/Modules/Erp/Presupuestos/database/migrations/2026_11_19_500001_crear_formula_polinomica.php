<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t2-formula-polinomica, plan 2.14): los monomios de la fórmula de reajuste de un presupuesto y los
 * índices unificados por mes (los oficiales se cargan cada mes). No están en el DBML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indices_unificados', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 120);
            $table->char('periodo', 7);
            $table->decimal('valor', 10, 2);
            $table->timestamps();
            $table->unique(['codigo', 'periodo']);
        });

        Schema::create('presupuesto_monomios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presupuesto_id')->index()->constrained('presupuestos')->cascadeOnDelete();
            $table->string('descripcion', 120);
            $table->string('indice_codigo', 10);
            $table->decimal('coeficiente', 5, 3);
            $table->smallInteger('orden');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE presupuesto_monomios ADD CONSTRAINT presupuesto_monomios_coef_check CHECK (coeficiente > 0 AND coeficiente <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_monomios');
        Schema::dropIfExists('indices_unificados');
    }
};
