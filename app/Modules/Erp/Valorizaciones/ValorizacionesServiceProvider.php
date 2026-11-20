<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones;

use App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos;
use App\Modules\Erp\Valorizaciones\Filament\Pages\ValorizacionesDeContrato;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t4-valorizaciones): valorizaciones del contrato y subcontratos (flujo 10). */
final class ValorizacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [ValorizacionesDeContrato::class, Subcontratos::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['subcontrato_alcance' => Models\SubcontratoAlcance::class, 'valorizacion' => Models\Valorizacion::class, 'subcontrato' => Models\Subcontrato::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $p = $this->app->make(RegistroPermisos::class);
        $p->declarar(ValorizacionesService::PERMISO_VER, 'Ver valorizaciones y subcontratos');
        $p->declarar(ValorizacionesService::PERMISO_GESTIONAR, 'Armar y aprobar valorizaciones y subcontratos');
        $m = $this->app->make(RegistroModulos::class);
        $m->registrar(new CajaModulo('valorizaciones', 'Valorizaciones', '/valorizaciones', permiso: ValorizacionesService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-chart-bar-square',
            descripcion: 'El avance de cada periodo con su reajuste, amortizaciones y retención: lo que se factura', preguntas: ['¿Cuánto facturamos este mes?', '¿Cuánto falta amortizar del adelanto?']));
        $m->registrar(new CajaModulo('subcontratos', 'Subcontratos', '/subcontratos', permiso: ValorizacionesService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-wrench-screwdriver',
            descripcion: 'Lo que valorizan los subcontratistas, sus adelantos y su retención', preguntas: ['¿Cuánto le debemos al subcontratista?', '¿Cuánto le retenemos?']));
    }
}
