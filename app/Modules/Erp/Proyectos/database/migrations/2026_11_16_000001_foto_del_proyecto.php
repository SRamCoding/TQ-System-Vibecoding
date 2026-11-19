<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La foto de referencia del proyecto (plan 2.61, D1): una por proyecto, en el disco privado de adjuntos
 * (`FotoProyectoService`). Nula mientras no tenga: la portada dibuja el genérico (D12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->string('foto_ruta', 255)->nullable();
        });

        DB::statement("ALTER TABLE proyectos ADD CONSTRAINT proyectos_foto_ruta_check CHECK (foto_ruta IS NULL OR btrim(foto_ruta) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE proyectos DROP CONSTRAINT IF EXISTS proyectos_foto_ruta_check');

        Schema::table('proyectos', function (Blueprint $table): void {
            $table->dropColumn('foto_ruta');
        });
    }
};
