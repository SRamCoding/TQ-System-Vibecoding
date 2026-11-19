<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido, ficha t0-datos-demo (MV-003): el gasto de demostración, documento de origen provisional del costo de un
 * proyecto mientras no existan órdenes, tareo ni rendiciones. Sus montos viven en `imputaciones` (como los de una
 * línea de orden); aquí solo queda qué fue. Se va cuando lleguen las órdenes de compra (tramo 3). No está en el DBML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_demo', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('proyecto_id')->nullable()->index()->constrained('proyectos');
            $table->foreignId('partida_control_id')->nullable()->index()->constrained('partidas_control');
            // MVP rápido (t2-presupuesto-empresa): el gasto directo (sin proyecto) lleva su sede (nula = «Empresa»), su rubro y su centro de costo.
            $table->foreignId('sede_id')->nullable()->index()->constrained('party_sedes');
            $table->string('rubro', 60)->nullable();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
            $table->boolean('es_gasto_directo')->default(false)->index();
            $table->string('motivo_exceso', 300)->nullable();
            $table->string('descripcion', 200);
            $table->string('proveedor', 150);
            $table->date('fecha');
            $table->decimal('monto', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_demo');
    }
};
