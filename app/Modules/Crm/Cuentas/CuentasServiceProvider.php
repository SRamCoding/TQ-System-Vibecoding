<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas;

use App\Modules\Crm\Cuentas\Acciones\CrearCuenta;
use App\Modules\Crm\Cuentas\Acciones\DesactivarCuenta;
use App\Modules\Crm\Cuentas\Acciones\EditarCuenta;
use App\Modules\Crm\Cuentas\Acciones\EditarCuentaPropia;
use App\Modules\Crm\Cuentas\Acciones\MarcarCliente;
use App\Modules\Crm\Cuentas\Acciones\QuitarMarcaCliente;
use App\Modules\Crm\Cuentas\Acciones\ReactivarCuenta;
use App\Modules\Crm\Cuentas\Acciones\ReasignarCuentas;
use App\Modules\Crm\Cuentas\Actividad\ActividadDeCuentas;
use App\Modules\Crm\Cuentas\Actividad\OportunidadEnlazable;
use App\Modules\Crm\Cuentas\Actividad\OportunidadSinModulo;
use App\Modules\Crm\Cuentas\Alertas\CuentasConEjecutivoInactivo;
use App\Modules\Crm\Cuentas\Filament\CuentaComercialDeFicha;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\CuentaResource;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use App\Modules\Crm\Cuentas\Parametros\ValidadorDiasSinActividad;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\MotivosDeCuentas;
use App\Modules\Crm\Cuentas\Services\OtrosDeOrigenDeCuentas;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Catalogos\RegistroOtrosDeCatalogo;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Navegacion\AnimacionCaja;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Notificaciones\DefinicionAviso;
use App\Modules\Nucleo\Notificaciones\GrupoAvisos;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\Parties\PestanaFicha;
use App\Modules\Nucleo\Parties\RegistroPestanasFicha;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Crm/Cuentas (plan 2.20, CRM-01): la ficha comercial de una persona o empresa, con su ejecutivo y su origen. Su dueño
 * es el área 2, Comercial (ADR 0020). Sus pantallas se registran en `register()`, antes de que el panel se arme.
 */
final class CuentasServiceProvider extends ServiceProvider
{
    /** Lista, ficha, caja y pestaña en Personas y empresas (D14). */
    public const string PERMISO_VER = 'crm.cuentas.ver';

    /** Motivos para desactivar una cuenta (D9), con su «Otro». */
    public const string FAMILIA_DESACTIVAR = 'crm.cuentas.desactivar';

    /** El motivo de partida que permite desactivar también la ficha (D9.2): su código no cambia aunque se renombre. */
    public const string MOTIVO_CREADA_POR_ERROR = 'creada_por_error_ruc_equivocado';

    /** Días sin contacto desde los que una cuenta sale en «Sin actividad» (D11). */
    public const string PARAMETRO_DIAS_SIN_ACTIVIDAD = 'crm.dias_sin_actividad';

    /** El aviso al ejecutivo que recibe cuentas (D7): uno por reasignación. */
    public const string AVISO_ASIGNADAS = 'crm.cuentas-asignadas';

    /** La alerta diaria de cuentas con ejecutivo inactivo (D7). */
    public const string ALERTA_EJECUTIVO_INACTIVO = 'crm.cuentas-ejecutivo-inactivo';

    public function register(): void
    {
        // Registros donde los demás módulos se enchufan (D11 y D12): uno por aplicación.
        $this->app->singleton(RegistroPestanasCuenta::class);
        $this->app->singleton(ActividadDeCuentas::class);
        // Plan 2.21, D13.3: Oportunidades registra la real; si no, «Oportunidad #12».
        $this->app->bindIf(OportunidadEnlazable::class, OportunidadSinModulo::class);
        // D11: las pantallas se declaran aquí, antes de que el panel se arme (como el núcleo).
        $this->app->make(RegistroPantallas::class)->registrar('app', recursos: [CuentaResource::class]);
    }

    public function boot(): void
    {
        Relation::morphMap(['cuenta' => Cuenta::class, 'cuenta_marca_cliente' => CuentaMarcaCliente::class]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'cuentas');
        // D13: «Cuenta comercial» en la ficha de Personas y empresas, solo en las de RUC.
        $this->app->make(RegistroPestanasFicha::class)->registrar(new PestanaFicha(
            'cuenta-comercial', 'Cuenta comercial', self::PERMISO_VER, CuentaComercialDeFicha::class, 'heroicon-o-building-storefront', orden: 10, tiposDocumento: [Catalogo::RUC],
        ));

        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar(self::PERMISO_VER, 'Ver cuentas');
        $permisos->declarar(CuentasService::PERMISO_ATENDER, 'Atender cuentas (ser su ejecutivo)');

        $this->app->make(RegistroParametros::class)->declarar(
            new DefinicionParametro('crm', 'dias_sin_actividad', TipoParametro::Entero, 90, 'Días sin contacto desde los que una cuenta sale en «Sin actividad» (de 7 a 365). Una cuenta sin ningún contacto cuenta desde que se creó.', validador: ValidadorDiasSinActividad::class),
        );

        // D9: los motivos con que nace la lista (MotivoInicial); el sincronizador no pisa lo editado en Configuración.
        $documentos = $this->app->make(RegistroDocumentos::class);
        $documentos->fuenteDeMotivos(MotivosDeCuentas::class);
        // Plan 2.0, D5: lo escrito en el origen «Otro».
        $this->app->make(RegistroOtrosDeCatalogo::class)->registrar(Catalogo::OrigenesComerciales, OtrosDeOrigenDeCuentas::class);
        $documentos->familiaDeMotivos(self::FAMILIA_DESACTIVAR, 'Desactivar · cuenta', [
            new MotivoInicial('dejo_de_operar', 'Dejó de operar'),
            new MotivoInicial(self::MOTIVO_CREADA_POR_ERROR, 'Creada por error (RUC equivocado)'),
            new MotivoInicial('ya_no_es_mercado_para_techquk', 'Ya no es mercado para Techquk'),
        ]);

        $acciones = $this->app->make(RegistroAcciones::class);
        foreach ([CrearCuenta::class, EditarCuenta::class, EditarCuentaPropia::class, ReasignarCuentas::class, DesactivarCuenta::class, ReactivarCuenta::class, MarcarCliente::class, QuitarMarcaCliente::class] as $accion) {
            $acciones->registrar($accion);
        }

        $permisos->nombrar('crm.cuentas.crear', 'Crear cuentas');
        $permisos->nombrar('crm.cuentas.editar', 'Editar cualquier cuenta');
        $permisos->nombrar('crm.cuentas.editar-propias', 'Editar las cuentas que atiendo');
        $permisos->nombrar('crm.cuentas.reasignar', 'Reasignar cuentas');
        $permisos->nombrar('crm.cuentas.desactivar', 'Desactivar y reactivar cuentas');
        $permisos->nombrar('crm.cuentas.marcar-cliente', 'Marcar cuentas como cliente a mano');
        $permisos->nombrar('crm.cuentas.quitar-marca-cliente', 'Quitar la marca de cliente puesta a mano');

        $this->declararAvisosYAlertas();

        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'cuentas', 'Cuentas', '/cuentas', permiso: self::PERMISO_VER, area: AreaModulo::Crm,
            icono: 'heroicon-o-building-storefront',
            descripcion: 'Los clientes y prospectos, quién atiende a cada uno y de dónde llegaron',
            // D14.5: solo lo que esta tarea responde.
            preguntas: ['¿Esta empresa ya es cuenta y quién la atiende?', '¿Cuántos clientes nuevos tuvimos este mes?', '¿Qué cuentas tiene cada ejecutivo?', '¿De dónde nos llegan las cuentas?'],
            animacion: AnimacionCaja::Pulso,
        ));
    }

    /** D7: el aviso de cuentas asignadas y la alerta de ejecutivo inactivo, en el grupo «Comercial». */
    private function declararAvisosYAlertas(): void
    {
        $avisos = $this->app->make(RegistroAvisos::class);
        $avisos->registrarGrupo(new GrupoAvisos('comercial', 'Comercial'));
        $avisos->registrar(new DefinicionAviso(self::AVISO_ASIGNADAS, 'comercial', icono: 'heroicon-o-arrows-right-left'));

        $alertas = $this->app->make(RegistroAlertas::class);
        $alertas->registrar(new DefinicionAlerta(
            self::ALERTA_EJECUTIVO_INACTIVO,
            'Cuentas con ejecutivo inactivo',
            'Cada mañana, si hay cuentas cuyo ejecutivo ya no está activo en el sistema. No se reasignan solas.',
            'comercial',
            'heroicon-o-user-minus',
            'Hay {cantidad} cuentas con ejecutivo inactivo',
            'Su ejecutivo ya no está activo en el sistema. Reasígnalas en Cuentas.',
            ['cantidad' => 'Cantidad de cuentas'],
            // De fábrica, Gerencia General: el rol de Gerencia Comercial aún no existe (pendiente «Grilla de roles real»).
            [SeguridadService::ROL_GERENCIA_GENERAL],
        ));
        $alertas->registrarFuente(CuentasConEjecutivoInactivo::class);
    }
}
