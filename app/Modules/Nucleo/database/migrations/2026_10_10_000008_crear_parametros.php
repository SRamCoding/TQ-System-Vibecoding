<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M9 · Parámetros del negocio editables sin tocar el programa (DBML v2). Ajustes aprobados en el plan
 * 1.2 (D5): tipos texto|entero|decimal|booleano|fecha (el DBML dice «numero»), `version` para el bloqueo
 * optimista, descripción obligatoria (es lo que lee quien lo cambia) y CHECK de que el valor tenga la forma
 * de su tipo. Un tipo nuevo (json…) es un caso más en
 * TipoParametro y en estos CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table): void {
            $table->id();
            $table->string('grupo', 60);
            $table->string('clave', 80);
            $table->text('valor');
            $table->string('tipo', 20);
            $table->string('descripcion', 255);
            $table->boolean('es_editable')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['grupo', 'clave']);
        });

        DB::statement("ALTER TABLE parametros ADD CONSTRAINT parametros_tipo_check CHECK (tipo IN ('texto', 'entero', 'decimal', 'booleano', 'fecha'))");
        DB::statement(<<<'SQL'
            ALTER TABLE parametros ADD CONSTRAINT parametros_valor_check CHECK (
                (tipo <> 'entero' OR valor ~ '^-?[0-9]+$')
                AND (tipo <> 'decimal' OR valor ~ '^-?[0-9]+(\.[0-9]+)?$')
                AND (tipo <> 'booleano' OR valor IN ('true', 'false'))
                AND (tipo <> 'fecha' OR valor ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
            )
            SQL);
        DB::statement('ALTER TABLE parametros ADD CONSTRAINT parametros_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
