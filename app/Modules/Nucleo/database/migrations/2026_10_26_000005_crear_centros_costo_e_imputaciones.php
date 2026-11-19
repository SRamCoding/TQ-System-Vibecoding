<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M11 · Imputación (plan 1.9). Toda línea de costo tiene exactamente un destino (regla 3) y sus tres montos.
 * Desviaciones del DBML, anotadas en el plan (D1):
 * - Sin `proyecto_id`, `partida_control_id` ni `tipo_compra_id`: sus tablas son de la etapa 2, que agrega esas columnas
 *   con sus llaves y cambia `imputaciones_destino_check` para aceptar una partida. Hoy el único destino es un centro de
 *   costo. Sin `es_posible_adicional` (va con la PC-99 y los contratos, etapa 2).
 * - `tipo_cambio` obligatorio (1 en la moneda local), para sumar en soles sin buscarlo otra vez.
 * - `motivo_id` y `comentario` los llena la etapa 2 (gasto no presupuestado en la PC-99, exceso de partida).
 * - El código de un centro de costo, en mayúsculas y sin espacios (como `ADM`), para que `adm` y `ADM` no sean dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE centros_costo ADD CONSTRAINT centros_costo_textos_check CHECK (codigo ~ '^[A-Z][A-Z0-9]*$' AND btrim(nombre) <> '')");
        DB::statement('ALTER TABLE centros_costo ADD CONSTRAINT centros_costo_version_check CHECK (version >= 1)');

        Schema::create('imputaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('origen_type', 120);
            $table->unsignedBigInteger('origen_id');
            $table->foreignId('centro_costo_id')->nullable()->index()->constrained('centros_costo');
            $table->date('fecha');
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->decimal('tipo_cambio', 10, 4);
            $table->decimal('monto_comprometido', 14, 2)->default(0);
            $table->decimal('monto_ejecutado', 14, 2)->default(0);
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->foreignId('motivo_id')->nullable()->index()->constrained('motivos');
            $table->string('comentario', 500)->nullable();
            $table->auditoria();

            $table->unique(['origen_type', 'origen_id']);
        });

        // Hoy, siempre un centro de costo; la etapa 2 lo cambia por «una partida o un centro de costo».
        DB::statement('ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_destino_check CHECK (centro_costo_id IS NOT NULL)');
        DB::statement('ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_montos_check CHECK (monto_comprometido >= 0 AND monto_ejecutado >= 0 AND monto_pagado >= 0 AND monto_ejecutado <= monto_comprometido)');
        DB::statement('ALTER TABLE imputaciones ADD CONSTRAINT imputaciones_tipo_cambio_check CHECK (tipo_cambio > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('imputaciones');
        Schema::dropIfExists('centros_costo');
    }
};
