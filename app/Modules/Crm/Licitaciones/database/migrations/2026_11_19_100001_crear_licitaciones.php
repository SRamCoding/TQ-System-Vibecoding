<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t1-licitaciones): licitaciones (CRM-04). Junta `convocatorias` y `participaciones` del DBML en una
 * fila (en el MVP se participa a lo más una vez por convocatoria); `participacion_resultado` se vuelve la lista de
 * participantes con su oferta, puntaje y puesto (gane quien gane, para el histórico de la entidad).
 * - `estado` es texto con su lista cerrada (atajo: no usa el motor de estados).
 * - `cuenta_id`: la entidad o empresa que convoca es una cuenta del CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licitaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nomenclatura', 80);
            $table->foreignId('cuenta_id')->index()->constrained('cuentas');
            $table->boolean('es_publica')->default(true);
            $table->string('tipo_procedimiento', 30);
            $table->string('objeto', 500);
            $table->foreignId('linea_solucion_id')->nullable()->constrained('lineas_solucion');
            $table->decimal('valor_referencial', 14, 2)->nullable();
            $table->date('fecha_convocatoria');
            $table->string('url_bases', 500)->nullable();
            $table->string('estado', 20)->index();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users');
            $table->string('motivo_no_participa', 300)->nullable();
            $table->decimal('monto_ofertado', 14, 2)->nullable();
            $table->date('fecha_presentacion')->nullable();
            $table->date('fecha_resultado')->nullable();
            $table->foreignId('oportunidad_id')->nullable()->unique()->constrained('oportunidades');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('licitacion_hitos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licitacion_id')->index()->constrained('licitaciones')->cascadeOnDelete();
            $table->string('etapa', 80);
            $table->date('fecha');
            $table->smallInteger('orden');
            $table->boolean('es_cumplido')->default(false);
            $table->timestamps();
        });

        Schema::create('licitacion_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licitacion_id')->index()->constrained('licitaciones')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('origen', 20)->default('manual');
            $table->boolean('es_confirmado')->default(true);
            $table->boolean('es_listo')->default(false);
            $table->string('nota', 300)->nullable();
            $table->date('vence')->nullable();
            $table->smallInteger('orden');
            $table->timestamps();
        });

        Schema::create('licitacion_consultas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licitacion_id')->index()->constrained('licitaciones')->cascadeOnDelete();
            $table->text('pregunta');
            $table->text('respuesta')->nullable();
            $table->date('fecha');
            $table->timestamps();
        });

        Schema::create('licitacion_participantes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licitacion_id')->index()->constrained('licitaciones')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->boolean('es_techquk')->default(false);
            $table->decimal('oferta', 14, 2)->nullable();
            $table->decimal('puntaje', 6, 2)->nullable();
            $table->smallInteger('puesto')->nullable();
            $table->boolean('es_ganador')->default(false);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE licitaciones ADD CONSTRAINT licitaciones_estado_check CHECK (estado IN ('registrada', 'en_preparacion', 'presentada', 'buena_pro', 'perdida', 'desierta', 'no_participa'))");
        DB::statement('ALTER TABLE licitaciones ADD CONSTRAINT licitaciones_montos_check CHECK ((valor_referencial IS NULL OR valor_referencial >= 0) AND (monto_ofertado IS NULL OR monto_ofertado >= 0))');
    }

    public function down(): void
    {
        foreach (['licitacion_participantes', 'licitacion_consultas', 'licitacion_documentos', 'licitacion_hitos', 'licitaciones'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
