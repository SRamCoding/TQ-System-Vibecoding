<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Restricciones de exclusión que mezclan igualdad y rangos: dos periodos del mismo rol de una party no se
 * cruzan (plan 1.3, D2). btree_gist viene con PostgreSQL (contrib), igual que unaccent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
    }
};
