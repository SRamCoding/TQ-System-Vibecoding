<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos;

use App\Modules\Crm\Oportunidades\Services\ProyectoAlGanar;
use App\Modules\Erp\Presupuestos\Acciones\AgregarInsumoApu;
use App\Modules\Erp\Presupuestos\Acciones\AgregarPartida;
use App\Modules\Erp\Presupuestos\Acciones\AgregarPartidaPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\AgregarTituloPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\ArmarArbolDesdePlantilla;
use App\Modules\Erp\Presupuestos\Acciones\ArmarArbolDesdePresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\CambiarUsaApu;
use App\Modules\Erp\Presupuestos\Acciones\CrearArbolPartidas;
use App\Modules\Erp\Presupuestos\Acciones\CrearInsumo;
use App\Modules\Erp\Presupuestos\Acciones\CrearPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\DesactivarInsumo;
use App\Modules\Erp\Presupuestos\Acciones\DesactivarPartida;
use App\Modules\Erp\Presupuestos\Acciones\EditarEncabezadoPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\EditarInsumo;
use App\Modules\Erp\Presupuestos\Acciones\EditarInsumoApu;
use App\Modules\Erp\Presupuestos\Acciones\EditarPartidaControl;
use App\Modules\Erp\Presupuestos\Acciones\EditarPartidaPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\FijarPresupuestado;
use App\Modules\Erp\Presupuestos\Acciones\NuevaVersionPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\QuitarInsumoApu;
use App\Modules\Erp\Presupuestos\Acciones\QuitarPartidaPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\QuitarTituloPresupuesto;
use App\Modules\Erp\Presupuestos\Acciones\ReclasificarGasto;
use App\Modules\Erp\Presupuestos\Acciones\TraerPresupuestoAlProyecto;
use App\Modules\Erp\Presupuestos\Efectos\CrearPartidaNoPresupuestada;
use App\Modules\Erp\Presupuestos\Efectos\HacerVigente;
use App\Modules\Erp\Presupuestos\Efectos\IniciarEjecucionDelProyecto;
use App\Modules\Erp\Presupuestos\Efectos\PedidoTraerAlProyecto;
use App\Modules\Erp\Presupuestos\Efectos\QuitarVigencia;
use App\Modules\Erp\Presupuestos\Efectos\ReemplazarOfertadasAnteriores;
use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Filament\ApuDePartida;
use App\Modules\Erp\Presupuestos\Filament\Desarrollo\ProbarSelectorImputacion;
use App\Modules\Erp\Presupuestos\Filament\InsumosDelCatalogo;
use App\Modules\Erp\Presupuestos\Filament\PartidasDeControlDeProyecto;
use App\Modules\Erp\Presupuestos\Filament\PresupuestoDeProyecto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\PresupuestoResource;
use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Imputacion\DestinosDePresupuestos;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\PresupuestadoDePartida;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Models\TituloDePresupuesto;
use App\Modules\Erp\Presupuestos\Plantillas\PlantillaArbol;
use App\Modules\Erp\Presupuestos\Resumen\FiltroMargenEnRiesgo;
use App\Modules\Erp\Presupuestos\Resumen\FiltroPartidasExcedidas;
use App\Modules\Erp\Presupuestos\Resumen\SaludDesdeElArbol;
use App\Modules\Erp\Presupuestos\Resumen\TarjetaDinero;
use App\Modules\Erp\Presupuestos\Resumen\TarjetaFaltaArbol;
use App\Modules\Erp\Presupuestos\Resumen\TarjetaPartidasExcedidas;
use App\Modules\Erp\Presupuestos\Services\AvisoDeGastosNoPresupuestados;
use App\Modules\Erp\Presupuestos\Services\AvisoDeSaldos;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Presupuestos\Services\PartidasEnBase;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Presupuestos\Services\PrecioSugeridoManoObra;
use App\Modules\Erp\Presupuestos\Services\PresupuestosEnBase;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\SinPrecioSugerido;
use App\Modules\Erp\Proyectos\Pestanas\PestanaProyecto;
use App\Modules\Erp\Proyectos\Pestanas\RegistroPestanasProyecto;
use App\Modules\Erp\Proyectos\Resumen\RegistroFiltrosSalud;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Resumen\SemaforoDeCosto;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Notificaciones\GrupoAvisos;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Erp/Presupuestos (plan 2.1): el árbol de partidas de control y la costura C1; los gastos no presupuestados (2.6); el
 * presupuesto con APU, su catálogo de insumos y la costura C8 (2.9). La fórmula polinómica llega en otra tarea.
 */
final class PresupuestosServiceProvider extends ServiceProvider
{
    /** Plan 2.10: ver presupuestos, su catálogo de insumos y el árbol de partidas (los montos piden además ver costos). */
    public const string PERMISO_VER = 'erp.presupuestos.ver';

    /** El de la caja, la pantalla y la ruta: ninguno se repite (`docs/convenciones/interfaz.md`). */
    public const string ICONO = 'heroicon-o-calculator';

    public function register(): void
    {
        $this->app->bind(PartidasService::class, PartidasEnBase::class);
        // Plan 2.61: el semáforo de costo de la portada de Proyectos.
        $this->app->bind(SemaforoDeCosto::class, SaludDesdeElArbol::class);
        $this->app->bind(PresupuestosService::class, PresupuestosEnBase::class);
        // MVP rápido (t0-datos-demo): lo vendido sale de la cotización aceptada hasta que exista Contratos.
        $this->app->bind(VentaDelProyecto::class, Services\VentaDesdeLaCotizacion::class);
        // MVP rápido (t1-puente-erp, MV-006): al ganar nace el proyecto con lo cotizado como presupuesto.
        $this->app->bind(ProyectoAlGanar::class, Services\ProyectoDesdeLaGanada::class);
        // Plan 2.9, D1: el precio sugerido de la mano de obra; C5 lo reemplaza cuando exista.
        $this->app->bind(PrecioSugeridoManoObra::class, SinPrecioSugerido::class);
        $this->app->scoped(PedidoTraerAlProyecto::class);
        // Plan 2.2, D1: lo que el selector de imputación del núcleo pide de proyectos y partidas.
        $this->app->bind(FuenteDeDestinos::class, DestinosDePresupuestos::class);
        // Plan 2.2, D8: solo existe con el modo desarrollo activo (responde 404 sin él). En `register`: el panel se arma
        // antes del `boot` de los módulos, como hace el núcleo con sus pantallas.
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [ProbarSelectorImputacion::class], recursos: [PresupuestoResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'arbol_partidas' => ArbolPartidas::class,
            'partida_control' => PartidaControl::class,
            'presupuestado_de_partida' => PresupuestadoDePartida::class,
            'presupuesto' => Presupuesto::class,
            'presupuesto_titulo' => TituloDePresupuesto::class,
            'presupuesto_partida' => PartidaDePresupuesto::class,
            'insumo' => Insumo::class,
            'linea_apu' => LineaApu::class,
            'gasto_demo' => Models\GastoDemo::class,
        ]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'presupuestos');

        $documentos = $this->app->make(RegistroDocumentos::class);
        $documentos->efecto('erp.presupuestos.crear-pc99', CrearPartidaNoPresupuestada::class);
        $documentos->efecto('erp.presupuestos.iniciar-ejecucion', IniciarEjecucionDelProyecto::class);
        $documentos->registrar(new DefinicionArbolPartidas);
        $documentos->efecto('erp.presupuestos.hacer-vigente', HacerVigente::class);
        $documentos->efecto('erp.presupuestos.quitar-vigencia', QuitarVigencia::class);
        $documentos->efecto('erp.presupuestos.reemplazar-ofertadas', ReemplazarOfertadasAnteriores::class);
        $documentos->registrar(new DefinicionPresupuesto);
        // El plan 1.9 la daba por sembrada; no lo estaba (plan 2.1, bitácora). Su «Otro» lo crea el sincronizador.
        // Plan 2.6, D3: los seis del v4 (parte 4, flujo 7). «Cambio pedido por el cliente» marca el posible adicional.
        $documentos->familiaDeMotivos(DestinoPartida::FAMILIA_NO_PRESUPUESTADO, 'Gasto no presupuestado', [
            new MotivoInicial('imprevisto_obra', 'Imprevisto en obra'),
            new MotivoInicial('error_metrado', 'Error de metrado'),
            new MotivoInicial('cambio_cliente', 'Cambio pedido por el cliente', esCambioCliente: true),
            new MotivoInicial('precio_mayor', 'Precio mayor al presupuestado'),
            new MotivoInicial('reposicion', 'Reposición por pérdida o daño'),
        ]);
        $documentos->familiaDeMotivos(DestinoPartida::FAMILIA_EXCESO, 'Exceso de partida');

        $this->declararAlertas();

        $this->app->make(RegistroPermisos::class)->declarar(self::PERMISO_VER, 'Ver presupuestos y partidas de control');
        // Plan 2.11 (ADR 0022, D2): el árbol, primer tipo de plantilla; publicar en la empresa pide su permiso.
        $this->app->make(RegistroPermisos::class)->declarar(PlantillaArbol::PERMISO_PUBLICAR, 'Publicar plantillas de presupuesto y costo para toda la empresa');
        $this->app->make(RegistroPlantillas::class)->registrar(new PlantillaArbol);
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'presupuestos', 'Presupuestos', '/presupuestos', permiso: self::PERMISO_VER, area: AreaModulo::Erp,
            icono: self::ICONO,
            descripcion: 'Los presupuestos con su análisis de precios unitarios y el catálogo de insumos',
            preguntas: ['¿Cuánto cuesta este presupuesto y cuánto se gana?', '¿Qué insumos y cuántos lleva?', '¿Qué presupuestos están ofertados?'],
        ));
        // Plan 2.10, D2: el catálogo de insumos, en el panel deslizante de la lista.
        Livewire::component('presupuestos.insumos', InsumosDelCatalogo::class);
        // D4: el APU de una partida, en el panel deslizante de la pestaña «Partidas».
        Livewire::component('presupuestos.apu', ApuDePartida::class);
        // Plan 2.10, D3: lo del área 1 en la ficha del proyecto, sin que Proyectos conozca sus modelos.
        $this->app->make(RegistroPestanasProyecto::class)->registrar(new PestanaProyecto(
            'presupuesto', 'Presupuesto', self::PERMISO_VER, PresupuestoDeProyecto::class, 'heroicon-o-document-currency-dollar', orden: 10,
        ));
        // MVP rápido (t1-puente-erp, MV-008): lo ofertado contra el presupuesto vigente, junto al presupuesto.
        $this->app->make(RegistroPestanasProyecto::class)->registrar(new PestanaProyecto(
            'ofertado-vs-presupuesto', 'Ofertado vs. presupuesto', self::PERMISO_VER, Filament\OfertadoVsPresupuesto::class, 'heroicon-o-scale', orden: 15,
        ));
        $this->app->make(RegistroPestanasProyecto::class)->registrar(new PestanaProyecto(
            'partidas-de-control', 'Partidas de control', self::PERMISO_VER, PartidasDeControlDeProyecto::class, 'heroicon-o-chart-bar', orden: 20,
        ));

        // Plan 2.61, D6: lo del área 1 en el Resumen de la ficha del proyecto.
        $tarjetas = $this->app->make(RegistroTarjetasResumen::class);
        $tarjetas->registrar(new TarjetaDinero);
        $tarjetas->registrar(new TarjetaPartidasExcedidas);
        $tarjetas->registrar(new TarjetaFaltaArbol);
        $salud = $this->app->make(RegistroFiltrosSalud::class);
        $salud->registrar(new FiltroMargenEnRiesgo);
        $salud->registrar(new FiltroPartidasExcedidas);

        $acciones = $this->app->make(RegistroAcciones::class);
        $acciones->registrar(CrearArbolPartidas::class);
        $acciones->registrar(ArmarArbolDesdePresupuesto::class);
        $acciones->registrar(ArmarArbolDesdePlantilla::class);
        $acciones->registrar(EditarPartidaControl::class);
        $acciones->registrar(AgregarPartida::class);
        $acciones->registrar(FijarPresupuestado::class);
        $acciones->registrar(DesactivarPartida::class);
        // Plan 2.6, D5: registrada con su permiso y sus campos; rechaza hasta la tarea 2.8.
        $acciones->registrar(ReclasificarGasto::class);
        // Plan 2.9: el catálogo de insumos y el presupuesto con APU. «Ofertar» y «Anular» las registra el motor.
        $acciones->registrar(CrearInsumo::class);
        $acciones->registrar(EditarInsumo::class);
        $acciones->registrar(DesactivarInsumo::class);
        $acciones->registrar(CrearPresupuesto::class);
        $acciones->registrar(EditarEncabezadoPresupuesto::class);
        $acciones->registrar(AgregarTituloPresupuesto::class);
        $acciones->registrar(QuitarTituloPresupuesto::class);
        $acciones->registrar(AgregarPartidaPresupuesto::class);
        $acciones->registrar(EditarPartidaPresupuesto::class);
        $acciones->registrar(QuitarPartidaPresupuesto::class);
        $acciones->registrar(AgregarInsumoApu::class);
        $acciones->registrar(EditarInsumoApu::class);
        $acciones->registrar(QuitarInsumoApu::class);
        $acciones->registrar(CambiarUsaApu::class);
        $acciones->registrar(NuevaVersionPresupuesto::class);
        $acciones->registrar(TraerPresupuestoAlProyecto::class);
    }

    /**
     * Plan 2.2, D6: la alerta «Partida excedida o al 90 %», su grupo de avisos y su umbral. Plan 2.6, D6: las del gasto
     * no presupuestado; el posible adicional va a Gerencia General mientras no exista Administración de Contratos
     * (`docs/operaciones/antes-de-datos-reales.md`).
     */
    private function declararAlertas(): void
    {
        $this->app->make(RegistroAvisos::class)->registrarGrupo(new GrupoAvisos('presupuestos', 'Presupuesto y costo'));
        // Plan 2.9, D4 y D5: las horas de una jornada para el rendimiento por día, y los decimales del P.U. (como S10).
        $parametros = $this->app->make(RegistroParametros::class);
        [$grupo, $clave] = explode('.', ConsultaPresupuestos::PARAMETRO_JORNADA);
        $parametros->declarar(new DefinicionParametro($grupo, $clave, TipoParametro::Decimal, '8.00', 'Horas de una jornada, para el rendimiento por día en el análisis de precios unitarios.'));
        [$grupo, $clave] = explode('.', ConsultaPresupuestos::PARAMETRO_DECIMALES);
        $parametros->declarar(new DefinicionParametro($grupo, $clave, TipoParametro::Entero, 2, 'Decimales del precio unitario de una partida del presupuesto (de 0 a 4).'));
        [$grupo, $clave] = explode('.', AvisoDeSaldos::PARAMETRO_UMBRAL);
        $this->app->make(RegistroParametros::class)->declarar(
            new DefinicionParametro($grupo, $clave, TipoParametro::Decimal, '90.00', 'Porcentaje de lo presupuestado de una partida desde el que se avisa que está por excederse.'),
        );
        // Plan 2.61, D11: el semáforo de la proyección al cierre, en puntos sobre lo presupuestado.
        [$grupo, $clave] = explode('.', DineroDelProyecto::PARAMETRO_AMBAR);
        $parametros->declarar(new DefinicionParametro($grupo, $clave, TipoParametro::Decimal, '2.00', 'Puntos sobre lo presupuestado desde los que la proyección al cierre de un proyecto se pinta en ámbar.'));
        [$grupo, $clave] = explode('.', DineroDelProyecto::PARAMETRO_ROJO);
        $parametros->declarar(new DefinicionParametro($grupo, $clave, TipoParametro::Decimal, '6.00', 'Puntos sobre lo presupuestado desde los que la proyección al cierre de un proyecto se pinta en rojo.'));
        $this->app->make(RegistroAlertas::class)->registrar(new DefinicionAlerta(
            AvisoDeSaldos::ALERTA,
            'Partida excedida o al 90 %',
            'Al aprobar una línea de costo que deja una partida de control al 90 % (o al porcentaje configurado) o excedida.',
            'presupuestos',
            'heroicon-o-chart-pie',
            'La partida {partida} de {proyecto} está al {porcentaje}',
            'Revisa el saldo de la partida antes de comprometer más.',
            ['partida' => 'Partida de control', 'proyecto' => 'Proyecto', 'porcentaje' => 'Porcentaje usado'],
            [],
            destinatarioPropio: 'al responsable de la partida',
        ));
        $this->app->make(RegistroAlertas::class)->registrar(new DefinicionAlerta(
            AvisoDeGastosNoPresupuestados::ALERTA_REGISTRADO,
            'Gasto no presupuestado registrado',
            'Al enviar a aprobación un documento con una línea cargada a la PC-99 de un proyecto.',
            'presupuestos',
            'heroicon-o-receipt-percent',
            'Gasto no presupuestado en {proyecto}: {motivo}',
            'Se envió a aprobación un gasto que no estaba en el presupuesto del proyecto.',
            ['proyecto' => 'Proyecto', 'motivo' => 'Motivo'],
            [],
            destinatarioPropio: 'al jefe del proyecto (responsable de su PC-99)',
        ));
        $this->app->make(RegistroAlertas::class)->registrar(new DefinicionAlerta(
            AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL,
            'Posible adicional detectado',
            'Al aprobar un gasto no presupuestado cuyo motivo es un cambio pedido por el cliente: se puede cobrar con una adenda.',
            'presupuestos',
            'heroicon-o-document-plus',
            'Posible adicional en {proyecto}',
            'Se aprobó un gasto por un cambio que pidió el cliente ({motivo}). Revisa si se cobra con una adenda.',
            ['proyecto' => 'Proyecto', 'motivo' => 'Motivo'],
            [SeguridadService::ROL_GERENCIA_GENERAL],
            destinatarioPropio: 'al jefe del proyecto (responsable de su PC-99)',
        ));
    }
}
