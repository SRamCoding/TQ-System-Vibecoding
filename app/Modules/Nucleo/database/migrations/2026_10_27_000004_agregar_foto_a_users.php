<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La foto de perfil de cada persona (plan 1.4o, D4 a D7). No está en el DBML: es pedido del desarrollador (2026-10-27).
 * Solo la ruta en el disco privado `adjuntos` (nunca el binario); nula, sin foto (el menú muestra las iniciales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('foto_ruta', 200)->nullable()->unique();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_foto_ruta_check CHECK (foto_ruta ~ '^fotos-perfil/[0-9]+/[0-9a-f-]{36}\\.(jpg|png|webp)$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_foto_ruta_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['foto_ruta']);
            $table->dropColumn('foto_ruta');
        });
    }
};
