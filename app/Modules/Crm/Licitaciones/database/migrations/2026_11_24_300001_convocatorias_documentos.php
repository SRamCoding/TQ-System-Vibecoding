<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 8.10, ficha t1-licitaciones): los enlaces a los documentos del proceso que trae el OECE (bases,
 * bases integradas, actas…): título, tipo, formato y dirección de descarga. Solo los enlaces, no los archivos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->json('documentos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn('documentos'));
    }
};
