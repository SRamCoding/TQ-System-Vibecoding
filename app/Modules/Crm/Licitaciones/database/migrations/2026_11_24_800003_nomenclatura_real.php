<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.13: lo ya leído del OECE guardó como nomenclatura el número interno y como presentación el mismo día de
 * la publicación. La nomenclatura real es la primera palabra del texto (el `title`); la presentación queda sin fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE convocatorias_seace SET nomenclatura = split_part(texto, ' ', 1)
            WHERE es_demo = false AND nomenclatura ~ '^[0-9]+$' AND texto IS NOT NULL AND split_part(texto, ' ', 1) ~ '^[A-Za-z0-9][A-Za-z0-9./]*(-[A-Za-z0-9./]+){2,}$'");
        DB::statement('UPDATE convocatorias_seace SET presentacion = NULL WHERE es_demo = false AND presentacion IS NOT NULL AND publicada IS NOT NULL AND presentacion <= publicada');
    }

    public function down(): void {}
};
