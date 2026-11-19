<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Búsquedas humanas sin mayúsculas ni acentos (docs/convenciones/base-de-datos.md, «Búsquedas»).
 * Las migraciones del núcleo llevan fecha posterior a las de database/migrations: dependen de users.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};
