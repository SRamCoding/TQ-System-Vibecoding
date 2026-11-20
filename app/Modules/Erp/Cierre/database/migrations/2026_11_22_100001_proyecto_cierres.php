<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP rápido (t9-cierre): lo que queda congelado al cerrar un proyecto, y lo aprendido. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_cierres', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('proyecto_id')->unique()->constrained('proyectos');
            $t->date('fecha');
            $t->decimal('vendido', 14, 2)->nullable();
            $t->decimal('facturado', 14, 2)->default(0);
            $t->decimal('presupuestado', 14, 2)->default(0);
            $t->decimal('costo', 14, 2)->default(0);
            $t->decimal('margen_real', 14, 2)->default(0);
            $t->decimal('margen_pct', 6, 1)->nullable();
            $t->json('partidas');
            $t->text('lecciones')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_cierres');
    }
};
