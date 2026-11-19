<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plantillas personalizables (ADR 0022; plan 2.11, D1 a D4). No están en el DBML v2: nacen del ADR.
 * - `plantillas` es la ficha: de qué tipo (la clave que registra su módulo), su nombre y si es personal (con su dueño)
 *   o de la empresa (sin dueño). La base lo exige con un CHECK; no hay compartir con una persona (D1).
 * - Dos plantillas activas del mismo tipo y del mismo dueño (o de la empresa) no se llaman igual: «Videovigilancia» y
 *   «videovigilancia » son la misma. Una desactivada libera su nombre.
 * - `plantilla_versiones` guarda el contenido de cada versión, inmutable; la vigente es la de número mayor (D3). El
 *   contenido es del tipo y el núcleo no lo interpreta (D4): solo exige que sea un objeto y guarda su formato.
 * - De qué documento se guardó: su clave de documento y su id, sin llave (el documento es de otro módulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 80);
            $table->string('nombre', 160);
            // El nombre como lo compara la base (minúsculas, sin tildes ni espacios repetidos): lo pide el modelo.
            $table->string('nombre_comparable', 160);
            $table->text('descripcion')->nullable();
            $table->string('alcance', 10);
            $table->foreignId('propietario_user_id')->nullable()->index()->constrained('users');
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['tipo', 'alcance']);
        });

        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_alcance_check CHECK (alcance IN ('personal', 'empresa'))");
        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_propietario_check CHECK ((alcance = 'personal') = (propietario_user_id IS NOT NULL))");
        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_tipo_check CHECK (btrim(tipo) <> '')");
        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_nombre_comparable_check CHECK (nombre_comparable = regexp_replace(lower(unaccent(btrim(nombre))), '\\s+', ' ', 'g'))");
        DB::statement("ALTER TABLE plantillas ADD CONSTRAINT plantillas_descripcion_check CHECK (descripcion IS NULL OR btrim(descripcion) <> '')");
        DB::statement('ALTER TABLE plantillas ADD CONSTRAINT plantillas_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX plantillas_nombre_activo_unique ON plantillas (tipo, alcance, COALESCE(propietario_user_id, 0), nombre_comparable) WHERE es_activa');

        Schema::create('plantilla_versiones', function (Blueprint $table): void {
            $table->id();
            // harness:sin-indice cubierto por plantilla_versiones_plantilla_id_numero_unique.
            $table->foreignId('plantilla_id')->constrained('plantillas');
            $table->integer('numero');
            $table->smallInteger('formato');
            $table->jsonb('contenido');
            $table->string('origen_documento', 120)->nullable();
            $table->bigInteger('origen_id')->nullable();
            $table->auditoria();

            $table->unique(['plantilla_id', 'numero']);
        });

        DB::statement('ALTER TABLE plantilla_versiones ADD CONSTRAINT plantilla_versiones_numero_check CHECK (numero >= 1)');
        DB::statement('ALTER TABLE plantilla_versiones ADD CONSTRAINT plantilla_versiones_formato_check CHECK (formato >= 1)');
        DB::statement("ALTER TABLE plantilla_versiones ADD CONSTRAINT plantilla_versiones_contenido_check CHECK (jsonb_typeof(contenido) = 'object')");
        DB::statement("ALTER TABLE plantilla_versiones ADD CONSTRAINT plantilla_versiones_origen_check CHECK ((origen_documento IS NULL) = (origen_id IS NULL) AND (origen_documento IS NULL OR btrim(origen_documento) <> ''))");
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_versiones');
        Schema::dropIfExists('plantillas');
    }
};
