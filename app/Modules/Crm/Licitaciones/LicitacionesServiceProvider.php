<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t1-licitaciones): Licitaciones (CRM-04). */
final class LicitacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Las pantallas se declaran aquí, antes de que el panel se arme (como Cuentas).
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [LicitacionResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['licitacion' => Licitacion::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(LicitacionesService::PERMISO_VER, 'Ver licitaciones');
        $permisos->declarar(LicitacionesService::PERMISO_GESTIONAR, 'Registrar y llevar licitaciones');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'licitaciones', 'Licitaciones', '/licitaciones', permiso: LicitacionesService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-building-library',
            descripcion: 'Las convocatorias públicas y privadas: cronograma, expediente, propuesta y resultado',
            preguntas: ['¿Qué convocatorias vencen esta semana?', '¿Qué falta para presentar?', '¿A qué precio adjudica esta entidad?', '¿Por qué no participamos?'],
        ));
    }
}
