<?php

declare(strict_types=1);

use App\Modules\Nucleo\Navegacion\BarraLateral;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** MV-050: la barra lateral tiene dos estados; un ancho guardado distinto del fijo pasa a «expandida» con el ancho fijo. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('preferencias_usuario')->where('clave', 'navegacion.ancho_barra')->where('valor', '<>', (string) BarraLateral::PLEGADA)
            ->update(['valor' => (string) BarraLateral::ANCHO_INICIAL]);
    }

    public function down(): void {}
};
