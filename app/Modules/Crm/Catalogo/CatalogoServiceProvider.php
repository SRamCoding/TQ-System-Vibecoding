<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo;

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\CatalogoEnBase;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Crm/Catalogo (plan 2.60): el esqueleto de la costura C4, un solo catálogo para el CRM y el ERP. Su dueño es el área 4,
 * que sigue desde aquí (acciones, pantalla «Catálogo comercial», marcas, familias y precios).
 */
final class CatalogoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogoService::class, CatalogoEnBase::class);
        // MVP rápido (t1-catalogo): las pantallas del catálogo comercial (CRM-06).
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [Filament\Resources\Catalogo\CatalogoResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['producto' => Producto::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        Relation::morphMap(['familia_producto' => Models\FamiliaProducto::class, 'producto_precio' => Models\PrecioProducto::class]);

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(Services\PreciosCatalogoService::PERMISO_VER, 'Ver el catálogo comercial');
        $permisos->declarar(Services\PreciosCatalogoService::PERMISO_GESTIONAR, 'Crear ítems y cambiar precios del catálogo');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'catalogo', 'Catálogo', '/catalogo', permiso: Services\PreciosCatalogoService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-squares-2x2',
            descripcion: 'Lo que vende Techquk, con su precio vigente y su código del Estado: un solo maestro para todos',
            preguntas: ['¿A cuánto vendemos este equipo?', '¿Qué ítems no tienen precio?', '¿Cuál es su código del Estado?', '¿Cómo cambió su precio?'],
        ));
    }
}
