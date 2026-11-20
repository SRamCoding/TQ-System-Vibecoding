<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** MV-049: nadie elige qué módulos ver; se borra lo que la persona había ocultado y su preferencia. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos_ocultos')->delete();
        DB::table('preferencias_usuario')->where('clave', 'navegacion.modulos_barra')->delete();
    }

    public function down(): void {}
};
