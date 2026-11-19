<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Saludo personalizado y ambientación de la fecha (plan 1.4b). Tablas nuevas: no están en el DBML.
 * - `categorias_frase` y `frases_saludo`: catálogos editables (ADR 0013). Un saludo lleva `{nombre}` y no tiene
 *   categoría; una frase siempre la tiene (D1, D1a). Las condiciones (`momento`, `dia_semana`, `area`, `ocasion`)
 *   son opcionales: nula vale siempre.
 * - Inglés, francés y ambientación de cada persona: preferencias del registro desde la 1.4g (`preferencias_usuario`).
 * - `categorias_frase_usuario`: las categorías que la persona apagó (D1a).
 * - `users.cumpleanos_dia` y `cumpleanos_mes`: sin año, los dos o ninguno, y una fecha que exista (D6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_frase', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE categorias_frase ADD CONSTRAINT categorias_frase_version_check CHECK (version >= 1)');

        Schema::create('frases_saludo', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('tipo', 10); // La amplía a 20 la migración 2026_10_19_000002.
            $table->foreignId('categoria_frase_id')->nullable()->index()->constrained('categorias_frase');
            $table->string('idioma', 2);
            $table->string('texto', 300);
            $table->string('fuente', 120)->nullable();
            $table->string('momento', 10)->nullable();
            $table->smallInteger('dia_semana')->nullable();
            $table->string('area', 10)->nullable();
            $table->string('ocasion', 20)->nullable();
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_valores_check CHECK (
                tipo IN ('saludo', 'frase')
                AND idioma IN ('es', 'en', 'fr')
                AND (momento IS NULL OR momento IN ('manana', 'tarde', 'noche'))
                AND (dia_semana IS NULL OR dia_semana BETWEEN 1 AND 7)
                AND (area IS NULL OR area IN ('crm', 'erp'))
                AND (ocasion IS NULL OR ocasion IN ('anio_nuevo', 'semana_santa', 'dia_madre', 'dia_padre', 'fiestas_patrias', 'navidad', 'cumpleanos', 'aniversario'))
            )
            SQL);
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_categoria_check CHECK ((tipo = 'saludo') = (categoria_frase_id IS NULL))");
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_nombre_check CHECK (tipo <> 'saludo' OR strpos(texto, '{nombre}') > 0)");
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_texto_check CHECK (btrim(texto) <> '' AND (fuente IS NULL OR btrim(fuente) <> ''))");
        DB::statement('ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_version_check CHECK (version >= 1)');

        Schema::create('categorias_frase_usuario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('categoria_frase_id')->index()->constrained('categorias_frase');
            $table->auditoria();

            $table->unique(['user_id', 'categoria_frase_id'], 'categorias_frase_usuario_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->smallInteger('cumpleanos_dia')->nullable();
            $table->smallInteger('cumpleanos_mes')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_cumpleanos_check CHECK (
                (cumpleanos_dia IS NULL) = (cumpleanos_mes IS NULL)
                AND (cumpleanos_mes IS NULL OR (
                    cumpleanos_mes BETWEEN 1 AND 12
                    AND cumpleanos_dia BETWEEN 1 AND CASE
                        WHEN cumpleanos_mes = 2 THEN 29
                        WHEN cumpleanos_mes IN (4, 6, 9, 11) THEN 30
                        ELSE 31
                    END
                ))
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_cumpleanos_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['cumpleanos_dia', 'cumpleanos_mes']);
        });

        Schema::dropIfExists('categorias_frase_usuario');
        Schema::dropIfExists('frases_saludo');
        Schema::dropIfExists('categorias_frase');
    }
};
