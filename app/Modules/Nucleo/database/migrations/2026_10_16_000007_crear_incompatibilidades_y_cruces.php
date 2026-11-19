<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M10 · Separación de funciones (plan 1.4, D1 y D9). Reemplaza `cruces_funciones` del DBML, que comparaba roles.
 * - `incompatibilidades_permisos`: el catálogo de pares de permisos que una persona no debería juntar. El par va
 *   ordenado (`permiso_a_id < permiso_b_id`) para que no se repita al revés. Nace vacío. Un par con cruces no se
 *   borra: se desactiva (`es_activa`) y sus cruces se cierran; la historia queda.
 * - `cruces_funciones`: una persona que tiene los dos permisos de un par. Abierto mientras los tenga (`hasta` nulo);
 *   se cierra, nunca se borra. Un solo cruce abierto por persona y par. `aprobado_por_user_id` lo llenará la 1.7.
 * - `controles_cruce`: el control compensatorio de un cruce, con su vencimiento. Renovarlo marca el anterior como
 *   no actual (`es_actual`) y deja uno solo actual por cruce. El responsable no puede ser la persona del cruce: la
 *   llave compuesta copia `user_id` del cruce y un CHECK los compara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incompatibilidades_permisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permiso_a_id')->index()->constrained('permissions');
            $table->foreignId('permiso_b_id')->index()->constrained('permissions');
            $table->string('motivo', 300);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['permiso_a_id', 'permiso_b_id'], 'incompatibilidades_permisos_par_unique');
        });

        DB::statement('ALTER TABLE incompatibilidades_permisos ADD CONSTRAINT incompatibilidades_permisos_orden_check CHECK (permiso_a_id < permiso_b_id)');
        DB::statement("ALTER TABLE incompatibilidades_permisos ADD CONSTRAINT incompatibilidades_permisos_motivo_check CHECK (btrim(motivo) <> '')");
        DB::statement('ALTER TABLE incompatibilidades_permisos ADD CONSTRAINT incompatibilidades_permisos_version_check CHECK (version >= 1)');

        Schema::create('cruces_funciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('incompatibilidad_id')->index()->constrained('incompatibilidades_permisos');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->foreignId('aprobado_por_user_id')->nullable()->index()->constrained('users');
            $table->auditoria();

            // Destino de la llave compuesta de controles_cruce.
            $table->unique(['id', 'user_id'], 'cruces_funciones_id_user_unique');
        });

        DB::statement('ALTER TABLE cruces_funciones ADD CONSTRAINT cruces_funciones_hasta_check CHECK (hasta IS NULL OR hasta >= desde)');
        DB::statement('CREATE UNIQUE INDEX cruces_funciones_abierto_unique ON cruces_funciones (user_id, incompatibilidad_id) WHERE hasta IS NULL');

        Schema::create('controles_cruce', function (Blueprint $table): void {
            $table->id();
            // FK compuesta abajo: el control copia la persona de su cruce.
            $table->unsignedBigInteger('cruce_id');
            $table->unsignedBigInteger('user_id');
            $table->text('control');
            $table->foreignId('responsable_user_id')->index()->constrained('users');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('es_actual')->default(true);
            $table->auditoria();

            $table->index(['cruce_id', 'user_id']);
        });

        DB::statement('ALTER TABLE controles_cruce ADD CONSTRAINT controles_cruce_cruce_foreign FOREIGN KEY (cruce_id, user_id) REFERENCES cruces_funciones (id, user_id)');
        DB::statement("ALTER TABLE controles_cruce ADD CONSTRAINT controles_cruce_control_check CHECK (btrim(control) <> '')");
        DB::statement('ALTER TABLE controles_cruce ADD CONSTRAINT controles_cruce_responsable_check CHECK (responsable_user_id <> user_id)');
        DB::statement('CREATE UNIQUE INDEX controles_cruce_actual_unique ON controles_cruce (cruce_id) WHERE es_actual');
    }

    public function down(): void
    {
        Schema::dropIfExists('controles_cruce');
        Schema::dropIfExists('cruces_funciones');
        Schema::dropIfExists('incompatibilidades_permisos');
    }
};
