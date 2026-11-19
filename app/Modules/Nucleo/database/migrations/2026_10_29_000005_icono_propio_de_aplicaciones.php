<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4z, D9: el ícono que sube administración para una aplicación de Techquk. Es un adjunto de la aplicación (M7);
 * aquí, cuál es el vigente. El ícono «propio» exige su imagen; usar uno de la lista con una imagen guardada vale (la
 * imagen queda para volver a ella). Las propias de cada persona no usan «propio».
 */
return new class extends Migration
{
    private const array ICONOS_ANTES = ['word', 'excel', 'powerpoint', 'outlook', 'onedrive', 'teams', 'onenote', 'generica', 'claude', 'whatsapp', 'gmail', 'google_drive', 'linkedin', 'google_meet', 'excalidraw', 'whiteboard'];

    public function up(): void
    {
        Schema::table('aplicaciones', function (Blueprint $table): void {
            $table->foreignId('icono_adjunto_id')->nullable()->index()->constrained('adjuntos');
        });

        $lista = implode(', ', array_map(fn (string $icono): string => "'{$icono}'", [...self::ICONOS_ANTES, 'propio']));
        DB::statement('ALTER TABLE aplicaciones DROP CONSTRAINT aplicaciones_icono_check');
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_icono_check CHECK (icono IN ({$lista}))");
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_icono_propio_check CHECK (icono <> 'propio' OR icono_adjunto_id IS NOT NULL)");
    }

    public function down(): void
    {
        $lista = implode(', ', array_map(fn (string $icono): string => "'{$icono}'", self::ICONOS_ANTES));
        DB::statement('ALTER TABLE aplicaciones DROP CONSTRAINT aplicaciones_icono_propio_check');
        DB::statement('ALTER TABLE aplicaciones DROP CONSTRAINT aplicaciones_icono_check');
        DB::statement("ALTER TABLE aplicaciones ADD CONSTRAINT aplicaciones_icono_check CHECK (icono IN ({$lista}))");

        Schema::table('aplicaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('icono_adjunto_id');
        });
    }
};
