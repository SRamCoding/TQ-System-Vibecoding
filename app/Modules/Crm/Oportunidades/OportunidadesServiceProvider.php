<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades;

use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Cuentas\Events\EjecutivoDeCuentaCambiado;
use App\Modules\Crm\Cuentas\Pestanas\BotonCuenta;
use App\Modules\Crm\Cuentas\Pestanas\PestanaCuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Oportunidades\Acciones\CalificarOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\CambiarFotoOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\CrearOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\EditarOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\EditarOportunidadPropia;
use App\Modules\Crm\Oportunidades\Acciones\EnviarPropuestaOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\GanarOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\GuardarCompetidor;
use App\Modules\Crm\Oportunidades\Acciones\GuardarCompetidorPropio;
use App\Modules\Crm\Oportunidades\Acciones\NegociarOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\PerderOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\PonerEnMiTablero;
use App\Modules\Crm\Oportunidades\Acciones\QuitarCompetidor;
use App\Modules\Crm\Oportunidades\Acciones\QuitarCompetidorPropio;
use App\Modules\Crm\Oportunidades\Acciones\QuitarDeMiTablero;
use App\Modules\Crm\Oportunidades\Acciones\QuitarFotoOportunidad;
use App\Modules\Crm\Oportunidades\Acciones\ReasignarOportunidades;
use App\Modules\Crm\Oportunidades\Efectos\EntraEnEtapa;
use App\Modules\Crm\Oportunidades\Efectos\GanadaAvisaAlErp;
use App\Modules\Crm\Oportunidades\Efectos\GanadaMarcaCuentaCliente;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Filament\OportunidadesDeCuenta;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Impedimentos\ImpedimentosDeOportunidad;
use App\Modules\Crm\Oportunidades\Listeners\TraspasaOportunidadesAlReasignarCuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\Models\OportunidadEnTablero;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorDiasMaximos;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorPeriodoCierre;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadCalificada;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadNegociacion;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadPropuestaEnviada;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadProspecto;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\DecisorIdentificado;
use App\Modules\Crm\Oportunidades\Requisitos\NecesidadConfirmada;
use App\Modules\Crm\Oportunidades\Requisitos\PresupuestoConfirmado;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\AporteOportunidadesACuentas;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\ContratosSinModulo;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\CotizacionesSinModulo;
use App\Modules\Crm\Oportunidades\Services\EtiquetaDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\OtrosDeOrigenDeOportunidades;
use App\Modules\Crm\Oportunidades\Services\TableroDeGemas;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Catalogos\RegistroOtrosDeCatalogo;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Imagenes\RegistroImagenes;
use App\Modules\Nucleo\Navegacion\AnimacionCaja;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Crm/Oportunidades (plan 2.21, CRM-02): el embudo comercial, de prospecto a ganada o perdida. Su dueño es el área 2,
 * Comercial (ADR 0020). Se fusiona después de Cuentas (2.20) y antes de la 2.0b.
 */
final class OportunidadesServiceProvider extends ServiceProvider
{
    /** Lista, tablero, ficha, caja y pestaña en la cuenta (D10). Todas: el equipo comercial ve todo el embudo. */
    public const string PERMISO_VER = 'crm.oportunidades.ver';

    /** D5: el traspaso de oportunidades al reasignar una cuenta agotó sus reintentos. */
    public const string ALERTA_TRASPASO_FALLIDO = 'crm.oportunidades.traspaso-fallido';

    /** Qué cerradas muestra la columna «Cierre» del tablero (D11.3). */
    public const string PARAMETRO_PERIODO_CIERRE = 'crm.tablero_cierre_periodo';

    /** @var array<string, class-string> el validador de la probabilidad de cada etapa (D2) */
    private const array VALIDADORES_PROBABILIDAD = [
        'prospecto' => ValidadorProbabilidadProspecto::class,
        'calificada' => ValidadorProbabilidadCalificada::class,
        'propuesta_enviada' => ValidadorProbabilidadPropuestaEnviada::class,
        'negociacion' => ValidadorProbabilidadNegociacion::class,
    ];

    public function register(): void
    {
        // MV-057: las direcciones de las vistas que ya no existen (Lista, Faja 3D) y la vieja de Tarjetas llevan a Mis
        // oportunidades; la del Tablero, al Embudo. Antes que las del panel, para que `/oportunidades/{record}` no las tome.
        $this->app->booting(fn () => Route::middleware('web')->get('oportunidades/{vista}', fn (string $vista) => redirect($vista === 'tablero' ? '/oportunidades/embudo' : '/oportunidades'))->whereIn('vista', ['lista', 'faja', 'tarjetas', 'tablero']));

        // Registros donde los demás módulos se enchufan (D9, D12): uno por aplicación.
        $this->app->singleton(RequisitosDeEtapa::class);
        $this->app->singleton(ImpedimentosDeOportunidad::class);
        $this->app->singleton(SeccionesDeOportunidad::class);
        $this->app->singleton(RegistroPestanasOportunidad::class);
        // C3 ampliada (D1): Contratos registra la real; mientras, «no hay contrato».
        $this->app->bindIf(ContratosDeOportunidad::class, ContratosSinModulo::class);
        $this->app->bindIf(Services\ProyectoAlGanar::class, Services\SinProyectoAlGanar::class);
        $this->app->bindIf(Services\ProximaAccionDeOportunidades::class, Services\SinProximaAccion::class);
        $this->app->singleton(Services\PedidoGanar::class);
        // Plan 2.22, D12: Cotizaciones registra la real; mientras, «no hay aceptada».
        $this->app->bindIf(CotizacionDeOportunidad::class, CotizacionesSinModulo::class);
        // D13.3: la etiqueta real de la oportunidad en el historial de la cuenta (Cuentas trae «Oportunidad #12»).
        $this->app->bind(OportunidadEnlazable::class, EtiquetaDeOportunidad::class);
        // D11: las pantallas se declaran aquí, antes de que el panel se arme (como Cuentas).
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [OportunidadResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['oportunidad' => Oportunidad::class, 'oportunidad_competidor' => OportunidadCompetidor::class, 'oportunidad_en_tablero' => OportunidadEnTablero::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'oportunidades');
        // Plan 2.0, D5: lo escrito en el origen «Otro».
        $this->app->make(RegistroOtrosDeCatalogo::class)->registrar(Catalogo::OrigenesComerciales, OtrosDeOrigenDeOportunidades::class);
        // Componentes anónimos del módulo (plan 2.21c): <x-oportunidades::gema />.
        Blade::anonymousComponentPath(__DIR__.'/resources/views/componentes', 'oportunidades');

        $this->app->make(RegistroPermisos::class)->declarar(self::PERMISO_VER, 'Ver oportunidades');
        // Plan 2.21c, D15: cómo ve cada persona Ganadas y Perdidas en el tablero, recordado en su cuenta.
        $this->app->make(RegistroPreferencias::class)->declarar(...TableroDeGemas::preferencias());
        $this->declararParametros();
        $this->declararDocumento();
        $this->declararAcciones();
        // D13: las abiertas en la lista y la cartera de Cuentas, y el impedimento para desactivarla.
        $this->app->make(ActividadDeCuentas::class)->registrar(new AporteOportunidadesACuentas);
        $this->registrarEnLaCuenta();

        // D5: al reasignar una cuenta, sus abiertas del ejecutivo anterior pasan al nuevo (en la cola).
        Event::listen(EjecutivoDeCuentaCambiado::class, TraspasaOportunidadesAlReasignarCuenta::class);
        $this->app->make(RegistroAlertas::class)->registrar(new DefinicionAlerta(
            self::ALERTA_TRASPASO_FALLIDO,
            'Oportunidades sin traspasar',
            'Si al reasignar una cuenta no se pudieron pasar sus oportunidades abiertas al nuevo ejecutivo, después de varios intentos.',
            'comercial',
            'heroicon-o-exclamation-triangle',
            'No se pudieron pasar las oportunidades de {cuenta}',
            'No se pudieron pasar las oportunidades de la cuenta {cuenta} de {anterior} a {nuevo}. Reasígnalas a mano en Oportunidades.',
            ['cuenta' => 'La cuenta', 'anterior' => 'Ejecutivo anterior', 'nuevo' => 'Ejecutivo nuevo'],
            // De fábrica, Gerencia General: el rol de Gerencia Comercial aún no existe (pendiente «Grilla de roles real»).
            [SeguridadService::ROL_GERENCIA_GENERAL],
        ));

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'oportunidades', 'Oportunidades', '/oportunidades', permiso: self::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-presentation-chart-line',
            descripcion: 'El embudo comercial: qué negocios están en curso, en qué etapa y cuánto valen',
            preguntas: ['¿Cuánto hay en el embudo y cuánto vale ponderado?', '¿Qué oportunidades cierran este mes?', '¿Cuáles están estancadas?', '¿Por qué perdemos?'],
            animacion: AnimacionCaja::Pulso,
        ));
    }

    /** D11.4 y D11.9: la pestaña «Oportunidades» y el botón «Nueva oportunidad» en la ficha de la cuenta. */
    private function registrarEnLaCuenta(): void
    {
        $cuenta = $this->app->make(RegistroPestanasCuenta::class);
        $cuenta->registrar(new PestanaCuenta(
            'oportunidades', 'Oportunidades', self::PERMISO_VER, OportunidadesDeCuenta::class, 'heroicon-o-presentation-chart-line', orden: 10,
        ));
        $cuenta->registrarBoton(new BotonCuenta('nueva-oportunidad', OportunidadesService::PERMISO_CREAR, fn (int $cuentaId): Action => PanelesOportunidad::nueva(
            $cuentaId,
            function (): void {
                Notification::make()->success()->title('Oportunidad creada.')->send();
                // La ficha de la cuenta y su pestaña se vuelven a dibujar con la nueva.
                Livewire::current()?->dispatch('cuenta-cambiada');
            },
        ), orden: 10));
    }

    /** D1: el documento, sus efectos y sus listas de motivos; D9: los requisitos propios para calificar. */
    private function declararDocumento(): void
    {
        $documentos = $this->app->make(RegistroDocumentos::class);
        $documentos->efecto(DefinicionOportunidad::EFECTO_ENTRA_EN_ETAPA, EntraEnEtapa::class);
        $documentos->efecto(DefinicionOportunidad::EFECTO_MARCA_CLIENTE, GanadaMarcaCuentaCliente::class);
        $documentos->efecto(DefinicionOportunidad::EFECTO_AVISA_ERP, GanadaAvisaAlErp::class);
        $documentos->registrar(new DefinicionOportunidad);

        // Los motivos con que nace cada lista (MotivoInicial, plan 2.6): el sincronizador crea solo los que faltan y no
        // pisa lo editado en Configuración. Los de retroceder, a la espera de la empresa (pendiente «Embudo
        // comercial»): por ahora solo «Otro».
        $clave = DefinicionOportunidad::CLAVE;
        $documentos->familiaDeMotivos("{$clave}.perder", 'Perder · oportunidad', [
            new MotivoInicial('precio', 'Precio'), new MotivoInicial('plazo', 'Plazo'),
            new MotivoInicial('especificaciones', 'Especificaciones'), new MotivoInicial('presupuesto_cancelado', 'Presupuesto cancelado'),
            new MotivoInicial('no_respondio', 'No respondió'), new MotivoInicial('decision_interna', 'Decisión interna'),
            new MotivoInicial('proceso_desierto', 'Proceso desierto'),
        ]);
        $documentos->familiaDeMotivos("{$clave}.retroceder", 'Retroceder · oportunidad');
        $documentos->familiaDeMotivos("{$clave}.anular", 'Anular · oportunidad', [new MotivoInicial('oportunidad_duplicada', 'Oportunidad duplicada'), new MotivoInicial('cuenta_equivocada', 'Cuenta equivocada')]);
        $documentos->familiaDeMotivos("{$clave}.anular_ganada", 'Anular ganada · oportunidad', [new MotivoInicial('ganada_por_equivocacion', 'Ganada por equivocación')]);

        $requisitos = $this->app->make(RequisitosDeEtapa::class);
        foreach ([NecesidadConfirmada::class, DecisorIdentificado::class, PresupuestoConfirmado::class] as $requisito) {
            $requisitos->registrar(new $requisito);
        }
    }

    /** D10: las acciones, con el nombre legible de sus permisos. */
    private function declararAcciones(): void
    {
        $acciones = $this->app->make(RegistroAcciones::class);
        foreach ([
            CrearOportunidad::class, EditarOportunidad::class, EditarOportunidadPropia::class, ReasignarOportunidades::class,
            CalificarOportunidad::class, EnviarPropuestaOportunidad::class, NegociarOportunidad::class, GanarOportunidad::class,
            PerderOportunidad::class, GuardarCompetidor::class, GuardarCompetidorPropio::class, QuitarCompetidor::class,
            QuitarCompetidorPropio::class,
            // Plan 2.21d, D2 y D13: la foto de referencia, su ejecutivo o Gerencia Comercial.
            CambiarFotoOportunidad::class, QuitarFotoOportunidad::class,
            // D5: lo que cada persona pone en su tablero.
            PonerEnMiTablero::class, QuitarDeMiTablero::class,
        ] as $accion) {
            $acciones->registrar($accion);
        }
        $this->app->make(RegistroImagenes::class)->registrar(FotoOportunidadService::CLAVE_IMAGEN, FotoOportunidadService::rutaParaVer(...));

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->nombrar(OportunidadesService::PERMISO_CREAR, 'Crear oportunidades');
        $permisos->nombrar(SobreLoPropio::EDITAR, 'Editar cualquier oportunidad');
        $permisos->nombrar(SobreLoPropio::EDITAR_PROPIAS, 'Editar las oportunidades que atiendo');
        $permisos->nombrar(OportunidadesService::PERMISO_REASIGNAR, 'Reasignar oportunidades');
        foreach ([
            'calificar' => 'Calificar oportunidades', 'enviar_propuesta' => 'Enviar la propuesta de oportunidades',
            'negociar' => 'Pasar oportunidades a negociación', 'retroceder' => 'Retroceder la etapa de oportunidades',
            'ganar' => 'Ganar oportunidades', 'perder' => 'Perder oportunidades', 'anular' => 'Anular oportunidades',
            'anular_ganada' => 'Anular oportunidades ganadas',
        ] as $codigo => $nombre) {
            $permisos->nombrar(DefinicionOportunidad::CLAVE.".{$codigo}", $nombre);
        }
    }

    /** D2 y D11.3: la probabilidad y los días máximos de cada etapa abierta, y el período de la columna «Cierre». */
    private function declararParametros(): void
    {
        $parametros = $this->app->make(RegistroParametros::class);

        foreach (EtapaAbierta::cases() as $etapa) {
            $parametros->declarar(
                new DefinicionParametro('crm', "probabilidad_{$etapa->value}", TipoParametro::Entero, $etapa->probabilidadInicial(),
                    "Probabilidad de cierre (%) de una oportunidad en «{$etapa->etiqueta()}». De 1 a 99, sin bajar de una etapa a la siguiente. Nadie la edita en la oportunidad: el embudo ponderado se calcula con esta.",
                    validador: self::VALIDADORES_PROBABILIDAD[$etapa->value]),
                new DefinicionParametro('crm', "dias_maximos_{$etapa->value}", TipoParametro::Entero, $etapa->diasMaximosIniciales(),
                    "Días en «{$etapa->etiqueta()}» desde los que una oportunidad sale como estancada. 0: sin máximo.",
                    validador: ValidadorDiasMaximos::class),
            );
        }

        $parametros->declarar(new DefinicionParametro('crm', 'tablero_cierre_periodo', TipoParametro::Texto, 'mes',
            'Qué ganadas y perdidas muestra la columna «Cierre» del tablero: las del «mes», el «trimestre» o el «anio» en curso. Las demás, en la lista.',
            validador: ValidadorPeriodoCierre::class));
    }
}
