<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El código del catálogo 03 de SUNAT con que se factura cada unidad (plan 2.9, unidad «Global»). Casi siempre es el
 * mismo código de la unidad; la excepción es GLB (global, la que usa el presupuesto), que no existe en SUNAT y se
 * factura como ZZ. Así la facturación de la etapa 3 lo traduce sin casos especiales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_medida', function (Blueprint $table): void {
            $table->string('codigo_sunat', 10)->nullable();
        });

        DB::statement('UPDATE unidades_medida SET codigo_sunat = codigo');
        DB::statement('ALTER TABLE unidades_medida ALTER COLUMN codigo_sunat SET NOT NULL');
        DB::statement("ALTER TABLE unidades_medida ADD CONSTRAINT unidades_medida_codigo_sunat_check CHECK (btrim(codigo_sunat) <> '')");
    }

    public function down(): void
    {
        Schema::table('unidades_medida', function (Blueprint $table): void {
            $table->dropColumn('codigo_sunat');
        });
    }
};
