<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.20a, N4: el rubro de la empresa, una sección de la CIIU Rev. 4 (la que SUNAT registra por RUC). Opcional; se
 * cambia con su propia acción, `nucleo.parties.cambiar-rubro`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->foreignId('rubro_id')->nullable()->index()->constrained('rubros');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rubro_id');
        });
    }
};
