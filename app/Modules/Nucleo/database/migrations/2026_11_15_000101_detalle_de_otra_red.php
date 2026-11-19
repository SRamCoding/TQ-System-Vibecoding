<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.0, D5: con la red «Otra red», la red de una persona o empresa guarda cuál es (`detalle`, «Mastodon»). Que vaya
 * solo con «Otra red» y con su mínimo lo valida RedesSocialesService con `CatalogosService::detalleDeOtro()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_redes_sociales', function (Blueprint $table): void {
            $table->string('detalle', 100)->nullable();
        });

        // Lo que la base sí puede exigir: nulo o con texto (revisión de arquitectura, plan 2.0).
        DB::statement("ALTER TABLE party_redes_sociales ADD CONSTRAINT party_redes_sociales_detalle_check CHECK (btrim(coalesce(detalle, 'x')) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE party_redes_sociales DROP CONSTRAINT party_redes_sociales_detalle_check');

        Schema::table('party_redes_sociales', function (Blueprint $table): void {
            $table->dropColumn('detalle');
        });
    }
};
