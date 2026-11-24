<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Observación 9.5: el margen mínimo de Techquk es 12 % (antes 22 %) y su explicación dice todo lo que lo usa. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametros')->where('grupo', 'crm')->where('clave', 'cotizacion_margen_minimo')
            ->update(['descripcion' => CotizacionesServiceProvider::DESCRIPCION_MARGEN_MINIMO]);
        DB::table('parametros')->where('grupo', 'crm')->where('clave', 'cotizacion_margen_minimo')->where('valor', '22.00')->update(['valor' => '12.00']);
    }

    public function down(): void {}
};
