<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones;

use App\Modules\Crm\Cotizaciones\Acciones\AceptarCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\AgregarLineaCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\AnularAceptacionCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\CrearCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\EditarCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\EditarLineaCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\EmitirCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\EnlazarPresupuestoCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\NuevaVersionCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\QuitarLineaCotizacion;
use App\Modules\Crm\Cotizaciones\Acciones\SolicitarAprobacionCotizacion;
use App\Modules\Crm\Cotizaciones\Console\VencerCotizaciones;
use App\Modules\Crm\Cotizaciones\Efectos\CongelarAlEmitir;
use App\Modules\Crm\Cotizaciones\Efectos\GuardarPdfAlEmitir;
use App\Modules\Crm\Cotizaciones\Efectos\MarcarAceptada;
use App\Modules\Crm\Cotizaciones\Efectos\OfertarPresupuesto;
use App\Modules\Crm\Cotizaciones\Efectos\PedidoAceptacion;
use App\Modules\Crm\Cotizaciones\Efectos\QuitarAceptacion;
use App\Modules\Crm\Cotizaciones\Efectos\ReemplazarVersionAnterior;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\CotizacionesDeCuenta;
use App\Modules\Crm\Cotizaciones\Filament\CotizacionesDeOportunidad;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Impedimentos\AceptadaImpideAnular;
use App\Modules\Crm\Cotizaciones\Impedimentos\AceptadaImpidePerder;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Crm\Cotizaciones\Parametros\ValidadorDiasCotizacion;
use App\Modules\Crm\Cotizaciones\Parametros\ValidadorPorcentajeMargen;
use App\Modules\Crm\Cotizaciones\Requisitos\UnaCotizacionAceptada;
use App\Modules\Crm\Cotizaciones\Requisitos\UnaCotizacionEmitida;
use App\Modules\Crm\Cotizaciones\Services\CotizacionDeOportunidadEnBase;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\ResumenCotizacionParaAprobar;
use App\Modules\Crm\Cuentas\Pestanas\PestanaCuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentosDeOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\PestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Nucleo\Aprobaciones\RegistroResumenesAprobacion;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Navegacion\AnimacionCaja;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Crm/Cotizaciones (plan 2.22, CRM-03): la propuesta al cliente, con versiones, margen, aprobación bajo el mínimo y
 * aceptación. Su dueño es el área 2, Comercial (ADR 0020).
 */
final class CotizacionesServiceProvider extends ServiceProvider
{
    public const string PERMISO_VER = 'crm.cotizaciones.ver';

    /** Lista de motivos de la versión nueva (D5): el DBML manda el motivo a catálogo (plan 1.5, D9). */
    public const string FAMILIA_NUEVA_VERSION = 'crm.cotizaciones.nueva-version';

    public const string PARAMETRO_DIAS_VALIDEZ = 'crm.cotizacion_dias_validez';

    /** D19: la caja, la pantalla y su pestaña comparten el ícono (interfaz.md). */
    public const string ICONO = 'heroicon-o-document-chart-bar';

    public const string PARAMETRO_DIAS_POR_VENCER = 'crm.cotizacion_dias_por_vencer';

    public const string PARAMETRO_MARGEN_OBJETIVO = 'crm.cotizacion_margen_objetivo';

    public const string PARAMETRO_MARGEN_MINIMO = 'crm.cotizacion_margen_minimo';

    /** Observación 9.5: el margen mínimo de Techquk y todo lo que lo usa (cambia sin tocar código). */
    public const string DESCRIPCION_MARGEN_MINIMO = 'Margen mínimo (%) de Techquk. Bajo él: la etiqueta de margen de las tarjetas de Oportunidades sale en coral, una cotización pide aprobación antes de emitirse (Configuración → Aprobaciones) y «¿Cuánto bajar?» de la postulación marca en coral.';

    public const string PARAMETRO_EXIGIR_EMITIDA = 'crm.cotizacion_exigir_emitida_para_propuesta';

    public const string PARAMETRO_EXIGIR_ACEPTADA = 'crm.cotizacion_exigir_aceptada_para_ganar';

    public function register(): void
    {
        // Lo que «Aceptar» le deja a su efecto mientras corre la transición (uno por petición).
        $this->app->scoped(PedidoAceptacion::class);
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [CotizacionResource::class]);
        // D12 (C3): la cotización aceptada que Oportunidades pregunta al ganar.
        $this->app->bind(CotizacionDeOportunidad::class, CotizacionDeOportunidadEnBase::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['cotizacion' => Cotizacion::class, 'cotizacion_linea' => LineaDeCotizacion::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'cotizaciones');

        $this->app->make(RegistroPermisos::class)->declarar(self::PERMISO_VER, 'Ver cotizaciones');
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'cotizaciones', 'Cotizaciones', '/cotizaciones', permiso: self::PERMISO_VER, area: AreaModulo::Crm,
            icono: self::ICONO,
            descripcion: 'Las propuestas al cliente por versión, con su margen, su aprobación y la que aceptó',
            preguntas: ['¿Qué cotizaciones esperan aprobación?', '¿Cuáles vencen esta semana?', '¿Qué cambió entre una versión y otra?'],
            animacion: AnimacionCaja::Pulso,
        ));
        $this->declararParametros();
        $this->declararMotivos();
        $this->declararDocumento();
        $this->declararAcciones();
        $this->registrarEnOportunidades();

        if ($this->app->runningInConsole()) {
            $this->commands([VencerCotizaciones::class]);
        }

        // D10: el vencimiento automático, cada día al empezar, en un solo servidor.
        $this->callAfterResolving(Schedule::class, function (Schedule $programa): void {
            $programa->command(VencerCotizaciones::class)
                ->dailyAt('00:10')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /** D5: el documento, sus efectos y la lista de motivos de rechazo. */
    private function declararDocumento(): void
    {
        $documentos = $this->app->make(RegistroDocumentos::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_CONGELAR, CongelarAlEmitir::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_OFERTAR_PRESUPUESTO, OfertarPresupuesto::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_REEMPLAZAR_ANTERIOR, ReemplazarVersionAnterior::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_GUARDAR_PDF, GuardarPdfAlEmitir::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_MARCAR_ACEPTADA, MarcarAceptada::class);
        $documentos->efecto(DefinicionCotizacion::EFECTO_QUITAR_ACEPTACION, QuitarAceptacion::class);
        $documentos->registrar(new DefinicionCotizacion);
        // P6: su resumen en «Mis aprobaciones» del núcleo.
        $this->app->make(RegistroResumenesAprobacion::class)->registrar('cotizacion', ResumenCotizacionParaAprobar::class);

        $clave = DefinicionCotizacion::CLAVE;
        $documentos->familiaDeMotivos("{$clave}.rechazar", 'Rechazo del cliente · cotización', [
            new MotivoInicial('precio', 'Precio'), new MotivoInicial('plazo', 'Plazo'),
            new MotivoInicial('especificaciones', 'Especificaciones'), new MotivoInicial('sin_presupuesto', 'El cliente no tiene presupuesto'),
        ]);
        $documentos->familiaDeMotivos("{$clave}.anular", 'Anular · cotización', [new MotivoInicial('cotizacion_duplicada', 'Cotización duplicada')]);
        $documentos->familiaDeMotivos("{$clave}.reabrir", 'Volver a borrador · cotización', [new MotivoInicial('cambiar_precios', 'Cambiar precios o condiciones antes de emitir')]);
        $documentos->familiaDeMotivos("{$clave}.anular_aceptacion", 'Anular aceptación · cotización', [new MotivoInicial('aceptada_por_equivocacion', 'Aceptada por equivocación')]);
    }

    /** D11 y D13: los requisitos de etapa y lo que impide perder o anular la oportunidad con una aceptada. */
    private function registrarEnOportunidades(): void
    {
        // D19: la pestaña en la oportunidad (también la capa `cotizaciones` del despiece) y en la cuenta.
        $this->app->make(RegistroPestanasOportunidad::class)->registrar(new PestanaOportunidad(
            'cotizaciones', 'Cotizaciones', self::PERMISO_VER, CotizacionesDeOportunidad::class, self::ICONO, orden: 10,
        ));
        $this->app->make(RegistroPestanasCuenta::class)->registrar(new PestanaCuenta(
            'cotizaciones', 'Cotizaciones', self::PERMISO_VER, CotizacionesDeCuenta::class, self::ICONO, orden: 20,
        ));
        $requisitos = $this->app->make(RequisitosDeEtapa::class);
        $requisitos->registrar(new UnaCotizacionEmitida);
        $requisitos->registrar(new UnaCotizacionAceptada);
        $this->app->make(ImpedimentosDeOportunidad::class)->registrar(new AceptadaImpidePerder);
        $this->app->make(RegistroDocumentos::class)->revisionAntesDeAnular('crm.oportunidades.oportunidad', AceptadaImpideAnular::class); // la clave de la oportunidad (D13)
    }

    /** D21: las acciones y el nombre legible de sus permisos. */
    private function declararAcciones(): void
    {
        $acciones = $this->app->make(RegistroAcciones::class);
        foreach ([
            CrearCotizacion::class, EditarCotizacion::class, AgregarLineaCotizacion::class, EnlazarPresupuestoCotizacion::class,
            EditarLineaCotizacion::class, QuitarLineaCotizacion::class, NuevaVersionCotizacion::class,
            SolicitarAprobacionCotizacion::class, EmitirCotizacion::class, AceptarCotizacion::class, AnularAceptacionCotizacion::class,
        ] as $accion) {
            $acciones->registrar($accion);
        }

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(CotizacionesService::PERMISO_TODAS, 'Cotizar sobre las oportunidades de cualquier ejecutivo');
        $permisos->nombrar(CotizacionesService::PERMISO_CREAR, 'Crear cotizaciones');
        $permisos->nombrar(CotizacionesService::PERMISO_EDITAR, 'Editar el borrador de cotizaciones');
        $permisos->nombrar(CotizacionesService::PERMISO_NUEVA_VERSION, 'Hacer versiones nuevas de cotizaciones');
        foreach ([
            'solicitar_aprobacion' => 'Solicitar la aprobación de cotizaciones', 'reabrir' => 'Volver a borrador cotizaciones aprobadas',
            'emitir' => 'Emitir cotizaciones', 'aceptar' => 'Aceptar cotizaciones', 'rechazar' => 'Registrar el rechazo de cotizaciones',
            'anular' => 'Anular cotizaciones', 'anular_aceptacion' => 'Anular la aceptación de cotizaciones',
        ] as $codigo => $nombre) {
            $permisos->nombrar(DefinicionCotizacion::CLAVE.".{$codigo}", $nombre);
        }
    }

    /** D10, D11 y D14 (los nombres van en snake_case: `crm.cotizacion_…`). */
    private function declararParametros(): void
    {
        $this->app->make(RegistroParametros::class)->declarar(
            new DefinicionParametro('crm', 'cotizacion_dias_validez', TipoParametro::Entero, 15,
                'Días calendario que vale una cotización desde que se emite, si no se escribe otro en ella.',
                validador: ValidadorDiasCotizacion::class),
            new DefinicionParametro('crm', 'cotizacion_dias_por_vencer', TipoParametro::Entero, 3,
                'Una cotización emitida sale en «Por vencer» cuando le quedan estos días o menos.',
                validador: ValidadorDiasCotizacion::class),
            new DefinicionParametro('crm', 'cotizacion_margen_objetivo', TipoParametro::Decimal, '25.00',
                'Margen (%) al que apunta una cotización. Solo se muestra como referencia.',
                esSensible: true, validador: ValidadorPorcentajeMargen::class),
            new DefinicionParametro('crm', 'cotizacion_margen_minimo', TipoParametro::Decimal, '12.00',
                self::DESCRIPCION_MARGEN_MINIMO,
                esSensible: true, validador: ValidadorPorcentajeMargen::class),
            new DefinicionParametro('crm', 'cotizacion_exigir_emitida_para_propuesta', TipoParametro::Booleano, true,
                'Si una oportunidad pasa a «Propuesta enviada» solo con alguna cotización emitida.'),
            new DefinicionParametro('crm', 'cotizacion_exigir_aceptada_para_ganar', TipoParametro::Booleano, true,
                'Si una oportunidad se gana solo con una cotización aceptada (con su documento de respaldo).'),
        );
    }

    /** D5 y D18: los motivos con que nacen las listas; Gerencia Comercial los ajusta en Configuración. */
    private function declararMotivos(): void
    {
        $this->app->make(RegistroDocumentos::class)->familiaDeMotivos(self::FAMILIA_NUEVA_VERSION, 'Versión nueva · cotización', [
            new MotivoInicial('pedido_del_cliente', 'Cambio pedido por el cliente'),
            new MotivoInicial('cambio_de_alcance', 'Cambio de alcance'),
            new MotivoInicial('correccion', 'Corrección de precios o cantidades'),
        ]);
    }
}
