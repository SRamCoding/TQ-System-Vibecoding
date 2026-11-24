<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Observación 9.13: una convocatoria que coincide con dos líneas aparece en las dos (`lineas_ids`). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->json('lineas_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn('lineas_ids'));
    }
};
