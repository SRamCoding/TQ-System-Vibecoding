<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t1-licitaciones v2): la bandeja de convocatorias que llegan del SEACE por los datos abiertos del OECE
 * (estándar OCDS). Solo entran las que coinciden con las palabras clave de Techquk; desde aquí se registran como
 * licitación o se descartan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocatorias_seace', function (Blueprint $table): void {
            $table->id();
            $table->string('ocid', 120)->unique();
            $table->string('nomenclatura', 120);
            $table->string('entidad', 250);
            $table->string('ruc_entidad', 11)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('objeto', 600);
            $table->string('tipo', 30);
            $table->decimal('valor_referencial', 16, 2)->nullable();
            $table->char('moneda', 3)->default('PEN');
            $table->date('publicada')->nullable();
            $table->date('presentacion')->nullable();
            $table->date('buena_pro')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('coincide', 200);
            $table->string('estado', 20)->default('nueva')->index(); // nueva | registrada | descartada
            $table->string('motivo_descarte', 200)->nullable();
            $table->foreignId('licitacion_id')->nullable()->constrained('licitaciones');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatorias_seace');
    }
};
