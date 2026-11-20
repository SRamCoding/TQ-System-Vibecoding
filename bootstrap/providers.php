<?php

declare(strict_types=1);

use App\Modules\Crm\Actividades\ActividadesServiceProvider;
use App\Modules\Crm\Campanias\CampaniasServiceProvider;
use App\Modules\Crm\Catalogo\CatalogoServiceProvider;
use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Licitaciones\LicitacionesServiceProvider;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Erp\Compras\ComprasServiceProvider;
use App\Modules\Erp\Contratos\ContratosServiceProvider;
use App\Modules\Erp\Finanzas\FinanzasServiceProvider;
use App\Modules\Erp\Postventa\PostventaServiceProvider;
use App\Modules\Erp\Personal\PersonalServiceProvider;
use App\Modules\Erp\Valorizaciones\ValorizacionesServiceProvider;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Nucleo\NucleoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    NucleoServiceProvider::class,
    ProyectosServiceProvider::class,
    CatalogoServiceProvider::class,
    PresupuestosServiceProvider::class,
    ContratosServiceProvider::class,
    ComprasServiceProvider::class,
    PersonalServiceProvider::class,
    ValorizacionesServiceProvider::class,
    FinanzasServiceProvider::class,
    PostventaServiceProvider::class,
    App\Modules\Erp\Tableros\TablerosServiceProvider::class,
    CuentasServiceProvider::class,
    ActividadesServiceProvider::class,
    OportunidadesServiceProvider::class,
    CotizacionesServiceProvider::class,
    LicitacionesServiceProvider::class,
    CampaniasServiceProvider::class,
    AppPanelProvider::class,
];
