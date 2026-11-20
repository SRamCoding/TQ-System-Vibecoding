<?php

declare(strict_types=1);

namespace App\Modules\Erp\Tableros;

use App\Modules\Erp\Tableros\Filament\Pages\MiTablero;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t8-tableros): el tablero de inicio de cada rol (v4, parte 5). */
final class TablerosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [MiTablero::class]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tableros');
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo('mi-tablero', 'Mi tablero', '/mi-tablero', permiso: 'erp.contratos.ver', area: AreaModulo::Erp, icono: 'heroicon-o-presentation-chart-bar',
            descripcion: 'Lo que vence, dónde se va el dinero y qué espera algo de ti, según tu rol', preguntas: ['¿Qué tengo pendiente hoy?', '¿Dónde se está yendo el costo?', '¿Nos alcanza la caja?']));
    }
}
