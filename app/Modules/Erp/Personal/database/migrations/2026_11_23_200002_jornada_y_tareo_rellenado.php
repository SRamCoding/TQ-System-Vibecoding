<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observaciones 6.9 y 6.10 (MV-069, MV-070): la jornada de cada persona (horas por día, Lu a Do) y lo que el tareo
 * necesita para rellenarse solo: el domingo, la marca «de la jornada» de las líneas propuestas, los días marcados
 * «faltó» y la partida pendiente de las líneas que todavía no la tienen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->json('jornada')->nullable(); // {"lu": 8, "ma": 8, …, "do": 0}
        });

        Schema::table('tareo_lineas', function (Blueprint $table): void {
            $table->decimal('do', 4, 1)->default(0);
            $table->boolean('de_jornada')->default(false);
            $table->json('faltas')->nullable(); // ["ma"]: días marcados «faltó»
            $table->unsignedBigInteger('partida_control_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tareo_lineas', function (Blueprint $table): void {
            $table->dropColumn(['do', 'de_jornada', 'faltas']);
        });

        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->dropColumn('jornada');
        });
    }
};
