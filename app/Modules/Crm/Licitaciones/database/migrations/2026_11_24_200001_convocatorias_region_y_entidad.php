<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 8.9, ficha t1-licitaciones): acotar por región y por entidad. Se agrega (sin reconstruir):
 * - convocatorias_seace.departamento: la región (departamento) de la entidad convocante, del dato del OECE
 *   (`address.department`; `region` es la provincia).
 * - seace_lineas.regiones (vacío = todas) y seace_lineas.entidades_excluidas ([{ruc, nombre}]).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->string('departamento', 40)->nullable()->index();
        });

        Schema::table('seace_lineas', function (Blueprint $table): void {
            $table->json('regiones')->default('[]');
            $table->json('entidades_excluidas')->default('[]');
        });

        // La demo vive en «Valle Norte», que se ubica en Lambayeque.
        DB::table('convocatorias_seace')->where('region', 'Valle Norte')->update(['departamento' => 'LAMBAYEQUE']);
        $norte = json_encode(['LAMBAYEQUE', 'PIURA', 'LA LIBERTAD', 'CAJAMARCA', 'AMAZONAS']);
        DB::table('seace_lineas')->whereIn('nombre', ['Videovigilancia', 'Redes'])->update(['regiones' => $norte]);
    }

    public function down(): void
    {
        Schema::table('seace_lineas', fn (Blueprint $t) => $t->dropColumn(['regiones', 'entidades_excluidas']));
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn('departamento'));
    }
};
