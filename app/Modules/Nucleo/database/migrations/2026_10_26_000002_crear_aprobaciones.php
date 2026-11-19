<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M2 · Aprobaciones (plan 1.7). Las reglas son datos (v4, «Matriz de aprobaciones»); cada pedido de aprobación abre una
 * ronda con sus firmas, calculadas en ese momento y atadas a la versión del documento (D5). Desviaciones del DBML,
 * anotadas en el plan:
 * - Un paso nombra una jefatura, no una persona ni un rol (D1): la de una unidad fija o la de la unidad del documento.
 *   Firma quien ocupa ese puesto al firmar; si está vacante, sube. Sin `puede_delegar` ni suplente (D7: después).
 * - Montos en soles, sin `moneda_id` (D3): `monto_mas_de` exclusivo (nulo = desde cero) y `monto_hasta` inclusivo
 *   (nulo = sin tope). Sin `ambito_*`, `prioridad`, `es_secuencial` ni `es_obligatorio`: las reglas se acumulan y sus
 *   firmas van siempre en orden (D2). Suma `transicion_codigo` (qué paso aprueba) y `tope_autoaprobacion` (D6).
 * - `aprobaciones` se divide en `rondas_aprobacion` (un pedido, con su versión) y `firmas_aprobacion` (sus pasos).
 *   Observar lleva motivo de catálogo y comentario (plan 1.5, D9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_aprobacion', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('documento_type', 120);
            $table->string('transicion_codigo', 40);
            $table->decimal('monto_mas_de', 14, 2)->nullable();
            $table->decimal('monto_hasta', 14, 2)->nullable();
            // Nulo: quien pidió la aprobación nunca firma su propio paso (D6).
            $table->decimal('tope_autoaprobacion', 14, 2)->nullable();
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['documento_type', 'transicion_codigo']);
        });

        DB::statement("ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_nombre_check CHECK (btrim(nombre) <> '' AND btrim(transicion_codigo) <> '')");
        DB::statement('ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_montos_check CHECK ((monto_mas_de IS NULL OR monto_mas_de >= 0) AND (monto_hasta IS NULL OR monto_hasta > COALESCE(monto_mas_de, 0)))');
        DB::statement('ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_tope_check CHECK (tope_autoaprobacion IS NULL OR tope_autoaprobacion > 0)');
        DB::statement('ALTER TABLE reglas_aprobacion ADD CONSTRAINT reglas_aprobacion_version_check CHECK (version >= 1)');

        Schema::create('pasos_regla_aprobacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('regla_aprobacion_id')->index()->constrained('reglas_aprobacion');
            $table->smallInteger('orden');
            // `unidad_fija`: la jefatura de esa unidad. `unidad_del_documento`: la jefatura de la unidad del documento.
            $table->string('firmante', 30);
            $table->foreignId('unidad_organizativa_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->auditoria();

            $table->unique(['regla_aprobacion_id', 'orden']);
        });

        DB::statement("ALTER TABLE pasos_regla_aprobacion ADD CONSTRAINT pasos_regla_aprobacion_firmante_check CHECK ((firmante = 'unidad_fija' AND unidad_organizativa_id IS NOT NULL) OR (firmante = 'unidad_del_documento' AND unidad_organizativa_id IS NULL))");
        DB::statement('ALTER TABLE pasos_regla_aprobacion ADD CONSTRAINT pasos_regla_aprobacion_orden_check CHECK (orden >= 1)');

        Schema::create('rondas_aprobacion', function (Blueprint $table): void {
            $table->id();
            $table->string('aprobable_type', 120);
            $table->unsignedBigInteger('aprobable_id');
            $table->string('transicion_codigo', 40);
            // La versión del documento que se firma (D5): si cambia, la ronda deja de valer.
            $table->integer('version_documento');
            $table->foreignId('solicitante_user_id')->index()->constrained('users');
            $table->decimal('monto_soles', 14, 2);
            // La unidad del documento al pedir: la de «su jefatura».
            $table->foreignId('unidad_organizativa_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->string('estado', 20)->default('abierta');
            $table->timestampTz('cerrada_at')->nullable();
            $table->auditoria();

            $table->index(['aprobable_type', 'aprobable_id']);
        });

        DB::statement("ALTER TABLE rondas_aprobacion ADD CONSTRAINT rondas_aprobacion_estado_check CHECK (estado IN ('abierta', 'completa', 'observada', 'anulada'))");
        DB::statement("ALTER TABLE rondas_aprobacion ADD CONSTRAINT rondas_aprobacion_cierre_check CHECK ((estado = 'abierta') = (cerrada_at IS NULL))");
        DB::statement('ALTER TABLE rondas_aprobacion ADD CONSTRAINT rondas_aprobacion_monto_check CHECK (monto_soles >= 0)');
        DB::statement('ALTER TABLE rondas_aprobacion ADD CONSTRAINT rondas_aprobacion_version_check CHECK (version_documento >= 1)');
        DB::statement("ALTER TABLE rondas_aprobacion ADD CONSTRAINT rondas_aprobacion_transicion_check CHECK (btrim(transicion_codigo) <> '')");
        // Una sola ronda abierta por documento y paso.
        DB::statement("CREATE UNIQUE INDEX rondas_aprobacion_una_abierta ON rondas_aprobacion (aprobable_type, aprobable_id, transicion_codigo) WHERE estado = 'abierta'");

        Schema::create('firmas_aprobacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ronda_aprobacion_id')->index()->constrained('rondas_aprobacion');
            $table->foreignId('regla_aprobacion_id')->index()->constrained('reglas_aprobacion');
            $table->smallInteger('orden');
            $table->string('firmante', 30);
            $table->foreignId('unidad_organizativa_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('user_id')->nullable()->index()->constrained('users');
            $table->timestampTz('firmado_at')->nullable();
            // Autoaprobación (D6): con su motivo y su revisión posterior.
            $table->boolean('es_autoaprobacion')->default(false);
            $table->text('motivo_autoaprobacion')->nullable();
            $table->foreignId('revisada_por_user_id')->nullable()->index()->constrained('users');
            $table->timestampTz('revisada_at')->nullable();
            // Observación (D4): motivo de catálogo y qué corregir.
            $table->foreignId('motivo_id')->nullable()->index()->constrained('motivos');
            $table->text('comentario')->nullable();
            $table->auditoria();

            $table->unique(['ronda_aprobacion_id', 'orden']);
        });

        DB::statement("ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_estado_check CHECK (estado IN ('pendiente', 'aprobada', 'observada', 'anulada'))");
        DB::statement("ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_firmante_check CHECK ((firmante = 'unidad_fija' AND unidad_organizativa_id IS NOT NULL) OR (firmante = 'unidad_del_documento' AND unidad_organizativa_id IS NULL))");
        DB::statement("ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_firmada_check CHECK ((estado IN ('aprobada', 'observada')) = (user_id IS NOT NULL AND firmado_at IS NOT NULL))");
        DB::statement("ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_observada_check CHECK ((estado = 'observada') = (motivo_id IS NOT NULL AND comentario IS NOT NULL AND btrim(comentario) <> ''))");
        DB::statement("ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_autoaprobacion_check CHECK (es_autoaprobacion = (motivo_autoaprobacion IS NOT NULL AND btrim(motivo_autoaprobacion) <> '') AND (NOT es_autoaprobacion OR estado IN ('aprobada', 'anulada')))");
        // Una autoaprobación la revisa otra persona.
        DB::statement('ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_revision_check CHECK ((revisada_por_user_id IS NULL) = (revisada_at IS NULL) AND (revisada_at IS NULL OR es_autoaprobacion) AND (revisada_por_user_id IS NULL OR revisada_por_user_id <> user_id))');
        DB::statement('ALTER TABLE firmas_aprobacion ADD CONSTRAINT firmas_aprobacion_orden_check CHECK (orden >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas_aprobacion');
        Schema::dropIfExists('rondas_aprobacion');
        Schema::dropIfExists('pasos_regla_aprobacion');
        Schema::dropIfExists('reglas_aprobacion');
    }
};
