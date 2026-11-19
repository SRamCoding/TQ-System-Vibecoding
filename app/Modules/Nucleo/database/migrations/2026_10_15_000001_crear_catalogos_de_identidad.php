<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M1 · Catálogos de identidad (DBML v2): tipos de documento de identidad (catálogo 06 de SUNAT) y roles
 * de party. Plan 1.3, D2 y D3: auditoría, `version` y código único, como los catálogos de la 1.2.
 * `longitud` vacía = sin longitud fija.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento_identidad', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 3)->unique();
            $table->string('nombre', 60);
            $table->smallInteger('longitud')->nullable();
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE tipos_documento_identidad ADD CONSTRAINT tipos_documento_identidad_longitud_check CHECK (longitud BETWEEN 1 AND 20)');

        Schema::create('roles_party', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        foreach (['tipos_documento_identidad', 'roles_party'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_version_check CHECK (version >= 1)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_party');
        Schema::dropIfExists('tipos_documento_identidad');
    }
};
