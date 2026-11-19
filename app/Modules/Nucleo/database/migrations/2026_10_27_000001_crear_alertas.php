<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M13 · Alertas (plan 1.10). El catálogo único de alertas: cada tipo lo declara su módulo en código (nombre, si es
 * obligatoria, sus textos y datos por defecto) y aquí vive lo que se edita en Configuración → Alertas. Cada hecho se
 * emite una sola vez por su clave. Desviaciones del DBML, anotadas en el plan:
 * - `alerta_tipos` sin `nombre` ni `es_obligatoria` (vienen del código, D1) y con `asunto`, `texto` (nulos = los de
 *   fábrica) y `version`: reemplaza a `plantillas_notificacion` (D5). Los destinatarios son roles en
 *   `alerta_tipo_roles`, no un texto (D2).
 * - `alertas_emitidas` es una fila por hecho, no por persona (por persona ya está `notificaciones`): sin `user_id` ni
 *   `leida_at`. La entidad es opcional: un resumen diario no es de una sola (D3).
 * - `alerta_preferencias` llega con la primera alerta que use la anticipación por persona (D6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_tipos', function (Blueprint $table): void {
            $table->id();
            // La clave con que el módulo la declara: `nucleo.cruces-sin-control`.
            $table->string('codigo', 80)->unique();
            $table->boolean('es_activa')->default(true);
            // Días antes del vencimiento, de mayor a menor: «60,30,15». Nulo: la alerta no avisa con anticipación.
            $table->string('anticipacion_dias', 40)->nullable();
            $table->string('asunto', 200)->nullable();
            $table->text('texto')->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE alerta_tipos ADD CONSTRAINT alerta_tipos_codigo_check CHECK (btrim(codigo) <> '')");
        DB::statement("ALTER TABLE alerta_tipos ADD CONSTRAINT alerta_tipos_anticipacion_check CHECK (anticipacion_dias IS NULL OR anticipacion_dias ~ '^[0-9]{1,3}(,[0-9]{1,3})*$')");
        DB::statement("ALTER TABLE alerta_tipos ADD CONSTRAINT alerta_tipos_textos_check CHECK ((asunto IS NULL OR btrim(asunto) <> '') AND (texto IS NULL OR btrim(texto) <> ''))");
        DB::statement('ALTER TABLE alerta_tipos ADD CONSTRAINT alerta_tipos_version_check CHECK (version >= 1)');

        Schema::create('alerta_tipo_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alerta_tipo_id')->index()->constrained('alerta_tipos');
            $table->foreignId('rol_id')->index()->constrained('roles');
            $table->auditoria();

            $table->unique(['alerta_tipo_id', 'rol_id']);
        });

        Schema::create('alertas_emitidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alerta_tipo_id')->index()->constrained('alerta_tipos');
            // IDEMPOTENCIA: tipo + hecho (entidad, umbral, fecha). Un reintento no la emite otra vez.
            $table->string('clave', 200)->unique();
            $table->string('entidad_type', 120)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->timestampTz('emitida_at')->useCurrent();
            $table->auditoria();

            $table->index(['entidad_type', 'entidad_id']);
        });

        DB::statement("ALTER TABLE alertas_emitidas ADD CONSTRAINT alertas_emitidas_clave_check CHECK (btrim(clave) <> '')");
        DB::statement('ALTER TABLE alertas_emitidas ADD CONSTRAINT alertas_emitidas_entidad_check CHECK ((entidad_type IS NULL) = (entidad_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_emitidas');
        Schema::dropIfExists('alerta_tipo_roles');
        Schema::dropIfExists('alerta_tipos');
    }
};
