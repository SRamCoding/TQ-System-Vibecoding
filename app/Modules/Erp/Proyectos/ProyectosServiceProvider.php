<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos;

use App\Modules\Erp\Proyectos\Acciones\CambiarFotoProyecto;
use App\Modules\Erp\Proyectos\Acciones\CrearProyectoInterno;
use App\Modules\Erp\Proyectos\Acciones\QuitarFotoProyecto;
use App\Modules\Erp\Proyectos\Efectos\AvisarCierreDelProyecto;
use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\ProyectoResource;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Pestanas\RegistroPestanasProyecto;
use App\Modules\Erp\Proyectos\Resumen\RegistroFiltrosSalud;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Resumen\SemaforoDeCosto;
use App\Modules\Erp\Proyectos\Resumen\SinSemaforoDeCosto;
use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Erp\Proyectos\Services\ProyectosEnBase;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\SinVentaDelProyecto;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Erp\Proyectos\Tablero\FiltroJefeInactivo;
use App\Modules\Erp\Proyectos\Tablero\FiltroVencidos;
use App\Modules\Erp\Proyectos\Tablero\TarjetaJefeInactivo;
use App\Modules\Erp\Proyectos\Tablero\TarjetaPlazo;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Imagenes\RegistroImagenes;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Erp/Proyectos (plan 2.1): lo mínimo del proyecto y la costura C2. Su dueño es el área 4, que sigue desde aquí. La
 * lista y la ficha (plan 2.10) las hizo el área 1 para ver lo ya construido.
 */
final class ProyectosServiceProvider extends ServiceProvider
{
    public const string PERMISO_VER = 'erp.proyectos.ver';

    /** El de la caja, la pantalla y la ruta: ninguno se repite (`docs/convenciones/interfaz.md`). */
    public const string ICONO = 'tq-proyecto-cohete';

    public function register(): void
    {
        $this->app->singleton(Condiciones\VerificacionesCierre::class);
        $this->app->bind(ProyectosService::class, ProyectosEnBase::class);
        // Plan 2.10, D3: donde los demás módulos suman sus pestañas a la ficha. Uno por aplicación.
        $this->app->singleton(RegistroPestanasProyecto::class);
        $this->app->singleton(RegistroTarjetasResumen::class);
        $this->app->singleton(RegistroFiltrosSalud::class);
        $this->app->bindIf(SemaforoDeCosto::class, SinSemaforoDeCosto::class);
        // Plan 2.61, D10: Contratos la reemplaza con el monto del contrato.
        $this->app->bindIf(VentaDelProyecto::class, SinVentaDelProyecto::class);
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [ProyectoResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['proyecto' => Proyecto::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'proyectos');

        $documentos = $this->app->make(RegistroDocumentos::class);
        $documentos->efecto('erp.proyectos.avisar-cierre', AvisarCierreDelProyecto::class);
        $documentos->registrar(new DefinicionProyecto);

        $acciones = $this->app->make(RegistroAcciones::class);
        $acciones->registrar(CrearProyectoInterno::class);
        // Plan 2.61, D2: la foto de referencia, solo el jefe del proyecto.
        $acciones->registrar(CambiarFotoProyecto::class);
        $acciones->registrar(QuitarFotoProyecto::class);
        // Plan 2.61, D6: las tarjetas del Resumen que son de Proyectos.
        $tarjetas = $this->app->make(RegistroTarjetasResumen::class);
        $tarjetas->registrar(new TarjetaPlazo);
        $tarjetas->registrar(new TarjetaJefeInactivo);
        $salud = $this->app->make(RegistroFiltrosSalud::class);
        $salud->registrar(new FiltroVencidos);
        $salud->registrar(new FiltroJefeInactivo);
        $this->app->make(RegistroImagenes::class)->registrar(FotoProyectoService::CLAVE_IMAGEN, FotoProyectoService::rutaParaVer(...));

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(self::PERMISO_VER, 'Ver proyectos');
        $permisos->nombrar('erp.proyectos.crear', 'Crear proyectos internos');

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'proyectos', 'Proyectos', '/proyectos', permiso: self::PERMISO_VER, area: AreaModulo::Erp,
            icono: self::ICONO,
            descripcion: 'Los proyectos, su presupuesto aprobado y sus partidas de control',
            preguntas: ['¿Qué proyectos están en ejecución?', '¿Qué presupuesto tiene aprobado este proyecto?', '¿Cómo se reparte su costo en partidas?'],
        ));
    }
}
