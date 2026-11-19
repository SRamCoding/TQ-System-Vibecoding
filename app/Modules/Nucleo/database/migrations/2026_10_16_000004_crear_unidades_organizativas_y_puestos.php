<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M10 · Organigrama (plan 1.4, D5). Nacen vacías: el organigrama real es dato pendiente.
 * - El jefe de una unidad es un puesto, no una persona (`jefe_puesto_id` en lugar de `jefe_user_id` del DBML): si
 *   cambia quien lo ocupa, la jefatura sigue sola y una vacante queda a la vista. La llave compuesta exige que el
 *   puesto sea de la misma unidad.
 * - Que una unidad no sea su propio ancestro lo verifica OrganigramaService con bloqueo de fila: un CHECK no
 *   recorre el árbol. Aquí solo se impide la forma directa (`parent_id = id`).
 * - Además del DBML: código único, nombre único entre hermanas y en su unidad, `version` (se editan en pantalla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_organizativas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->string('tipo', 30);
            // FK compuesta abajo, cuando existe `puestos`.
            $table->unsignedBigInteger('jefe_puesto_id')->nullable();
            $table->smallInteger('orden')->default(0);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE unidades_organizativas ADD CONSTRAINT unidades_organizativas_tipo_check CHECK (tipo IN ('gerencia', 'jefatura', 'coordinacion', 'area'))");
        DB::statement("ALTER TABLE unidades_organizativas ADD CONSTRAINT unidades_organizativas_textos_check CHECK (btrim(codigo) <> '' AND btrim(nombre) <> '')");
        DB::statement('ALTER TABLE unidades_organizativas ADD CONSTRAINT unidades_organizativas_parent_check CHECK (parent_id <> id)');
        DB::statement('ALTER TABLE unidades_organizativas ADD CONSTRAINT unidades_organizativas_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX unidades_organizativas_nombre_unique ON unidades_organizativas (parent_id, lower(nombre)) NULLS NOT DISTINCT');

        Schema::create('puestos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120);
            $table->foreignId('unidad_organizativa_id')->index()->constrained('unidades_organizativas');
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            // Destino de la llave compuesta de la jefatura.
            $table->unique(['id', 'unidad_organizativa_id'], 'puestos_id_unidad_unique');
        });

        DB::statement("ALTER TABLE puestos ADD CONSTRAINT puestos_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement('ALTER TABLE puestos ADD CONSTRAINT puestos_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX puestos_nombre_unique ON puestos (unidad_organizativa_id, lower(nombre))');

        DB::statement('CREATE INDEX unidades_organizativas_jefe_puesto_id_index ON unidades_organizativas (jefe_puesto_id, id)');
        DB::statement('ALTER TABLE unidades_organizativas ADD CONSTRAINT unidades_organizativas_jefe_foreign FOREIGN KEY (jefe_puesto_id, id) REFERENCES puestos (id, unidad_organizativa_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE unidades_organizativas DROP CONSTRAINT unidades_organizativas_jefe_foreign');
        Schema::dropIfExists('puestos');
        Schema::dropIfExists('unidades_organizativas');
    }
};
