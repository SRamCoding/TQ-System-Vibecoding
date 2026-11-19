<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M3 · Estados, transiciones, su historial y los efectos aplicados; motivos de catálogo (plan 1.5, D1-D4, D9, D10).
 * Sin triggers (prohibidos): la integridad sale de copias atadas por llaves compuestas y CHECK.
 * - `estados` y `transiciones_estado` los escribe el sincronizador desde las definiciones de cada módulo. De los
 *   estados, nombre y color se editan por pantalla (`version`). `documento_type` es el alias del morph map.
 * - `estados.color` guarda el código de un tono con nombre (D10), no un color libre: lista fija con CHECK.
 * - Una transición une dos estados del mismo documento (`documento_type` copiado en las dos llaves) y copia si su
 *   destino es de cancelación, para que la base exija motivo al anular (regla 6).
 * - El historial copia de su transición los estados, si exige motivo y la familia del motivo, y del motivo si es
 *   «Otro»: así la base impide un motivo de otra lista, «Otro» sin detalle o un salto que la transición no hace.
 *   La fila de la creación no tiene `desde` ni transición. Solo se inserta (el modelo lo hace cumplir).
 * - `efectos_aplicados` guarda lo que hizo cada efecto (`datos`) para revertir con eso; los externos quedan con
 *   `ejecutado_at` nulo hasta que el job los corre, y `reversion_ejecutada_at` nulo hasta que el job los revierte.
 *   Los jobs los envían fuera de toda transacción, con una reserva que vence (`reservado_hasta`,
 *   `reversion_reservada_hasta`): al menos una vez, nunca perdido. Uno reservado sin confirmar pudo salir: se revierte.
 * - `tipos_motivo.codigo` mide 80, no 40: es la clave de la transición (`compras.orden.anular`), como un permiso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_motivo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 120);
            $table->auditoria();
        });

        Schema::create('motivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tipo_motivo_id')->index()->constrained('tipos_motivo');
            $table->string('codigo', 40);
            $table->string('nombre', 160);
            // El nombre como lo compara la base (minúsculas, sin tildes ni espacios repetidos): lo pide el modelo.
            $table->string('nombre_comparable', 160);
            $table->boolean('es_cambio_cliente')->default(false);
            $table->boolean('es_otro')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['tipo_motivo_id', 'codigo']);
            // Dos motivos de una lista no se llaman igual: «Precio alto» y «precio  ÁLTO» son el mismo.
            $table->unique(['tipo_motivo_id', 'nombre_comparable']);
            // Destino de la llave compuesta del historial.
            $table->unique(['id', 'tipo_motivo_id', 'es_otro']);
        });

        DB::statement('CREATE UNIQUE INDEX motivos_un_otro_por_familia ON motivos (tipo_motivo_id) WHERE es_otro');
        DB::statement('ALTER TABLE motivos ADD CONSTRAINT motivos_otro_activo_check CHECK (NOT es_otro OR es_activo)');
        DB::statement("ALTER TABLE motivos ADD CONSTRAINT motivos_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE motivos ADD CONSTRAINT motivos_nombre_comparable_check CHECK (nombre_comparable = regexp_replace(lower(unaccent(btrim(nombre))), '\\s+', ' ', 'g'))");
        DB::statement('ALTER TABLE motivos ADD CONSTRAINT motivos_version_check CHECK (version >= 1)');

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('documento_type', 120);
            $table->string('codigo', 40);
            $table->string('nombre', 80);
            $table->string('color', 20);
            $table->smallInteger('orden');
            $table->boolean('es_inicial')->default(false);
            $table->boolean('es_final')->default(false);
            $table->boolean('es_editable')->default(false);
            $table->boolean('es_cancelacion')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['documento_type', 'codigo']);
            // Destinos de las llaves compuestas de transiciones e historial.
            $table->unique(['id', 'documento_type']);
            $table->unique(['id', 'documento_type', 'es_cancelacion']);
        });

        DB::statement('CREATE UNIQUE INDEX estados_un_inicial_por_documento ON estados (documento_type) WHERE es_inicial');
        DB::statement("ALTER TABLE estados ADD CONSTRAINT estados_color_check CHECK (color IN ('neutro', 'en_curso', 'por_aprobar', 'aprobado', 'alerta', 'cerrado', 'anulado'))");
        DB::statement('ALTER TABLE estados ADD CONSTRAINT estados_cancelacion_check CHECK (NOT es_cancelacion OR (es_final AND NOT es_editable))');
        DB::statement("ALTER TABLE estados ADD CONSTRAINT estados_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement('ALTER TABLE estados ADD CONSTRAINT estados_version_check CHECK (version >= 1)');

        Schema::create('transiciones_estado', function (Blueprint $table): void {
            $table->id();
            $table->string('documento_type', 120);
            $table->string('codigo', 40);
            $table->string('nombre', 80);
            $table->smallInteger('orden');
            // harness:sin-indice cubierto por transiciones_estado_desde_index (llave compuesta de abajo).
            $table->foreignId('desde_estado_id')->constrained('estados');
            // harness:sin-indice cubierto por transiciones_estado_hacia_index (llave compuesta de abajo).
            $table->foreignId('hacia_estado_id')->constrained('estados');
            $table->boolean('hacia_es_cancelacion')->default(false);
            $table->boolean('requiere_motivo')->default(false);
            $table->foreignId('tipo_motivo_id')->nullable()->index()->constrained('tipos_motivo');
            $table->boolean('requiere_aprobacion')->default(false);
            $table->string('permiso', 80)->nullable();
            $table->boolean('es_activa')->default(true);
            $table->auditoria();

            $table->unique(['documento_type', 'desde_estado_id', 'codigo'], 'transiciones_estado_codigo_unique');
            $table->index(['desde_estado_id', 'documento_type'], 'transiciones_estado_desde_index');
            $table->index(['hacia_estado_id', 'documento_type', 'hacia_es_cancelacion'], 'transiciones_estado_hacia_index');
            // Destinos de las llaves compuestas del historial.
            $table->unique(['id', 'desde_estado_id', 'hacia_estado_id', 'requiere_motivo']);
            $table->unique(['id', 'tipo_motivo_id']);
        });

        DB::statement('ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_desde_foreign_documento FOREIGN KEY (desde_estado_id, documento_type) REFERENCES estados (id, documento_type)');
        DB::statement('ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_hacia_foreign_documento FOREIGN KEY (hacia_estado_id, documento_type, hacia_es_cancelacion) REFERENCES estados (id, documento_type, es_cancelacion)');
        DB::statement('ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_cancelacion_motivo_check CHECK (NOT hacia_es_cancelacion OR requiere_motivo)');
        // Quién firma vive en las reglas de aprobación (1.7), no en un permiso por transición.
        DB::statement('ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_aprobacion_check CHECK (NOT requiere_aprobacion OR permiso IS NULL)');
        DB::statement('ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_motivo_check CHECK (requiere_motivo = (tipo_motivo_id IS NOT NULL))');
        DB::statement("ALTER TABLE transiciones_estado ADD CONSTRAINT transiciones_estado_nombre_check CHECK (btrim(nombre) <> '')");

        Schema::create('historial_estados', function (Blueprint $table): void {
            // harness:sin-auditoria es una bitácora: user_id y fecha son su auditoría; solo se inserta.
            $table->id();
            $table->string('documentable_type', 120);
            $table->bigInteger('documentable_id');
            $table->foreignId('desde_estado_id')->nullable()->index()->constrained('estados');
            // harness:sin-indice cubierto por historial_estados_hacia_index (llave compuesta de abajo).
            $table->foreignId('hacia_estado_id')->constrained('estados');
            // harness:sin-indice cubierto por historial_estados_transicion_index (llave compuesta de abajo).
            $table->foreignId('transicion_id')->nullable()->constrained('transiciones_estado');
            $table->boolean('requiere_motivo')->default(false);
            $table->foreignId('tipo_motivo_id')->nullable()->index()->constrained('tipos_motivo');
            // harness:sin-indice cubierto por historial_estados_motivo_index (llave compuesta de abajo).
            $table->foreignId('motivo_id')->nullable()->constrained('motivos');
            $table->boolean('motivo_es_otro')->default(false);
            $table->text('motivo_detalle')->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('originado_por_user_id')->nullable()->index()->constrained('users');
            $table->timestampTz('fecha')->useCurrent();

            $table->index(['documentable_type', 'documentable_id', 'id']);
            $table->index(['hacia_estado_id', 'documentable_type'], 'historial_estados_hacia_index');
            $table->index(['transicion_id', 'desde_estado_id', 'hacia_estado_id', 'requiere_motivo'], 'historial_estados_transicion_index');
            $table->index(['motivo_id', 'tipo_motivo_id', 'motivo_es_otro'], 'historial_estados_motivo_index');
            $table->index(['transicion_id', 'tipo_motivo_id']);
        });

        // El estado de destino es del mismo tipo de documento.
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_hacia_foreign_documento FOREIGN KEY (hacia_estado_id, documentable_type) REFERENCES estados (id, documento_type)');
        // Los estados y si exige motivo son los de la transición: la copia no puede mentir.
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_transicion_foreign_copia FOREIGN KEY (transicion_id, desde_estado_id, hacia_estado_id, requiere_motivo) REFERENCES transiciones_estado (id, desde_estado_id, hacia_estado_id, requiere_motivo)');
        // El motivo es de la lista de esa transición, y si es «Otro» lo dice el motivo, no quien escribe.
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_transicion_foreign_familia FOREIGN KEY (transicion_id, tipo_motivo_id) REFERENCES transiciones_estado (id, tipo_motivo_id)');
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_motivo_foreign_familia FOREIGN KEY (motivo_id, tipo_motivo_id, motivo_es_otro) REFERENCES motivos (id, tipo_motivo_id, es_otro)');
        // La fila de la creación no tiene `desde` ni transición; las demás, los dos.
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_transicion_check CHECK ((transicion_id IS NULL) = (desde_estado_id IS NULL))');
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_creacion_check CHECK (transicion_id IS NOT NULL OR NOT requiere_motivo)');
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_motivo_check CHECK (requiere_motivo = (motivo_id IS NOT NULL) AND requiere_motivo = (tipo_motivo_id IS NOT NULL))');
        DB::statement('ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_otro_check CHECK (NOT motivo_es_otro OR motivo_id IS NOT NULL)');
        DB::statement("ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_detalle_check CHECK (motivo_es_otro = (motivo_detalle IS NOT NULL) AND (motivo_detalle IS NULL OR btrim(motivo_detalle) <> ''))");
        DB::statement("ALTER TABLE historial_estados ADD CONSTRAINT historial_estados_comentario_check CHECK (comentario IS NULL OR btrim(comentario) <> '')");

        Schema::create('efectos_aplicados', function (Blueprint $table): void {
            // harness:sin-auditoria parte de la bitácora: su autor y fecha son los de su fila del historial.
            $table->id();
            $table->foreignId('historial_estado_id')->constrained('historial_estados');
            $table->string('clave', 120);
            $table->smallInteger('orden');
            $table->boolean('es_externo')->default(false);
            $table->jsonb('datos')->default('{}');
            $table->timestampTz('ejecutado_at')->nullable();
            $table->foreignId('revertido_por_historial_id')->nullable()->index()->constrained('historial_estados');
            // Un externo se envía fuera de toda transacción: el intento que lo toma lo reserva hasta esta hora; si el
            // worker muere, la reserva vence y otro intento lo retoma (al menos una vez, nunca perdido).
            $table->timestampTz('reservado_hasta')->nullable();
            // Un externo se revierte por cola, como se aplica: con su reserva y su marca de hecho.
            $table->timestampTz('reversion_reservada_hasta')->nullable();
            $table->timestampTz('reversion_ejecutada_at')->nullable();

            // Cubre la llave de `historial_estado_id`.
            $table->unique(['historial_estado_id', 'orden']);
        });

        DB::statement('ALTER TABLE efectos_aplicados ADD CONSTRAINT efectos_aplicados_interno_ejecutado_check CHECK (es_externo OR ejecutado_at IS NOT NULL)');
        DB::statement("ALTER TABLE efectos_aplicados ADD CONSTRAINT efectos_aplicados_datos_check CHECK (jsonb_typeof(datos) = 'object')");
        DB::statement('ALTER TABLE efectos_aplicados ADD CONSTRAINT efectos_aplicados_revertido_check CHECK (revertido_por_historial_id IS NULL OR revertido_por_historial_id > historial_estado_id)');
        DB::statement('ALTER TABLE efectos_aplicados ADD CONSTRAINT efectos_aplicados_reserva_check CHECK ((reservado_hasta IS NULL OR (es_externo AND ejecutado_at IS NULL)) AND (reversion_reservada_hasta IS NULL OR (es_externo AND revertido_por_historial_id IS NOT NULL AND reversion_ejecutada_at IS NULL)))');
        DB::statement('ALTER TABLE efectos_aplicados ADD CONSTRAINT efectos_aplicados_reversion_check CHECK (reversion_ejecutada_at IS NULL OR (es_externo AND revertido_por_historial_id IS NOT NULL AND (ejecutado_at IS NOT NULL OR reservado_hasta IS NOT NULL)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('efectos_aplicados');
        Schema::dropIfExists('historial_estados');
        Schema::dropIfExists('transiciones_estado');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('motivos');
        Schema::dropIfExists('tipos_motivo');
    }
};
