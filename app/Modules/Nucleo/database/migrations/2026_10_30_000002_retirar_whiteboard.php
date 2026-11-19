<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Whiteboard sale del panel (pedido del desarrollador, 2026-10-30: Techquk usa otra pizarra; además, Microsoft retiró
 * Whiteboard para cuentas personales y su aplicación de escritorio para todos el 16 de octubre de 2026). Una aplicación
 * usada no se borra: se desactiva. Su programa de escritorio sale de la lista cerrada (el CHECK ya no lo acepta) y nadie
 * la abre más en el escritorio. Excalidraw queda como pizarra.
 */
return new class extends Migration
{
    private const array CON_WHITEBOARD = ['word', 'excel', 'powerpoint', 'outlook', 'onenote', 'teams', 'whiteboard', 'whatsapp', 'claude', 'zoom'];

    public function up(): void
    {
        $whiteboard = DB::table('aplicaciones')->where('codigo', 'whiteboard')->value('id');

        if ($whiteboard !== null) {
            DB::table('aplicaciones_en_escritorio')->where('aplicacion_id', $whiteboard)->delete();
            DB::table('aplicaciones')->where('id', $whiteboard)->update(['es_activo' => false, 'escritorio' => null, 'version' => DB::raw('version + 1')]);
        }

        DB::table('aplicaciones')->where('escritorio', 'whiteboard')->update(['escritorio' => null, 'version' => DB::raw('version + 1')]);
        DB::table('aplicaciones_propias')->where('escritorio', 'whiteboard')->update(['escritorio' => null, 'version' => DB::raw('version + 1')]);
        $this->programas(array_values(array_diff(self::CON_WHITEBOARD, ['whiteboard'])));
    }

    public function down(): void
    {
        $this->programas(self::CON_WHITEBOARD);
        DB::table('aplicaciones')->where('codigo', 'whiteboard')->update(['es_activo' => true, 'escritorio' => 'whiteboard']);
    }

    /** @param list<string> $programas */
    private function programas(array $programas): void
    {
        $lista = implode(', ', array_map(fn (string $programa): string => "'{$programa}'", $programas));

        foreach (['aplicaciones', 'aplicaciones_propias'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT {$tabla}_escritorio_check");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_escritorio_check CHECK (escritorio IS NULL OR escritorio IN ({$lista}))");
        }
    }
};
