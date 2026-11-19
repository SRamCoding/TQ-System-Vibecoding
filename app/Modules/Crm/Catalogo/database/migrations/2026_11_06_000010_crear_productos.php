<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Lo mínimo de `productos` (plan 2.60, D1): el catálogo único que leen presupuesto, compras, almacén y cotizaciones por
 * C4. Desviaciones del DBML, a propósito:
 * - Sin `marca_id`, `familia_producto_id`, `linea_solucion_id`, `modelo`, `parte_fabricante` ni `stock_minimo`: todas
 *   opcionales, llegan con la tarea completa del catálogo (área 4) sin romper nada; tampoco sus tablas ni los precios.
 * - `codigo` único en la base (regla 5), aunque el DBML no lo marque, y siempre en mayúsculas (D6).
 * - `version` para el bloqueo optimista de la edición que llegará.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('descripcion', 500);
            $table->string('descripcion_corta', 150)->nullable();
            $table->foreignId('unidad_medida_id')->index()->constrained('unidades_medida');
            $table->boolean('requiere_serie')->default(false);
            $table->boolean('es_servicio')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_textos_check CHECK (btrim(codigo) <> '' AND btrim(descripcion) <> '' AND (descripcion_corta IS NULL OR btrim(descripcion_corta) <> ''))");
        // D6: el código va en mayúsculas y sin espacios en los extremos: «cam-d4m» y «CAM-D4M» son el mismo.
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_codigo_mayusculas_check CHECK (codigo = upper(btrim(codigo)))');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
