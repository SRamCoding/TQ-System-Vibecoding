<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Observación 9.10: el comprobante (archivo opcional) de cada gasto de una campaña. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campania_gastos', function (Blueprint $table): void {
            $table->string('comprobante_ruta', 300)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('campania_gastos', fn (Blueprint $t) => $t->dropColumn(['comprobante_ruta', 'registrado_por']));
    }
};
