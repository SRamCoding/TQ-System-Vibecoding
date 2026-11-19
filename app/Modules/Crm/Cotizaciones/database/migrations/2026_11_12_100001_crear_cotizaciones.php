<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La cotización al cliente (plan 2.22, CRM-03). Desviaciones del DBML, a propósito:
 * - Una fila por versión (D2, P9): `cotizaciones` reúne la cabecera y `cotizacion_versiones` del DBML, con `numero` +
 *   `numero_version` únicos juntos; `version` es el bloqueo optimista. Sin `version_actual`: la última es la de mayor
 *   `numero_version`.
 * - `oportunidad_id` obligatoria (D4, P1); sin `party_id`: la cuenta es la de la oportunidad.
 * - Sin `condicion_comercial_id` (plantillas diferidas, D16): las condiciones se escriben en la versión (plazo, forma
 *   de pago con su detalle, garantía, lugar de entrega).
 * - `fecha_emision`, `fecha_vencimiento`, `tipo_cambio` e `igv_porcentaje` nulos hasta emitir, cuando se congelan
 *   (D9, D10); la base exige que lleguen juntos.
 * - `motivo_cambio` del DBML pasa a `motivo_version_id` (lista de motivos, plan 1.5 D9) con su detalle.
 * - `descuento_total`, `margen_minimo`, `margen_objetivo` (D14: con qué se midió), `es_aceptada` (D7: una sola
 *   aceptada por número y por oportunidad, en la base), el respaldo de la aceptación (D5, P2) y `presupuesto_id` (D8).
 * - Sin `elaborada_por`: es `created_by`.
 * - «Una sola versión en curso por número» (D5) sale de dos reglas: la versión nueva sale solo de la última (el
 *   servicio, con la fila bloqueada) y `numero` + `numero_version` es único, así que dos a la vez chocan en la base.
 * - Líneas: `origen` (D6: catálogo, libre o presupuesto), sin `es_opcional` (P7, diferido) y `margen_porcentaje` nulo
 *   cuando el subtotal es cero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30);
            $table->smallInteger('numero_version');
            $table->foreignId('oportunidad_id')->index()->constrained('oportunidades');
            $table->foreignId('cuenta_id')->index()->constrained('cuentas');
            $table->foreignId('contacto_id')->nullable()->index()->constrained('party_contactos');
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->foreignId('presupuesto_id')->nullable()->index()->constrained('presupuestos');
            $table->smallInteger('dias_validez');
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->timestampTz('emitida_at')->nullable();
            $table->decimal('tipo_cambio', 10, 4)->nullable();
            $table->decimal('igv_porcentaje', 5, 2)->nullable();
            $table->smallInteger('plazo_entrega_dias')->nullable();
            $table->foreignId('forma_pago_id')->nullable()->index()->constrained('formas_pago');
            $table->string('forma_pago_detalle', 200)->nullable();
            $table->smallInteger('garantia_meses')->nullable();
            $table->string('lugar_entrega', 200)->nullable();
            $table->foreignId('motivo_version_id')->nullable()->index()->constrained('motivos');
            $table->string('motivo_version_detalle', 300)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento_total', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->decimal('margen_porcentaje', 6, 2)->nullable();
            $table->decimal('margen_minimo', 5, 2)->nullable();
            $table->decimal('margen_objetivo', 5, 2)->nullable();
            $table->boolean('es_aceptada')->default(false);
            $table->date('fecha_aceptacion')->nullable();
            $table->string('respaldo_tipo', 20)->nullable();
            $table->string('respaldo_referencia', 100)->nullable();
            $table->foreignId('respaldo_adjunto_id')->nullable()->index()->constrained('adjuntos');
            $table->foreignId('adjunto_pdf_id')->nullable()->index()->constrained('adjuntos');
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['numero', 'numero_version']);
        });

        DB::statement("ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_numero_check CHECK (numero ~ '^OP-[0-9]{4}-[0-9]{4,} · COT-[0-9]{2,}$' AND numero_version >= 1 AND version >= 1)");
        DB::statement('ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_condiciones_check CHECK (dias_validez BETWEEN 1 AND 365 AND (plazo_entrega_dias IS NULL OR plazo_entrega_dias >= 0) AND (garantia_meses IS NULL OR garantia_meses >= 0))');
        DB::statement('ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_montos_check CHECK (subtotal >= 0 AND descuento_total >= 0 AND igv >= 0 AND total >= 0 AND costo_total >= 0 AND (tipo_cambio IS NULL OR tipo_cambio > 0) AND (igv_porcentaje IS NULL OR igv_porcentaje BETWEEN 0 AND 100) AND (margen_minimo IS NULL OR margen_minimo BETWEEN 0 AND 100) AND (margen_objetivo IS NULL OR margen_objetivo BETWEEN 0 AND 100))');
        // D9, D10: lo que se congela al emitir llega junto.
        DB::statement('ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_emision_check CHECK ((emitida_at IS NULL) = (fecha_emision IS NULL) AND (emitida_at IS NULL) = (fecha_vencimiento IS NULL) AND (emitida_at IS NULL) = (tipo_cambio IS NULL) AND (emitida_at IS NULL) = (igv_porcentaje IS NULL) AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= fecha_emision))');
        // D5 y P2: la aceptada lleva su fecha y su respaldo, y solo una emitida se acepta.
        DB::statement("ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_aceptada_check CHECK (NOT es_aceptada OR (emitida_at IS NOT NULL AND fecha_aceptacion IS NOT NULL AND respaldo_tipo IS NOT NULL AND respaldo_referencia IS NOT NULL AND btrim(respaldo_referencia) <> ''))");
        DB::statement("ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_respaldo_check CHECK (respaldo_tipo IS NULL OR respaldo_tipo IN ('orden_compra', 'orden_servicio', 'buena_pro', 'contrato', 'correo'))");
        // D7: una sola aceptada por número y por oportunidad.
        DB::statement('CREATE UNIQUE INDEX cotizaciones_una_aceptada_por_numero ON cotizaciones (numero) WHERE es_aceptada');
        DB::statement('CREATE UNIQUE INDEX cotizaciones_una_aceptada_por_oportunidad ON cotizaciones (oportunidad_id) WHERE es_aceptada');

        Schema::create('cotizacion_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_id')->index()->constrained('cotizaciones');
            $table->string('origen', 12);
            $table->foreignId('producto_id')->nullable()->index()->constrained('productos');
            $table->string('descripcion', 500);
            $table->foreignId('unidad_medida_id')->index()->constrained('unidades_medida');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('margen_porcentaje', 6, 2)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE cotizacion_lineas ADD CONSTRAINT cotizacion_lineas_origen_check CHECK (origen IN ('catalogo', 'libre', 'presupuesto') AND (origen = 'catalogo') = (producto_id IS NOT NULL) AND btrim(descripcion) <> '')");
        DB::statement('ALTER TABLE cotizacion_lineas ADD CONSTRAINT cotizacion_lineas_montos_check CHECK (cantidad > 0 AND precio_unitario >= 0 AND costo_unitario >= 0 AND descuento_porcentaje BETWEEN 0 AND 100 AND subtotal >= 0 AND version >= 1)');
        // D8: a lo más una línea del presupuesto enlazado por versión.
        DB::statement("CREATE UNIQUE INDEX cotizacion_lineas_un_presupuesto ON cotizacion_lineas (cotizacion_id) WHERE origen = 'presupuesto'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_lineas');
        Schema::dropIfExists('cotizaciones');
    }
};
