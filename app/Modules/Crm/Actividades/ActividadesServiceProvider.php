<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades;

use App\Modules\Crm\Actividades\Filament\Pages\Agenda;
use App\Modules\Crm\Actividades\Models\Actividad;
use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Crm\Actividades\Services\AporteActividadesACuentas;
use App\Modules\Crm\Actividades\Services\ProximaAccionDesdeActividades;
use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Oportunidades\Services\ProximaAccionDeOportunidades;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t1-agenda): Agenda y actividades (CRM-05). Va antes de Oportunidades en la lista de providers. */
final class ActividadesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProximaAccionDeOportunidades::class, ProximaAccionDesdeActividades::class);
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Agenda::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['actividad' => Actividad::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'actividades');
        $this->app->make(ActividadDeCuentas::class)->registrar(new AporteActividadesACuentas);

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(ActividadesService::PERMISO_VER, 'Ver la agenda');
        $permisos->declarar(ActividadesService::PERMISO_REGISTRAR, 'Registrar actividades');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'agenda', 'Agenda', '/agenda', permiso: ActividadesService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-calendar-days',
            descripcion: 'Las visitas, llamadas y reuniones con los clientes, y lo que sigue con cada uno',
            preguntas: ['¿Qué tengo hoy?', '¿Qué se me venció?', '¿Qué oportunidades no tienen próxima acción?', '¿Cuándo hablamos por última vez con este cliente?'],
        ));
    }
}
