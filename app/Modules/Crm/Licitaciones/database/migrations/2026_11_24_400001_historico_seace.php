<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 8.12, ficha t1-licitaciones): el histórico del OECE. Se agrega (sin reconstruir):
 * - convocatorias_seace.es_historico: lo cargado de las descargas masivas; no entra a la bandeja, no se prioriza ni
 *   avisa: solo alimenta «Ganadores anteriores», «¿Cuánto bajar?» y «Buscar en todo el SEACE».
 * - seace_cargas: una fila por mes y año (ensayo o carga), con su estado, por dónde va (para retomar) y lo contado.
 * - Un índice de texto en español, sin importar tildes, sobre objeto, entidad y nomenclatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->boolean('es_historico')->default(false)->index();
        });

        Schema::create('seace_cargas', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('fuente', 20)->default('seace_v3');
            $table->boolean('es_ensayo')->default(false);
            $table->string('estado', 20)->default('pendiente'); // pendiente | en_curso | pausada | hecha | error
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('procesos')->default(0);
            $table->unsignedInteger('en_regiones')->default(0);
            $table->unsignedInteger('ya_estaban')->default(0);
            $table->unsignedInteger('guardados')->default(0);
            $table->unsignedInteger('posicion')->default(0);
            $table->unsignedInteger('segundos')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->unique(['anio', 'mes', 'fuente', 'es_ensayo']);
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement("CREATE OR REPLACE FUNCTION tq_sin_tildes(texto text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE AS \$\$ SELECT public.unaccent('public.unaccent', coalesce(texto, '')) \$\$");
        DB::statement("CREATE INDEX convocatorias_seace_busqueda ON convocatorias_seace USING gin (to_tsvector('spanish', tq_sin_tildes(objeto || ' ' || entidad || ' ' || nomenclatura)))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS convocatorias_seace_busqueda');
        DB::statement('DROP FUNCTION IF EXISTS tq_sin_tildes(text)');
        Schema::dropIfExists('seace_cargas');
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn('es_historico'));
    }
};
