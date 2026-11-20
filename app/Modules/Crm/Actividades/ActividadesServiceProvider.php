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
use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Mien\RegistroTarjetasMien;
use App\Modules\Nucleo\Mien\TarjetaMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        // MV-025: la agenda sale del inicio y es una herramienta del muelle; Mien muestra «Mi agenda».
        Livewire::component('agenda-muelle', Filament\Muelle\AgendaMuelle::class);
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END,
            fn (): string => auth()->user()?->can(ActividadesService::PERMISO_VER) === true ? Livewire::mount('agenda-muelle') : '');
        // MV-032: lo de hoy y lo vencido cuenta para la estrella de Mien.
        $this->app->make(RegistroPendientesMien::class)->registrar('agenda', function (User $u): array {
            if (! $u->can(ActividadesService::PERMISO_VER)) {
                return ['cantidad' => 0, 'texto' => ''];
            }

            $a = app(ActividadesService::class)->miAgenda($u->id);

            return ['cantidad' => count($a['hoy']) + count($a['vencidas']), 'texto' => 'actividades para hoy o vencidas'];
        });
        $this->app->make(RegistroTarjetasMien::class)->registrar(new TarjetaMien(
            'mi-agenda', 'Mi agenda', ActividadesService::PERMISO_VER, Filament\Muelle\TarjetaMiAgenda::class, 5,
        ));
    }
}
