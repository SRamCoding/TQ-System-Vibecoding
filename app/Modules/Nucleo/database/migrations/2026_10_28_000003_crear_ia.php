<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M12 · IA (plan 1.11). El mecanismo para que la IA sugiera y una persona confirme, sin procesos activos. Desviaciones
 * del DBML, anotadas en el plan:
 * - `ia_proveedores`, `ia_modelos` e `ia_precios_modelo` son nuevas (D5, D3): el modelo de cada proceso y su precio por
 *   token son datos. La clave del proveedor nunca se guarda: solo el nombre de su variable de entorno, que no puede
 *   apuntar a otro secreto. El modelo por defecto del sistema es una marca en `ia_modelos`, no un parámetro (así la
 *   base garantiza que existe y que es uno solo).
 * - `ia_procesos` sin `nombre` (viene del código, D1) y con huella de lo autorizado, tope en dólares y modelos (D2, D3,
 *   D5); los destinos autorizados, en la tabla nueva `ia_proceso_destinos`. `envia_datos_externos` sale de sus proveedores: lo mantiene IaConfiguracionService.
 * - `ia_llamadas` es nueva (D3): una fila por llamada, salga bien o no. El tope se mide desde aquí.
 * - `ia_sugerencias`: el origen es un registro y el adjunto, opcional (D4); apunta a la llamada que la produjo; sin
 *   estado `invalida` (una respuesta inválida queda solo en `ia_llamadas`, D9).
 * - `ia_aprendizajes` llega con el primer proceso que lo use (D12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            // La implementación registrada en el contenedor: `anthropic`.
            $table->string('implementacion', 40);
            // Sale de la empresa (Claude API) o no (un modelo en un servidor propio).
            $table->boolean('es_externo')->default(true);
            // Nombre de la variable de entorno con la clave, nunca la clave. Nulo: no la necesita.
            $table->string('variable_clave', 80)->nullable();
            $table->smallInteger('llamadas_en_paralelo')->default(2);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_codigo_check CHECK (codigo ~ '^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement("ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_implementacion_check CHECK (implementacion ~ '^[a-z][a-z0-9_]*$')");
        // D5: que nadie apunte a DB_PASSWORD o APP_KEY y lo mande a un servicio externo.
        DB::statement("ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_variable_clave_check CHECK (variable_clave IS NULL OR variable_clave ~ '^IA_CLAVE_[A-Z0-9_]+$')");
        DB::statement('ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_paralelo_check CHECK (llamadas_en_paralelo BETWEEN 1 AND 50)');
        DB::statement('ALTER TABLE ia_proveedores ADD CONSTRAINT ia_proveedores_version_check CHECK (version >= 1)');

        Schema::create('ia_modelos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proveedor_id')->index()->constrained('ia_proveedores');
            // El nombre del modelo para el proveedor: es dato, no código.
            $table->string('codigo', 100);
            $table->string('nombre', 120);
            $table->integer('max_tokens_salida')->default(4096);
            $table->boolean('es_por_defecto')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['proveedor_id', 'codigo']);
        });

        DB::statement("ALTER TABLE ia_modelos ADD CONSTRAINT ia_modelos_codigo_check CHECK (btrim(codigo) <> '')");
        DB::statement("ALTER TABLE ia_modelos ADD CONSTRAINT ia_modelos_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement('ALTER TABLE ia_modelos ADD CONSTRAINT ia_modelos_max_tokens_check CHECK (max_tokens_salida BETWEEN 1 AND 1000000)');
        DB::statement('ALTER TABLE ia_modelos ADD CONSTRAINT ia_modelos_por_defecto_activo_check CHECK (NOT es_por_defecto OR es_activo)');
        DB::statement('ALTER TABLE ia_modelos ADD CONSTRAINT ia_modelos_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX ia_modelos_un_por_defecto_unique ON ia_modelos (es_por_defecto) WHERE es_por_defecto');

        Schema::create('ia_precios_modelo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('modelo_id')->index()->constrained('ia_modelos');
            $table->date('vigente_desde');
            // USD por millón de tokens; 0 en un modelo local.
            $table->decimal('usd_millon_entrada', 12, 6);
            $table->decimal('usd_millon_salida', 12, 6);
            $table->auditoria();

            $table->unique(['modelo_id', 'vigente_desde']);
            // Destino de la llave compuesta de `ia_llamadas`: el precio aplicado es del modelo llamado.
            $table->unique(['id', 'modelo_id']);
        });

        DB::statement('ALTER TABLE ia_precios_modelo ADD CONSTRAINT ia_precios_modelo_montos_check CHECK (usd_millon_entrada >= 0 AND usd_millon_salida >= 0)');

        Schema::create('ia_procesos', function (Blueprint $table): void {
            $table->id();
            // La clave con que el módulo lo declara: `licitacion_bases`.
            $table->string('codigo', 60)->unique();
            $table->boolean('esta_activo')->default(false);
            $table->boolean('envia_datos_externos')->default(true);
            $table->foreignId('autorizado_por')->nullable()->index()->constrained('users');
            $table->timestampTz('autorizado_at')->nullable();
            // D2: huella de lo que el proceso declaraba enviar cuando Gerencia lo autorizó (un prompt nuevo no la cambia).
            // Los proveedores externos autorizados están en `ia_proceso_destinos`.
            $table->string('autorizacion_huella', 64)->nullable();
            // D3: lo fija Gerencia; 0 = no llama.
            $table->decimal('tope_mensual_usd', 12, 2)->default(0);
            // D5: nulo = el modelo por defecto del sistema.
            $table->foreignId('modelo_id')->nullable()->index()->constrained('ia_modelos');
            $table->foreignId('modelo_respaldo_id')->nullable()->index()->constrained('ia_modelos');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_codigo_check CHECK (codigo ~ '^[a-z][a-z0-9_]*$')");
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_autorizacion_check CHECK ((autorizado_por IS NULL) = (autorizado_at IS NULL) AND (autorizado_por IS NULL) = (autorizacion_huella IS NULL))');
        // D2: lo que sale de la empresa, solo con autorización de Gerencia.
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_activo_autorizado_check CHECK (NOT (esta_activo AND envia_datos_externos) OR autorizado_por IS NOT NULL)');
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_tope_check CHECK (tope_mensual_usd >= 0)');
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_respaldo_check CHECK (modelo_respaldo_id IS NULL OR modelo_respaldo_id IS DISTINCT FROM modelo_id)');
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_version_check CHECK (version >= 1)');

        // Destino de la llave compuesta de `ia_proceso_destinos`: un destino solo existe mientras dura la autorización.
        DB::statement('ALTER TABLE ia_procesos ADD CONSTRAINT ia_procesos_id_huella_unique UNIQUE (id, autorizacion_huella)');

        // D2: a qué proveedores externos autorizó Gerencia que salgan los datos del proceso. Se llena al autorizar y se
        // vacía cuando la autorización cae. La llave compuesta con la huella impide que quede un destino sin autorización
        // vigente: para quitar la huella hay que borrar antes los destinos.
        Schema::create('ia_proceso_destinos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proceso_id')->index()->constrained('ia_procesos');
            $table->foreignId('proveedor_id')->index()->constrained('ia_proveedores');
            $table->string('autorizacion_huella', 64);
            $table->auditoria();

            $table->unique(['proceso_id', 'proveedor_id']);
        });

        DB::statement('ALTER TABLE ia_proceso_destinos ADD CONSTRAINT ia_proceso_destinos_autorizacion_foreign FOREIGN KEY (proceso_id, autorizacion_huella) REFERENCES ia_procesos (id, autorizacion_huella)');

        Schema::create('ia_llamadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proceso_id')->index()->constrained('ia_procesos');
            // Del morph map (D4).
            $table->string('origen_type', 120);
            $table->unsignedBigInteger('origen_id');
            $table->foreignId('adjunto_id')->nullable()->index()->constrained('adjuntos');
            // El proveedor sale del modelo.
            $table->foreignId('modelo_id')->index()->constrained('ia_modelos');
            $table->boolean('es_respaldo')->default(false);
            $table->smallInteger('intento')->default(1);
            $table->smallInteger('prompt_version');
            $table->string('resultado', 20);
            $table->integer('tokens_entrada')->default(0);
            $table->integer('tokens_salida')->default(0);
            $table->integer('duracion_ms');
            // Ajuste del 2026-10-28: sin precio vigente no hay llamada (un modelo propio lleva su precio en 0).
            $table->foreignId('precio_id')->index()->constrained('ia_precios_modelo');
            $table->decimal('costo_usd', 12, 6)->default(0);
            $table->auditoria();

            $table->index(['proceso_id', 'created_at']);
            $table->index(['origen_type', 'origen_id']);
            // Destino de la llave compuesta de `ia_sugerencias`: la sugerencia es de la llamada, su proceso y su origen.
            $table->unique(['id', 'proceso_id', 'origen_type', 'origen_id']);
        });

        DB::statement("ALTER TABLE ia_llamadas ADD CONSTRAINT ia_llamadas_origen_type_check CHECK (origen_type ~ '^[a-z][a-z0-9_]*$')");
        DB::statement('ALTER TABLE ia_llamadas ADD CONSTRAINT ia_llamadas_intento_check CHECK (intento >= 1 AND prompt_version >= 1)');
        // D3 y ajuste del 2026-10-28: `cortada` (límite de tokens) aparte de `invalida` (mal formada).
        DB::statement("ALTER TABLE ia_llamadas ADD CONSTRAINT ia_llamadas_resultado_check CHECK (resultado IN ('valida', 'invalida', 'cortada', 'error', 'tiempo_agotado', 'saturado', 'sin_credito', 'clave_invalida'))");
        DB::statement('ALTER TABLE ia_llamadas ADD CONSTRAINT ia_llamadas_medidas_check CHECK (tokens_entrada >= 0 AND tokens_salida >= 0 AND duracion_ms >= 0 AND costo_usd >= 0)');
        DB::statement('ALTER TABLE ia_llamadas ADD CONSTRAINT ia_llamadas_precio_foreign FOREIGN KEY (precio_id, modelo_id) REFERENCES ia_precios_modelo (id, modelo_id)');

        Schema::create('ia_sugerencias', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('proceso_id')->index()->constrained('ia_procesos');
            $table->foreignId('llamada_id')->unique()->constrained('ia_llamadas');
            $table->string('origen_type', 120);
            $table->unsignedBigInteger('origen_id');
            $table->foreignId('origen_adjunto_id')->nullable()->index()->constrained('adjuntos');
            // Cada campo con su valor y su evidencia (D6).
            $table->jsonb('propuesta');
            // alta | media | baja por campo.
            $table->jsonb('seguridad_por_campo');
            $table->string('explicacion', 300);
            $table->string('estado', 15)->default('por_revisar');
            $table->string('accion', 15)->nullable();
            $table->jsonb('correccion')->nullable();
            $table->foreignId('resuelta_por')->nullable()->index()->constrained('users');
            $table->timestampTz('resuelta_at')->nullable();
            $table->string('resultado_type', 120)->nullable();
            $table->unsignedBigInteger('resultado_id')->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['proceso_id', 'estado']);
            $table->index(['origen_type', 'origen_id']);
            $table->index(['resultado_type', 'resultado_id']);
        });

        DB::statement('ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_llamada_foreign FOREIGN KEY (llamada_id, proceso_id, origen_type, origen_id) REFERENCES ia_llamadas (id, proceso_id, origen_type, origen_id)');
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_numero_check CHECK (btrim(numero) <> '')");
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_explicacion_check CHECK (btrim(explicacion) <> '')");
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_estado_check CHECK (estado IN ('por_revisar', 'resuelta'))");
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_accion_check CHECK (accion IS NULL OR accion IN ('aceptada', 'corregida', 'rechazada'))");
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_resuelta_check CHECK ((estado = 'resuelta') = (accion IS NOT NULL) AND (accion IS NULL) = (resuelta_por IS NULL) AND (accion IS NULL) = (resuelta_at IS NULL))");
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_correccion_check CHECK ((accion IS NOT DISTINCT FROM 'corregida') = (correccion IS NOT NULL))");
        // Revisión de arquitectura: al aceptarla o corregirla va el registro creado con ella; rechazada o por revisar, ninguno.
        DB::statement("ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_resultado_check CHECK ((resultado_type IS NULL) = (resultado_id IS NULL) AND COALESCE(accion IN ('aceptada', 'corregida'), false) = (resultado_type IS NOT NULL))");
        DB::statement('ALTER TABLE ia_sugerencias ADD CONSTRAINT ia_sugerencias_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_sugerencias');
        Schema::dropIfExists('ia_llamadas');
        Schema::dropIfExists('ia_proceso_destinos');
        Schema::dropIfExists('ia_procesos');
        Schema::dropIfExists('ia_precios_modelo');
        Schema::dropIfExists('ia_modelos');
        Schema::dropIfExists('ia_proveedores');
    }
};
