<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las aplicaciones web de Techquk del panel de aplicaciones (plan 1.4p, D5 a D7): las pone administración para todos.
 * No está en el DBML: es pedido del desarrollador (2026-10-27). `orden` es el de la cuadrícula y, sin uso todavía, el
 * del muelle (D5). Solo enlaces `https://` (paso 3). `icono` es uno de IconoAplicacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 40);
            $table->string('enlace', 300);
            $table->string('icono', 20);
            $table->smallInteger('orden')->default(0);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_codigo_check CHECK (codigo ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_enlace_check CHECK (enlace ~ '^https://[^\\s/]+\\.[^\\s]+$')");
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_icono_check CHECK (icono IN ('word', 'excel', 'powerpoint', 'outlook', 'onedrive', 'teams', 'onenote', 'generica'))");
        DB::statement('ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_orden_check CHECK (orden >= 0)');
        DB::statement('ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones');
    }
};
