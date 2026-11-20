<?php

declare(strict_types=1);

namespace App\Modules\Erp\Postventa;

use App\Modules\Erp\Postventa\Filament\Pages\Rastreo;
use App\Modules\Erp\Postventa\Filament\Pages\Suscripciones;
use App\Modules\Erp\Postventa\Filament\Pages\Tickets;
use App\Modules\Erp\Postventa\Services\PostventaService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t6-postventa): series, tickets, visitas y suscripciones (flujo 8). */
final class PostventaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Tickets::class, Rastreo::class, Suscripciones::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'postventa');
        $p = $this->app->make(RegistroPermisos::class);
        $p->declarar(PostventaService::PERMISO_VER, 'Ver tickets de postventa, series y suscripciones');
        $p->declarar(PostventaService::PERMISO_ATENDER, 'Abrir y atender tickets de postventa');
        $m = $this->app->make(RegistroModulos::class);
        $m->registrar(new CajaModulo('postventa', 'Postventa', '/postventa', permiso: PostventaService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-wrench',
            descripcion: 'Los reclamos de los clientes por equipo, con su garantía y su plazo de atención', preguntas: ['¿Qué ticket vence primero?', '¿Este equipo sigue en garantía?', '¿A quién se le compró esta cámara?']));
        $m->registrar(new CajaModulo('suscripciones', 'Suscripciones', '/suscripciones', permiso: PostventaService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-arrow-path-rounded-square',
            descripcion: 'Los servicios recurrentes que Techquk vende, con su margen mensual', preguntas: ['¿Cuánto nos deja el monitoreo cada mes?', '¿Qué suscripción se renueva pronto?']));
    }
}
