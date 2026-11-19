<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4z, D5: suma los íconos de Google Meet, Excalidraw y la pizarra a los CHECK de `aplicaciones` y
 * `aplicaciones_propias` (Teams ya estaba).
 */
return new class extends Migration
{
    private const array ICONOS_ANTES = ['word', 'excel', 'powerpoint', 'outlook', 'onedrive', 'teams', 'onenote', 'generica', 'claude', 'whatsapp', 'gmail', 'google_drive', 'linkedin'];

    private const array ICONOS = [...self::ICONOS_ANTES, 'google_meet', 'excalidraw', 'whiteboard'];

    public function up(): void
    {
        $this->iconos(self::ICONOS);
    }

    public function down(): void
    {
        $this->iconos(self::ICONOS_ANTES);
    }

    /** @param list<string> $iconos */
    private function iconos(array $iconos): void
    {
        $lista = implode(', ', array_map(fn (string $icono): string => "'{$icono}'", $iconos));

        foreach (['aplicaciones', 'aplicaciones_propias'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT {$tabla}_icono_check");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_icono_check CHECK (icono IN ({$lista}))");
        }
    }
};
