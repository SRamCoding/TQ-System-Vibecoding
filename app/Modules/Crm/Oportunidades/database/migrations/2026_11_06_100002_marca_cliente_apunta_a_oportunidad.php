<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lo que Cuentas dejó para CRM-02 (plan 2.20, D8; plan 2.21, paso 8): la marca de cliente apunta a su oportunidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_marcas_cliente', function (Blueprint $table): void {
            // harness:sin-indice lo cubre cuenta_marcas_cliente_oportunidad_unique (único parcial de Cuentas).
            $table->foreign('oportunidad_id')->references('id')->on('oportunidades');
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_marcas_cliente', function (Blueprint $table): void {
            $table->dropForeign(['oportunidad_id']);
        });
    }
};
