<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El presupuesto con análisis de precios unitarios (plan 2.9). Desviaciones del DBML, a propósito:
 * - `presupuestos.numero_version` en lugar de `version` (D11): `version` es el bloqueo optimista, como en el árbol.
 * - `presupuestos.es_vigente` (D6): la versión aprobada vigente de un proyecto; la base garantiza una sola.
 * - Sin `aprobado_por` / `aprobado_at`: quién lo trajo al proyecto y cuándo ya queda en `historial_estados`.
 * - `presupuesto_partidas.titulo_id` obligatorio (D9: toda partida cuelga de un título).
 * - Títulos y partidas amarrados a su presupuesto con llaves compuestas, como el árbol.
 * - `insumos.es_sistema` (D10: «Precio sin detallar», uno solo), `insumos.categoria_costo_hora_id` sin llave hasta
 *   que exista C5 (D1, D8) y `producto_id` único (D8: un producto tiene a lo sumo un insumo).
 * - `apu_insumos`: lo escrito (cantidad neta y desperdicio; o rendimiento, su unidad, la cuadrilla y las horas de la
 *   jornada con que se calculó) y la cantidad efectiva a 5 decimales, que es la que vale (D4).
 * - El rendimiento va en la partida (`presupuesto_partidas.rendimiento` y su unidad, D12) y cada línea de mano de obra o
 *   equipo lo usa; `apu_insumos.rendimiento_propio` marca la que lleva el suyo. La línea guarda siempre el rendimiento
 *   con que se calculó: el propio o una copia del de la partida.
 * - `version` para el bloqueo optimista en todas.
 * - `presupuestos.decimales_precio_unitario`: los decimales del P.U. (D5) como foto, tomados del parámetro al crearlo;
 *   si el parámetro cambia, un presupuesto ofertado sigue cumpliendo metrado × P.U. = parcial.
 * - Dos reglas quedan en el servicio, protegidas por el bloqueo del presupuesto: un código no se repite entre títulos
 *   y partidas, y hay una sola versión en curso (borrador u ofertado) por número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30);
            $table->smallInteger('numero_version')->default(1);
            $table->foreignId('oportunidad_id')->nullable()->index()->constrained('oportunidades');
            $table->foreignId('proyecto_id')->nullable()->index()->constrained('proyectos');
            $table->string('nombre', 200);
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->boolean('usa_apu')->default(true);
            $table->smallInteger('decimales_precio_unitario')->default(2);
            $table->decimal('gastos_generales_pct', 5, 2)->default(0);
            $table->decimal('utilidad_pct', 5, 2)->default(0);
            $table->decimal('igv_pct', 5, 2)->default(18);
            $table->decimal('costo_directo', 14, 2)->default(0);
            $table->decimal('gastos_generales', 14, 2)->default(0);
            $table->decimal('utilidad', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->boolean('es_vigente')->default(false);
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['numero', 'numero_version']);
        });

        DB::statement("ALTER TABLE presupuestos ADD CONSTRAINT presupuestos_textos_check CHECK (numero ~ '^PRE-[0-9]{4}-[0-9]{4,}$' AND btrim(nombre) <> '')");
        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT presupuestos_porcentajes_check CHECK (gastos_generales_pct BETWEEN 0 AND 100 AND utilidad_pct BETWEEN 0 AND 100 AND igv_pct BETWEEN 0 AND 100 AND decimales_precio_unitario BETWEEN 0 AND 4)');
        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT presupuestos_montos_check CHECK (costo_directo >= 0 AND gastos_generales >= 0 AND utilidad >= 0 AND igv >= 0 AND total >= 0)');
        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT presupuestos_version_check CHECK (numero_version >= 1 AND version >= 1)');
        // D6: solo una versión vigente por proyecto, y la vigente siempre tiene proyecto.
        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT presupuestos_vigente_check CHECK (NOT es_vigente OR proyecto_id IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX presupuestos_un_vigente_por_proyecto ON presupuestos (proyecto_id) WHERE es_vigente');

        Schema::create('presupuesto_titulos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presupuesto_id')->index()->constrained('presupuestos');
            $table->unsignedBigInteger('padre_id')->nullable()->index();
            $table->string('codigo', 30);
            $table->string('nombre', 200);
            $table->smallInteger('orden')->default(0);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['presupuesto_id', 'codigo']);
            $table->unique(['id', 'presupuesto_id']);
            $table->foreign(['padre_id', 'presupuesto_id'])->references(['id', 'presupuesto_id'])->on('presupuesto_titulos');
        });

        DB::statement("ALTER TABLE presupuesto_titulos ADD CONSTRAINT presupuesto_titulos_textos_check CHECK (codigo ~ '^[0-9]{2}(\\.[0-9]{2})*$' AND btrim(nombre) <> '' AND version >= 1)");

        Schema::create('presupuesto_partidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presupuesto_id')->index()->constrained('presupuestos');
            $table->unsignedBigInteger('titulo_id')->index();
            $table->string('codigo', 30);
            $table->string('descripcion', 300);
            $table->foreignId('unidad_medida_id')->index()->constrained('unidades_medida');
            $table->decimal('metrado', 12, 3);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->decimal('parcial', 14, 2)->default(0);
            $table->decimal('rendimiento', 12, 4)->nullable();
            $table->string('unidad_rendimiento', 10)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['presupuesto_id', 'codigo']);
            $table->foreign(['titulo_id', 'presupuesto_id'])->references(['id', 'presupuesto_id'])->on('presupuesto_titulos');
        });

        DB::statement("ALTER TABLE presupuesto_partidas ADD CONSTRAINT presupuesto_partidas_check CHECK (codigo ~ '^[0-9]{2}(\\.[0-9]{2})+$' AND btrim(descripcion) <> '' AND metrado >= 0 AND costo_unitario >= 0 AND parcial >= 0 AND version >= 1)");
        // D12: el rendimiento de la partida va con su unidad, o no va.
        DB::statement("ALTER TABLE presupuesto_partidas ADD CONSTRAINT presupuesto_partidas_rendimiento_check CHECK ((rendimiento IS NULL AND unidad_rendimiento IS NULL) OR (rendimiento > 0 AND unidad_rendimiento IS NOT NULL AND unidad_rendimiento IN ('hora', 'jornada')))");

        Schema::create('insumos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 200);
            $table->string('tipo', 15);
            $table->foreignId('unidad_medida_id')->index()->constrained('unidades_medida');
            $table->decimal('precio_referencia', 14, 4)->nullable();
            // El índice único sirve de índice de la llave.
            $table->foreignId('producto_id')->nullable()->unique()->constrained('productos');
            // Sin llave: `categorias_costo_hora` llega con C5 (área 4).
            $table->unsignedBigInteger('categoria_costo_hora_id')->nullable()->index();
            $table->boolean('es_sistema')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE insumos ADD CONSTRAINT insumos_tipo_check CHECK (tipo IN ('material', 'mano_obra', 'equipo', 'subcontrato'))");
        DB::statement("ALTER TABLE insumos ADD CONSTRAINT insumos_textos_check CHECK (btrim(nombre) <> '' AND codigo = upper(btrim(codigo)) AND codigo <> '' AND version >= 1)");
        DB::statement('ALTER TABLE insumos ADD CONSTRAINT insumos_precio_check CHECK (precio_referencia IS NULL OR precio_referencia >= 0)');
        // D8: el producto es solo de un material; la categoría de costo por hora, solo de la mano de obra.
        DB::statement("ALTER TABLE insumos ADD CONSTRAINT insumos_enlaces_check CHECK ((producto_id IS NULL OR tipo = 'material') AND (categoria_costo_hora_id IS NULL OR tipo = 'mano_obra'))");
        DB::statement('CREATE UNIQUE INDEX insumos_un_solo_del_sistema ON insumos (es_sistema) WHERE es_sistema');

        Schema::create('apu_insumos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('presupuesto_partida_id')->index()->constrained('presupuesto_partidas');
            $table->foreignId('insumo_id')->index()->constrained('insumos');
            $table->string('tipo_cantidad', 15);
            $table->decimal('cantidad_neta', 12, 5)->nullable();
            $table->decimal('desperdicio_pct', 5, 2)->default(0);
            $table->decimal('rendimiento', 12, 4)->nullable();
            $table->string('unidad_rendimiento', 10)->nullable();
            $table->decimal('cuadrilla', 8, 2)->nullable();
            $table->decimal('horas_jornada', 5, 2)->nullable();
            $table->boolean('rendimiento_propio')->default(false);
            $table->decimal('cantidad', 12, 5);
            $table->decimal('precio', 14, 4);
            $table->decimal('parcial', 14, 4);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['presupuesto_partida_id', 'insumo_id']);
        });

        DB::statement("ALTER TABLE apu_insumos ADD CONSTRAINT apu_insumos_tipo_check CHECK (tipo_cantidad IN ('cantidad', 'rendimiento') AND (unidad_rendimiento IS NULL OR unidad_rendimiento IN ('hora', 'jornada')))");
        // D4: con «cantidad» se escribe la neta y el desperdicio; con «rendimiento», el rendimiento, su unidad y la cuadrilla.
        DB::statement("ALTER TABLE apu_insumos ADD CONSTRAINT apu_insumos_escrito_check CHECK (
            (tipo_cantidad = 'cantidad' AND cantidad_neta IS NOT NULL AND cantidad_neta > 0 AND rendimiento IS NULL AND unidad_rendimiento IS NULL AND cuadrilla IS NULL AND horas_jornada IS NULL AND NOT rendimiento_propio)
            OR (tipo_cantidad = 'rendimiento' AND cantidad_neta IS NULL AND desperdicio_pct = 0 AND rendimiento > 0 AND unidad_rendimiento IS NOT NULL AND cuadrilla > 0
                AND ((unidad_rendimiento = 'hora' AND horas_jornada IS NULL) OR (unidad_rendimiento = 'jornada' AND horas_jornada > 0)))
        )");
        DB::statement('ALTER TABLE apu_insumos ADD CONSTRAINT apu_insumos_montos_check CHECK (desperdicio_pct BETWEEN 0 AND 100 AND cantidad > 0 AND precio >= 0 AND parcial >= 0 AND version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('apu_insumos');
        Schema::dropIfExists('insumos');
        Schema::dropIfExists('presupuesto_partidas');
        Schema::dropIfExists('presupuesto_titulos');
        Schema::dropIfExists('presupuestos');
    }
};
