<?php

declare(strict_types=1);

namespace App\Modules\Nucleo;

use App\Modules\Nucleo\Acciones\ActualizarUbigeos;
use App\Modules\Nucleo\Acciones\Adjuntos\AgregarAdjunto;
use App\Modules\Nucleo\Acciones\Adjuntos\QuitarAdjunto;
use App\Modules\Nucleo\Acciones\Adjuntos\ReemplazarAdjunto;
use App\Modules\Nucleo\Acciones\AgregarMiRedSocial;
use App\Modules\Nucleo\Acciones\Alertas\ConfigurarAlerta;
use App\Modules\Nucleo\Acciones\Aplicaciones\AgregarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\CambiarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\ElegirDondeAbrirAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\MostrarAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\MoverAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\OcultarAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\PonerAplicacionPrincipal;
use App\Modules\Nucleo\Acciones\Aplicaciones\QuitarMiAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\RegistrarUsoAplicacion;
use App\Modules\Nucleo\Acciones\Aplicaciones\SubirIconoAplicacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\ActivarReglaAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\AutoaprobarAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\CrearReglaAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\DesactivarReglaAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\EditarReglaAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\FirmarAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\ObservarAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\RecalcularAprobacion;
use App\Modules\Nucleo\Acciones\Aprobaciones\RevisarAutoaprobacion;
use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisoLeido;
use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisoNoLeido;
use App\Modules\Nucleo\Acciones\Avisos\MarcarAvisosLeidos;
use App\Modules\Nucleo\Acciones\CambiarMiClave;
use App\Modules\Nucleo\Acciones\CambiarMiFoto;
use App\Modules\Nucleo\Acciones\CambiarMiRedSocial;
use App\Modules\Nucleo\Acciones\CambiarParametro;
use App\Modules\Nucleo\Acciones\CambiarParametroSensible;
use App\Modules\Nucleo\Acciones\CambiarPreferencia;
use App\Modules\Nucleo\Acciones\Catalogos\ActivarElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\BorrarElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\CrearElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\DesactivarElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\EditarElementoCatalogo;
use App\Modules\Nucleo\Acciones\Catalogos\EditarSensiblesElementoCatalogo;
use App\Modules\Nucleo\Acciones\ConfiguracionInicial\MarcarPasoListo;
use App\Modules\Nucleo\Acciones\ConfiguracionInicial\MostrarBienvenida;
use App\Modules\Nucleo\Acciones\ConfiguracionInicial\ReabrirPaso;
use App\Modules\Nucleo\Acciones\ConfiguracionInicial\SeguirDespues;
use App\Modules\Nucleo\Acciones\ElegirModulos;
use App\Modules\Nucleo\Acciones\Estados\ActivarMotivo;
use App\Modules\Nucleo\Acciones\Estados\AgregarMotivo;
use App\Modules\Nucleo\Acciones\Estados\CambiarMotivo;
use App\Modules\Nucleo\Acciones\Estados\CambiarPresentacionEstado;
use App\Modules\Nucleo\Acciones\Estados\DesactivarMotivo;
use App\Modules\Nucleo\Acciones\Ia\AgregarPrecioModeloIa;
use App\Modules\Nucleo\Acciones\Ia\AutorizarProcesoIa;
use App\Modules\Nucleo\Acciones\Ia\CambiarProcesoIa;
use App\Modules\Nucleo\Acciones\Ia\CrearModeloIa;
use App\Modules\Nucleo\Acciones\Ia\CrearProveedorIa;
use App\Modules\Nucleo\Acciones\Ia\EditarModeloIa;
use App\Modules\Nucleo\Acciones\Ia\EditarProveedorIa;
use App\Modules\Nucleo\Acciones\Ia\MarcarModeloPorDefectoIa;
use App\Modules\Nucleo\Acciones\Ia\ResolverSugerenciaIa;
use App\Modules\Nucleo\Acciones\MarcarMomentoVisto;
use App\Modules\Nucleo\Acciones\MostrarBarraModulos;
use App\Modules\Nucleo\Acciones\Organigrama\ActivarPuesto;
use App\Modules\Nucleo\Acciones\Organigrama\ActivarUnidad;
use App\Modules\Nucleo\Acciones\Organigrama\CrearPuesto;
use App\Modules\Nucleo\Acciones\Organigrama\CrearUnidad;
use App\Modules\Nucleo\Acciones\Organigrama\DesactivarPuesto;
use App\Modules\Nucleo\Acciones\Organigrama\DesactivarUnidad;
use App\Modules\Nucleo\Acciones\Organigrama\DesignarJefatura;
use App\Modules\Nucleo\Acciones\Organigrama\EditarPuesto;
use App\Modules\Nucleo\Acciones\Organigrama\EditarUnidad;
use App\Modules\Nucleo\Acciones\Parties\ActivarCuentaBancaria;
use App\Modules\Nucleo\Acciones\Parties\ActivarParty;
use App\Modules\Nucleo\Acciones\Parties\ActivarSede;
use App\Modules\Nucleo\Acciones\Parties\AgregarContacto;
use App\Modules\Nucleo\Acciones\Parties\AgregarRedSocial;
use App\Modules\Nucleo\Acciones\Parties\AgregarSede;
use App\Modules\Nucleo\Acciones\Parties\AsignarRolParty;
use App\Modules\Nucleo\Acciones\Parties\BorrarContacto;
use App\Modules\Nucleo\Acciones\Parties\BorrarCuentaBancaria;
use App\Modules\Nucleo\Acciones\Parties\BorrarParty;
use App\Modules\Nucleo\Acciones\Parties\BorrarSede;
use App\Modules\Nucleo\Acciones\Parties\CambiarRedSocial;
use App\Modules\Nucleo\Acciones\Parties\CambiarRubroParty;
use App\Modules\Nucleo\Acciones\Parties\CerrarRolParty;
use App\Modules\Nucleo\Acciones\Parties\CerrarVinculoContacto;
use App\Modules\Nucleo\Acciones\Parties\CompletarCciCuentaBancaria;
use App\Modules\Nucleo\Acciones\Parties\DesactivarCuentaBancaria;
use App\Modules\Nucleo\Acciones\Parties\DesactivarParty;
use App\Modules\Nucleo\Acciones\Parties\DesactivarSede;
use App\Modules\Nucleo\Acciones\Parties\EditarContacto;
use App\Modules\Nucleo\Acciones\Parties\EditarParty;
use App\Modules\Nucleo\Acciones\Parties\EditarSede;
use App\Modules\Nucleo\Acciones\Parties\EditarTributariosParty;
use App\Modules\Nucleo\Acciones\Parties\MarcarContactoPrincipal;
use App\Modules\Nucleo\Acciones\Parties\MarcarCuentaPrincipal;
use App\Modules\Nucleo\Acciones\Parties\QuitarRedSocial;
use App\Modules\Nucleo\Acciones\Parties\RegistrarCuentaBancaria;
use App\Modules\Nucleo\Acciones\Parties\RegistrarParty;
use App\Modules\Nucleo\Acciones\Parties\TrasladarContacto;
use App\Modules\Nucleo\Acciones\PedirCodigoClave;
use App\Modules\Nucleo\Acciones\Plantillas\ActualizarPlantilla;
use App\Modules\Nucleo\Acciones\Plantillas\DesactivarPlantilla;
use App\Modules\Nucleo\Acciones\Plantillas\EditarPlantilla;
use App\Modules\Nucleo\Acciones\Plantillas\GuardarComoPlantilla;
use App\Modules\Nucleo\Acciones\Plantillas\PublicarPlantilla;
use App\Modules\Nucleo\Acciones\QuitarMiFoto;
use App\Modules\Nucleo\Acciones\QuitarMiRedSocial;
use App\Modules\Nucleo\Acciones\RegistrarMiCumpleanos;
use App\Modules\Nucleo\Acciones\RegistrarMiTelefono;
use App\Modules\Nucleo\Acciones\RegistrarTipoCambio;
use App\Modules\Nucleo\Acciones\RenovarMiClave;
use App\Modules\Nucleo\Acciones\RestablecerPreferencias;
use App\Modules\Nucleo\Acciones\Seguridad\ActivarUsuario;
use App\Modules\Nucleo\Acciones\Seguridad\AsignarRol;
use App\Modules\Nucleo\Acciones\Seguridad\AsignarRolEnAmbito;
use App\Modules\Nucleo\Acciones\Seguridad\CambiarPermisosDeRol;
use App\Modules\Nucleo\Acciones\Seguridad\CambiarPuestoUsuario;
use App\Modules\Nucleo\Acciones\Seguridad\CrearRol;
use App\Modules\Nucleo\Acciones\Seguridad\DesactivarIncompatibilidad;
use App\Modules\Nucleo\Acciones\Seguridad\DesactivarUsuario;
use App\Modules\Nucleo\Acciones\Seguridad\QuitarRol;
use App\Modules\Nucleo\Acciones\Seguridad\QuitarRolEnAmbito;
use App\Modules\Nucleo\Acciones\Seguridad\RegistrarControlCruce;
use App\Modules\Nucleo\Acciones\Seguridad\RegistrarCumpleanosUsuario;
use App\Modules\Nucleo\Acciones\Seguridad\RegistrarIncompatibilidad;
use App\Modules\Nucleo\Acciones\Seguridad\RegistrarUsuario;
use App\Modules\Nucleo\Acciones\Seguridad\RenombrarRol;
use App\Modules\Nucleo\Acciones\SincronizarTipoCambio;
use App\Modules\Nucleo\Adjuntos\ValidadorTamanoAdjunto;
use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Aprobaciones\JefaturaQueSube;
use App\Modules\Nucleo\Aprobaciones\RegistroResumenesAprobacion;
use App\Modules\Nucleo\Aprobaciones\ReglasDeAprobacion;
use App\Modules\Nucleo\Aprobaciones\ResolutorDeFirmante;
use App\Modules\Nucleo\Catalogos\OtrasRedesSociales;
use App\Modules\Nucleo\Catalogos\RegistroOtrosDeCatalogo;
use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\Console\ActualizarCsvUbigeos;
use App\Modules\Nucleo\Console\ArchivarAvisos;
use App\Modules\Nucleo\Console\ArchivarPulse;
use App\Modules\Nucleo\Console\GerenteGeneral;
use App\Modules\Nucleo\Console\LatidoProgramador;
use App\Modules\Nucleo\Console\LeerHistorico;
use App\Modules\Nucleo\Console\PrimerAdministrador;
use App\Modules\Nucleo\Console\RecalcularNombresNormalizados;
use App\Modules\Nucleo\Console\Respaldar;
use App\Modules\Nucleo\Console\RevisarAlertas;
use App\Modules\Nucleo\Console\RevisarHuerfanosAdjuntos;
use App\Modules\Nucleo\Console\SincronizarTipoCambio as SincronizarTipoCambioComando;
use App\Modules\Nucleo\Desarrollo\Piezas\ClimaDelDibujo;
use App\Modules\Nucleo\Desarrollo\Piezas\DibujoDelSaludo;
use App\Modules\Nucleo\Desarrollo\Piezas\EstacionDelInicio;
use App\Modules\Nucleo\Desarrollo\Piezas\GranMomentoDeFecha;
use App\Modules\Nucleo\Desarrollo\Piezas\LogoDeFiesta;
use App\Modules\Nucleo\Desarrollo\Piezas\TemperaturaDelClima;
use App\Modules\Nucleo\Desarrollo\RegistroPiezasDecorativas;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Estados\AprobacionDeTransicion;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\ValidadorMinimoDetalle;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\Pages\Alertas;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial;
use App\Modules\Nucleo\Filament\Pages\FaltaGerenteGeneral;
use App\Modules\Nucleo\Filament\Pages\InteligenciaArtificial;
use App\Modules\Nucleo\Filament\Pages\Mien;
use App\Modules\Nucleo\Filament\Pages\MisAprobaciones;
use App\Modules\Nucleo\Filament\Pages\MisAvisos;
use App\Modules\Nucleo\Filament\Pages\MisDatos;
use App\Modules\Nucleo\Filament\Pages\MisPreferencias;
use App\Modules\Nucleo\Filament\Pages\RenovarClave;
use App\Modules\Nucleo\Filament\Pages\RolesPermisos;
use App\Modules\Nucleo\Filament\Pages\SaludDelSistema;
use App\Modules\Nucleo\Filament\Pages\VistaPreviaFechas;
use App\Modules\Nucleo\Filament\PanelAplicaciones;
use App\Modules\Nucleo\Filament\Plantillas\PlantillasDeTipo;
use App\Modules\Nucleo\Filament\Resources\Aprobaciones\Pages\ListarReglasAprobacion;
use App\Modules\Nucleo\Filament\Resources\Aprobaciones\ReglaAprobacionResource;
use App\Modules\Nucleo\Filament\Resources\Conflictos\ConflictoResource;
use App\Modules\Nucleo\Filament\Resources\Estados\EstadoResource;
use App\Modules\Nucleo\Filament\Resources\Incompatibilidades\IncompatibilidadResource;
use App\Modules\Nucleo\Filament\Resources\Motivos\MotivoResource;
use App\Modules\Nucleo\Filament\Resources\Numeracion\SerieDocumentoResource;
use App\Modules\Nucleo\Filament\Resources\Organigrama\Pages\ListarUnidades;
use App\Modules\Nucleo\Filament\Resources\Organigrama\UnidadOrganizativaResource;
use App\Modules\Nucleo\Filament\Resources\Parametros\Pages\ListarParametros;
use App\Modules\Nucleo\Filament\Resources\Parametros\ParametroResource;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Filament\Resources\TiposCambio\TipoCambioResource;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\ListarUsuarios;
use App\Modules\Nucleo\Filament\Resources\Usuarios\UsuarioResource;
use App\Modules\Nucleo\Filament\RespuestaDeIngreso;
use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\PromptsIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Ia\TextoDeAdjunto;
use App\Modules\Nucleo\Ia\TextoDePdf;
use App\Modules\Nucleo\Imagenes\RegistroImagenes;
use App\Modules\Nucleo\Listeners\RegistrarUltimoAcceso;
use App\Modules\Nucleo\Listeners\ReiniciarEleccionDeModulos;
use App\Modules\Nucleo\Listeners\RevisarClaveAlEntrar;
use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Mien\RegistroSeccionesMien;
use App\Modules\Nucleo\Mien\RegistroTarjetasMien;
use App\Modules\Nucleo\Mien\TarjetaMien;
use App\Modules\Nucleo\Mien\Tarjetas\TarjetaMisAprobaciones;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\AlertaTipo;
use App\Modules\Nucleo\Models\AlertaTipoRol;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\AplicacionEnEscritorio;
use App\Modules\Nucleo\Models\AplicacionOculta;
use App\Modules\Nucleo\Models\AplicacionPrincipal;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\ConfiguracionBienvenida;
use App\Modules\Nucleo\Models\ConfiguracionPaso;
use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\Cubso;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\GranMomento;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\IaLlamada;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProcesoDestino;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\IaSugerencia;
use App\Modules\Nucleo\Models\Imputacion;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\ModuloOculto;
use App\Modules\Nucleo\Models\MomentoVisto;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\OrdenAplicacion;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\PasoReglaAprobacion;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\PlantillaVersion;
use App\Modules\Nucleo\Models\PreferenciaUsuario;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\SaludEjecucion;
use App\Modules\Nucleo\Models\SaludoMostrado;
use App\Modules\Nucleo\Models\SerieDocumento;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\TipoPenalidad;
use App\Modules\Nucleo\Models\TipoTrabajo;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Models\UsoAplicacion;
use App\Modules\Nucleo\Navegacion\AnimacionCaja;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\BarraLateral;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\ModulosDeLaBarra;
use App\Modules\Nucleo\Navegacion\OcultosDelPedido;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use App\Modules\Nucleo\Notificaciones\GrupoAvisos;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\Notificaciones\ValidadorDiasLeidos;
use App\Modules\Nucleo\Parties\FuentePadronContribuyentes;
use App\Modules\Nucleo\Parties\RegistroPestanasFicha;
use App\Modules\Nucleo\Parties\SinPadron;
use App\Modules\Nucleo\Parties\ValidadorBancoDetracciones;
use App\Modules\Nucleo\Parties\ValidadorFichaEmpresa;
use App\Modules\Nucleo\Parties\ValidadorUmbralParecido;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroParametros\DefinicionParametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use App\Modules\Nucleo\Respaldos\FrecuenciaRespaldo;
use App\Modules\Nucleo\Respaldos\Respaldador;
use App\Modules\Nucleo\Respaldos\RespaldadorSpatie;
use App\Modules\Nucleo\Respaldos\RespaldosVencidos;
use App\Modules\Nucleo\Respaldos\ValidadorFrecuenciaRespaldo;
use App\Modules\Nucleo\Respaldos\ValidadorPruebaRestauracion;
use App\Modules\Nucleo\Salud\ComprobacionCola;
use App\Modules\Nucleo\Salud\ComprobacionHuerfanos;
use App\Modules\Nucleo\Salud\ComprobacionIa;
use App\Modules\Nucleo\Salud\ComprobacionProgramador;
use App\Modules\Nucleo\Salud\ComprobacionRespaldos;
use App\Modules\Nucleo\Salud\PulseSinPersonas;
use App\Modules\Nucleo\Salud\RegistroSalud;
use App\Modules\Nucleo\Salud\ValidadorDiasCuarentena;
use App\Modules\Nucleo\Salud\ValidadorDiasHistorial;
use App\Modules\Nucleo\Salud\ValidadorDiasPausaHuerfanos;
use App\Modules\Nucleo\Salud\ValidadorTopeHuerfanos;
use App\Modules\Nucleo\Saludo\Ambientacion;
use App\Modules\Nucleo\Saludo\GeneradorSaludo;
use App\Modules\Nucleo\Saludo\Piezas\PiezasDeLaFecha;
use App\Modules\Nucleo\Saludo\Piezas\RegistroPiezasDibujo;
use App\Modules\Nucleo\Saludo\RegistroAmbientaciones;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;
use App\Modules\Nucleo\Saludo\SaludoPorReglas;
use App\Modules\Nucleo\Saludo\SemillaSesion;
use App\Modules\Nucleo\Saludo\ValidadorAniversario;
use App\Modules\Nucleo\Saludo\ValidadorCoordenadas;
use App\Modules\Nucleo\Saludo\ValidadorDiasVersiculo;
use App\Modules\Nucleo\Saludo\ValidadorPlazoGranMomento;
use App\Modules\Nucleo\Saludo\ValidadorPorcentaje;
use App\Modules\Nucleo\Saludo\VarianteGranMomento;
use App\Modules\Nucleo\Seguridad\CamposVisibles;
use App\Modules\Nucleo\Seguridad\CamposVisiblesPorPermiso;
use App\Modules\Nucleo\Seguridad\CrucesSinControl;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\CalidadPartiesService;
use App\Modules\Nucleo\Services\ClaveService;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\ContextoEfectos;
use App\Modules\Nucleo\Services\ContextoTransicion;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use App\Modules\Nucleo\Tiempo\CalendarioLaboral;
use App\Modules\Nucleo\Tiempo\ValidadorDiasLaborables;
use App\Modules\Nucleo\Tiempo\ValidadorHora;
use App\Modules\Nucleo\TipoCambio\FuenteSunat;
use App\Modules\Nucleo\TipoCambio\FuenteTipoCambio;
use App\Modules\Nucleo\Ubigeos\LectorXlsx;
use App\Modules\Nucleo\Ubigeos\PadronUbigeos;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pulse\Contracts\ResolvesUsers;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class NucleoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Campos sensibles por permiso (plan 1.4, D6).
        $this->app->bind(CamposVisibles::class, CamposVisiblesPorPermiso::class);
        // Cuando se integre el padrón de SUNAT, aquí va su implementación.
        $this->app->bind(FuentePadronContribuyentes::class, SinPadron::class);
        $this->app->singleton(RegistroModulos::class);
        $this->app->singleton(RegistroImagenes::class);
        // Plan 2.0, D5: quién guarda detalles de «Otro» de cada catálogo.
        $this->app->singleton(RegistroOtrosDeCatalogo::class);
        // Observación 9.5 (2): quién más puede firmar una transición, por permiso.
        $this->app->singleton(Aprobaciones\PermisosQueFirman::class);
        $this->app->singleton(RegistroTarjetasMien::class);
        $this->app->singleton(RegistroSeccionesMien::class);
        $this->app->singleton(RegistroPendientesMien::class);
        $this->app->singleton(RegistroResumenesAprobacion::class);
        $this->app->singleton(RegistroPestanasFicha::class);
        // Por petición: `once()` guarda ahí los posibles duplicados, que la portada y el filtro piden juntos (plan 1.4m).
        $this->app->scoped(CalidadPartiesService::class);
        $this->app->singleton(RegistroPiezasDecorativas::class, function ($app): RegistroPiezasDecorativas {
            // Plan 1.4i, D3: las piezas decorativas que el modo desarrollo deja cambiar en el sitio.
            $registro = new RegistroPiezasDecorativas;
            $registro->registrar($app->make(DibujoDelSaludo::class));
            $registro->registrar(new LogoDeFiesta);
            $registro->registrar($app->make(GranMomentoDeFecha::class));
            $registro->registrar(new ClimaDelDibujo);
            // Plan 1.4r, D6: la estación de las cajas del inicio, con un clic.
            $registro->registrar(new EstacionDelInicio);
            // Plan 1.4u, D5: la temperatura del clima, para ver el calor y el frío sin esperarlos.
            $registro->registrar(new TemperaturaDelClima);

            return $registro;
        });
        $this->app->singleton(RegistroPantallas::class);
        $this->registrarPantallas();
        $this->app->singleton(RegistroAcciones::class);
        // Plan 2.20a, N8: los permisos que no son acciones y el nombre legible de los de cada módulo.
        $this->app->singleton(RegistroPermisos::class);
        $this->app->singleton(RegistroParametros::class);
        $this->app->singleton(RegistroPreferencias::class);
        // Plan 1.4g, D4: al iniciar sesión, la pantalla que eligió la persona.
        $this->app->bind(LoginResponse::class, RespuestaDeIngreso::class);
        $this->app->singleton(RegistroDocumentos::class);
        // ADR 0022: los tipos de plantilla que registra cada módulo.
        $this->app->singleton(RegistroPlantillas::class);
        $this->app->singleton(RegistroAvisos::class);
        // Plan 1.11: los procesos de IA que declaran los módulos y sus prompts versionados.
        $this->app->singleton(RegistroProcesosIa::class);
        $this->app->singleton(PromptsIa::class, fn (): PromptsIa => new PromptsIa(__DIR__.'/Ia/Prompts'));
        // Plan 1.11, D6 y ADR 0019: el texto de un PDF, detrás de su interfaz.
        $this->app->bind(TextoDeAdjunto::class, TextoDePdf::class);
        $this->app->singleton(ClientesModeloLenguaje::class, function ($app): ClientesModeloLenguaje {
            $clientes = new ClientesModeloLenguaje($app);
            $clientes->registrar('anthropic', ClienteAnthropic::class);

            return $clientes;
        });
        $this->app->singleton(RegistroAlertas::class);
        // Plan 1.12, D2: los recuadros de la página de salud.
        $this->app->singleton(RegistroSalud::class);
        $this->app->singleton(RegistroPasosConfiguracion::class);
        // Plan 1.12 (prueba de carga): los módulos ocultos de la persona, leídos una sola vez por pedido.
        $this->app->scoped(OcultosDelPedido::class);
        $this->app->bind(Respaldador::class, RespaldadorSpatie::class);
        // Plan 1.12, D6: Pulse sin personas.
        $this->app->singleton(ResolvesUsers::class, PulseSinPersonas::class);
        $this->app->singleton(ContextoEfectos::class);
        $this->app->singleton(ContextoTransicion::class);
        // La 1.7 pone aquí el mecanismo de aprobaciones.
        $this->app->bind(AprobacionDeTransicion::class, ReglasDeAprobacion::class);
        $this->app->bind(ResolutorDeFirmante::class, JefaturaQueSube::class);
        $this->app->bind(FuenteTipoCambio::class, fn (Application $app): FuenteTipoCambio => new FuenteSunat(
            $app->make(Http::class),
            (string) config('nucleo.tipo_cambio.url_sunat'),
        ));
        // Recuerda lo leído durante una petición o un job, y se olvida al terminar.
        $this->app->scoped(ParametrosService::class);
        $this->app->bind(PadronUbigeos::class, fn (Application $app): PadronUbigeos => new PadronUbigeos(
            $app->make(Http::class),
            new LectorXlsx,
            (string) config('nucleo.ubigeos.csv'),
            (int) config('nucleo.ubigeos.minimo_distritos'),
        ));
        $this->app->singleton(UsuarioSistema::class);
        // Plan 1.4b, D4: hoy solo por reglas; la versión con IA se enchufa aquí cuando Gerencia lo permita.
        $this->app->bind(GeneradorSaludo::class, SaludoPorReglas::class);
        $this->app->singleton(RegistroAmbientaciones::class);
        // Plan 1.4e2: las piezas de los dibujos por fecha (hoy, la muestra de primavera).
        $this->app->singleton(RegistroPiezasDibujo::class, function (): RegistroPiezasDibujo {
            $registro = new RegistroPiezasDibujo;
            PiezasDeLaFecha::registrarEn($registro);

            return $registro;
        });
        $this->app->singleton(RegistroGranMomento::class);
        // Plan 1.4d, D2: los días no trabajados se leen una vez por petición.
        $this->app->scoped(CalendarioLaboral::class);
        // Recuerda lo calculado en la petición: la franja, el ícono y el inicio la piden.
        $this->app->scoped(Ambientacion::class);
        // Recuerda las preferencias leídas en la petición (el logo, la franja y el inicio las piden).
        $this->app->scoped(PreferenciasUsuarioService::class);
        $this->app->singleton(ContextoAuditoria::class, fn (Application $app): ContextoAuditoria => new ContextoAuditoria(
            $app->make('auth'),
            $app->make(UsuarioSistema::class),
            $app->runningInConsole(),
            fn (): ?Request => $app->bound('request') ? $app->make('request') : null,
        ));
    }

    public function boot(): void
    {
        $this->registrarMorphMap();
        $this->registrarMacroAuditoria();
        // Plan 2.0, D5: lo escrito en «Otra red».
        $this->app->make(RegistroOtrosDeCatalogo::class)->registrar(Catalogo::RedesSociales, OtrasRedesSociales::class);
        // Plan 2.22, P6: la bandeja de firmas de cada persona, en su tablero.
        // MV-032: lo que espera la firma de la persona cuenta para la estrella de Mien.
        $this->app->make(RegistroPendientesMien::class)->registrar('firmas', function (User $u): array {
            $bandeja = app(AprobacionesService::class)->bandeja($u, app(RegistroResumenesAprobacion::class));

            return ['cantidad' => count($bandeja), 'texto' => 'esperan tu firma', 'grupos' => [['titulo' => 'Esperan tu firma', 'items' => array_map(fn ($p): array => [
                'texto' => "{$p->documento} espera tu firma", 'nota' => 'S/ '.number_format((float) $p->montoSoles, 2).' · '.$p->solicitante, 'url' => $p->enlace ?? '/mis-aprobaciones', 'tono' => 'warning',
            ], $bandeja)]]];
        });
        $this->app->make(RegistroTarjetasMien::class)->registrar(new TarjetaMien('mis-aprobaciones', 'Mis aprobaciones', null, TarjetaMisAprobaciones::class, 10));
        $acciones = $this->app->make(RegistroAcciones::class);
        $acciones->registrar(MostrarBarraModulos::class);
        $acciones->registrar(ElegirModulos::class);
        $acciones->registrar(CambiarPreferencia::class);
        $acciones->registrar(RestablecerPreferencias::class);
        $acciones->registrar(MarcarMomentoVisto::class);
        // ADR 0022: las plantillas de todos los módulos; el permiso lo pone el tipo y lo revisa el servicio.
        $acciones->registrar(GuardarComoPlantilla::class);
        $acciones->registrar(EditarPlantilla::class);
        $acciones->registrar(ActualizarPlantilla::class);
        $acciones->registrar(PublicarPlantilla::class);
        $acciones->registrar(DesactivarPlantilla::class);
        // La pieza común que cada módulo pone en su panel deslizante (`nucleo::filament.plantillas.panel`).
        Livewire::component('nucleo.plantillas', PlantillasDeTipo::class);
        $acciones->registrar(PedirCodigoClave::class);
        $acciones->registrar(CambiarMiClave::class);
        $acciones->registrar(RenovarMiClave::class);
        $acciones->registrar(RegistrarMiTelefono::class);
        $acciones->registrar(RegistrarMiCumpleanos::class);
        // Mi foto y mis redes sociales (plan 1.4o, D2 a D4).
        foreach ([CambiarMiFoto::class, QuitarMiFoto::class, AgregarMiRedSocial::class, CambiarMiRedSocial::class, QuitarMiRedSocial::class] as $accion) {
            $acciones->registrar($accion);
        }
        // Plan 1.4y, D2: leer los avisos propios, sin permiso.
        foreach ([MarcarAvisoLeido::class, MarcarAvisosLeidos::class, MarcarAvisoNoLeido::class] as $accion) {
            $acciones->registrar($accion);
        }
        // El panel de aplicaciones de cada persona (plan 1.4p, D5 y D7).
        foreach ([AgregarMiAplicacion::class, CambiarMiAplicacion::class, QuitarMiAplicacion::class, OcultarAplicacion::class, MostrarAplicacion::class, RegistrarUsoAplicacion::class, PonerAplicacionPrincipal::class, SubirIconoAplicacion::class, MoverAplicacion::class, ElegirDondeAbrirAplicacion::class] as $accion) {
            $acciones->registrar($accion);
        }
        $acciones->registrar(CambiarParametro::class);
        $acciones->registrar(CambiarParametroSensible::class);
        // Plan 1.11, paso 5: la configuración de IA, la autorización de Gerencia y resolver sugerencias.
        $acciones->registrar(AutorizarProcesoIa::class);
        $acciones->registrar(CambiarProcesoIa::class);
        $acciones->registrar(CrearProveedorIa::class);
        $acciones->registrar(EditarProveedorIa::class);
        $acciones->registrar(CrearModeloIa::class);
        $acciones->registrar(EditarModeloIa::class);
        $acciones->registrar(MarcarModeloPorDefectoIa::class);
        $acciones->registrar(AgregarPrecioModeloIa::class);
        $acciones->registrar(ResolverSugerenciaIa::class);
        $acciones->registrar(RegistrarTipoCambio::class);
        $acciones->registrar(SincronizarTipoCambio::class);
        $acciones->registrar(CambiarPresentacionEstado::class);
        $acciones->registrar(AgregarMotivo::class);
        $acciones->registrar(CambiarMotivo::class);
        $acciones->registrar(DesactivarMotivo::class);
        $acciones->registrar(ActivarMotivo::class);
        $acciones->registrar(ActualizarUbigeos::class);
        // Adjuntos (plan 1.8).
        foreach ([AgregarAdjunto::class, QuitarAdjunto::class, ReemplazarAdjunto::class] as $accion) {
            $acciones->registrar($accion);
        }
        // Aprobaciones (plan 1.7): firmar, observar y autoaprobar, y las reglas de Configuración.
        foreach ([FirmarAprobacion::class, ObservarAprobacion::class, AutoaprobarAprobacion::class, RevisarAutoaprobacion::class, RecalcularAprobacion::class, CrearReglaAprobacion::class, EditarReglaAprobacion::class, DesactivarReglaAprobacion::class, ActivarReglaAprobacion::class] as $accion) {
            $acciones->registrar($accion);
        }
        $this->app->make(RegistroDocumentos::class)->familiaDeMotivos(AprobacionesService::FAMILIA_OBSERVAR, 'Observaciones al aprobar');
        // Alertas (plan 1.10, D7): roles, textos y anticipación de cada una, desde Configuración → Alertas.
        $acciones->registrar(ConfigurarAlerta::class);
        $acciones->registrar(MarcarPasoListo::class);
        $acciones->registrar(ReabrirPaso::class);
        $acciones->registrar(SeguirDespues::class);
        $acciones->registrar(MostrarBienvenida::class);
        $this->registrarAccionesDeCatalogos($acciones);

        foreach ([RegistrarParty::class, EditarParty::class, EditarTributariosParty::class, DesactivarParty::class, ActivarParty::class, BorrarParty::class, AsignarRolParty::class, CerrarRolParty::class, CambiarRubroParty::class] as $accion) {
            $acciones->registrar($accion);
        }
        // Contactos, sedes y cuentas bancarias de una party (plan 1.3b): la única puerta de escritura para otros módulos.
        foreach ([
            AgregarContacto::class,
            EditarContacto::class,
            MarcarContactoPrincipal::class,
            CerrarVinculoContacto::class,
            TrasladarContacto::class,
            BorrarContacto::class,
            AgregarSede::class,
            EditarSede::class,
            DesactivarSede::class,
            ActivarSede::class,
            BorrarSede::class,
            RegistrarCuentaBancaria::class,
            CompletarCciCuentaBancaria::class,
            MarcarCuentaPrincipal::class,
            DesactivarCuentaBancaria::class,
            ActivarCuentaBancaria::class,
            BorrarCuentaBancaria::class,
            // Redes sociales de la party y de sus contactos (plan 1.4o, D2).
            AgregarRedSocial::class,
            CambiarRedSocial::class,
            QuitarRedSocial::class,
        ] as $accion) {
            $acciones->registrar($accion);
        }
        // Organigrama (plan 1.4, D5).
        foreach ([CrearUnidad::class, EditarUnidad::class, DesactivarUnidad::class, ActivarUnidad::class, DesignarJefatura::class, CrearPuesto::class, EditarPuesto::class, DesactivarPuesto::class, ActivarPuesto::class] as $accion) {
            $acciones->registrar($accion);
        }
        // Usuarios, roles y separación de funciones (plan 1.4).
        foreach ([
            RegistrarUsuario::class,
            CambiarPuestoUsuario::class,
            DesactivarUsuario::class,
            ActivarUsuario::class,
            AsignarRol::class,
            QuitarRol::class,
            AsignarRolEnAmbito::class,
            QuitarRolEnAmbito::class,
            CrearRol::class,
            RenombrarRol::class,
            CambiarPermisosDeRol::class,
            RegistrarIncompatibilidad::class,
            DesactivarIncompatibilidad::class,
            RegistrarControlCruce::class,
            RegistrarCumpleanosUsuario::class,
        ] as $accion) {
            $acciones->registrar($accion);
        }
        Event::listen(Login::class, RegistrarUltimoAcceso::class);
        Event::listen(Login::class, ReiniciarEleccionDeModulos::class);
        // Plan 1.4h, D5: una contraseña que no cumple la regla obliga a renovarla al entrar.
        Event::listen(Attempting::class, [RevisarClaveAlEntrar::class, 'alIntentar']);
        Event::listen(Login::class, [RevisarClaveAlEntrar::class, 'alEntrar']);
        Event::listen(PasswordReset::class, [RevisarClaveAlEntrar::class, 'alRestablecer']);
        // Plan 1.4e, D3: cada inicio de sesión trae otro saludo.
        Event::listen(Login::class, fn () => SemillaSesion::renovar());
        // Plan 1.4p, D3: en cada ingreso, el panel de aplicaciones empieza en el muelle.
        Event::listen(Login::class, fn () => PanelAplicaciones::olvidarEstado());
        // Plan 1.4h, D3: una sola regla de contraseña para todo el sistema, también al restablecerla.
        Password::defaults(fn (): Password => ClaveService::regla());
        $this->declararPermisos();
        $this->declararParametros();
        $this->declararPreferencias();
        $this->declararAlertas();
        $this->declararSalud();
        $this->declararConfiguracionInicial();
        $this->registrarGranMomento();
        // Plan 1.4l: «Mien», de todas las personas (sin permiso a propósito), en su área «Personal».
        // MVP rápido (op-solicitudes-permiso): los pedidos de permiso; quien administra roles los resuelve.
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo('solicitudes-permiso', 'Solicitudes de permiso', '/solicitudes-permiso', permiso: null, area: AreaModulo::Nucleo, icono: 'heroicon-o-key', descripcion: 'Los permisos que alguien pidió desde un botón bloqueado', preguntas: ['¿Quién pidió qué permiso?', '¿Me dieron el permiso que pedí?']));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo('tickets-ayuda', 'Tickets de ayuda', '/tickets-ayuda', permiso: null, area: AreaModulo::Nucleo, icono: 'tq-ticket-soporte', descripcion: 'Los problemas que se reportaron al asistente', preguntas: ['¿Qué problemas reportaron?', '¿En qué quedó mi ticket?']));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'mien', 'Mien', '/mien', permiso: null, area: AreaModulo::Personal, icono: 'heroicon-o-star',
            descripcion: 'Cómo va tu trabajo de un vistazo: tus pendientes, tus aprobaciones y los números de tu área.',
            // Plan 1.4q, D5 y D6: lo que aparece al dejar el mouse sobre la caja.
            preguntas: ['¿Qué tengo pendiente hoy?', '¿Qué espera mi aprobación?', '¿Cómo van los números de mi área?'],
            animacion: AnimacionCaja::Camara,
        ));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'personas', 'Personas y empresas', '/personas', permiso: 'parties.ver', area: AreaModulo::Nucleo,
            icono: 'tq-apreton-manos', descripcion: 'Clientes, proveedores, entidades y sus roles',
            preguntas: ['¿Quién es este cliente y cómo lo contacto?', '¿Con qué proveedores trabajamos?', '¿Quién es el contacto principal de cada empresa?', '¿Dónde están sus sedes y sus cuentas bancarias?'],
            animacion: AnimacionCaja::Apreton,
        ));
        // MV-016: la vieja caja «Usuarios y organigrama» se parte: Usuarios (Sistema) y Organigrama (Directorio y catálogos).
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'usuarios', 'Usuarios', '/seguridad', permiso: 'usuarios.ver', area: AreaModulo::Nucleo,
            icono: 'tq-tres-personas', descripcion: 'Cuentas, roles y permisos',
            preguntas: ['¿Quién tiene acceso al sistema?', '¿Qué puede hacer cada persona?'],
            animacion: AnimacionCaja::Personas,
        ));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'organigrama', 'Organigrama', '/organigrama', permiso: 'organigrama.ver', area: AreaModulo::Nucleo,
            icono: 'heroicon-o-rectangle-group', descripcion: 'Unidades, puestos y jefaturas',
            preguntas: ['¿Quién es jefe de quién?'],
        ));
        // MV-016: lo que sale de Configuración. Reglas del negocio va a Techquk; Listas, a Directorio y catálogos.
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'reglas-negocio', 'Reglas del negocio', '/reglas-negocio', permiso: 'catalogos.ver', area: AreaModulo::Nucleo,
            icono: 'heroicon-o-scale', descripcion: 'Quién aprueba qué y los parámetros que deciden cómo trabaja Techquk',
            preguntas: ['¿Quién aprueba una compra grande?', '¿Con qué margen mínimo se cotiza?'],
        ));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'listas', 'Listas', '/listas', permiso: 'catalogos.ver', area: AreaModulo::Nucleo,
            icono: 'heroicon-o-list-bullet', descripcion: 'Catálogos y motivos',
            preguntas: ['¿Qué monedas, unidades y catálogos usamos?', '¿Qué motivos hay para anular o rechazar?'],
        ));
        $this->app->make(RegistroModulos::class)->registrar(new CajaModulo(
            'configuracion', 'Configuración', '/configuracion', permiso: 'catalogos.ver', area: AreaModulo::Nucleo,
            icono: 'heroicon-o-cog-6-tooth', descripcion: 'Parámetros, tipo de cambio y estados',
            preguntas: ['¿A cuánto está el dólar hoy?', '¿Qué estados tiene cada documento?'],
            animacion: AnimacionCaja::Rueda,
        ));
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'nucleo');
        // Íconos propios del Núcleo (`tq-…`), como los de Heroicons: el interruptor de «Barra de módulos», el apretón de manos
        // de Personas y empresas (de Lucide, licencia ISC, aviso dentro del archivo) y la persona con el edificio.
        $this->callAfterResolving(IconFactory::class, fn (IconFactory $iconos) => $iconos->add('techquk', ['path' => __DIR__.'/resources/svg', 'prefix' => 'tq']));

        if ($this->app->runningInConsole()) {
            $this->commands([ActualizarCsvUbigeos::class, SincronizarTipoCambioComando::class, PrimerAdministrador::class, GerenteGeneral::class, RevisarAlertas::class, LatidoProgramador::class, Respaldar::class, RevisarHuerfanosAdjuntos::class, LeerHistorico::class, ArchivarAvisos::class, ArchivarPulse::class, RecalcularNombresNormalizados::class]);
        }

        $this->programarTareas();
    }

    /**
     * Pantallas del núcleo en el panel principal. Cada módulo declara las suyas así, para su panel; el
     * panel se arma después y toma solo las que le tocan.
     */
    private function registrarPantallas(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar(
            'app',
            paginas: [Configuracion::class, Filament\Clusters\Listas::class, Filament\Clusters\ReglasNegocio::class, Catalogos::class, Alertas::class, InteligenciaArtificial::class, Seguridad::class, MisPreferencias::class, VistaPreviaFechas::class, RenovarClave::class, FaltaGerenteGeneral::class, MisDatos::class, Mien::class, Filament\Pages\PortadaProceso::class, MisAvisos::class, MisAprobaciones::class, SaludDelSistema::class, ConfiguracionInicial::class, RolesPermisos::class, Filament\Pages\SolicitudesPermiso::class, Filament\Pages\TicketsAyuda::class],
            recursos: [ParametroResource::class, Filament\Resources\Parametros\ParametroNegocioResource::class, TipoCambioResource::class, EstadoResource::class, MotivoResource::class, SerieDocumentoResource::class, ReglaAprobacionResource::class, PartyResource::class, UsuarioResource::class, UnidadOrganizativaResource::class, ConflictoResource::class, IncompatibilidadResource::class],
        );
    }

    /**
     * Tareas programadas del núcleo (las corre `schedule:run`). Cada módulo programa las suyas así.
     */
    private function programarTareas(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $programa): void {
            foreach ((array) config('nucleo.tipo_cambio.horas') as $hora) {
                $programa->command(SincronizarTipoCambioComando::class)
                    ->dailyAt((string) $hora)
                    ->timezone('America/Lima')
                    ->withoutOverlapping();
            }

            // Plan 1.10, D4: la revisión diaria de alertas, en un solo servidor y sin encimarse.
            $programa->command(RevisarAlertas::class)
                ->dailyAt('07:00')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();

            // Plan 1.12, D3: cada noche; `respaldos.frecuencia` decide si ya toca.
            $programa->command(Respaldar::class)
                ->dailyAt('02:00')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();

            // Plan 1.12, D4: los huérfanos de adjuntos, cada domingo, después del respaldo.
            $programa->command(RevisarHuerfanosAdjuntos::class)
                ->weeklyOn(0, '04:00')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();

            // Plan 1.12, D7: el latido, para la página de salud y Healthchecks.
            $programa->command(LatidoProgramador::class)
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->onOneServer();

            // Plan 1.12: las mediciones de Pulse de más de 7 días, al archivo histórico (Pulse ya no borra al azar).
            $programa->command(ArchivarPulse::class)
                ->dailyAt('03:45')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();

            // Plan 1.4y, D4 y D6: los avisos leídos hace más del plazo, al archivo histórico.
            $programa->command(ArchivarAvisos::class)
                ->dailyAt('03:30')
                ->timezone('America/Lima')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /**
     * Los pasos del asistente de configuración inicial que trae el núcleo (plan 1.13, D1). Cada uno abre su pantalla de
     * siempre; el resumen solo lee. Roles y permisos no tiene pantalla todavía (su grilla es la 1.4c): ella suma su paso
     * aquí, después de Usuarios. Solo cuando alguien pide el registro, como los recuadros de salud.
     */
    private function declararConfiguracionInicial(): void
    {
        $this->callAfterResolving(RegistroPasosConfiguracion::class, function (RegistroPasosConfiguracion $pasos): void {
            $cantidad = fn (int $n, string $uno, string $varios): string => $n === 0 ? "Sin {$varios}" : ($n === 1 ? "1 {$uno}" : "{$n} {$varios}");

            // La ficha de Techquk si ya se eligió; si no, la lista, para registrarla o encontrarla y elegirla.
            $pasos->registrar(new DefinicionPaso(
                'nucleo.empresa',
                'Datos de la empresa',
                'La ficha de Techquk en Personas y empresas: razón social, RUC, dirección fiscal, sedes y cuentas bancarias.',
                'tq-apreton-manos',
                function (): array {
                    $ficha = app(EmpresaService::class)->ficha();

                    return $ficha === null
                        ? ['clase' => ListarParties::class, 'parametros' => []]
                        : ['clase' => VerParty::class, 'parametros' => ['record' => (string) $ficha->id]];
                },
                fn (): string => ($ficha = app(EmpresaService::class)->ficha()) === null ? 'Sin elegir' : $ficha->razon_social.' · RUC '.$ficha->numero_documento,
            ));
            $pasos->registrar(new DefinicionPaso(
                'nucleo.fundacion',
                'Fecha de fundación',
                'El aniversario de Techquk: el inicio lo celebra cada año.',
                'heroicon-o-gift',
                ListarParametros::class,
                function (): string {
                    $fecha = ValidadorAniversario::fecha(app(ParametrosService::class)->texto('interfaz.aniversario_techquk'));

                    return $fecha === null ? 'Sin fecha' : sprintf('%02d/%02d/%d', $fecha['dia'], $fecha['mes'], $fecha['anio']);
                },
                parametros: ['tableSearch' => 'aniversario'],
            ));
            $pasos->registrar(new DefinicionPaso('nucleo.organigrama', 'Organigrama', 'Las unidades de Techquk, de la más alta hacia abajo, con sus puestos y jefaturas.', 'heroicon-o-building-office-2', ListarUnidades::class, fn (): string => $cantidad(UnidadOrganizativa::query()->where('es_activa', true)->count(), 'unidad', 'unidades')));
            $pasos->registrar(new DefinicionPaso('nucleo.usuarios', 'Usuarios', 'Quiénes entran al sistema, con su puesto y sus roles.', 'heroicon-o-users', ListarUsuarios::class, fn (): string => $cantidad(User::query()->where('es_activo', true)->where('es_sistema', false)->count(), 'usuario activo', 'usuarios activos')));
            $pasos->registrar(new DefinicionPaso('nucleo.roles', 'Roles y permisos', 'Qué puede hacer cada rol y qué permisos no deben juntarse. Lo define Gerencia.', 'heroicon-o-shield-check', RolesPermisos::class, fn (): string => $cantidad(Role::query()->count(), 'rol', 'roles')));
            $pasos->registrar(new DefinicionPaso('nucleo.catalogos', 'Monedas y catálogos', 'Las monedas, formas de pago, unidades de medida y demás listas.', 'heroicon-o-queue-list', Catalogos::class, fn (): string => $cantidad(Moneda::query()->where('es_activa', true)->count(), 'moneda activa', 'monedas activas'), parametros: ['catalogo' => 'monedas']));
            $pasos->registrar(new DefinicionPaso('nucleo.parametros', 'Parámetros', 'El IGV, la moneda local, el horario de trabajo y demás valores del sistema.', 'heroicon-o-adjustments-horizontal', ListarParametros::class));
            $pasos->registrar(new DefinicionPaso('nucleo.aprobaciones', 'Aprobaciones', 'Quiénes aprueban cada documento, según su monto.', 'heroicon-o-document-check', ListarReglasAprobacion::class, fn (): string => $cantidad(ReglaAprobacion::query()->where('es_activa', true)->count(), 'regla activa', 'reglas activas')));
            $pasos->registrar(new DefinicionPaso('nucleo.alertas', 'Alertas', 'A quién le llega cada alerta y con qué texto.', 'heroicon-o-bell-alert', Alertas::class, fn (): string => $cantidad(AlertaTipo::query()->where('es_activa', true)->count(), 'alerta activa', 'alertas activas')));
            $pasos->registrar(new DefinicionPaso('nucleo.ia', 'Inteligencia artificial', 'Opcional: solo si Gerencia la autoriza. Proveedores, modelos y topes de gasto.', 'heroicon-o-sparkles', InteligenciaArtificial::class, obligatorio: false));
        });
    }

    /**
     * Los recuadros de la página de salud que trae el núcleo (plan 1.12, D2). Cada módulo registra los suyos igual.
     */
    private function declararSalud(): void
    {
        // Plan 1.12, D6: Pulse solo con el permiso de la página de salud (de fábrica, solo en local).
        Gate::define('viewPulse', fn (User $usuario): bool => $usuario->can(SaludService::PERMISO_VER));

        // Solo cuando alguien pide el registro (la página, una prueba), no en cada pedido ni en cada comando: registrar
        // arma la comprobación para leer su clave, y una que toque la base tumbaría `artisan` antes de migrar (revisión de
        // arquitectura de la 1.12). Los módulos registran los suyos igual.
        $this->callAfterResolving(RegistroSalud::class, function (RegistroSalud $salud): void {
            $salud->registrar(ComprobacionProgramador::class);
            $salud->registrar(ComprobacionRespaldos::class);
            $salud->registrar(ComprobacionHuerfanos::class);
            $salud->registrar(ComprobacionCola::class);
            $salud->registrar(ComprobacionIa::class);
        });
    }

    /** Plan 2.20a, N8: los permisos de solo lectura del núcleo (antes, `PermisosSeeder::DE_LECTURA`). */
    private function declararPermisos(): void
    {
        $permisos = $this->app->make(RegistroPermisos::class);
        $permisos->declarar('catalogos.ver', 'Ver catálogos');
        $permisos->declarar('parties.ver', 'Ver personas y empresas');
        $permisos->declarar('usuarios.ver', 'Ver usuarios');
        $permisos->declarar('organigrama.ver', 'Ver el organigrama');
        $permisos->declarar(SaludService::PERMISO_VER, 'Ver la salud del sistema');
    }

    /**
     * Las alertas del núcleo (plan 1.10, D3) y sus dos grupos de avisos. Los textos de fábrica nunca llevan datos
     * sensibles: viajan por correo (regla 10).
     */
    private function declararAlertas(): void
    {
        $avisos = $this->app->make(RegistroAvisos::class);
        $avisos->registrarGrupo(new GrupoAvisos('control', 'Control interno'));
        $avisos->registrarGrupo(new GrupoAvisos('sistema', 'Fallas del sistema'));

        $alertas = $this->app->make(RegistroAlertas::class);
        $alertas->registrar(new DefinicionAlerta(
            CrucesSinControl::ALERTA,
            'Cruces de funciones sin control',
            'Cada mañana, si hay personas que juntan permisos que no deberían ir juntos sin un control registrado.',
            'control',
            'heroicon-o-shield-exclamation',
            'Hay {cantidad} cruces de funciones sin control',
            'Hay personas que juntan permisos que no deberían ir juntos, sin un control registrado. Registra su control en Usuarios y organigrama, pestaña Conflictos.',
            ['cantidad' => 'Cantidad de cruces'],
            [SeguridadService::ROL_GERENCIA_GENERAL],
            obligatoria: true,
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::EFECTO_FALLIDO,
            'Proceso externo que falló',
            'Cuando el paso hacia otro sistema que sigue a un cambio de estado agotó sus intentos.',
            'sistema',
            'heroicon-o-bolt-slash',
            'No se completó un paso de {documento}',
            'Después de «{paso}», el paso hacia otro sistema agotó sus intentos. El documento conserva su estado: revisa el paso pendiente.',
            ['documento' => 'Documento', 'paso' => 'Paso que falló'],
            [SeguridadService::ROL_ADMINISTRADOR],
            obligatoria: true,
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::IA_TOPE_ALCANZADO,
            'Proceso de IA en su tope del mes',
            'Cuando un proceso de IA gasta su tope mensual: queda en manual hasta el mes siguiente.',
            'sistema',
            'heroicon-o-banknotes',
            'La IA de «{proceso}» llegó a su tope del mes',
            'El proceso «{proceso}» gastó su tope de {tope} dólares de este mes. Hasta el mes siguiente, sus formularios siguen en manual. Si hace falta más, Gerencia puede subir el tope en Configuración → IA.',
            ['proceso' => 'Proceso', 'tope' => 'Tope del mes en dólares'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::IA_PROVEEDOR_NO_DISPONIBLE,
            'Proveedor de IA no disponible',
            'Cuando un proveedor de IA no tiene crédito o su clave no vale: los procesos que lo usan siguen en manual.',
            'sistema',
            'heroicon-o-cpu-chip',
            'La IA de {proveedor} no está disponible',
            'El proveedor {proveedor} respondió: {motivo}. Los procesos que lo usan siguen en manual. Revisa su crédito o su clave con TI.',
            ['proveedor' => 'Proveedor', 'motivo' => 'Qué respondió'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::IA_PEDIDO_FALLIDO,
            'Sugerencia de IA que falló',
            'Como máximo una vez al día por proceso, si un pedido de sugerencia falló o esperó demasiado un cupo del proveedor.',
            'sistema',
            'heroicon-o-sparkles',
            'Falló una sugerencia de IA de «{proceso}»',
            'Un pedido de sugerencia del proceso «{proceso}» no terminó. Quien la pidió siguió en manual. Revisa el registro de errores y el proveedor en Configuración → IA.',
            ['proceso' => 'Proceso'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::RESPALDO_FALLIDO,
            'Respaldo que falló',
            'Como máximo una vez al día, si el respaldo de la base y los adjuntos no se pudo hacer.',
            'sistema',
            'heroicon-o-archive-box-x-mark',
            'Falló el respaldo de hoy',
            'El respaldo de la base y los adjuntos hacia el disco «{disco}» no terminó. Revisa el registro de errores; mientras tanto, el último respaldo bueno es el anterior.',
            ['disco' => 'Disco de destino'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::RESPALDO_VENCIDO,
            'Respaldo vencido',
            'Cada día en que el último respaldo correcto es más viejo que su frecuencia más un día.',
            'sistema',
            'heroicon-o-calendar-date-range',
            'El sistema lleva días sin respaldo',
            'El último respaldo correcto es del {ultimo}. Revisa que el programador corra y que el destino de los respaldos esté disponible.',
            ['ultimo' => 'Último respaldo'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::HUERFANOS_DEMASIADOS,
            'Demasiados archivos huérfanos',
            'Si la revisión semanal encuentra más archivos de adjuntos sin registro que su tope: no mueve ninguno.',
            'sistema',
            'heroicon-o-document-duplicate',
            'La revisión de huérfanos se detuvo',
            'Se encontraron {cantidad} archivos de adjuntos sin registro, más que el tope. No se movió ninguno: puede ser un error o una restauración a medias. Revisa con `php artisan nucleo:revisar-huerfanos --simular`.',
            ['cantidad' => 'Archivos encontrados'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::HISTORICO_FALLIDO,
            'Archivo histórico que falló',
            'Como máximo una vez al día por tabla, si una limpieza no pudo dejar sus filas en el archivo histórico. No se borró nada.',
            'sistema',
            'heroicon-o-archive-box-arrow-down',
            'No se pudo archivar «{tabla}»',
            'Una limpieza no pudo escribir o comprobar el archivo histórico de «{tabla}», así que no borró nada: la base sigue completa. Revisa la clave de cifrado de los respaldos y el disco del histórico.',
            ['tabla' => 'Tabla'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::CORREO_NO_ENVIADO,
            'Correo de aviso que no salió',
            'Como máximo una vez al día, si un aviso no se pudo enviar por correo. Llega solo a la campana.',
            'sistema',
            'heroicon-o-envelope',
            'Hay correos de aviso que no salieron ({fecha})',
            'Desde el {fecha} hay avisos que no se pudieron enviar por correo. Las personas los ven igual en su campana. Revisa el servicio de correo.',
            ['fecha' => 'Fecha del primer correo que no salió'],
            [SeguridadService::ROL_ADMINISTRADOR],
            soloCampana: true,
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::AUTOAPROBACION,
            'Autoaprobación por revisar',
            'Cuando alguien firma su propia aprobación con el permiso de autoaprobar.',
            'control',
            'heroicon-o-document-magnifying-glass',
            '{persona} se autoaprobó {documento}',
            'La firma quedó marcada para revisión. Revísala en Configuración → Aprobaciones, en las autoaprobaciones por revisar.',
            ['persona' => 'Quién se autoaprobó', 'documento' => 'Documento'],
            [SeguridadService::ROL_GERENCIA_GENERAL],
        ));
        $alertas->registrar(new DefinicionAlerta(
            AlertasDelNucleo::TIPO_CAMBIO_FALTANTE,
            'Tipo de cambio que no llegó',
            'Como máximo una vez al día, si no se pudo traer el tipo de cambio de SUNAT y todavía no hay uno para hoy.',
            'sistema',
            'heroicon-o-signal-slash',
            'No se pudo traer el tipo de cambio de hoy ({fecha})',
            'El sistema lo vuelve a intentar en su siguiente hora programada. Si sigue sin llegar, regístralo a mano en Configuración → Tipos de cambio.',
            ['fecha' => 'Fecha del tipo de cambio que falta'],
            [SeguridadService::ROL_ADMINISTRADOR],
        ));
        $alertas->registrarFuente(CrucesSinControl::class);
        $alertas->registrarFuente(RespaldosVencidos::class);
    }

    /**
     * Las preferencias por usuario del núcleo (plan 1.4g, D3 y D4). Solo presentación: nunca datos personales ni estado
     * de trabajo (D7). Cada una con un defecto pensado para quien nunca la cambia.
     */
    /**
     * Las variantes del gran momento (plan 1.4d, D7): dos por fecha, el año elige cuál. Agregar una es una vista en
     * `resources/views/filament/gran-momento/` y una línea aquí.
     */
    private function registrarGranMomento(): void
    {
        $this->app->make(RegistroGranMomento::class)->registrar(
            new VarianteGranMomento(Ocasion::Navidad, 'navidad-establo', 'El nacimiento, en silueta', '¡Feliz Navidad!', 'nucleo::filament.gran-momento.navidad-establo'),
            // MVP rápido (op-gran-momento, plan 1.4d paso 7): las otras tres variantes.
            new VarianteGranMomento(Ocasion::Navidad, 'navidad-estrella', 'La estrella sobre los cerros', '¡Feliz Navidad!', 'nucleo::filament.gran-momento.navidad-estrella'),
            new VarianteGranMomento(Ocasion::AnioNuevo, 'anio-nuevo-fuegos', 'Fuegos sobre la ciudad', '¡Feliz Año Nuevo!', 'nucleo::filament.gran-momento.anio-nuevo-fuegos'),
            new VarianteGranMomento(Ocasion::AnioNuevo, 'anio-nuevo-amanecer', 'El primer amanecer', '¡Feliz Año Nuevo!', 'nucleo::filament.gran-momento.anio-nuevo-amanecer'),
        );
    }

    private function declararPreferencias(): void
    {
        $this->app->make(RegistroPreferencias::class)->declarar(
            // Apariencia (D4 y D5): los valores del tema son los que usa Filament en el navegador.
            DefinicionPreferencia::opcion(GrupoPreferencia::Apariencia, 'tema', 'Tema', 'Automático sigue a tu equipo: claro de día, oscuro si lo tienes así.',
                'system', ['system' => 'Automático', 'light' => 'Claro', 'dark' => 'Oscuro']),
            DefinicionPreferencia::opcion(GrupoPreferencia::Apariencia, 'letra', 'Tamaño de letra', 'Más grande si lees de lejos o muchas horas.',
                'normal', ['normal' => 'Normal', 'grande' => 'Grande']),
            DefinicionPreferencia::opcion(GrupoPreferencia::Apariencia, 'densidad', 'Densidad de las tablas', 'Compacta muestra más filas a la vez.',
                'comodo', ['comodo' => 'Cómoda', 'compacto' => 'Compacta']),
            DefinicionPreferencia::opcion(GrupoPreferencia::Apariencia, 'animaciones', 'Animaciones',
                'Automático sigue a tu equipo. «Reducir siempre» quita el movimiento de los dibujos, del logo y de la barra aunque tu equipo no lo pida.',
                'sistema', ['sistema' => 'Automático', 'reducir' => 'Reducir siempre']),
            // Planes 1.4r (D5), 1.4s (D4) y 1.4ac (D6, renombrada): prendida por defecto; apagada, todo como antes.
            DefinicionPreferencia::siNo(GrupoPreferencia::Apariencia, 'estacion', 'Apariencia según el día',
                'Las cajas del inicio cambian con la estación; el fondo y la barra superior, con la hora y la temperatura: la luz del sol de día y la luna de noche.', true),
            // Plan 1.4aa, D5: venía del grupo Saludo, que ya no existe; el mismo texto y prendida por defecto.
            DefinicionPreferencia::siNo(GrupoPreferencia::Apariencia, 'ambientacion', 'Ambientación de la fecha',
                'El dibujo y la franja de las fechas especiales, el logo de fiesta y el gran momento de Navidad, Año Nuevo y las demás fechas. Apagarla también quita el gran momento.', true),
            // Navegación (D4): solo al iniciar sesión; si pierde el permiso del módulo, el inicio.
            DefinicionPreferencia::opcion(GrupoPreferencia::Navegacion, 'pantalla_inicio', 'Pantalla al entrar', 'Adónde llegas al iniciar sesión.',
                'inicio', fn (User $usuario): array => ['inicio' => 'El inicio', ...app(ModulosDeLaBarra::class)->opciones($usuario)]),
            DefinicionPreferencia::siNo(GrupoPreferencia::Navegacion, 'barra_modulos', 'Mostrar barra de módulos',
                'Una barra a la izquierda con tus módulos, para cambiar de uno a otro sin volver al inicio.', false)->soloEnEscritorio(),
            // MV-049: ya no se elige qué módulos ver («Módulos de la barra» se quitó).
            // Se cambia arrastrando el borde de la barra (plan 1.3d); «Restablecer» la devuelve al ancho inicial.
            DefinicionPreferencia::numero(GrupoPreferencia::Navegacion, 'ancho_barra', 'Ancho de la barra',
                'En píxeles; 0 la deja solo con íconos.', BarraLateral::ANCHO_INICIAL, BarraLateral::PLEGADA, BarraLateral::ANCHO_MAXIMO)
                ->soloSi(fn (int $ancho): bool => BarraLateral::esValido($ancho))->soloEnEscritorio()->sinMostrar(),
        );
    }

    /**
     * Parámetros del núcleo. Solo valores que no son decisión pendiente (plan 1.2, D5). Es «de dinero»
     * (`esSensible`) todo parámetro que cambia un monto, una tasa o a dónde va el dinero (plan 1.3b): hoy, los cuatro.
     */
    private function declararParametros(): void
    {
        $this->app->make(RegistroParametros::class)->declarar(
            // MV-035: la frase de la portada de cada proceso.
            new DefinicionParametro('procesos', 'frase_techquk', TipoParametro::Texto, 'Así va Techquk.', 'La frase de la portada de Techquk (MV-035).'),
            new DefinicionParametro('procesos', 'frase_conseguir_negocio', TipoParametro::Texto, '¡Vamos por esas oportunidades!', 'La frase de la portada de Conseguir el negocio (MV-035).'),
            new DefinicionParametro('procesos', 'frase_gerencia_proyectos', TipoParametro::Texto, 'Cada proyecto, bajo control.', 'La frase de la portada de Gerencia de proyectos (MV-035).'),
            new DefinicionParametro('procesos', 'frase_abastecer', TipoParametro::Texto, 'Lo que la obra necesita, a tiempo.', 'La frase de la portada de Abastecer (MV-035).'),
            new DefinicionParametro('procesos', 'frase_facturacion_cobranza', TipoParametro::Texto, 'Lo trabajado, cobrado.', 'La frase de la portada de Facturación y cobranza (MV-035).'),
            new DefinicionParametro('procesos', 'frase_postventa', TipoParametro::Texto, 'El cliente sigue siendo nuestro.', 'La frase de la portada de Postventa (MV-035).'),
            new DefinicionParametro('procesos', 'frase_presupuestar_cotizar', TipoParametro::Texto, 'Números claros, ofertas que ganan.', 'La frase de la portada de Presupuestar y cotizar (MV-035).'),
            new DefinicionParametro('procesos', 'frase_finanzas', TipoParametro::Texto, 'La caja en orden.', 'La frase de la portada de Finanzas (MV-035).'),
            new DefinicionParametro('procesos', 'frase_personas', TipoParametro::Texto, 'La gente correcta en cada obra.', 'La frase de la portada de Personas (MV-035).'),
            new DefinicionParametro('procesos', 'frase_directorio', TipoParametro::Texto, 'Todo en su sitio.', 'La frase de la portada de Directorio y catálogos (MV-035).'),
            new DefinicionParametro('procesos', 'frase_sistema', TipoParametro::Texto, 'Lo que hace funcionar todo.', 'La frase de la portada de Sistema (MV-035).'),
            new DefinicionParametro('tributario', 'igv_porcentaje', TipoParametro::Decimal, '18.00', 'Tasa del IGV, en porcentaje.', esSensible: true),
            // Cambiarla con datos registrados rehace todas las conversiones: no se edita desde la pantalla.
            new DefinicionParametro('general', 'moneda_local', TipoParametro::Texto, 'PEN', 'Moneda en la que se lleva la contabilidad y se suman los resultados (código ISO).', esEditable: false, esSensible: true),
            // ADR 0012: cubre fines de semana, feriados y cortes breves de la fuente. Decide qué tipo de cambio se usa y
            // con eso los montos convertidos: de dinero.
            new DefinicionParametro('tipo_cambio', 'dias_tolerancia', TipoParametro::Entero, 4, 'Días hacia atrás en que se acepta el último tipo de cambio registrado cuando falta el del día. 0: el del día es obligatorio.', esSensible: true),
            // Plan 1.3b, D5: la cuenta de detracciones solo puede ser de este banco. Editable si contabilidad indica
            // otro; decide a dónde van las detracciones: permiso de dinero y un banco registrado.
            new DefinicionParametro('parties', 'ruc_banco_detracciones', TipoParametro::Texto, '20100030595', 'RUC del Banco de la Nación: el único banco donde se acepta la cuenta de detracciones de una persona o empresa.', esSensible: true, validador: ValidadorBancoDetracciones::class),
            // Plan 2.20a, N1: desde qué parecido (pg_trgm, 0 a 1) se avisa al crear una cuenta que el nombre se parece al de
            // otra ficha. Medido con nombres inventados: tipeos y variaciones pequeñas dan 0.78 o más; entidades distintas
            // con palabras comunes («municipalidad distrital de…»), 0.64 o menos.
            new DefinicionParametro('parties', 'umbral_nombre_parecido', TipoParametro::Decimal, '0.70', 'Desde qué parecido (de 0.01 a 1) se avisa, al crear una cuenta, que el nombre escrito se parece al de otra persona o empresa. Más bajo, más avisos. Solo avisa, nunca bloquea.', validador: ValidadorUmbralParecido::class),
            // Plan 1.4b: el saludo y la ambientación de la fecha. Ninguno mueve dinero.
            new DefinicionParametro('interfaz', 'saludo_porcentaje_espanol', TipoParametro::Entero, 70, 'De cada 100 sesiones, cuántas saludan en español. Las demás, de vez en cuando, en inglés, francés, portugués o alemán: como mucho una vez al día por persona y nunca dos sesiones seguidas.', validador: ValidadorPorcentaje::class),
            // Pedido del desarrollador, 2026-10-21: al menos cuatro días de versículo por semana.
            new DefinicionParametro('interfaz', 'saludo_dias_versiculo', TipoParametro::Entero, 4, 'Cuántos días por semana, de lunes a viernes, la frase del inicio es un versículo de la Biblia en todos los momentos del día (0 a 5). Los días cambian por persona y por semana.', validador: ValidadorDiasVersiculo::class),
            // Plan 1.4e, D2 a D4: el horario de la empresa y la mezcla del saludo.
            new DefinicionParametro('interfaz', 'horario_inicio', TipoParametro::Texto, '08:00', 'Hora en que empieza el horario de la empresa (HH:MM); debe ser anterior a la de fin. Antes, el saludo del inicio es de fuera de horario.', validador: ValidadorHora::class),
            new DefinicionParametro('interfaz', 'horario_fin', TipoParametro::Texto, '19:00', 'Hora en que termina el horario de la empresa (HH:MM). Desde entonces, el saludo del inicio es de fuera de horario.', validador: ValidadorHora::class),
            new DefinicionParametro('interfaz', 'horario_dias', TipoParametro::Texto, '1,2,3,4,5', 'Días que trabaja la empresa: del 1 (lunes) al 7 (domingo), separados por comas. Los demás, y los feriados, el saludo es de fuera de horario.', validador: ValidadorDiasLaborables::class),
            new DefinicionParametro('interfaz', 'saludo_porcentaje_de_la_hora', TipoParametro::Entero, 50, 'En horario, de cada 100 saludos, cuántos son propios de la hora («Buenos días») y no casuales («Hola»).', validador: ValidadorPorcentaje::class),
            new DefinicionParametro('interfaz', 'saludo_porcentaje_frase_especial', TipoParametro::Entero, 70, 'Fuera de horario, de cada 100 veces, cuántas la frase es una especial de esas horas y no la del día.', validador: ValidadorPorcentaje::class),
            // Plan 1.4e2, D5: el clima del dibujo, consultado por el navegador con la ubicación de cada computadora.
            new DefinicionParametro('adjuntos', 'tamano_maximo_mb', TipoParametro::Entero, 20, 'Tamaño máximo de un archivo adjunto, en MB (de 1 a 50, el techo del servidor). Un tipo de adjunto puede pedir menos.', validador: ValidadorTamanoAdjunto::class),
            new DefinicionParametro('adjuntos', 'extensiones', TipoParametro::Texto, 'pdf,jpg,jpeg,png,webp,xlsx,docx,xml,zip', 'Extensiones que se aceptan como adjunto, separadas por comas. Además se revisa que el contenido sea de verdad de ese tipo.'),
            new DefinicionParametro('interfaz', 'clima_activo', TipoParametro::Booleano, true, 'Muestra el clima en el dibujo del inicio. El navegador pide permiso de ubicación y consulta Open-Meteo con la posición redondeada a unos 10 km; no pasa por el servidor ni se guarda.'),
            // MVP rápido (op-gran-momento): cada cuántos minutos se repite la ambientación de pantalla completa de una fecha.
            new DefinicionParametro('interfaz', 'ambientacion_minutos', TipoParametro::Entero, 240, 'Cada cuántos minutos, como mínimo, se repite la ambientación de pantalla completa de Navidad y Año Nuevo para una persona.'),
            new DefinicionParametro('interfaz', 'clima_ubicacion_respaldo', TipoParametro::Texto, '-6.77,-79.84', 'Ubicación para el clima cuando la persona no da permiso de ubicación: «latitud,longitud» (Chiclayo por defecto).', validador: ValidadorCoordenadas::class),
            new DefinicionParametro('interfaz', 'ambientacion_activa', TipoParametro::Booleano, true, 'Muestra la ambientación de la fecha (animación del inicio, franja e ícono). Cada persona puede apagarla para sí en su perfil.'),
            new DefinicionParametro('interfaz', 'aniversario_techquk', TipoParametro::Texto, ValidadorAniversario::SIN_FECHA, 'Fecha de fundación de Techquk como AAAA-MM-DD (por ejemplo, 2010-05-15), o «sin fecha». Da el día del aniversario y los años del agradecimiento. Lo decide Gerencia.', validador: ValidadorAniversario::class),
            // Plan 1.13, D2: la ficha de Techquk en Personas y empresas (su RUC). De ahí salen sus datos.
            new DefinicionParametro('empresa', 'ficha', TipoParametro::Texto, ValidadorFichaEmpresa::SIN_FICHA, 'El RUC de la ficha de Techquk en Personas y empresas: de ahí salen su razón social, dirección fiscal, sedes y cuentas bancarias. «sin ficha» mientras no se elija.', validador: ValidadorFichaEmpresa::class),
            // Plan 1.4d, D3: hasta cuántos días después del día clave sale el gran momento a quien no lo vio ese día.
            new DefinicionParametro('interfaz', 'gran_momento_plazo_navidad', TipoParametro::Entero, 6, 'Gran momento de Navidad: días después del 25 de diciembre en que todavía sale a quien no lo vio (0 a 30). Con 6, hasta el 31.', validador: ValidadorPlazoGranMomento::class),
            new DefinicionParametro('interfaz', 'gran_momento_plazo_anio_nuevo', TipoParametro::Entero, 6, 'Gran momento de Año Nuevo: días después del 1 de enero en que todavía sale a quien no lo vio (0 a 30). Con 6, hasta el 7.', validador: ValidadorPlazoGranMomento::class),
            new DefinicionParametro('interfaz', 'gran_momento_plazo_semana_santa', TipoParametro::Entero, 7, 'Gran momento de Semana Santa: días después del Domingo de Resurrección en que todavía sale a quien no lo vio (0 a 30). Con 7, hasta el domingo siguiente.', validador: ValidadorPlazoGranMomento::class),
            new DefinicionParametro('interfaz', 'gran_momento_plazo_fiestas_patrias', TipoParametro::Entero, 7, 'Gran momento de Fiestas Patrias: días después del 28 de julio en que todavía sale a quien no lo vio (0 a 30). Con 7, hasta el 4 de agosto.', validador: ValidadorPlazoGranMomento::class),
            new DefinicionParametro('interfaz', 'gran_momento_plazo_aniversario', TipoParametro::Entero, 7, 'Gran momento del aniversario de Techquk: días después del aniversario en que todavía sale a quien no lo vio (0 a 30).', validador: ValidadorPlazoGranMomento::class),
            // Plan 1.12, D3 y D1: respaldos y el registro de corridas de la página de salud. Ninguno mueve dinero.
            new DefinicionParametro('respaldos', 'frecuencia', TipoParametro::Texto, FrecuenciaRespaldo::Semanal->value, 'Cada cuánto se respaldan la base y los adjuntos: «diaria» o «semanal». Semanal durante el desarrollo; diaria antes de cargar datos reales (docs/operaciones/respaldos.md).', validador: ValidadorFrecuenciaRespaldo::class),
            new DefinicionParametro('respaldos', 'ultima_prueba_restauracion', TipoParametro::Texto, ValidadorPruebaRestauracion::SIN_PROBAR, 'Fecha de la última prueba de restauración aprobada en otra máquina (AAAA-MM-DD), o «sin probar». Se edita al hacerla (docs/operaciones/restauracion.md); la página de salud muestra quién la registró.', validador: ValidadorPruebaRestauracion::class),
            new DefinicionParametro('adjuntos', 'revision_huerfanos_activa', TipoParametro::Booleano, true, 'Revisión semanal de archivos huérfanos de adjuntos. Se apaga mientras se restaura una base y se vuelve a encender al terminar (docs/operaciones/restauracion.md).'),
            new DefinicionParametro('adjuntos', 'dias_pausa_huerfanos', TipoParametro::Entero, 14, 'Días que la revisión de huérfanos puede quedar pausada antes de que la página de salud lo muestre en rojo (1 a 90): una pausa olvidada tras una restauración no queda así para siempre.', validador: ValidadorDiasPausaHuerfanos::class),
            new DefinicionParametro('adjuntos', 'dias_cuarentena', TipoParametro::Entero, 30, 'Días que un archivo huérfano de adjuntos queda en cuarentena antes de borrarse (7 a 365). Mientras tanto se puede devolver.', validador: ValidadorDiasCuarentena::class),
            new DefinicionParametro('adjuntos', 'tope_huerfanos', TipoParametro::Entero, 50, 'Si la revisión semanal encuentra más huérfanos que esto, no mueve ninguno y avisa: es más probable un error o una restauración a medias (1 a 10000).', validador: ValidadorTopeHuerfanos::class),
            new DefinicionParametro('salud', 'dias_historial', TipoParametro::Entero, 400, 'Días que se guardan las corridas de respaldos y de la revisión de huérfanos en la página de salud (sirven para ver tendencias). El latido del programador guarda siempre una semana.', validador: ValidadorDiasHistorial::class),
            // Plan 1.4y, D4: cuánto quedan los avisos leídos en la base antes de pasar al archivo histórico.
            new DefinicionParametro('avisos', 'dias_leidos', TipoParametro::Entero, 365, 'Días que un aviso leído sigue en la base y en «Mis avisos» (30 a 3650). Después pasa al archivo histórico, cifrado, y sale de la base. Los no leídos nunca salen.', validador: ValidadorDiasLeidos::class),
            // Plan 1.5, D9: un CHECK no lee parámetros; el mínimo lo revisa el servicio de transiciones.
            new DefinicionParametro('motivos', 'minimo_detalle_otro', TipoParametro::Entero, 10, 'Caracteres mínimos del detalle cuando el motivo elegido es «Otro».', validador: ValidadorMinimoDetalle::class),
        );
    }

    /**
     * Una instancia por catálogo y operación (plan 1.2b, E5). La de campos de dinero, solo donde los hay.
     */
    private function registrarAccionesDeCatalogos(RegistroAcciones $acciones): void
    {
        foreach (Catalogo::cases() as $catalogo) {
            $clases = [CrearElementoCatalogo::class, EditarElementoCatalogo::class, DesactivarElementoCatalogo::class, ActivarElementoCatalogo::class, BorrarElementoCatalogo::class];

            if ($catalogo->tieneSensibles()) {
                $clases[] = EditarSensiblesElementoCatalogo::class;
            }

            foreach ($clases as $clase) {
                $acciones->registrar($this->app->make($clase, ['catalogo' => $catalogo]));
            }
        }
    }

    /**
     * Alias que se guardan en la base en lugar del nombre de la clase (`auditable_type` de la bitácora,
     * `model_type` de spatie): mover o renombrar un modelo no rompe lo guardado (plan 1.4, paso 1). Cada módulo
     * agrega los suyos con `Relation::morphMap()`; un modelo sin alias falla al guardarse.
     */
    private function registrarMorphMap(): void
    {
        Relation::enforceMorphMap([
            'auditoria_cambio' => AuditoriaCambio::class,
            'cubso' => Cubso::class,
            'forma_pago' => FormaPago::class,
            'linea_solucion' => LineaSolucion::class,
            'origen_comercial' => OrigenComercial::class,
            'nivel_gobierno' => NivelGobierno::class,
            'rubro' => Rubro::class,
            'moneda' => Moneda::class,
            'parametro' => Parametro::class,
            'plantilla' => Plantilla::class,
            'plantilla_version' => PlantillaVersion::class,
            'party' => Party::class,
            'party_contacto' => PartyContacto::class,
            'party_cuenta_bancaria' => PartyCuentaBancaria::class,
            'party_rol' => PartyRol::class,
            'party_sede' => PartySede::class,
            'party_red_social' => PartyRedSocial::class,
            'rol_party' => RolParty::class,
            'tipo_cambio' => TipoCambio::class,
            'tipo_documento' => TipoDocumento::class,
            'tipo_documento_identidad' => TipoDocumentoIdentidad::class,
            'tipo_penalidad' => TipoPenalidad::class,
            'tipo_trabajo' => TipoTrabajo::class,
            'ubigeo' => Ubigeo::class,
            'unidad_medida' => UnidadMedida::class,
            'unidad_organizativa' => UnidadOrganizativa::class,
            'puesto' => Puesto::class,
            'permiso_por_ambito' => PermisoPorAmbito::class,
            'incompatibilidad_permisos' => IncompatibilidadPermisos::class,
            'cruce_funciones' => CruceFunciones::class,
            'control_cruce' => ControlCruce::class,
            'categoria_frase' => CategoriaFrase::class,
            'frase_saludo' => FraseSaludo::class,
            'modulo_oculto' => ModuloOculto::class,
            'preferencia_usuario' => PreferenciaUsuario::class,
            'feriado' => Feriado::class,
            'gran_momento' => GranMomento::class,
            'momento_visto' => MomentoVisto::class,
            'saludo_mostrado' => SaludoMostrado::class,
            'tipo_motivo' => TipoMotivo::class,
            'motivo' => Motivo::class,
            'estado' => Estado::class,
            'transicion_estado' => TransicionEstado::class,
            'serie_documento' => SerieDocumento::class,
            'regla_aprobacion' => ReglaAprobacion::class,
            'salud_ejecucion' => SaludEjecucion::class,
            'configuracion_paso' => ConfiguracionPaso::class,
            'configuracion_bienvenida' => ConfiguracionBienvenida::class,
            'tipo_adjunto' => TipoAdjunto::class,
            'adjunto' => Adjunto::class,
            'centro_costo' => CentroCosto::class,
            'red_social' => RedSocial::class,
            'aplicacion' => Aplicacion::class,
            'aplicacion_propia' => AplicacionPropia::class,
            'aplicacion_oculta' => AplicacionOculta::class,
            'uso_aplicacion' => UsoAplicacion::class,
            'aplicacion_principal' => AplicacionPrincipal::class,
            'orden_aplicacion' => OrdenAplicacion::class,
            'aplicacion_en_escritorio' => AplicacionEnEscritorio::class,
            'imputacion' => Imputacion::class,
            'notificacion' => Notificacion::class,
            'alerta_tipo' => AlertaTipo::class,
            'alerta_tipo_rol' => AlertaTipoRol::class,
            'alerta_emitida' => AlertaEmitida::class,
            'ia_proveedor' => IaProveedor::class,
            'ia_modelo' => IaModelo::class,
            'ia_precio_modelo' => IaPrecioModelo::class,
            'ia_proceso' => IaProceso::class,
            'ia_proceso_destino' => IaProcesoDestino::class,
            'ia_llamada' => IaLlamada::class,
            'ia_sugerencia' => IaSugerencia::class,
            'paso_regla_aprobacion' => PasoReglaAprobacion::class,
            'ronda_aprobacion' => RondaAprobacion::class,
            'firma_aprobacion' => FirmaAprobacion::class,
            'historial_estado' => HistorialEstado::class,
            'efecto_aplicado' => EfectoAplicado::class,
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
        ]);
    }

    /**
     * Columnas de auditoría (M4) de toda tabla de negocio: docs/convenciones/base-de-datos.md.
     */
    private function registrarMacroAuditoria(): void
    {
        Blueprint::macro('auditoria', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->index()->constrained('users');
            $this->timestampTz('created_at')->useCurrent();
            $this->foreignId('updated_by')->nullable()->index()->constrained('users');
            $this->timestampTz('updated_at')->nullable();
        });
    }
}
