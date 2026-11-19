<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras;

use App\Modules\Erp\Compras\Filament\Pages\Almacen;
use App\Modules\Erp\Compras\Filament\Resources\Ordenes\OrdenResource;
use App\Modules\Erp\Compras\Filament\Resources\Requerimientos\RequerimientoResource;
use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Compras\Models\OrdenLinea;
use App\Modules\Erp\Compras\Models\Requerimiento;
use App\Modules\Erp\Compras\Models\RequerimientoLinea;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t3-compras): Compras y almacén (flujo 2). */
final class ComprasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Almacen::class], recursos: [RequerimientoResource::class, OrdenResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['requerimiento' => Requerimiento::class, 'requerimiento_linea' => RequerimientoLinea::class, 'orden' => Orden::class, 'orden_linea' => OrdenLinea::class,
            'cuadro_comparativo' => Models\CuadroComparativo::class, 'recepcion' => Models\Recepcion::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'compras');

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(ComprasService::PERMISO_VER, 'Ver requerimientos, órdenes y almacén');
        $permisos->declarar(ComprasService::PERMISO_REQUERIR, 'Hacer requerimientos');
        $permisos->declarar(ComprasService::PERMISO_APROBAR, 'Aprobar requerimientos y órdenes');
        $permisos->declarar(ComprasService::PERMISO_COMPRAR, 'Cotizar, adjudicar, recibir y despachar');

        $modulos = $this->app->make(RegistroModulos::class);
        $modulos->registrar(new CajaModulo('requerimientos', 'Requerimientos', '/requerimientos', permiso: ComprasService::PERMISO_VER, area: AreaModulo::Erp,
            icono: 'heroicon-o-clipboard-document-list', descripcion: 'Lo que el proyecto necesita, con su imputación, su aprobación y su cuadro comparativo',
            preguntas: ['¿Qué falta aprobar?', '¿Hay stock o hay que comprar?', '¿Por qué se le compró a ese proveedor?']));
        $modulos->registrar(new CajaModulo('ordenes', 'Órdenes', '/ordenes', permiso: ComprasService::PERMISO_VER, area: AreaModulo::Erp,
            icono: 'heroicon-o-shopping-cart', descripcion: 'Órdenes de compra y de servicio: lo comprometido, lo recibido y lo que falta',
            preguntas: ['¿Qué órdenes faltan recibir?', '¿Qué se aprobó excediendo su partida?', '¿Cuánto le debemos a este proveedor?']));
        $modulos->registrar(new CajaModulo('almacen', 'Almacén', '/almacen', permiso: ComprasService::PERMISO_VER, area: AreaModulo::Erp,
            icono: 'heroicon-o-archive-box', descripcion: 'El stock del Almacén Central, lo reservado y lo disponible',
            preguntas: ['¿Tenemos postes en almacén?', '¿Cuánto vale el stock?']));
    }
}
