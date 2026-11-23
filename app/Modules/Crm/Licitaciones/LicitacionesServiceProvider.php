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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
            // Observación 7.4.
            new DefinicionParametro('crm', 'seace_valor_prioritaria', TipoParametro::Entero, 500000,
                'Desde este valor referencial (en soles) una convocatoria es prioritaria (también si la entidad ya es cuenta).'),
        );

        // Observación 7.4: cada convocatoria que llega o cambia dispara sus reacciones, en este orden.
        foreach ([Listeners\FiltrarConvocatoria::class, Listeners\PriorizarConvocatoria::class, Listeners\AvisarConvocatoriaEnMien::class] as $reaccion) {
            Event::listen([Events\ConvocatoriaLlego::class, Events\ConvocatoriaCambio::class], $reaccion);
        }

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
        // Observación 7.4: lo que dejó «Avisar en Mien» en los últimos 3 días (nuevas en la bandeja y cronogramas cambiados).
        $this->app->make(RegistroPendientesMien::class)->registrar('convocatorias', function (User $u): array {
            if (! $u->can(LicitacionesService::PERMISO_VER)) {
                return ['cantidad' => 0, 'texto' => ''];
            }

            $avisos = DB::table('convocatoria_avisos as a')->join('convocatorias_seace as c', 'c.id', '=', 'a.convocatoria_id')
                ->where('a.created_at', '>=', now()->subDays(3))->orderByDesc('a.created_at')->limit(20)->get(['a.texto', 'a.tipo', 'a.created_at', 'c.id', 'c.licitacion_id']);
            $items = $avisos->map(fn (object $a): array => ['texto' => $a->texto, 'nota' => CarbonImmutable::parse($a->created_at)->locale('es')->diffForHumans(),
                'url' => $a->tipo === 'cambio' && $a->licitacion_id !== null ? '/licitaciones/'.$a->licitacion_id : '/convocatorias?c='.$a->id, 'tono' => $a->tipo === 'cambio' ? 'danger' : null])->all();

            return ['cantidad' => count($items), 'texto' => count($items) === 1 ? 'aviso del SEACE' : 'avisos del SEACE', 'grupos' => [
                ['titulo' => 'Convocatorias del SEACE', 'items' => $items],
            ]];
        });

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
