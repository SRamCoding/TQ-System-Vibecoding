<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Lo de cada persona en el panel de aplicaciones (plan 1.4p, D5 y D7): sus aplicaciones propias, las de Techquk que
 * ocultó y cuántas veces abrió cada una desde el panel. No está en el DBML: pedido del desarrollador (2026-10-27).
 *
 * Un uso es de una aplicación de Techquk o de una propia, nunca de las dos. El de una propia lleva la misma persona que
 * la propia (FK compuesta) y se borra con ella; el de una de Techquk la protege del borrado: se desactiva (ADR 0013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_propias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('nombre', 40);
            $table->string('enlace', 300);
            $table->string('icono', 20)->default('generica');
            $table->integer('version')->default(1);
            $table->auditoria();

            // Destino de la FK compuesta de usos_aplicacion.
            $table->unique(['id', 'user_id'], 'aplicaciones_propias_id_user_unique');
        });

        // Dos propias con el mismo nombre no se distinguirían en la cuadrícula.
        DB::statement('CREATE UNIQUE INDEX aplicaciones_propias_nombre_unique ON aplicaciones_propias (user_id, lower(btrim(nombre)))');
        DB::statement("ALTER TABLE aplicaciones_propias ADD CONSTRAINT aplicaciones_propias_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE aplicaciones_propias ADD CONSTRAINT aplicaciones_propias_enlace_check CHECK (enlace ~ '^https://[^\\s/]+\\.[^\\s]+$')");
        DB::statement("ALTER TABLE aplicaciones_propias ADD CONSTRAINT aplicaciones_propias_icono_check CHECK (icono IN ('word', 'excel', 'powerpoint', 'outlook', 'onedrive', 'teams', 'onenote', 'generica'))");
        DB::statement('ALTER TABLE aplicaciones_propias ADD CONSTRAINT aplicaciones_propias_version_check CHECK (version >= 1)');

        Schema::create('aplicaciones_ocultas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('aplicacion_id')->index()->constrained('aplicaciones');
            $table->auditoria();

            $table->unique(['user_id', 'aplicacion_id'], 'aplicaciones_ocultas_unique');
        });

        Schema::create('usos_aplicacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('aplicacion_id')->nullable()->index()->constrained('aplicaciones');
            // Su FK es la compuesta de abajo (con la persona), con su índice aparte.
            $table->unsignedBigInteger('aplicacion_propia_id')->nullable();
            $table->integer('usos');
            $table->timestampTz('ultimo_uso_at');
            $table->auditoria();

            // Los usa el ON CONFLICT de AplicacionesService::registrarUso.
            $table->unique(['user_id', 'aplicacion_id'], 'usos_aplicacion_techquk_unique');
            $table->unique(['user_id', 'aplicacion_propia_id'], 'usos_aplicacion_propia_unique');
            $table->index('aplicacion_propia_id', 'usos_aplicacion_aplicacion_propia_id_index');
        });

        DB::statement('ALTER TABLE usos_aplicacion ADD CONSTRAINT usos_aplicacion_propia_fk FOREIGN KEY (aplicacion_propia_id, user_id) REFERENCES aplicaciones_propias (id, user_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE usos_aplicacion ADD CONSTRAINT usos_aplicacion_una_check CHECK (num_nonnulls(aplicacion_id, aplicacion_propia_id) = 1)');
        DB::statement('ALTER TABLE usos_aplicacion ADD CONSTRAINT usos_aplicacion_usos_check CHECK (usos >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('usos_aplicacion');
        Schema::dropIfExists('aplicaciones_ocultas');
        Schema::dropIfExists('aplicaciones_propias');
    }
};
