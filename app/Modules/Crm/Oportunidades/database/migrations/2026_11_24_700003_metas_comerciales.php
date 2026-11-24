<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Observación 9.5 (5): la meta de cada ejecutivo por trimestre (persona, periodo, monto en soles). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas_comerciales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->smallInteger('anio');
            $table->smallInteger('trimestre');
            $table->decimal('monto', 14, 2);
            $table->auditoria();
            $table->unique(['user_id', 'anio', 'trimestre']);
        });
        DB::statement('ALTER TABLE metas_comerciales ADD CONSTRAINT metas_comerciales_check CHECK (trimestre BETWEEN 1 AND 4 AND monto >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_comerciales');
    }
};
