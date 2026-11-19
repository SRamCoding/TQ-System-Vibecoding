<?php

declare(strict_types=1);

use App\Modules\Crm\Actividades\ActividadesServiceProvider;
use App\Modules\Crm\Catalogo\CatalogoServiceProvider;
use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Licitaciones\LicitacionesServiceProvider;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
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
    CuentasServiceProvider::class,
    ActividadesServiceProvider::class,
    OportunidadesServiceProvider::class,
    CotizacionesServiceProvider::class,
    LicitacionesServiceProvider::class,
    AppPanelProvider::class,
];
