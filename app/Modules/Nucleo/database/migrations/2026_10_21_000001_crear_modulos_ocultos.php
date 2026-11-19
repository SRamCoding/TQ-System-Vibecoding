<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4f, D2: los módulos que cada persona sacó de su barra lateral. Tabla nueva: no está en el DBML de agosto.
 * `clave_modulo` es la clave de su `CajaModulo`, sin FK: las cajas son código, no filas. Una clave de una caja que ya
 * no existe no hace daño (se ignora).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos_ocultos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('clave_modulo', 40);
            $table->auditoria();

            $table->unique(['user_id', 'clave_modulo'], 'modulos_ocultos_unique');
        });

        // El mismo formato y el mismo tope que exige CajaModulo.
        DB::statement("ALTER TABLE modulos_ocultos ADD CONSTRAINT modulos_ocultos_clave_check CHECK (clave_modulo ~ '^[a-z][a-z0-9-]{0,39}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos_ocultos');
    }
};
