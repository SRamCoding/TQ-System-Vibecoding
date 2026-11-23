<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones;

use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Pestanas\PestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t1-licitaciones): Licitaciones (CRM-04). */
final class LicitacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Las pantallas se declaran aquí, antes de que el panel se arme (como Cuentas).
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [Filament\Pages\BandejaConvocatorias::class], recursos: [LicitacionResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['licitacion' => Licitacion::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // MVP rápido (t1-licitaciones v2): qué convocatorias del SEACE entran a la bandeja.
        $this->app->make(RegistroParametros::class)->declarar(
            new DefinicionParametro('crm', 'seace_palabras_clave', TipoParametro::Texto,
                'videovigilancia, cámaras, cctv, red de datos, cableado estructurado, fibra óptica, switch, servidor, centro de datos, data center, software, sistema de información, telemetría, wifi, inalámbric',
                'Palabras que debe mencionar una convocatoria del SEACE para entrar a la bandeja, separadas por comas.'),
            new DefinicionParametro('crm', 'seace_valor_minimo', TipoParametro::Entero, 20000,
                'Valor referencial mínimo (en soles) de una convocatoria para entrar a la bandeja.'),
        );

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(LicitacionesService::PERMISO_VER, 'Ver licitaciones');
        $permisos->declarar(LicitacionesService::PERMISO_GESTIONAR, 'Registrar y llevar licitaciones');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'convocatorias', 'Convocatorias del SEACE', '/convocatorias', permiso: LicitacionesService::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-inbox-stack',
            descripcion: 'Lo que publica el SEACE cada día y coincide con lo que vende Techquk, listo para registrar',
            preguntas: ['¿Qué salió hoy en el SEACE para nosotros?', '¿Qué convocatoria vence primero?'],
        ));
        // MV-023: Licitaciones ya no es caja: es una pestaña de la oportunidad y de la portada de Oportunidades.
        $this->loadViewsFrom(__DIR__.'/resources/views', 'licitaciones');
        // Observación 7.3: cada fecha del cronograma que se acerca y lo que le falta a cada paso de la postulación.
        $this->app->make(RegistroPendientesMien::class)->registrar('licitaciones', function (User $u): array {
            if (! $u->can(LicitacionesService::PERMISO_GESTIONAR)) {
                return ['cantidad' => 0, 'texto' => ''];
            }

            $items = app(Services\PostulacionService::class)->avisos();

            return ['cantidad' => count($items), 'texto' => count($items) === 1 ? 'paso de licitación' : 'pasos de licitaciones', 'grupos' => [
                ['titulo' => 'Licitaciones: lo que toca', 'items' => $items],
            ]];
        });
        $this->app->make(RegistroPestanasOportunidad::class)->registrar(new PestanaOportunidad(
            'licitacion', 'Licitación', LicitacionesService::PERMISO_VER, Filament\LicitacionDeOportunidad::class, 'heroicon-o-building-library', orden: 5,
            aplicaA: fn (int $id): bool => Filament\LicitacionDeOportunidad::tieneLicitacion($id),
        ));
    }
}
