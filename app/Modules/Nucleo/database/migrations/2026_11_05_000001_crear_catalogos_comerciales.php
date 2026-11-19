<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.20a, N3: los catálogos que pide Cuentas (plan 2.20, D4 y D5). «Orígenes comerciales», común a cuentas, leads y
 * oportunidades; «Niveles de gobierno», de una cuenta pública; «Rubros», las 21 secciones de la CIIU Rev. 4, cerrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['origenes_comerciales' => 80, 'niveles_gobierno' => 80, 'rubros' => 120] as $tabla => $largo) {
            Schema::create($tabla, function (Blueprint $table) use ($largo): void {
                $table->id();
                $table->string('codigo', 20)->unique();
                $table->string('nombre', $largo);
                $table->boolean('es_activo')->default(true);
                $table->integer('version')->default(1);
                $table->auditoria();
            });

            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_version_check CHECK (version >= 1)");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_nombre_check CHECK (btrim(nombre) <> '')");
        }

        // Las secciones de la CIIU son letras de la A a la U.
        DB::statement("ALTER TABLE rubros ADD CONSTRAINT rubros_codigo_check CHECK (codigo ~ '^[A-U]$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('rubros');
        Schema::dropIfExists('niveles_gobierno');
        Schema::dropIfExists('origenes_comerciales');
    }
};
