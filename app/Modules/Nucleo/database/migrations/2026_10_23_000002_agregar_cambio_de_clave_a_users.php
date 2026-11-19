<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4h, D3 y D5. `debe_cambiar_clave`: la persona entró con una contraseña que no cumple la regla y no puede usar
 * el sistema hasta renovarla. `clave_cambiada_at`: cuándo la cambió por última vez (la contraseña nunca va a la
 * bitácora; esto sí).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('debe_cambiar_clave')->default(false);
            $table->timestampTz('clave_cambiada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['debe_cambiar_clave', 'clave_cambiada_at']);
        });
    }
};
