<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tu orden de la cuadrícula (plan 1.4z, D10): la posición de cada aplicación en el panel de una persona, dentro de su
 * sección (las de Techquk entre sí, las propias entre sí). No está en el DBML: pedido del desarrollador (2026-10-29).
 * Sin filas, el orden de administración; una que se suma después va al final de su sección.
 *
 * Como en `aplicaciones_principales`: una fila es de una de Techquk o de una propia, nunca de las dos; la propia lleva la
 * misma persona (FK compuesta) y se borra con ella. Ni la aplicación ni la posición se repiten en su sección.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_orden', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('aplicacion_id')->nullable()->index()->constrained('aplicaciones');
            // Su FK es la compuesta de abajo (con la persona), con su índice aparte.
            $table->unsignedBigInteger('aplicacion_propia_id')->nullable();
            $table->integer('posicion');
            $table->auditoria();

            $table->unique(['user_id', 'aplicacion_id'], 'aplicaciones_orden_techquk_unique');
            $table->unique(['user_id', 'aplicacion_propia_id'], 'aplicaciones_orden_propia_unique');
            $table->index('aplicacion_propia_id', 'aplicaciones_orden_aplicacion_propia_id_index');
        });

        DB::statement('ALTER TABLE aplicaciones_orden ADD CONSTRAINT aplicaciones_orden_propia_fk FOREIGN KEY (aplicacion_propia_id, user_id) REFERENCES aplicaciones_propias (id, user_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE aplicaciones_orden ADD CONSTRAINT aplicaciones_orden_una_check CHECK (num_nonnulls(aplicacion_id, aplicacion_propia_id) = 1)');
        DB::statement('ALTER TABLE aplicaciones_orden ADD CONSTRAINT aplicaciones_orden_posicion_check CHECK (posicion >= 1)');
        // Una posición no se repite dentro de cada sección.
        DB::statement('CREATE UNIQUE INDEX aplicaciones_orden_posicion_techquk_unique ON aplicaciones_orden (user_id, posicion) WHERE aplicacion_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX aplicaciones_orden_posicion_propia_unique ON aplicaciones_orden (user_id, posicion) WHERE aplicacion_propia_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_orden');
    }
};
