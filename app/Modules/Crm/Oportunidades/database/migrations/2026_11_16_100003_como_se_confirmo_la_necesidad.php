<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.21d, D8: la compuerta pide, junto con «necesidad confirmada», **cómo** se confirmó (reunión, oficio, correo), y
 * lo cumplido queda marcado con lo que se registró. Nulo en las de antes y mientras no se confirme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->string('necesidad_detalle', 500)->nullable();
        });

        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_necesidad_detalle_check CHECK (necesidad_detalle IS NULL OR btrim(necesidad_detalle) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidades DROP CONSTRAINT IF EXISTS oportunidades_necesidad_detalle_check');

        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->dropColumn('necesidad_detalle');
        });
    }
};
