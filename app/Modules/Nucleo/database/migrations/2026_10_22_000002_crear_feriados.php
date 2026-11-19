<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4d, D2: catálogo editable de feriados y días no laborables, para saber qué días trabaja Techquk
 * (CalendarioLaboral). Tabla nueva: no está en el DBML de agosto. Una fila por fecha concreta: el código del catálogo es
 * la fecha (AAAA-MM-DD), porque Jueves y Viernes Santo cambian cada año y el gobierno decreta días sueltos.
 * Un feriado nacional o un día libre de la empresa nunca se trabaja; un día no laborable, solo si se marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 80);
            $table->string('tipo', 20);
            $table->boolean('techquk_no_trabaja')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE feriados ADD CONSTRAINT feriados_codigo_check CHECK (
                codigo ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                AND to_char(to_date(codigo, 'YYYY-MM-DD'), 'YYYY-MM-DD') = codigo
            )
            SQL);
        DB::statement("ALTER TABLE feriados ADD CONSTRAINT feriados_tipo_check CHECK (tipo IN ('feriado_nacional', 'no_laborable', 'libre_empresa'))");
        DB::statement("ALTER TABLE feriados ADD CONSTRAINT feriados_no_trabaja_check CHECK (tipo = 'no_laborable' OR techquk_no_trabaja)");
        DB::statement("ALTER TABLE feriados ADD CONSTRAINT feriados_nombre_check CHECK (btrim(nombre) <> '')");
        DB::statement('ALTER TABLE feriados ADD CONSTRAINT feriados_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
