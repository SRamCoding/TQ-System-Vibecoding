<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.0, D5: con el origen «Otro», la oportunidad guarda cuál es (`origen_detalle`); la hereda de su cuenta si toma
 * su origen. Lo valida OportunidadesService con `CatalogosService::detalleDeOtro()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->string('origen_detalle', 150)->nullable();
        });

        // Lo que la base sí puede exigir: nulo o con texto (revisión de arquitectura, plan 2.0).
        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_origen_detalle_check CHECK (btrim(coalesce(origen_detalle, 'x')) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidades DROP CONSTRAINT oportunidades_origen_detalle_check');

        Schema::table('oportunidades', function (Blueprint $table): void {
            $table->dropColumn('origen_detalle');
        });
    }
};
