<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones;

use App\Modules\Erp\Valorizaciones\Acciones\LiberarRetencion;
use App\Modules\Erp\Valorizaciones\Filament\Pages\FichaSubcontrato;
use App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos;
use App\Modules\Erp\Valorizaciones\Filament\Pages\ValorizacionesDeContrato;
use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t4-valorizaciones): valorizaciones del contrato y subcontratos (flujo 10). */
final class ValorizacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [ValorizacionesDeContrato::class, Subcontratos::class, FichaSubcontrato::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['subcontrato_alcance' => Models\SubcontratoAlcance::class, 'valorizacion' => Models\Valorizacion::class, 'subcontrato' => Models\Subcontrato::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'valorizaciones');
        // Observaciones 6, punto 12: «Liberar retención», acción registrada con su permiso.
        $this->app->make(RegistroAcciones::class)->registrar(LiberarRetencion::class);
        $p = $this->app->make(RegistroPermisos::class);
        $p->declarar(ValorizacionesService::PERMISO_VER, 'Ver valorizaciones y subcontratos');
        $p->declarar(ValorizacionesService::PERMISO_GESTIONAR, 'Armar y aprobar valorizaciones y subcontratos');
        $p->nombrar(SubcontratosService::PERMISO_LIBERAR, 'Liberar la retención de un subcontrato terminado');
        $this->app->make(RegistroPendientesMien::class)->registrar('subcontratos', fn (User $u): array => self::pendientesMien($u));
        $m = $this->app->make(RegistroModulos::class);
        $m->registrar(new CajaModulo('valorizaciones', 'Valorizaciones', '/valorizaciones', permiso: ValorizacionesService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-chart-bar-square',
            descripcion: 'El avance de cada periodo con su reajuste, amortizaciones y retención: lo que se factura', preguntas: ['¿Cuánto facturamos este mes?', '¿Cuánto falta amortizar del adelanto?']));
        $m->registrar(new CajaModulo('subcontratos', 'Subcontratos', '/subcontratos', permiso: ValorizacionesService::PERMISO_VER, area: AreaModulo::Erp, icono: 'heroicon-o-wrench-screwdriver',
            descripcion: 'Lo que valorizan los subcontratistas, sus adelantos y su retención', preguntas: ['¿Cuánto le debemos al subcontratista?', '¿Cuánto le retenemos?']));
    }

    /**
     * Observaciones 6, punto 12: en Mien, las valorizaciones que esperan aprobación desde hace 3 días o más y los
     * subcontratos a los que se les paga más avance del que se le cobró al cliente.
     *
     * @return array<string, mixed>
     */
    private static function pendientesMien(User $u): array
    {
        if (! $u->can(ValorizacionesService::PERMISO_VER)) {
            return ['cantidad' => 0, 'texto' => ''];
        }

        $estados = app(SubcontratosService::class)->estados();
        $esperan = [];
        $alarmas = [];

        foreach ($estados as $e) {
            foreach ($e['pendientes'] as $v) {
                if ($v['dias'] >= SubcontratosService::DIAS_AVISO) {
                    $esperan[] = ['texto' => "La valorización de {$e['subcontratista']} espera aprobación hace {$v['dias']} días", 'nota' => "{$e['codigo']} · val. {$v['numero']} · {$v['periodo']}",
                        'url' => Subcontratos::getUrl(['filtro' => 'por_aprobar']), 'tono' => 'warning'];
                }
            }

            if ($e['alarma']) {
                $alarmas[] = ['texto' => "A {$e['subcontratista']} le pagas más avance del que cobraste en {$e['proyecto']}", 'nota' => 'Le pagas '.number_format((float) $e['avance'], 0).' %, cobraste '.number_format((float) $e['cliente'], 0).' %',
                    'url' => FichaSubcontrato::getUrl(['id' => $e['id']]), 'tono' => 'danger'];
            }
        }

        return ['cantidad' => count($esperan) + count($alarmas), 'texto' => 'avisos de subcontratos', 'grupos' => [
            ['titulo' => 'Subcontratos por aprobar', 'items' => $esperan],
            ['titulo' => 'Subcontratos que pagas de más', 'items' => $alarmas],
        ]];
    }
}
