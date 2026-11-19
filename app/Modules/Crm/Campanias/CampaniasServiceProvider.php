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
            'leads', 'Campañas y leads', '/leads', permiso: LeadsService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-inbox-arrow-down',
            descripcion: 'Los contactos que llegan por ferias, la web o referidos, y su conversión en oportunidades',
            preguntas: ['¿Qué leads llevan días sin atender?', '¿Esta organización ya es cuenta?', '¿Cuánto nos costó cada lead de la feria?'],
        ));
    }
}
