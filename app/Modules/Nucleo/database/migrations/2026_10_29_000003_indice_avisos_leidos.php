<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4y (revisión de arquitectura): la limpieza diaria busca en todas las personas los avisos leídos antes de una
 * fecha. El índice de `notificaciones` empieza por la persona y no le sirve: este, parcial, solo tiene los leídos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX notificaciones_leidas_index ON notificaciones (read_at, id) WHERE read_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX notificaciones_leidas_index');
    }
};
