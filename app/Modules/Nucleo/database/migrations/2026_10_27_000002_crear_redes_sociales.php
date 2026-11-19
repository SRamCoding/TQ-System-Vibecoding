<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Catálogo de redes sociales (plan 1.4o, D1). No está en el DBML: es pedido del desarrollador (2026-10-27).
 * `dominios` dice de qué dominios puede ser el enlace (también sus subdominios: `www.`, `pe.`); nulo, cualquiera
 * (el sitio web). `icono` es uno de IconoRed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redes_sociales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 40);
            $table->string('dominios', 200)->nullable();
            $table->string('icono', 20);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE redes_sociales ADD CONSTRAINT redes_sociales_codigo_check CHECK (codigo ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE redes_sociales ADD CONSTRAINT redes_sociales_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE redes_sociales ADD CONSTRAINT redes_sociales_dominios_check CHECK (dominios IS NULL OR dominios ~ '^[a-z0-9-]+(\\.[a-z0-9-]+)+(,[a-z0-9-]+(\\.[a-z0-9-]+)+)*$')");
        DB::statement("ALTER TABLE redes_sociales ADD CONSTRAINT redes_sociales_icono_check CHECK (icono IN ('linkedin', 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'sitio_web', 'enlace'))");
        DB::statement('ALTER TABLE redes_sociales ADD CONSTRAINT redes_sociales_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_sociales');
    }
};
