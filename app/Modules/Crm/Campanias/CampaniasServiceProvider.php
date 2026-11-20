<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t1-campanias): Campañas y leads (CRM-07). */
final class CampaniasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [LeadResource::class, CampaniaResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['campania' => Campania::class, 'lead' => Lead::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(LeadsService::PERMISO_VER, 'Ver campañas y leads');
        $permisos->declarar(LeadsService::PERMISO_GESTIONAR, 'Registrar campañas y atender leads');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'campanias', 'Campañas', '/campanias', permiso: LeadsService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-inbox-arrow-down',
            descripcion: 'Las campañas con sus resultados: leads, conversión, monto y costo por lead',
            preguntas: ['¿Cuánto nos costó cada lead de la feria?', '¿Qué campaña convierte más?'],
        ));
    }
}
