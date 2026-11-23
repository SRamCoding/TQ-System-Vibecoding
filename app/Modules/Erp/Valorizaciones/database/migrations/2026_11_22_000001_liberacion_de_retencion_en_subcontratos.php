<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t4-valorizaciones, observaciones 6, punto 12): «Liberar retención». Con el subcontrato al 100 % y su
 * conformidad, la retención acumulada se devuelve: queda por pagar al subcontratista y el subcontrato pasa a terminado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontratos', function (Blueprint $table): void {
            $table->date('conformidad_fecha')->nullable();
            $table->string('conformidad_nota', 500)->nullable();
            $table->decimal('retencion_liberada', 14, 2)->nullable();
            $table->boolean('retencion_pagada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('subcontratos', function (Blueprint $table): void {
            $table->dropColumn(['conformidad_fecha', 'conformidad_nota', 'retencion_liberada', 'retencion_pagada']);
        });
    }
};
