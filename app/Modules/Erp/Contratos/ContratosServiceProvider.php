<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos;

use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\ContratoResource;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Resumen\TarjetaContrato;
use App\Modules\Erp\Contratos\Services\ContratosDesdeElErp;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Contratos\Services\VentaDesdeElContrato;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t2-contratos): Contratos (ERP-10). Va después de Presupuestos y antes de Oportunidades. */
final class ContratosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VentaDelProyecto::class, VentaDesdeElContrato::class);
        $this->app->bind(ContratosDeOportunidad::class, ContratosDesdeElErp::class);
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [ContratoResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['contrato' => Contrato::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->app->make(RegistroTarjetasResumen::class)->registrar(new TarjetaContrato);

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(ContratosService::PERMISO_VER, 'Ver contratos');
        $permisos->declarar(ContratosService::PERMISO_GESTIONAR, 'Administrar contratos, garantías, adendas y penalidades');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'contratos', 'Contratos', '/contratos', permiso: ContratosService::PERMISO_VER, area: AreaModulo::Erp,
            icono: 'heroicon-o-document-check',
            descripcion: 'Los contratos con sus hitos, cartas fianza, adendas y penalidades',
            preguntas: ['¿Cuántos días le quedan a este contrato?', '¿Cuánto nos cuesta cada día de atraso?', '¿Qué carta fianza vence pronto?'],
        ));
    }
}
