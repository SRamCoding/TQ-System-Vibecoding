<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.0, D5: con el origen «Otro», la cuenta guarda cuál es (`origen_detalle`). Que vaya solo con «Otro» y con su
 * mínimo lo valida CuentasService con `CatalogosService::detalleDeOtro()`: la base no conoce el id del «Otro» del
 * catálogo del núcleo. Nulo en todas las que ya existen (ninguna tiene «Otro»: nació con esta tarea).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas', function (Blueprint $table): void {
            $table->string('origen_detalle', 150)->nullable();
        });

        // Lo que la base sí puede exigir: nulo o con texto (revisión de arquitectura, plan 2.0).
        DB::statement("ALTER TABLE cuentas ADD CONSTRAINT cuentas_origen_detalle_check CHECK (btrim(coalesce(origen_detalle, 'x')) <> '')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cuentas DROP CONSTRAINT cuentas_origen_detalle_check');

        Schema::table('cuentas', function (Blueprint $table): void {
            $table->dropColumn('origen_detalle');
        });
    }
};
