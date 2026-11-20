<?php

declare(strict_types=1);

namespace App\Modules\Erp\Cierre;

use App\Modules\Erp\Cierre\Filament\CierreDelProyecto;
use App\Modules\Erp\Cierre\Filament\Pages\LoAprendido;
use App\Modules\Erp\Cierre\Services\CierreService;
use App\Modules\Erp\Proyectos\Condiciones\VerificacionesCierre;
use App\Modules\Erp\Proyectos\Events\ProyectoCerrado;
use App\Modules\Erp\Proyectos\Pestanas\PestanaProyecto;
use App\Modules\Erp\Proyectos\Pestanas\RegistroPestanasProyecto;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t9-cierre): el cierre del proyecto (flujo 6). */
final class CierreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [LoAprendido::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'cierre');
        CierreService::registrarVerificaciones($this->app->make(VerificacionesCierre::class));
        $this->app->make(RegistroPestanasProyecto::class)->registrar(new PestanaProyecto('cierre', 'Cierre', null, CierreDelProyecto::class, 'heroicon-o-check-badge', orden: 90));
        Event::listen(ProyectoCerrado::class, fn (ProyectoCerrado $e) => app(CierreService::class)->congelar($e->proyectoId));
        $this->app->make(RegistroPermisos::class)->declarar(CierreService::PERMISO_GESTIONAR, 'Cerrar proyectos, escribir lo aprendido y registrar la devolución de la garantía');
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo('lo-aprendido', 'Lo aprendido', '/lo-aprendido', permiso: 'erp.proyectos.ver', area: AreaModulo::Erp, icono: 'heroicon-o-light-bulb',
            descripcion: 'Los proyectos cerrados con su margen real, dónde se pasaron y qué haríamos distinto', preguntas: ['¿Cuánto se ganó de verdad en los proyectos cerrados?', '¿En qué partidas solemos pasarnos?']));
    }
}
