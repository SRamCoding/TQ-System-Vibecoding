<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M7 · Adjuntos (plan 1.8). El archivo vive en el disco privado `adjuntos`; aquí solo su ruta (nunca el binario).
 * Desviaciones del DBML, anotadas en el plan:
 * - `tipos_adjunto`: `extensiones` y `tamano_maximo_mb` nulos toman los parámetros `adjuntos.*`; `grupo_sensible`
 *   (en vez de solo marcar) dice qué permiso lo deja ver (D5). Con `version`, como los demás catálogos.
 * - `adjuntos`: `grupo_sensible` copiado del tipo al guardar (`es_sensible` sale de él); quitar deja rastro (D2:
 *   `quitado_at`, `quitado_por_user_id`) y un reemplazo apunta al que reemplazó. El mismo archivo (por su huella) no
 *   se repite entre los vigentes de un registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_adjunto', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            // Nulo: sirve para cualquier registro.
            $table->string('documento_type', 120)->nullable();
            $table->string('extensiones', 150)->nullable();
            $table->smallInteger('tamano_maximo_mb')->nullable();
            $table->boolean('es_obligatorio')->default(false);
            $table->string('grupo_sensible', 30)->nullable();
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_codigo_check CHECK (codigo ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement('ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_tamano_check CHECK (tamano_maximo_mb IS NULL OR tamano_maximo_mb BETWEEN 1 AND 1024)');
        DB::statement("ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_extensiones_check CHECK (extensiones IS NULL OR extensiones ~ '^[a-z0-9]+(,[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_grupo_check CHECK (grupo_sensible IS NULL OR grupo_sensible IN ('costos', 'margen', 'remuneracion', 'detalle_medico', 'precio_compra', 'datos_bancarios'))");
        DB::statement('ALTER TABLE tipos_adjunto ADD CONSTRAINT tipos_adjunto_version_check CHECK (version >= 1)');

        Schema::create('adjuntos', function (Blueprint $table): void {
            $table->id();
            $table->string('adjuntable_type', 120);
            $table->unsignedBigInteger('adjuntable_id');
            $table->foreignId('tipo_adjunto_id')->nullable()->index()->constrained('tipos_adjunto');
            $table->string('nombre_original', 255);
            $table->string('ruta', 500)->unique();
            $table->string('mime', 120);
            $table->bigInteger('tamano_bytes');
            $table->string('hash', 64);
            $table->string('disco', 30);
            $table->string('grupo_sensible', 30)->nullable();
            $table->foreignId('reemplaza_a_adjunto_id')->nullable()->index()->constrained('adjuntos');
            $table->timestampTz('quitado_at')->nullable();
            $table->foreignId('quitado_por_user_id')->nullable()->index()->constrained('users');
            $table->auditoria();

            $table->index(['adjuntable_type', 'adjuntable_id']);
        });

        DB::statement('ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_tamano_check CHECK (tamano_bytes > 0)');
        DB::statement("ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_hash_check CHECK (hash ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_nombre_check CHECK (btrim(nombre_original) <> '' AND btrim(ruta) <> '')");
        DB::statement('ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_quitado_check CHECK ((quitado_at IS NULL) = (quitado_por_user_id IS NULL))');
        DB::statement("ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_grupo_check CHECK (grupo_sensible IS NULL OR grupo_sensible IN ('costos', 'margen', 'remuneracion', 'detalle_medico', 'precio_compra', 'datos_bancarios'))");
        DB::statement('ALTER TABLE adjuntos ADD CONSTRAINT adjuntos_reemplazo_check CHECK (reemplaza_a_adjunto_id <> id)');
        DB::statement('CREATE UNIQUE INDEX adjuntos_un_archivo_por_registro ON adjuntos (adjuntable_type, adjuntable_id, hash) WHERE quitado_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('adjuntos');
        Schema::dropIfExists('tipos_adjunto');
    }
};
