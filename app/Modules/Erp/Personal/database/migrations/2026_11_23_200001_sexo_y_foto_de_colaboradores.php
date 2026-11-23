<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Observación 6.7 (MV-067): el sexo (para el avatar por defecto) y la foto de perfil de cada colaborador. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->string('sexo', 6)->nullable(); // hombre | mujer; obligatorio en los formularios
            $table->string('foto_ruta', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->dropColumn(['sexo', 'foto_ruta']);
        });
    }
};
