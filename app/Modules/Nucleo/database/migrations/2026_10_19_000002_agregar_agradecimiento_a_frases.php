<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4b, D10: un tercer tipo de frase, el agradecimiento del aniversario, que se muestra en la barra superior junto
 * al logo («Gracias a Dios por {anios} años…»). Lleva `{anios}`, no tiene categoría y es corto (cabe entre el nombre
 * y el buscador). Una frase sigue siendo la única que lleva categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        // «agradecimiento» no cabe en los 10 caracteres de antes.
        DB::statement('ALTER TABLE frases_saludo ALTER COLUMN tipo TYPE varchar(20)');
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_valores_check');
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_categoria_check');
        DB::statement(<<<'SQL'
            ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_valores_check CHECK (
                tipo IN ('saludo', 'frase', 'agradecimiento')
                AND idioma IN ('es', 'en', 'fr')
                AND (momento IS NULL OR momento IN ('manana', 'tarde', 'noche'))
                AND (dia_semana IS NULL OR dia_semana BETWEEN 1 AND 7)
                AND (area IS NULL OR area IN ('crm', 'erp'))
                AND (ocasion IS NULL OR ocasion IN ('anio_nuevo', 'semana_santa', 'dia_madre', 'dia_padre', 'fiestas_patrias', 'navidad', 'cumpleanos', 'aniversario'))
            )
            SQL);
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_categoria_check CHECK ((tipo = 'frase') = (categoria_frase_id IS NOT NULL))");
        DB::statement("ALTER TABLE frases_saludo ADD CONSTRAINT frases_saludo_agradecimiento_check CHECK (tipo <> 'agradecimiento' OR (strpos(texto, '{anios}') > 0 AND char_length(texto) <= 60))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_agradecimiento_check');
        // Sin el tipo, sus filas no caben en el CHECK anterior.
        DB::table('frases_saludo')->where('tipo', 'agradecimiento')->delete();
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_categoria_check');
        DB::statement('ALTER TABLE frases_saludo DROP CONSTRAINT frases_saludo_valores_check');
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
        DB::statement('ALTER TABLE frases_saludo ALTER COLUMN tipo TYPE varchar(10)');
    }
};
