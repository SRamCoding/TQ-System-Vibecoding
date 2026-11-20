<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas;

use App\Modules\Erp\Finanzas\Filament\Pages\Comprobantes;
use App\Modules\Erp\Finanzas\Filament\Pages\FlujoCaja;
use App\Modules\Erp\Finanzas\Filament\Pages\Rendiciones;
use App\Modules\Erp\Finanzas\Filament\Pages\Resultados;
use App\Modules\Erp\Finanzas\Filament\Pages\TableroPagos;
use App\Modules\Erp\Finanzas\Resumen\TarjetaResultado;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t5-finanzas): el ciclo del dinero (flujos 4 a 7). */
final class FinanzasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Comprobantes::class, TableroPagos::class, FlujoCaja::class, Rendiciones::class, Resultados::class, Filament\Pages\ExportarContador::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['fondo_gasto' => Models\FondoGasto::class, 'comprobante' => Models\Comprobante::class, 'fondo' => Models\Fondo::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'finanzas');
        $this->app->make(RegistroTarjetasResumen::class)->registrar(new TarjetaResultado);
        $p = $this->app->make(RegistroPermisos::class);
        $p->declarar(FinanzasService::PERMISO_VER, 'Ver comprobantes, pagos, caja y resultados');
        $p->declarar(FinanzasService::PERMISO_FACTURAR, 'Facturar y cobrar');
        $p->declarar(FinanzasService::PERMISO_PAGAR, 'Registrar facturas de proveedor, programar y pagar');
        $m = $this->app->make(RegistroModulos::class);
        foreach ([
            ['resultados', '¿Cuánto se ganó?', '/resultados', 'heroicon-o-trophy', 'Por proyecto: vendido, facturado, costo real y margen real', ['¿Cuánto se ganó realmente en cada proyecto?', '¿Qué proyecto pierde dinero?']],
            ['comprobantes', 'Comprobantes', '/comprobantes', 'heroicon-o-document-text', 'Facturas emitidas y de proveedores, con su cruce de tres vías', ['¿Qué falta facturar?', '¿Qué facturas tienen diferencia con lo recibido?']],
            ['tablero-pagos', 'Tablero de pagos', '/tablero-pagos', 'heroicon-o-view-columns', 'Tesorería ordena y paga las facturas de proveedores', ['¿Qué pagamos este viernes?', '¿Qué está vencido?']],
            ['flujo-caja', 'Flujo de caja', '/flujo-caja', 'heroicon-o-arrows-up-down', 'Lo que entra y sale por mes, real y proyectado, y la conciliación', ['¿Nos alcanza la caja el próximo mes?', '¿Qué falta conciliar?']],
            ['exportar-contador', 'Para el contador', '/exportar-contador', 'heroicon-o-arrow-down-tray', 'Registros de ventas y compras y caja y bancos del mes, para Excel', ['¿Qué le mando al contador este mes?']],
            ['rendiciones', 'Rendiciones', '/rendiciones', 'heroicon-o-receipt-percent', 'Fondos por proyecto y su rendición con comprobantes', ['¿Quién tiene fondos sin rendir?']],
        ] as [$clave, $nombre, $url, $icono, $desc, $preguntas]) {
            $m->registrar(new CajaModulo($clave, $nombre, $url, permiso: FinanzasService::PERMISO_VER, area: AreaModulo::Erp, icono: $icono, descripcion: $desc, preguntas: $preguntas));
        }
    }
}
