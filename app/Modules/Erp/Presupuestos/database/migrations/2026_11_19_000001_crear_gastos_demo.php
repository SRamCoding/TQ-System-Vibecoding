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
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->foreignId('partida_control_id')->index()->constrained('partidas_control');
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
