<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Observación 9.3: el canal de cada lead y de cada campaña (la campaña es la acción concreta; el canal, el medio). Los
 * que ya están toman el canal de su campaña o, sin campaña, el de su origen comercial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanias', function (Blueprint $table): void {
            $table->foreignId('canal_lead_id')->nullable()->constrained('canales_lead');
        });
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('canal_lead_id')->nullable()->index()->constrained('canales_lead');
        });

        $canal = DB::table('canales_lead')->pluck('id', 'codigo');

        if ($canal->isEmpty()) {
            return;
        }

        foreach (['web' => 'sitio_web', 'feria' => 'feria', 'referidos' => 'referido', 'redes' => 'facebook', 'correo' => 'correo', 'clientes' => 'correo'] as $tipo => $codigo) {
            DB::table('campanias')->where('tipo', $tipo)->update(['canal_lead_id' => $canal[$codigo]]);
        }

        DB::statement('UPDATE leads SET canal_lead_id = c.canal_lead_id FROM campanias c WHERE c.id = leads.campania_id');
        $origen = DB::table('origenes_comerciales')->pluck('id', 'codigo');

        foreach (['SITIO_WEB' => 'sitio_web', 'FERIA' => 'feria', 'REFERENCIA' => 'referido'] as $codigoOrigen => $codigo) {
            DB::table('leads')->whereNull('canal_lead_id')->where('origen_comercial_id', $origen[$codigoOrigen] ?? 0)->update(['canal_lead_id' => $canal[$codigo]]);
        }

        DB::table('leads')->whereNull('canal_lead_id')->update(['canal_lead_id' => $canal['otro']]);
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $t) => $t->dropConstrainedForeignId('canal_lead_id'));
        Schema::table('campanias', fn (Blueprint $t) => $t->dropConstrainedForeignId('canal_lead_id'));
    }
};
