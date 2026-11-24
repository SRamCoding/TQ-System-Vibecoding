<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Observación 9.5 (3): los motivos de pérdida se llaman como los dice Sebastián («Sin presupuesto», «Técnica»). */
return new class extends Migration
{
    public function up(): void
    {
        $tipo = DB::table('tipos_motivo')->where('codigo', 'crm.oportunidades.oportunidad.perder')->value('id');

        if ($tipo === null) {
            return;
        }

        foreach (['presupuesto_cancelado' => 'Sin presupuesto', 'especificaciones' => 'Técnica'] as $codigo => $nombre) {
            DB::table('motivos')->where('tipo_motivo_id', $tipo)->where('codigo', $codigo)->update(['nombre' => $nombre, 'nombre_comparable' => mb_strtolower($nombre === 'Técnica' ? 'tecnica' : $nombre)]);
        }
    }

    public function down(): void {}
};
