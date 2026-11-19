<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La foto de referencia de la oportunidad (plan 2.21d, D2): una por oportunidad, en el disco privado de adjuntos
 * (`FotoOportunidadService`), como la de Proyectos (2.61). Nula mientras no tenga: la portada dibuja la de su línea de
 * solución (D1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->string('foto_ruta', 255)->nullable();
        });

        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_foto_ruta_check CHECK (foto_ruta IS NULL OR btrim(foto_ruta) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidades DROP CONSTRAINT IF EXISTS oportunidades_foto_ruta_check');

        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->dropColumn('foto_ruta');
        });
    }
};
