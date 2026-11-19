<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo;

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\CatalogoEnBase;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
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
    }

    public function boot(): void
    {
        Relation::morphMap(['producto' => Producto::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
