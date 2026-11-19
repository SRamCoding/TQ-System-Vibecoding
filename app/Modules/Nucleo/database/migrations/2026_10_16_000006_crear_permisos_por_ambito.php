<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M10 · Un rol dado a un usuario solo dentro de un ámbito, con vigencia (plan 1.4, D7).
 * - Asigna un rol (FK a `roles` de spatie), no un permiso suelto: el rol es lo que se asigna en la grilla. El DBML
 *   trae `rol` y `permiso` como texto.
 * - `hasta` es el último día vigente, en hora de Lima; vacíos, sin límite. El mismo rol en el mismo ámbito no se
 *   cruza consigo mismo en el tiempo.
 * - `ambito_type` es un alias del morph map. Hoy solo `unidad_organizativa`; `proyecto`, `contrato` y `almacen`
 *   se agregan a este CHECK y al mapa cuando nazcan. Que el `ambito_id` exista lo verifica SeguridadService: una
 *   llave polimórfica no se declara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_por_ambito', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('rol_id')->index()->constrained('roles');
            $table->string('ambito_type', 120);
            $table->bigInteger('ambito_id');
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['ambito_type', 'ambito_id']);
        });

        DB::statement("ALTER TABLE permisos_por_ambito ADD CONSTRAINT permisos_por_ambito_ambito_type_check CHECK (ambito_type IN ('unidad_organizativa'))");
        DB::statement('ALTER TABLE permisos_por_ambito ADD CONSTRAINT permisos_por_ambito_hasta_check CHECK (hasta IS NULL OR desde IS NULL OR hasta >= desde)');
        DB::statement('ALTER TABLE permisos_por_ambito ADD CONSTRAINT permisos_por_ambito_version_check CHECK (version >= 1)');
        DB::statement("ALTER TABLE permisos_por_ambito ADD CONSTRAINT permisos_por_ambito_sin_cruce EXCLUDE USING gist (user_id WITH =, rol_id WITH =, ambito_type WITH =, ambito_id WITH =, daterange(desde, hasta, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_por_ambito');
    }
};
