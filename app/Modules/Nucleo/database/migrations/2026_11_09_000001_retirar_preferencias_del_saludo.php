<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4aa, D5: el saludo ya no se configura. Salen las preferencias del grupo Saludo (inglés, francés y categorías
 * de frases) y la tabla de categorías apagadas por persona. «Ambientación de la fecha» pasa a Apariencia: quien la
 * había apagado la sigue teniendo apagada. Migración y no limpieza a mano: las bases de desarrollo de cada copia ya
 * tienen esas filas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array RETIRADAS = ['saludo.ingles', 'saludo.frances', 'saludo.categorias_frase', 'saludo.ambientacion'];

    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO preferencias_usuario (user_id, clave, valor, created_by, created_at, updated_by, updated_at)
            SELECT p.user_id, 'apariencia.ambientacion', p.valor, p.created_by, p.created_at, p.updated_by, p.updated_at
            FROM preferencias_usuario p
            WHERE p.clave = 'saludo.ambientacion'
            ON CONFLICT (user_id, clave) DO NOTHING
            SQL);
        DB::table('preferencias_usuario')->whereIn('clave', self::RETIRADAS)->delete();

        Schema::dropIfExists('categorias_frase_usuario');
    }

    /** Vuelve la tabla vacía y la ambientación a su clave anterior; lo apagado por cada persona no se recupera. */
    public function down(): void
    {
        Schema::create('categorias_frase_usuario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('categoria_frase_id')->index()->constrained('categorias_frase');
            $table->auditoria();

            $table->unique(['user_id', 'categoria_frase_id'], 'categorias_frase_usuario_unique');
        });

        DB::table('preferencias_usuario')->where('clave', 'apariencia.ambientacion')->update(['clave' => 'saludo.ambientacion']);
    }
};
