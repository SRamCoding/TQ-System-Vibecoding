<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * users es tabla de framework (sin auditoria()). Aquí solo lo que la etapa 0 necesita; party_id y
 * unidad_organizativa_id del DBML llegan en la etapa 1, cuando existan sus tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('es_activo')->default(true);
            $table->boolean('es_sistema')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX users_un_solo_usuario_sistema ON users (es_sistema) WHERE es_sistema');
        // El correo identifica al usuario sin importar mayúsculas: Ana@ y ana@ son la misma persona.
        DB::statement('CREATE UNIQUE INDEX users_email_minusculas_unique ON users (lower(email))');

        // Usuario de sistema: autor de lo que hacen seeders, jobs y comandos. No inicia sesión.
        DB::table('users')->insert([
            'name' => 'Sistema',
            'email' => 'sistema@techquk.invalid',
            'password' => Hash::make(Str::random(64)),
            'es_activo' => true,
            'es_sistema' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('es_sistema', true)->delete();
        DB::statement('DROP INDEX IF EXISTS users_email_minusculas_unique');
        DB::statement('DROP INDEX IF EXISTS users_un_solo_usuario_sistema');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['es_activo', 'es_sistema']);
        });
    }
};
