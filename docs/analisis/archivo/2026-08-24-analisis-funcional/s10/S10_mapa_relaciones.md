# Mapa de relaciones de la base de datos S10

**Sistema:** ERP S10 en producción en Techquk S.A.C.  
**Foto:** respaldo del 17 de agosto de 2026  
**Fuente:** `sys.foreign_keys` y `sys.foreign_key_columns` del catálogo de SQL Server

---

## Qué contiene este documento

`[V]` **Las 1,916 tablas de la base, cada una con las relaciones que salen de ella y la tabla
a la que apunta cada relación.** Es un volcado fiel del catálogo del sistema, sin
interpretación.

### Cómo leer cada fila

| Columna | Qué significa |
|---|---|
| **Tabla** | La tabla de origen, con su cantidad de filas |
| **Relación** | La columna que apunta hacia afuera, y entre paréntesis el nombre de la restricción |
| **Tabla referenciada** | Hacia dónde apunta esa columna, con su cantidad de filas |

Una tabla con tres relaciones ocupa tres filas. Una tabla sin relaciones salientes ocupa una,
marcada con `—`.

### Cifras

| Concepto | Cantidad |
|---|---:|
| Tablas | **1,916** |
| Relaciones declaradas | **1,572** |
| Tablas con relaciones salientes | 616 |
| Tablas sin relaciones salientes | **1,300** |

`[S]` **Que 1,300 tablas no apunten a ninguna otra no significa que estén aisladas.** Muchas
son catálogos: nadie apunta desde ellas, pero muchas apuntan hacia ellas. Ese sentido inverso
se ve en la columna de referencias entrantes del archivo `s10_catalogo_tablas.csv`.

### Sobre la agrupación por dominio

`[S]` **La base no está dividida en módulos.** Las 1,916 tablas viven en un solo esquema. La
agrupación que ordena este documento se construyó clasificando cada tabla por su nombre, y es
inferencia nuestra: cubre el 79.5% de las tablas y el resto quedó sin clasificar.

---

## Índice de dominios

| Dominio | Tablas | Relaciones salientes |
|---|---:|---:|
| [M01 · Núcleo y maestros](#m01-nucleo-y-maestros) | 261 | 234 |
| [M02 · Presupuestos](#m02-presupuestos) | 117 | 169 |
| [M03 · Proyectos y producción](#m03-proyectos-y-produccion) | 206 | 197 |
| [M04 · Logística y compras](#m04-logistica-y-compras) | 61 | 104 |
| [M05 · Almacén e inventario](#m05-almacen-e-inventario) | 63 | 81 |
| [M06 · Contabilidad](#m06-contabilidad) | 76 | 89 |
| [M07 · Tesorería y finanzas](#m07-tesoreria-y-finanzas) | 75 | 92 |
| [M08 · Facturación y ventas](#m08-facturacion-y-ventas) | 38 | 42 |
| [M09 · Planillas y personal](#m09-planillas-y-personal) | 103 | 92 |
| [M10 · Asistencia y tareo](#m10-asistencia-y-tareo) | 60 | 70 |
| [M11 · Subcontratos](#m11-subcontratos) | 35 | 61 |
| [M12 · Activos y equipos](#m12-activos-y-equipos) | 21 | 42 |
| [M13 · Inmobiliaria](#m13-inmobiliaria) | 34 | 19 |
| [M14 · Temporales y respaldo](#m14-temporales-y-respaldo) | 307 | 3 |
| [M15 · Metadatos del sistema](#m15-metadatos-del-sistema) | 66 | 19 |
| [ZZ · Sin clasificar](#zz-sin-clasificar) | 393 | 258 |

---

## M01 · Núcleo y maestros

`[V]` 261 tablas · 234 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `AccesoAplicacion` ·13 | — | — |
| `AccesoCatalogo` ·1,743 | `CodUsuario` · `FK_AccesoCatalogo_Usuario` | `Usuario` ·14 |
| `AccesoCatalogoTipoIdentificador` ·0 | — | — |
| `AccesoCatalogo_Historial` ·2,852 | — | — |
| `AccesoDocumento` ·0 | `CodUsuario` · `FK_AccesoDocumento_Usuario` | `Usuario` ·14 |
| `AccesoDocumentoUnidadOperativa` ·0 | `CodFichaDocumento` · `FK_AccesoDocumentoUnidadOperativa_FichaDocumento` | `FichaDocumento` ·0 |
| `AccesoDocumentoUnidadOperativaRol` ·0 | `CodFichaDocumento` · `FK_AccesoDocumentoUnidadOperativaRol_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodRol` · `FK_AccesoDocumentoUnidadOperativaRol_Rol` | `Rol` ·21 |
|   | `CodUnidadOperativa` · `FK_AccesoDocumentoUnidadOperativaRol_UnidadOperativa` | `UnidadOperativa` ·13 |
| `AccesoDocumentoUsuario` ·0 | `CodFichaDocumento` · `FK_AccesoDocumentoUsuario_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodOrganizadorDocumento` · `FK_AccesoDocumentoUsuario_OrganizadorDocumento` | `OrganizadorDocumento` ·82 |
|   | `Usuario` · `FK_AccesoDocumentoUsuario_OrganizadorDocumento` | `OrganizadorDocumento` ·82 |
| `AccesoEscenario` ·1,505 | `CodUsuario` · `FK_AccesoEscenario_Usuario` | `Usuario` ·14 |
| `AccesoEscenario_Historial` ·1,760 | — | — |
| `AccesoInsumoServicios` ·0 | — | — |
| `AccesoMaquina` ·0 | `CodUsuario` · `FK_AccesoMaquina_Usuario` | `Usuario` ·14 |
| `AccesoProyectoUsuario` ·91 | — | — |
| `Aplicacion` ·18 | — | — |
| `AplicacionCorporacion` ·0 | — | — |
| `AplicacionEmpresa` ·0 | — | — |
| `AplicacionInsumo` ·0 | — | — |
| `AplicacionNotificacion` ·0 | — | — |
| `AplicacionRol` ·24 | — | — |
| `AplicacionURL` ·6 | — | — |
| `ConfiguracionAlterno` ·6 | — | — |
| `ConfiguracionCalculo` ·0 | — | — |
| `ConfiguracionCalculoDetalle` ·0 | — | — |
| `ConfiguracionClon` ·1 | — | — |
| `ConfiguracionClonDetalle` ·108 | — | — |
| `ConfiguracionCorreoMasivo` ·0 | — | — |
| `ConfiguracionCronograma` ·73 | — | — |
| `ConfiguracionDataControl` ·2 | — | — |
| `ConfiguracionFeriado` ·14 | — | — |
| `ConfiguracionLogStore` ·0 | — | — |
| `ConfiguracionPais` ·24 | — | — |
| `ConfiguracionPartidaTipo` ·0 | — | — |
| `ConfiguracionPartidaTipoDetalleEstructura` ·0 | — | — |
| `ConfiguracionPortalProveedor` ·3 | — | — |
| `ConfiguracionS10` ·114 | — | — |
| `ConfiguracionS10Detalle` ·36 | — | — |
| `ConfiguracionServidorCorreo` ·4 | — | — |
| `ConfiguracionVsflex` ·51,127 | — | — |
| `ConfiguracionWeb` ·1 | — | — |
| `Cubso` ·57,610 | — | — |
| `Documento` ·25 | — | — |
| `DocumentoCriterioEstadistica` ·0 | — | — |
| `DocumentoCriterioEstadisticaClasificacionDocumento` ·0 | — | — |
| `DocumentoCriterioEstadisticaItemDeControl` ·0 | — | — |
| `DocumentoDetalle` ·0 | — | — |
| `DocumentoEstadisticaClasificacionDocumento` ·0 | — | — |
| `DocumentoEstadisticaItemDeControl` ·0 | — | — |
| `DocumentoIdentificadorUsuario` ·0 | — | — |
| `DocumentoItemdeControl` ·0 | `CodFichaDocumento` · `FK_DocumentoItemdeControl_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodItemDeControl` · `FK_DocumentoItemdeControl_ItemDeControl` | `ItemDeControl` ·10 |
| `DocumentoLeido` ·0 | `CodFichaDocumento` · `FK_DocumentoLeido_FichaDocumento` | `FichaDocumento` ·0 |
| `DocumentoOrganizadorUsuario` ·26 | — | — |
| `DocumentoPersonal` ·0 | — | — |
| `DocumentoRevision` ·0 | `CodFichaDocumento` · `FK_DocumentoRevision_FichaDocumento` | `FichaDocumento` ·0 |
| `DocumentoUbicacionFisica` ·8 | — | — |
| `DocumentoVenta` ·32 | — | — |
| `Documentos` ·0 | — | — |
| `EmpresaDescuentoPersonal` ·0 | `CodIdentificador` · `FK_EmpresaDescuentoPersonal_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorEmpresa` · `FK_EmpresaDescuentoPersonal_Empresa` | `Identificador` ·1,638 |
| `EmpresaDocumentoConfiguracion` ·0 | `CodIdentificadorEmpresa` · `FK_EmpresaDocumentoConfiguracion_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_EmpresaDocumentoConfiguracion_Insumo` | `Insumo` ·10,254 |
|   | `CodDocumento` · `FK_EmpresaDocumentoConfiguracion_TipoDocumento` | `TipoDocumento` ·66 |
| `EmpresaEntidadCrediticia` ·0 | `NroConceptoAmortiza` · `FK_EmpresaEntidadCrediticia_ConceptoAmortiza` | `Concepto` ·982 |
|   | `NroConceptoCredito` · `FK_EmpresaEntidadCrediticia_ConceptoCredito` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_EmpresaEntidadCrediticia_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodContacto` · `FK_EmpresaEntidadCrediticia_Contacto` | `Identificador` ·1,638 |
|   | `CodIdentificador` · `FK_EmpresaEntidadCrediticia_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorEmpresa` · `FK_EmpresaEntidadCrediticia_Empresa` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_EmpresaEntidadCrediticia_Insumo` | `Insumo` ·10,254 |
|   | `CodProyecto` · `FK_EmpresaEntidadCrediticia_Proyecto` | `Proyecto` ·234 |
|   | `CodTipoDocumento` · `FK_EmpresaEntidadCrediticia_TipoDocumento` | `TipoDocumento` ·66 |
| `EmpresaFormato` ·30 | `CodFormato` · `FK_EmpresaFormato_FormatoImpresion` | `FormatoImpresion` ·30 |
|   | `CodIdentificador` · `FK_EmpresaFormato_Identificador` | `Identificador` ·1,638 |
| `EmpresaPrestamoPersonal` ·0 | — | — |
| `EmpresaSincronizacionMovil` ·0 | — | — |
| `EmpresaUsuario` ·8 | `CodIdentificador` · `FK_EmpresaUsuario_Identificador` | `Identificador` ·1,638 |
|   | `CodUsuario` · `FK_EmpresaUsuario_Usuario` | `Usuario` ·14 |
| `EstadoCuenta` ·0 | — | — |
| `EstadoCuentaDetalle` ·0 | — | — |
| `EstadoCuentaMovimiento` ·11 | — | — |
| `EstadoFinanciero` ·0 | — | — |
| `EstadoFinancieroColumna` ·0 | — | — |
| `EstadoFinancieroRubro` ·0 | — | — |
| `EstadoFinancieroRubroCuenta` ·0 | — | — |
| `EstadoMaster` ·22 | — | — |
| `EstadoProyecto` ·1,946 | — | — |
| `EstadoServicio` ·0 | — | — |
| `FichaDocumento` ·0 | `CodClasificacionDocumento` · `FK_FichaDocumento_ClasificacionDocumento` | `ClasificacionDocumento` ·246 |
|   | `CodUbicacionFisica` · `FK_FichaDocumento_DocumentoUbicacionFisica` | `DocumentoUbicacionFisica` ·8 |
|   | `CodContacto` · `FK_FichaDocumento_Contacto` | `Identificador` ·1,638 |
|   | `CodIdentificador` · `FK_FichaDocumento_Identificador` | `Identificador` ·1,638 |
|   | `CodRolRegistrador` · `FK_FichaDocumento_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativaRegistrador` · `FK_FichaDocumento_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CorrelativoRegistrador` · `FK_FichaDocumento_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `FichaDocumentoArchivo` ·0 | — | — |
| `FichaDocumentoAuditoria` ·0 | `CodFichaDocumento` · `FK_FichaDocumentoAuditoria_FichaDocumento` | `FichaDocumento` ·0 |
| `FichaDocumentoAuditoriaNota` ·0 | `CodFichaDocumento` · `FK_FichaDocumentoAuditoriaNota_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
|   | `Fecha` · `FK_FichaDocumentoAuditoriaNota_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
|   | `Usuario` · `FK_FichaDocumentoAuditoriaNota_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
|   | `NroNota` · `FK_FichaDocumentoAuditoriaNota_NoteroMultiple` | `NoteroMultiple` ·24 |
| `FichaDocumentoAuditoriaRespuesta` ·0 | `CodAccion` · `FK_FichaDocumentoAuditoriaRespuesta_Accion` | `Accion` ·4 |
|   | `CodFichaDocumento` · `FK_FichaDocumentoAuditoriaRespuesta_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
|   | `Fecha` · `FK_FichaDocumentoAuditoriaRespuesta_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
|   | `Usuario` · `FK_FichaDocumentoAuditoriaRespuesta_FichaDocumentoAuditoria` | `FichaDocumentoAuditoria` ·0 |
| `FichaDocumentoDependencia` ·0 | — | — |
| `FichaDocumentoDetalle` ·0 | — | — |
| `FichaDocumentoIdentificador` ·0 | `CodIdentificador` · `FK_FichaDocumentoIdentificador_Identificador` | `Identificador` ·1,638 |
| `FichaDocumentoRelaciones` ·0 | — | — |
| `FormatoCampos` ·67 | — | — |
| `FormatoCliente` ·0 | `CodIdentificador` · `FK_FormatoCliente_Identificador` | `Identificador` ·1,638 |
| `FormatoEquivalencia` ·507 | `NroFE` · `FK_FormatoEquivalencia_FormatoExportacion` | `FormatoExportacion` ·1,195 |
| `FormatoExportacion` ·1,195 | — | — |
| `FormatoImpresion` ·30 | — | — |
| `FormatoVarios` ·0 | — | — |
| `Identificador` ·1,638 | `CodLogo` · `FK_Identificador_Logo` | `Logo` ·5 |
|   | `NroRubroIdentificador` · `FK_Identificador_RubroIdentificador` | `RubroIdentificador` ·7 |
|   | `CodLugar` · `FK_Identificador_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `IdentificadorAFP` ·0 | — | — |
| `IdentificadorBolsaTolerancia` ·0 | — | — |
| `IdentificadorCapacitacion` ·0 | `CodIdentificador` · `FK_IdentificadorCapacitacion_Identificador` | `Identificador` ·1,638 |
| `IdentificadorCategoria` ·0 | — | — |
| `IdentificadorClientes` ·0 | — | — |
| `IdentificadorComplemento` ·0 | — | — |
| `IdentificadorContacto` ·0 | — | — |
| `IdentificadorContrato` ·0 | `CodIdentificador` · `FK_IdentificadorContrato_Identificador` | `Identificador` ·1,638 |
| `IdentificadorContratoConfiguracion` ·0 | `NroEsquemaPlanilla` · `FK_IdentificadorContratoConfiguracion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_IdentificadorContratoConfiguracion_Proyecto` | `Proyecto` ·234 |
| `IdentificadorContratoNotificacion` ·0 | `NroEsquemaPlanilla` · `FK_IdentificadorContratoNotificacion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_IdentificadorContratoNotificacion_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_IdentificadorContratoNotificacion_Proyecto` | `Proyecto` ·234 |
| `IdentificadorCuentaBanco` ·0 | `Banco_ID` · `FK_IdentificadorCuentaBanco_OB_Banco` | `OB_Banco` ·6 |
| `IdentificadorCuentaBanco_Historial_Eliminacion` ·0 | — | — |
| `IdentificadorDerechoHabiente` ·0 | — | — |
| `IdentificadorDocumentoAlterno` ·0 | `CodIdentificador` · `FK_IdentificadorDocumentoAlterno_Identificador` | `Identificador` ·1,638 |
|   | `NroTipoDoc` · `FK_IdentificadorDocumentoAlterno_TablaTablas` | `TablaTablas` ·17,070 |
| `IdentificadorDocumentoHistorial` ·0 | — | — |
| `IdentificadorHorarioGlobal` ·0 | `NroEsquemaPlanilla` · `FkNroEsquemaPlanillaIdentificadorHorarioGlobal` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FkCodIdentificadorIdentificadorHorarioGlobal` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FkCodProyectoIdentificadorHorarioGlobal` | `Proyecto` ·234 |
| `IdentificadorIngreso` ·0 | — | — |
| `IdentificadorIngresoCalificacion` ·0 | `NroCalificacionCriterio` · `FK_IdentificadorIngresoCalificacion_CalificacionCriterio` | `CalificacionCriterio` ·0 |
|   | `NroCalificacionEscala` · `FK_IdentificadorIngresoCalificacion_CalificacionEscala` | `CalificacionEscala` ·0 |
|   | `NroIdentificadorIngreso` · `FK_IdentificadorIngresoCalificacion_IdentificadorIngreso` | `IdentificadorIngreso` ·0 |
| `IdentificadorIngreso_Historial_Eliminacion` ·0 | — | — |
| `IdentificadorItemDeControl` ·0 | `CodIdentificador` · `FK_IdentificadorItemDeControl_Identificador` | `Identificador` ·1,638 |
|   | `CodItemDeControl` · `FK_IdentificadorItemDeControl_ItemDeControl` | `ItemDeControl` ·10 |
| `IdentificadorMarcacion` ·0 | — | — |
| `IdentificadorPortalProveedor` ·0 | — | — |
| `IdentificadorSucursal` ·72 | `CodIdentificador` · `FK_IdentificadorSucursal_Identificador` | `Identificador` ·1,638 |
| `IdentificadorTarjeta` ·0 | `NroEsquemaPlanilla` · `FkNroEsquemaPlanillaIdentificadorTarjeta` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FkCodIdentificadorIdentificadorTarjeta` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FkCodProyectoIdentificadorTarjeta` | `Proyecto` ·234 |
| `IdentificadorTercero` ·0 | `CodIdentificador` · `FK_IdentificadorTercero_Identificador` | `Identificador` ·1,638 |
|   | `NroTercero` · `FK_IdentificadorTercero_Tercero` | `Tercero` ·14 |
| `IdentificadorTipoIdentificador` ·1,655 | `CodIdentificador` · `FK_IdentificadorTipoIdentificador_Identificador` | `Identificador` ·1,638 |
|   | `CodTipoIdentificador` · `FK_IdentificadorTipoIdentificador_TipoIdentificador` | `TipoIdentificador` ·13 |
| `IdentificadorTipoIdentificadorIdentificador` ·329 | — | — |
| `Identificador_Notas` ·0 | — | — |
| `Identificador_SSCO` ·212 | — | — |
| `IndicadorGestion` ·18 | — | — |
| `IndicadorGestionCabecera` ·0 | `NroIG` · `FK_IndicadorGestionCabecera_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `CodInsumo` · `FK_IndicadorGestionCabecera_Insumo` | `Insumo` ·10,254 |
|   | `NroReglaIG` · `FK_IndicadorGestionCabecera_ReglaIndicadorGestion` | `ReglaIndicadorGestion` ·0 |
| `IndicadorGestionDetalle` ·0 | `NroIG` · `FK_IndicadorGestionDetalle_IndicadorGestion` | `IndicadorGestion` ·18 |
| `IndicadorGestionGlobal` ·0 | — | — |
| `InformacionBaseDatos` ·12 | — | — |
| `Licencia` ·0 | — | — |
| `Modulo` ·1 | — | — |
| `ModuloRecurso` ·18 | — | — |
| `Moneda` ·2 | — | — |
| `MonedaInternacional` ·180 | — | — |
| `Notero` ·508 | — | — |
| `NoteroCalificacion` ·0 | — | — |
| `NoteroCambio` ·11 | — | — |
| `NoteroEstado` ·0 | — | — |
| `NoteroMultiple` ·24 | — | — |
| `Notificacion` ·0 | `CodIdentificador` · `FK_Notificacion_Identificador` | `Identificador` ·1,638 |
|   | `NroSelectividad` · `FK_Notificacion_Selectividad` | `Selectividad` ·0 |
| `NotificacionDestinatarios` ·24 | — | — |
| `NotificacionDestinatariosDetalle` ·13 | `NroEnvio` · `FK_NotificacionDestinatariosDetalle_NotificacionEnvios` | `NotificacionEnvios` ·9 |
| `NotificacionEnvios` ·9 | — | — |
| `NotificacionEnviosAdjunto` ·18 | `NroEnvio` · `FK_NotificacionEnviosAdjunto_NotificacionEnvios` | `NotificacionEnvios` ·9 |
| `NotificacionWeb` ·0 | `CodModulo` · `FK_NotificacionWeb_Modulo` | `Modulo` ·1 |
|   | `CodRecurso` · `FK_NotificacionWeb_Recurso` | `Recurso` ·18 |
| `Pais` ·240 | — | — |
| `Registro` ·4 | `CodFlujo` · `FK_Registro_Flujo` | `Flujo` ·0 |
|   | `CodIdentificador` · `FK_Registro_Identificador` | `Identificador` ·1,638 |
|   | `CodRol` · `FK_Registro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_Registro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_Registro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `RegistroAdicionalItemDeControl` ·0 | — | — |
| `RegistroAuditoria` ·0 | `CodRegistro` · `FK_RegistroAuditoria_Registro` | `Registro` ·4 |
| `RegistroAuditoriaNota` ·0 | `NroNota` · `FK_RegistroAuditoriaNota_NoteroMultiple` | `NoteroMultiple` ·24 |
|   | `CodRegistro` · `FK_RegistroAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Fecha` · `FK_RegistroAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Usuario` · `FK_RegistroAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
| `RegistroAuditoriaRespuesta` ·0 | `CodAccion` · `FK_RegistroAuditoriaRespuesta_Accion` | `Accion` ·4 |
|   | `CodRegistro` · `FK_RegistroAuditoriaRespuesta_Registro` | `Registro` ·4 |
| `RegistroCertificadoDimension` ·0 | — | — |
| `RegistroConsumo` ·0 | `CodPartidaDeControl` · `FK_RegistroConsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyectoPeriodo` · `FK_RegistroConsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodPeriodo` · `FK_RegistroConsumo_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoPeriodo` · `FK_RegistroConsumo_Periodo` | `Periodo` ·34,066 |
| `RegistroConsumoDetalle` ·0 | `CodInsumo` · `FK_RegistroConsumoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodProyecto` · `FK_RegistroConsumoDetalle_RegistroConsumo` | `RegistroConsumo` ·0 |
|   | `CodRegistro` · `FK_RegistroConsumoDetalle_RegistroConsumo` | `RegistroConsumo` ·0 |
| `RegistroConsumoDiario` ·0 | `CodInsumo` · `FK__RegistroC__CodIn__0FD41AB9` | `Insumo` ·10,254 |
|   | `CodProyecto` · `FK__RegistroConsumoD__0EDFF680` | `RegistroConsumo` ·0 |
|   | `CodRegistro` · `FK__RegistroConsumoD__0EDFF680` | `RegistroConsumo` ·0 |
| `RegistroCriterioEstadistica` ·0 | — | — |
| `RegistroCriterioEstadisticaAccion` ·0 | `CodAccion` · `FK_RegistroCriterioEstadisticaAccion_Accion` | `Accion` ·4 |
| `RegistroCriterioEstadisticaItemDeControl` ·0 | `CodItemDeControl` · `FK_RegistroCriterioEstadisticaItemDeControl_ItemDeControl` | `ItemDeControl` ·10 |
| `RegistroDimensionApp` ·0 | — | — |
| `RegistroDocumento` ·0 | `CodFichaDocumento` · `FK_RegistroDocumento_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodRegistro` · `FK_RegistroDocumento_Registro` | `Registro` ·4 |
| `RegistroDocumentoUsuario` ·0 | `CodFichaDocumento` · `FK_RegistroDocumentoUsuario_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodRegistro` · `FK_RegistroDocumentoUsuario_Registro` | `Registro` ·4 |
| `RegistroEstadisticaAccion` ·0 | `CodAccion` · `FK_RegistroEstadisticaAccion_Accion` | `Accion` ·4 |
| `RegistroEstadisticaItemDeControl` ·0 | `CodItemDeControl` · `FK_RegistroEstadisticaItemDeControl_ItemDeControl` | `ItemDeControl` ·10 |
| `RegistroIdentificador` ·0 | `CodContacto` · `FK_RegistroIdentificador_Contacto` | `Identificador` ·1,638 |
|   | `CodIdentificador` · `FK_RegistroIdentificador_Identificador` | `Identificador` ·1,638 |
|   | `CodRegistro` · `FK_RegistroIdentificador_Registro` | `Registro` ·4 |
| `RegistroIdentificadorAdicionalAccion` ·0 | — | — |
| `RegistroIdentificadorAdicionalItemDeControl` ·0 | — | — |
| `RegistroIdentificadorAplicacion` ·0 | — | — |
| `RegistroIdentificadorConfiguracion` ·0 | `CodDocumento` · `FK_RegistroIdentificadorConfiguracion_TipoDocumento` | `TipoDocumento` ·66 |
| `RegistroIdentificadorNota` ·0 | `NroNota` · `FK_RegistroIdentificadorNota_NoteroMultiple` | `NoteroMultiple` ·24 |
|   | `CodIdentificador` · `FK_RegistroIdentificadorNota_RegistroIdentificador` | `RegistroIdentificador` ·0 |
|   | `CodRegistro` · `FK_RegistroIdentificadorNota_RegistroIdentificador` | `RegistroIdentificador` ·0 |
|   | `CodSecuencial` · `FK_RegistroIdentificadorNota_RegistroIdentificador` | `RegistroIdentificador` ·0 |
| `RegistroIdentificadorNotificacion` ·0 | — | — |
| `RegistroIdentificadorParticipante` ·0 | — | — |
| `RegistroIdentificadorPermuta` ·0 | — | — |
| `RegistroIdentificadorProyecto` ·0 | `CodProyecto` · `FK_RegistroIdentificadorProyecto_Proyecto` | `Proyecto` ·234 |
| `RegistroIdentificadorRespuesta` ·0 | `CodAccion` · `FK_RegistroIdentificadorRespuesta_Accion` | `Accion` ·4 |
|   | `CodIdentificador` · `FK_RegistroIdentificadorRespuesta_RegistroIdentificador` | `RegistroIdentificador` ·0 |
|   | `CodRegistro` · `FK_RegistroIdentificadorRespuesta_RegistroIdentificador` | `RegistroIdentificador` ·0 |
|   | `CodSecuencial` · `FK_RegistroIdentificadorRespuesta_RegistroIdentificador` | `RegistroIdentificador` ·0 |
| `RegistroIdentificadorRespuestaNota` ·0 | — | — |
| `RegistroIdentificadorUsuario` ·0 | `CodIdentificador` · `FK_RegistroIdentificadorUsuario_Identificador` | `Identificador` ·1,638 |
| `RegistroItemdeControl` ·0 | `CodItemDeControl` · `FK_RegistroItemDeControl_ItemDeControl` | `ItemDeControl` ·10 |
|   | `CodRegistro` · `FK_RegistroItemdeControl_Registro` | `Registro` ·4 |
| `RegistroLimpiarTrazaDeLog` ·0 | — | — |
| `RegistroMuestra` ·0 | — | — |
| `RegistroMuestraDetalle` ·0 | — | — |
| `RegistroOrganizadorUsuario` ·0 | `CodOrganizadorRegistro` · `FK_RegistroOrganizadorUsuario_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
|   | `Usuario` · `FK_RegistroOrganizadorUsuario_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
|   | `CodRegistro` · `FK_RegistroOrganizadorUsuario_Registro` | `Registro` ·4 |
| `RegistroPaquete` ·0 | `CodPaquete` · `FK_RegistroPaquete_Paquete` | `Paquete` ·0 |
|   | `CodRegistro` · `FK_RegistroPaquete_Registro` | `Registro` ·4 |
| `RegistroPaso` ·0 | `CodRegistro` · `FK_RegistroPaso_Registro` | `Registro` ·4 |
|   | `CodRol` · `FK_RegistroPaso_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_RegistroPaso_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_RegistroPaso_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `RegistroPasoAuditoriaNota` ·0 | `NroNota` · `FK_RegistroPasoAuditoriaNota_NoteroMultiple` | `NoteroMultiple` ·24 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoria_Registro` | `Registro` ·4 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Fecha` · `FK_RegistroPasoAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Usuario` · `FK_RegistroPasoAuditoriaNota_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `CodPaso` · `FK_RegistroPasoAuditoriaNota_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoriaNota_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoAuditoriaRespuesta` ·0 | `CodAccion` · `FK_RegistroPasoAuditoriaRespuesta_Accion` | `Accion` ·4 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoriaRespuesta_Registro` | `Registro` ·4 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoriaRespuesta_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Fecha` · `FK_RegistroPasoAuditoriaRespuesta_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `Usuario` · `FK_RegistroPasoAuditoriaRespuesta_RegistroAuditoria` | `RegistroAuditoria` ·0 |
|   | `CodPaso` · `FK_RegistroPasoAuditoriaRespuesta_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoAuditoriaRespuesta_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoDocumento` ·0 | `CodFichaDocumento` · `FK_RegistroPasoDocumento_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodPaso` · `FK_RegistroPasoDocumento_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoDocumento_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoInstruccion` ·0 | `CodFichaDocumento` · `FK_RegistroPasoInstruccion_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodPaso` · `FK_RegistroPasoInstruccion_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoInstruccion_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoLeido` ·0 | `CodRegistro` · `FK_RegistroPasoLeido_Registro` | `Registro` ·4 |
| `RegistroPasoNota` ·0 | `NroNota` · `FK_RegistroPasoNota_NoteroMultiple` | `NoteroMultiple` ·24 |
|   | `CodPaso` · `FK_RegistroPasoNota_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoNota_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoPaquete` ·0 | `CodPaquete` · `FK_RegistroPasoPaquete_Paquete` | `Paquete` ·0 |
|   | `CodPaso` · `FK_RegistroPasoPaquete_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoPaquete_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroPasoRespuesta` ·0 | `CodAccion` · `FK_RegistroPasoRespuesta_Accion` | `Accion` ·4 |
|   | `CodPaso` · `FK_RegistroPasoRespuesta_RegistroPaso` | `RegistroPaso` ·0 |
|   | `CodRegistro` · `FK_RegistroPasoRespuesta_RegistroPaso` | `RegistroPaso` ·0 |
| `RegistroSoporte` ·0 | — | — |
| `RegistroSoporteTablas` ·0 | — | — |
| `RegistroUsuario` ·0 | — | — |
| `Registro_Impresion_Comprobante_Retencion_Portal` ·0 | — | — |
| `Responsable` ·0 | `CodCargo` · `FK_Responsable_Cargo` | `Cargo` ·14 |
|   | `CodIdentificador` · `FK_Responsable_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_Responsable_Proyecto` | `Proyecto` ·234 |
| `Restriccion` ·0 | `CodProyecto` · `FK_Restriccion_Proyecto` | `Proyecto` ·234 |
|   | `NroTipoRestriccion` · `FK_Restriccion_TipoRestriccion` | `TipoRestriccion` ·9 |
| `RestriccionAccion` ·0 | `NroRestriccion` · `FK_RestriccionAccion_Restriccion` | `Restriccion` ·0 |
| `RestriccionActividad` ·0 | `NroRestriccion` · `FK_RestriccionActividad_Restriccion` | `Restriccion` ·0 |
| `RestriccionIdentificador` ·0 | `CodIdentificador` · `FK_RestriccionIdentificador_Identificador` | `Identificador` ·1,638 |
|   | `NroRestriccion` · `FK_RestriccionIdentificador_Restriccion` | `Restriccion` ·0 |
| `RestriccionNota` ·0 | `NroRestriccion` · `FK_RestriccionNota_Restriccion` | `Restriccion` ·0 |
| `Rol` ·21 | — | — |
| `Selectividad` ·0 | `NroDivision` · `FK_Selectividad_Division` | `Division` ·0 |
|   | `CodModalidad` · `FK_Selectividad_Modalidad` | `Modalidad` ·0 |
|   | `CodMoneda` · `FK_Selectividad_Moneda` | `Moneda` ·2 |
|   | `CodPrecalificacion` · `FK_Selectividad_Precalificacion` | `Precalificacion` ·0 |
| `SelectividadAplicabilidad` ·0 | `NroAplicabilidad` · `FK_SelectividadAplicabilidad_Aplicabilidad` | `Aplicabilidad` ·0 |
|   | `NroSelectividad` · `FK_SelectividadAplicabilidad_Selectividad` | `Selectividad` ·0 |
| `SelectividadCalificacion` ·0 | `NroSelectividad` · `FK_SelectividadCalificacion_Selectividad` | `Selectividad` ·0 |
| `SelectividadFecha` ·0 | `CodFecha` · `FK_SelectividadFecha_Fecha` | `Fecha` ·0 |
|   | `NroSelectividad` · `FK_SelectividadFecha_Selectividad` | `Selectividad` ·0 |
| `SelectividadHistorico` ·0 | `NroSelectividad` · `FK_SelectividadHistorico_Selectividad` | `Selectividad` ·0 |
| `SelectividadPresupuestador` ·0 | `CodIdentificador` · `FK_SelectividadPresupuestador_Identificador` | `Identificador` ·1,638 |
|   | `NroSelectividad` · `FK_SelectividadPresupuestador_Selectividad` | `Selectividad` ·0 |
| `ServicePack` ·2 | — | — |
| `SucursalUsuario` ·8 | `CodIdentificador` · `FK_SucursalUsuario_IdentificadorSucursal` | `IdentificadorSucursal` ·72 |
|   | `CodSucursal` · `FK_SucursalUsuario_IdentificadorSucursal` | `IdentificadorSucursal` ·72 |
|   | `CodUsuario` · `FK_SucursalUsuario_Usuario` | `Usuario` ·14 |
| `SucursalUsuarioPermiso` ·1,936 | `NroSucursalUsuario` · `FK_SucursalUsuarioPermiso_SucursalUsuario` | `SucursalUsuario` ·8 |
| `SucursalUsuarioPermiso_Historial` ·1,012 | — | — |
| `SucursalUsuario_Historial` ·22 | — | — |
| `TipoActa` ·3 | — | — |
| `TipoActaProyecto` ·0 | `CodProyecto` · `FK_TipoActaProyecto_Proyecto` | `Proyecto` ·234 |
|   | `NroTipoActa` · `FK_TipoActaProyecto_TipoActa` | `TipoActa` ·3 |
| `TipoAgrupamientoDocumento` ·0 | — | — |
| `TipoAlerta` ·0 | — | — |
| `TipoBarra` ·0 | — | — |
| `TipoBoletaNomina` ·0 | `NroEsquemaPlanilla` · `FK_TipoBoletaNomina_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `TipoBoletaNominaFormato` ·809 | — | — |
| `TipoBoletaNominaProceso` ·0 | `NroProcesoNomina` · `FK_TipoBoletaNominaProceso_ProcesoNomina` | `ProcesoNomina` ·11 |
| `TipoBoletaNominaProcesoConcepto` ·0 | `NroConcepto` · `FK_TipoBoletaNominaProcesoConcepto_Concepto` | `Concepto` ·982 |
|   | `NroTipoBoletaNominaProceso` · `FK_TipoBoletaNominaProcesoConcepto_TipoBoletaNominaProceso` | `TipoBoletaNominaProceso` ·0 |
| `TipoBoletaNominaProcesoConceptoHistorial` ·0 | — | — |
| `TipoCalidad` ·10 | — | — |
| `TipoCalificacionEscala` ·0 | — | — |
| `TipoCambio` ·1,494 | `CodMoneda` · `FK__TipoCambi__CodMo__1EF0A18D` | `Moneda` ·2 |
| `TipoCampoEspecificacionDinamica` ·7 | — | — |
| `TipoCartaFianza` ·3 | — | — |
| `TipoCentroCompra` ·1 | `NroCC` · `FK_TipoCentroCompra_CentroCompra` | `CentroCompra` ·1 |
| `TipoCentroCompraDetalle` ·0 | `NroCCP` · `FK_TipoCentroCompraDetalle_CentroCompraProyecto1` | `CentroCompraProyecto` ·11 |
|   | `NroTipoCC` · `FK_TipoCentroCompraDetalle_TipoCentroCompra` | `TipoCentroCompra` ·1 |
| `TipoCentroCompraDetalleUsuario` ·0 | `NroTipoCCD` · `FK_TipoCentroCompraDetalleUsuario_TipoCentroCompraDetalle1` | `TipoCentroCompraDetalle` ·0 |
| `TipoContrato` ·0 | — | — |
| `TipoCredito` ·0 | — | — |
| `TipoCuentaBancaria` ·0 | — | — |
| `TipoDescuento` ·0 | `NroConceptoAmortizacion` · `FK_TipoDescuento_ConceptoAmortizacion` | `Concepto` ·982 |
|   | `NroConceptoDescuento` · `FK_TipoDescuento_ConceptoDescuento` | `Concepto` ·982 |
|   | `CodInsumo` · `FK_TipoDescuento_Insumo` | `Insumo` ·10,254 |
|   | `CodDocumento` · `FK_TipoDescuento_TipoDocumento` | `TipoDocumento` ·66 |
| `TipoDocumento` ·66 | `CodCTD` · `FK_TipoDocumento_CategoriaTipoDocumento` | `CategoriaTipoDocumento` ·0 |
|   | `NroTDS` · `FK_TipoDocumento_TipoDocumentoSunat` | `TipoDocumentoSunat` ·50 |
| `TipoDocumentoImpuesto` ·18 | `NroImpuesto` · `FK_TipoDocumentoImpuesto_Impuesto` | `Impuesto` ·13 |
|   | `CodDocumento` · `FK_TipoDocumentoImpuesto_TipoDocumento` | `TipoDocumento` ·66 |
| `TipoDocumentoMapper` ·0 | — | — |
| `TipoDocumentoSunat` ·50 | — | — |
| `TipoEquipo` ·0 | — | — |
| `TipoEvaluacionPersonal` ·0 | — | — |
| `TipoIdentificador` ·13 | — | — |
| `TipoModulo` ·5 | — | — |
| `TipoObra` ·5 | — | — |
| `TipoOperacion` ·11 | — | — |
| `TipoPrestamo` ·4 | `NroConceptoSaldo` · `FK_TipoPrestamo_ConceptoSaldo` | `Concepto` ·982 |
| `TipoProyecto` ·6 | — | — |
| `TipoProyectoDetalle` ·0 | `CodProyecto` · `FK_TipoProyectoDetalle_Proyecto` | `Proyecto` ·234 |
|   | `NroTipoProyecto` · `FK_TipoProyectoDetalle_TipoProyecto` | `TipoProyecto` ·6 |
| `TipoProyectoDetalleUsuario` ·0 | `NroTPD` · `FK_TipoProyectoDetalleUsuario_TipoProyectoDetalle` | `TipoProyectoDetalle` ·0 |
| `TipoRestriccion` ·9 | — | — |
| `TipoSeguridad` ·9 | — | — |
| `TipoTercero` ·4 | — | — |
| `TipoTerceroConcepto` ·0 | `NroConcepto` · `FK_TipoTerceroConcepto_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_TipoTerceroConcepto_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroTipoTercero` · `FK_TipoTerceroConcepto_TipoTercero` | `TipoTercero` ·4 |
| `TipoTerceroConfiguracionConcepto` ·7 | `NroConcepto` · `FK_TipoTerceroConfiguracionConcepto_Concepto` | `Concepto` ·982 |
|   | `NroTipoTercero` · `FK_TipoTerceroConfiguracionConcepto_TipoTercero` | `TipoTercero` ·4 |
| `TipoVectorCaracteristica` ·0 | — | — |
| `TipoVectorEspecificacion` ·0 | — | — |
| `TipodocumentoSunatComplemento` ·127 | `NroTDS` · `FK_TipodocumentoSunatComplemento_NroTDS` | `TipoDocumentoSunat` ·50 |
| `UbicacionGeografica` ·2,115 | — | — |
| `Unidad` ·83 | — | — |
| `UnidadEquivalente` ·0 | — | — |
| `UnidadMedida` ·1,094 | — | — |
| `UnidadNegocio` ·0 | `CodIdentificador` · `FK_UnidadNegocio_Identificador` | `Identificador` ·1,638 |
| `UnidadNegocioProyecto` ·0 | `NroUnidadNegocio` · `FK_UnidadNegocioProyecto_UnidadNegocio` | `UnidadNegocio` ·0 |
| `UnidadTmp` ·0 | — | — |
| `Usuario` ·14 | `CodIdentificador` · `FK_Usuario_Identificador` | `Identificador` ·1,638 |
| `UsuarioPedidoSincronizacion` ·0 | — | — |
| `Usuario_Historial` ·264 | — | — |

---

## M02 · Presupuestos

`[V]` 117 tablas · 169 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Clasificacion` ·348 | `CodProyecto` · `FK_Clasificacion_Proyecto` | `Proyecto` ·234 |
| `ClasificacionCampos` ·0 | — | — |
| `ClasificacionCentralProducto` ·0 | — | — |
| `ClasificacionDocumento` ·246 | — | — |
| `ClasificacionGeneralCampos` ·1,627 | — | — |
| `ClasificacionGeneralGrupos` ·2 | — | — |
| `ClasificacionGeneralGruposCampos` ·43 | `NroCGG` · `FK_ClasificacionGeneralGruposCampos_ClasificacionGeneralGrupos` | `ClasificacionGeneralGrupos` ·2 |
| `ClasificacionPapeleta` ·14 | `NroConcepto` · `FK_ClasificacionPapeleta_ConceptoGenerico` | `ConceptoGenerico` ·264 |
| `ClasificacionPapeletaPlantilla` ·0 | `NroClasificacionPapeleta` · `FK_ClasificacionPapeletaPlantilla_ClasificacionPapeleta` | `ClasificacionPapeleta` ·14 |
|   | `NroConceptoDestino` · `FK_ClasificacionPapeletaPlantilla_ConceptoDestino` | `Concepto` ·982 |
|   | `NroConceptoOrigen` · `FK_ClasificacionPapeletaPlantilla_ConceptoOrigen` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ClasificacionPapeletaPlantilla_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `ClasificacionSuma` ·0 | `CodProyecto` · `FK_ClasificacionSuma_Proyecto` | `Proyecto` ·234 |
| `ClasificacionSumaRecurso` ·0 | `CodClasificacion` · `FK_ClasificacionSumaRecurso_ClasificacionSuma` | `ClasificacionSuma` ·0 |
|   | `CodPeriodo` · `FK_ClasificacionSumaRecurso_ClasificacionSuma` | `ClasificacionSuma` ·0 |
|   | `CodProyecto` · `FK_ClasificacionSumaRecurso_ClasificacionSuma` | `ClasificacionSuma` ·0 |
|   | `Tipo` · `FK_ClasificacionSumaRecurso_ClasificacionSuma` | `ClasificacionSuma` ·0 |
| `ClasificacionTipo` ·11,776 | — | — |
| `ClasificacionTipoDetalle` ·131,871 | — | — |
| `Especialidad` ·5 | — | — |
| `EspecialidadControl` ·2 | `CodProyecto` · `FK_EspecialidadControl_Proyecto` | `Proyecto` ·234 |
| `FormulaPolinomica` ·40 | `CodIndiceUnificado` · `FK_FormulaPolinomica_IndiceUnificado` | `IndiceUnificado` ·85 |
|   | `CodMoneda` · `FK_FormulaPolinomica_Moneda` | `Moneda` ·2 |
|   | `CodPresupuesto` · `FK_FormulaPolinomica_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_FormulaPolinomica_Subpresupuesto` | `Subpresupuesto` ·33 |
| `FormulaPolinomicaDetalle` ·0 | — | — |
| `FormulaPolinomicaDetalleValorizacion` ·0 | — | — |
| `FormulaPolinomicaValorizacion` ·0 | — | — |
| `GrupoEmpresarial` ·0 | `CodMoneda` · `FK_GrupoEmpresarial_Moneda` | `Moneda` ·2 |
| `GrupoEmpresarialIdentificador` ·0 | `NroEsquemaContable` · `FK_GrupoEmpresarialIdentificador_EsquemaContable` | `EsquemaContable` ·1 |
|   | `NroGrupoEmpresarial` · `FK_GrupoEmpresarialIdentificador_GrupoEmpresarial` | `GrupoEmpresarial` ·0 |
| `GrupoFactor` ·0 | — | — |
| `GrupoFactorInflacion` ·0 | `NroGrupoFactor` · `FK_GrupoFactorInflacion_GrupoFactor` | `GrupoFactor` ·0 |
|   | `CodIndiceUnificado` · `FK_GrupoFactorInflacion_IndiceUnificado` | `IndiceUnificado` ·85 |
| `GrupoPresupuesto` ·0 | — | — |
| `GrupoProcesoNomina` ·0 | `NroEsquemaPlanilla` · `FK_GrupoProcesoNomina_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `GrupoProcesoNominaDetalle` ·0 | `NroGrupoProcesoNomina` · `FK_GrupoProcesoNominaDetalle_GrupoProcesoNomina` | `GrupoProcesoNomina` ·0 |
|   | `NroProcesoNomina` · `FK_GrupoProcesoNominaDetalle_ProcesoNomina` | `ProcesoNomina` ·11 |
| `GrupoProyecto` ·0 | — | — |
| `GrupoProyectoDetalle` ·0 | `CodGrupo` · `FK__GrupoProy__CodGr__139B2389` | `GrupoProyecto` ·0 |
|   | `CodProyecto` · `FK__GrupoProy__CodPr__148F47C2` | `Proyecto` ·234 |
| `Grupos` ·608 | — | — |
| `GruposCampos` ·48 | `Correlativo` · `FK_GruposCampos_Grupos` | `Grupos` ·608 |
|   | `Escenario` · `FK_GruposCampos_Grupos` | `Grupos` ·608 |
|   | `Usuario` · `FK_GruposCampos_Grupos` | `Grupos` ·608 |
| `Insumo` ·10,254 | `CodIndiceUnificado` · `FK_Insumo_IndiceUnificado` | `IndiceUnificado` ·85 |
|   | `NroCuentaDebe` · `FK_Insumo_PlanContable` | `PlanContable` ·926 |
|   | `NroCuentaHaber` · `FK_Insumo_PlanContable1` | `PlanContable` ·926 |
|   | `CodUnidad` · `FK_Insumo_Unidad` | `Unidad` ·83 |
| `InsumoActivo` ·0 | `CodInsumo` · `FK_InsumoActivo_Insumo` | `Insumo` ·10,254 |
|   | `CodInsumoD` · `FK_InsumoActivo_Insumo1` | `Insumo` ·10,254 |
| `InsumoAnticipo` ·0 | `CodInsumo` · `FK_InsumoAnticipo_Insumo` | `Insumo` ·10,254 |
| `InsumoCalendario` ·0 | `CodCalendario` · `FK__InsumoCalendario__7BF93DAF` | `Calendario` ·33 |
|   | `CodProyectoCalendario` · `FK__InsumoCalendario__7BF93DAF` | `Calendario` ·33 |
|   | `CodInsumo` · `FK__InsumoCal__CodIn__7B051976` | `Insumo` ·10,254 |
|   | `CodProyecto` · `FK__InsumoCal__CodPr__7A10F53D` | `Proyecto` ·234 |
| `InsumoCategoria` ·0 | `CodCategoria` · `FK_InsumoCategoria_Categoria` | `Categoria` ·7 |
|   | `CodInsumo` · `FK_InsumoCategoria_Insumo` | `Insumo` ·10,254 |
|   | `CodProyecto` · `FK_InsumoCategoria_Proyecto` | `Proyecto` ·234 |
| `InsumoClasificacion` ·13 | — | — |
| `InsumoComplemento` ·10,254 | `NroDetraccion` · `FK_InsumoComplemento_Detraccion` | `Detraccion` ·41 |
|   | `CodInsumo` · `FK_InsumoComplemento_Insumo` | `Insumo` ·10,254 |
|   | `NroInsumoCategoria` · `FK_InsumoComplemento_RecursoCategoria` | `RecursoCategoria` ·0 |
| `InsumoConceptoAjuste` ·2 | `CodInsumo` · `FK_InsumoConceptoAjuste_Insumo` | `Insumo` ·10,254 |
| `InsumoConceptoBanco` ·0 | `CodInsumo` · `FK_InsumoConceptoBanco_Insumo` | `Insumo` ·10,254 |
| `InsumoConceptoBoletajeNotaDebito` ·0 | `CodInsumo` · `FK_InsumoConceptoBoletajeNotaDebito_Insumo` | `Insumo` ·10,254 |
| `InsumoCostos` ·1 | — | — |
| `InsumoDesagregadoTecnologico` ·0 | `CodInsumo` · `FK_InsumoDesagregadoTecnologico_Insumo` | `Insumo` ·10,254 |
| `InsumoDetalle` ·7 | `CodInsumo` · `FK_InsumoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodInsumoD` · `FK_InsumoDetalle_InsumoD` | `Insumo` ·10,254 |
| `InsumoEquivalente` ·0 | — | — |
| `InsumoMapper` ·0 | — | — |
| `InsumoNoDeducible` ·0 | `CodInsumo` · `FK_InsumoNoDeducible_Insumo` | `Insumo` ·10,254 |
| `InsumoRecodifica` ·0 | — | — |
| `InsumoRecurso` ·0 | — | — |
| `InsumoRendicion` ·0 | `CodInsumo` · `FK_InsumoRendicion_Insumo` | `Insumo` ·10,254 |
| `InsumoServicios` ·0 | — | — |
| `InsumoTipoTitulo` ·0 | `CodInsumo` · `FK_InsumoTipoTitulo_Insumo` | `Insumo` ·10,254 |
| `Insumo_Historial` ·0 | `CodInsumo` · `FK_Insumo_Historial_Insumo` | `Insumo` ·10,254 |
| `Insumo_Incidencia` ·0 | — | — |
| `Insumo_Mantenimiento` ·0 | `CodInsumo` · `FK_Insumo_Mantenimiento_Insumo` | `Insumo` ·10,254 |
| `Insumo_Observacion` ·0 | `CodInsumo` · `FK_Insumo_Observacion_Insumo` | `Insumo` ·10,254 |
| `MetradoDetalle` ·0 | — | — |
| `Partida` ·9,113 | `CodFase` · `FK_Partida_Fase` | `Fase` ·0 |
|   | `CodPresupuesto` · `FK_Partida_Presupuesto` | `Presupuesto` ·44 |
|   | `CodUnidad` · `FK_Partida_Unidad` | `Unidad` ·83 |
|   | `CodUnidadPeso` · `FK_Partida_UnidadPeso` | `Unidad` ·83 |
| `PartidaControlGeneral` ·57 | — | — |
| `PartidaDetalle` ·28,429 | `CodInsumo` · `FK_PartidaDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_PartidaDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPartidaR` · `FK_PartidaDetalle_Partida_` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PartidaDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoR` · `FK_PartidaDetalle_Partida_` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PartidaDetalle_Partida` | `Partida` ·9,113 |
|   | `PropioPartidaR` · `FK_PartidaDetalle_Partida_` | `Partida` ·9,113 |
| `PartidaEspecialidad` ·494 | — | — |
| `PartidaPeriodoCronograma` ·0 | `CodPeriodo` · `FK_PartidaPeriodoCronograma_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_PartidaPeriodoCronograma_Periodo` | `Periodo` ·34,066 |
|   | `CodPresupuesto` · `FK_PartidaPeriodoCronograma_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_PartidaPeriodoCronograma_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_PartidaPeriodoCronograma_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `PartidaRendimiento` ·0 | `CodPartida` · `FK_PartidaRendimiento_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PartidaRendimiento_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PartidaRendimiento_Partida` | `Partida` ·9,113 |
| `Presupuesto` ·44 | `CodIdentificador` · `FK_Presupuesto_Identificador` | `Identificador` ·1,638 |
|   | `CodLogo` · `FK_Presupuesto_Logo` | `Logo` ·5 |
|   | `CodMoneda1` · `FK_Presupuesto_Moneda` | `Moneda` ·2 |
|   | `CodMoneda2` · `FK_Presupuesto_Moneda__` | `Moneda` ·2 |
|   | `CodMonedaCambio` · `FK_Presupuesto_Moneda_` | `Moneda` ·2 |
|   | `CodLugar` · `FK_Presupuesto_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `PresupuestoAgrupacion` ·0 | — | — |
| `PresupuestoAgrupacionPresupuesto` ·0 | `CodPresupuesto` · `FK_PresupuestoAgrupacionPresupuesto_Presupuesto` | `Presupuesto` ·44 |
|   | `NroAgrupacion` · `FK_PresupuestoAgrupacionPresupuesto_PresupuestoAgrupacion` | `PresupuestoAgrupacion` ·0 |
| `PresupuestoComplemento` ·7 | `CodCargo` · `FK_PresupuestoComplemento_Cargo` | `Cargo` ·14 |
|   | `CodIdentificadorOferente` · `FK_PresupuestoComplemento_IdentificadorOferente` | `Identificador` ·1,638 |
|   | `CodIdentificadorResponsable` · `FK_PresupuestoComplemento_IdentificadorResponsable` | `Identificador` ·1,638 |
|   | `CodPresupuesto` · `FK_PresupuestoComplemento_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoEquipoAlquiler` ·0 | `CodIdentificador` · `FK_PresupuestoEquipoAlquiler_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_PresupuestoEquipoAlquiler_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoEquipoAlquiler_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoEstimadaDetalle` ·0 | `CodInsumo` · `FK_PresupuestoEstimadaDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoEstimadaDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_PresupuestoEstimadaDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_PresupuestoEstimadaDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `PresupuestoFaseDetalle` ·0 | `CodPresupuesto` · `FK_PresupuestoFaseDetalle_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoFaseInsumoFactor` ·0 | `CodPresupuesto` · `FK_PresupuestoFaseInsumoFactor_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoHistoria` ·12,156 | — | — |
| `PresupuestoIdentificador` ·0 | — | — |
| `PresupuestoInsumo` ·671 | `CodInsumo` · `FK_PresupuestoInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoInsumo_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoInsumoCotizacion` ·0 | `CodProveedor` · `FK_PresupuestoInsumoCotizacion_Identificador` | `Identificador` ·1,638 |
|   | `CodPresupuesto` · `FK_PresupuestoInsumoCotizacion_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoInsumoDetalle` ·0 | `CodInsumo` · `FK_PresupuestoInsumoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodInsumoD` · `FK_PresupuestoInsumoDetalle_InsumoD` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoInsumoDetalle_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoInsumoFactor` ·0 | `CodPresupuesto` · `FK_PresupuestoInsumoFactor_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoInsumoTUnitario` ·0 | — | — |
| `PresupuestoInsumoVinculo` ·0 | `CodPresupuesto` · `FK_PresupuestoInsumoVinculo_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoInsumoVinculoDetalle` ·0 | `CodInsumo` · `FK_PresupuestoInsumoVinculoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoInsumoVinculoDetalle_PresupuestoInsumoVinculo` | `PresupuestoInsumoVinculo` ·0 |
|   | `Secuencial` · `FK_PresupuestoInsumoVinculoDetalle_PresupuestoInsumoVinculo` | `PresupuestoInsumoVinculo` ·0 |
| `PresupuestoItemDetalle` ·12,582 | `CodInsumo` · `FK_PresupuestoItemDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoItemDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_PresupuestoItemDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_PresupuestoItemDetalle_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `PresupuestoPartida` ·3,354 | `CodPartida` · `FK_PresupuestoPartida_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_PresupuestoPartida_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PresupuestoPartida_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PresupuestoPartida_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_PresupuestoPartida_Subpresupuesto` | `Subpresupuesto` ·33 |
| `PresupuestoPartidaAnalisis` ·11,604 | `CodPresupuesto` · `FK_PresupuestoPartidaAnalisis_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_PresupuestoPartidaAnalisis_Subpresupuesto` | `Subpresupuesto` ·33 |
| `PresupuestoPartidaDetalle` ·11,612 | `CodInsumo` · `FK_PresupuestoPartidaDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_PresupuestoPartidaDetalle_PresupuestoPartida` | `PresupuestoPartida` ·3,354 |
|   | `CodPresupuesto` · `FK_PresupuestoPartidaDetalle_PresupuestoPartida` | `PresupuestoPartida` ·3,354 |
|   | `CodPresupuestoPartida` · `FK_PresupuestoPartidaDetalle_PresupuestoPartida` | `PresupuestoPartida` ·3,354 |
|   | `CodSubpresupuesto` · `FK_PresupuestoPartidaDetalle_PresupuestoPartida` | `PresupuestoPartida` ·3,354 |
|   | `PropioPartida` · `FK_PresupuestoPartidaDetalle_PresupuestoPartida` | `PresupuestoPartida` ·3,354 |
| `PresupuestoPartidaEquipo` ·0 | `CodInsumo` · `FK_PresupuestoPartidaEquipo_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_PresupuestoPartidaEquipo_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_PresupuestoPartidaEquipo_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PresupuestoPartidaEquipo_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PresupuestoPartidaEquipo_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoPartidaEquipoDetalle` ·0 | `CodPresupuesto` · `FK_PresupuestoPartidaEquipoDetalle_Presupuesto` | `Presupuesto` ·44 |
|   | `NroPresupuestoPartidaEquipo` · `FK_PresupuestoPartidaEquipoDetalle_PresupuestoPartidaEquipo` | `PresupuestoPartidaEquipo` ·0 |
| `PresupuestoReporte` ·0 | — | — |
| `PresupuestoSubpartida` ·1,225 | — | — |
| `PresupuestoSubpartidaControl` ·10 | — | — |
| `PresupuestoSubpartidaDetalle` ·10,373 | `CodInsumo` · `FK_PresupuestoSubpartidaDetalle_Insumo` | `Insumo` ·10,254 |
| `PresupuestoSubpartidaProyecto` ·856 | `CodProyecto` · `FK_PresupuestoSubpartidaProyecto_Proyecto` | `Proyecto` ·234 |
| `PresupuestoSubpartidaProyectoConsolidado` ·0 | — | — |
| `PresupuestoSubpartidaProyectoDetalle` ·2,225 | `CodPresupuesto` · `FK_PresupuestoSubpartidaProyectoDetalle_PresupuestoSubpartidaProyecto1` | `PresupuestoSubpartidaProyecto` ·856 |
|   | `CodProyecto` · `FK_PresupuestoSubpartidaProyectoDetalle_PresupuestoSubpartidaProyecto1` | `PresupuestoSubpartidaProyecto` ·856 |
|   | `CodSubpartida` · `FK_PresupuestoSubpartidaProyectoDetalle_PresupuestoSubpartidaProyecto1` | `PresupuestoSubpartidaProyecto` ·856 |
|   | `CodSubpresupuesto` · `FK_PresupuestoSubpartidaProyectoDetalle_PresupuestoSubpartidaProyecto1` | `PresupuestoSubpartidaProyecto` ·856 |
|   | `Item` · `FK_PresupuestoSubpartidaProyectoDetalle_PresupuestoSubpartidaProyecto1` | `PresupuestoSubpartidaProyecto` ·856 |
| `PresupuestoSubpartidaReporte` ·3 | — | — |
| `PresupuestoTitulo` ·1,315 | — | — |
| `PresupuestoTituloDetalle` ·19,588 | `CodInsumo` · `FK_PresupuestoTituloDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PresupuestoTituloDetalle_PresupuestoTitulo` | `PresupuestoTitulo` ·1,315 |
|   | `CodSubpresupuesto` · `FK_PresupuestoTituloDetalle_PresupuestoTitulo` | `PresupuestoTitulo` ·1,315 |
|   | `Item` · `FK_PresupuestoTituloDetalle_PresupuestoTitulo` | `PresupuestoTitulo` ·1,315 |
| `PresupuestoUsuario` ·10 | `CodPresupuesto` · `FK_PresupuestoUsuario_Presupuesto` | `Presupuesto` ·44 |
| `PresupuestoVinculoPrecio` ·5 | — | — |
| `Subpresupuesto` ·33 | `CodPresupuesto` · `FK_Subpresupuesto_Presupuesto` | `Presupuesto` ·44 |
| `SubpresupuestoDetalle` ·6,675 | `CodPartida` · `FK_SubpresupuestoDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_SubpresupuestoDetalle_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_SubpresupuestoDetalle_Partida` | `Partida` ·9,113 |
|   | `NroPrioridad` · `FK_SubpresupuestoDetalle_Prioridad` | `Prioridad` ·10 |
|   | `CodPresupuesto` · `FK_SubpresupuestoDetalle_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_SubpresupuestoDetalle_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodTitulo` · `FK_SubpresupuestoDetalle_Titulo` | `Titulo` ·390 |
| `SubpresupuestoDetalleAsociado` ·0 | — | — |
| `SubpresupuestoDetalleCalculo` ·0 | — | — |
| `SubpresupuestoDetalleCalculoDetalle` ·0 | — | — |
| `SubpresupuestoDetalleConfiguracionDuplicado` ·0 | — | — |
| `SubpresupuestoDetalleEstructura` ·0 | — | — |
| `SubpresupuestoDetalleMedicion` ·0 | — | — |
| `SubpresupuestoDetallePendiente` ·0 | `NroPrioridad` · `FK_SubpresupuestoDetallePendiente_Prioridad` | `Prioridad` ·10 |
| `SubpresupuestoDetallePlano` ·0 | — | — |
| `SubpresupuestoDetalleSeleccion` ·510 | — | — |
| `Titulo` ·390 | — | — |
| `UnidadOperativa` ·13 | — | — |
| `UnidadOperativaAuditor` ·0 | — | — |
| `UnidadOperativaClasificacionDocumento` ·0 | `CodClasificacionDocumento` · `FK_UnidadOperativaClasificacionDocumento_ClasificacionDocumento` | `ClasificacionDocumento` ·246 |
|   | `CodUbicacionFisica` · `FK_UnidadOperativaClasificacionDocumento_DocumentoUbicacionFisica` | `DocumentoUbicacionFisica` ·8 |
|   | `CodUnidadOperativa` · `FK_UnidadOperativaClasificacionDocumento_UnidadOperativa` | `UnidadOperativa` ·13 |
| `UnidadOperativaIC` ·0 | `CodItemDeControl` · `FK_UnidadOperativaIC_ItemDeControl` | `ItemDeControl` ·10 |
|   | `CodUnidadOperativa` · `FK_UnidadOperativaIC_UnidadOperativa` | `UnidadOperativa` ·13 |
| `UnidadOperativaRol` ·1 | `CodRol` · `FK_UnidadOperativaRol_Rol` | `Rol` ·21 |
|   | `CodUnidadOperativa` · `FK_UnidadOperativaRol_UnidadOperativa` | `UnidadOperativa` ·13 |
|   | `CodUsuario` · `FK_UnidadOperativaRol_Usuario` | `Usuario` ·14 |
| `UnidadOperativaRolDetalle` ·1 | `CodRol` · `FK_UnidadOperativaRolDetalle_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_UnidadOperativaRolDetalle_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_UnidadOperativaRolDetalle_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `UnidadOperativaRolManualFunciones` ·0 | — | — |
| `UnidadOperativaRolUsuario` ·0 | `IdRol` · `FK_UnidadOperativaRolUsuario_OrganizacionRol` | `OrganizacionRol` ·0 |
|   | `IdUnidadOperativa` · `FK_UnidadOperativaRolUsuario_OrganizacionUnidadOperativa` | `OrganizacionUnidadOperativa` ·0 |

---

## M03 · Proyectos y producción

`[V]` 206 tablas · 197 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Actividad` ·653 | `CodProyecto` · `FK_Actividad_Tarea` | `Tarea` ·209 |
|   | `CodTarea` · `FK_Actividad_Tarea` | `Tarea` ·209 |
|   | `NroTareaWBS` · `FK_Actividad_TareaWBS` | `TareaWBS` ·0 |
| `ActividadAsignacionRecurso` ·0 | — | — |
| `ActividadAsignadaReporte` ·0 | — | — |
| `ActividadAutomatica` ·0 | — | — |
| `ActividadCronograma` ·0 | `CodProyecto` · `FK_ActividadCronograma_Proyecto` | `Proyecto` ·234 |
| `ActividadCronogramaActivo` ·0 | `CodPeriodo` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodPresupuesto` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodProyecto` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodSubpartida` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodSubpresupuesto` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodTarea` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `Item` · `FK_ActividadCronogramaActivo_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
| `ActividadCronogramaCambio` ·0 | — | — |
| `ActividadEstadoCalidad` ·0 | — | — |
| `ActividadPeriodoCronograma` ·0 | `CodPartida` · `FK_ActividadPeriodoCronograma_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_ActividadPeriodoCronograma_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_ActividadPeriodoCronograma_Partida` | `Partida` ·9,113 |
|   | `CodPeriodo` · `FK_ActividadPeriodoCronograma_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoPeriodo` · `FK_ActividadPeriodoCronograma_Periodo` | `Periodo` ·34,066 |
| `ActividadPeriodoDeductivo` ·0 | `NroActividad` · `FK_ActividadPeriodoDeductivo_Actividad` | `Actividad` ·653 |
| `ActividadPeriodoInsumoCronograma` ·0 | `CodPeriodo` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodPresupuesto` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodProyecto` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodSubpartida` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodSubpresupuesto` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodTarea` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `Item` · `FK_ActividadPeriodoInsumoCronograma_ActividadPeriodoCronograma` | `ActividadPeriodoCronograma` ·0 |
|   | `CodInsumo` · `FK_ActividadPeriodoInsumoCronograma_Insumo` | `Insumo` ·10,254 |
| `ActividadPeriodoRestriccion` ·0 | `NroActividad` · `FK_ActividadPeriodoRestriccion_Actividad` | `Actividad` ·653 |
|   | `NroRestriccion` · `FK_ActividadPeriodoRestriccion_Restriccion` | `Restriccion` ·0 |
| `ActividadVector` ·0 | — | — |
| `Calendario` ·33 | `CodProyecto` · `FK_Calendario_Proyecto` | `Proyecto` ·234 |
| `CalidadEstadoMaster` ·54 | — | — |
| `CalidadFechaControl` ·36,524 | — | — |
| `CronogramaInsumoReporte` ·2,228 | — | — |
| `CronogramaPartidaReporte` ·58 | — | — |
| `CronogramaReporte` ·216 | — | — |
| `CronogramaReporteFiltro` ·136 | — | — |
| `Frente` ·0 | — | — |
| `IncidenciaInsumo` ·3,453 | `CodInsumo` · `FK_IncidenciaInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_IncidenciaInsumo_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_IncidenciaInsumo_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_IncidenciaInsumo_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_IncidenciaInsumo_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_IncidenciaInsumo_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_IncidenciaInsumo_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `IncidenciaInsumoSubproyecto` ·0 | `CodInsumo` · `FK_IncidenciaInsumoSubproyecto_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_IncidenciaInsumoSubproyecto_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_IncidenciaInsumoSubproyecto_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_IncidenciaInsumoSubproyecto_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_IncidenciaInsumoSubproyecto_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_IncidenciaInsumoSubproyecto_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_IncidenciaInsumoSubproyecto_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `ItemDeControl` ·10 | — | — |
| `MSP_ASSIGNMENTS` ·2,322 | — | — |
| `MSP_ASSIGNMENT_BASELINES` ·0 | — | — |
| `MSP_ASSN_ENTERPRISE` ·0 | — | — |
| `MSP_ATTRIBUTE_STRINGS` ·0 | — | — |
| `MSP_AVAILABILITY` ·0 | — | — |
| `MSP_CALENDARS` ·210 | — | — |
| `MSP_CALENDAR_DATA` ·112 | — | — |
| `MSP_CODE_FIELDS` ·0 | — | — |
| `MSP_CONVERSIONS` ·0 | — | — |
| `MSP_DATE_FIELDS` ·0 | — | — |
| `MSP_DURATION_FIELDS` ·0 | — | — |
| `MSP_FIELD_ATTRIBUTES` ·0 | — | — |
| `MSP_FLAG_FIELDS` ·0 | — | — |
| `MSP_LINKS` ·104 | — | — |
| `MSP_MV_FIELDS` ·0 | — | — |
| `MSP_NUMBER_FIELDS` ·0 | — | — |
| `MSP_OUTLINE_CODES` ·32 | — | — |
| `MSP_PROJECTS` ·9 | — | — |
| `MSP_RESOURCES` ·526 | — | — |
| `MSP_RESOURCE_BASELINES` ·0 | — | — |
| `MSP_RESOURCE_RATES` ·0 | — | — |
| `MSP_RES_GLOBAL_BINARY` ·0 | — | — |
| `MSP_STRING_TYPES` ·0 | — | — |
| `MSP_TASKS` ·1,482 | — | — |
| `MSP_TASK_BASELINES` ·0 | — | — |
| `MSP_TEXT_FIELDS` ·9,236 | — | — |
| `MSP_TIMEPHASED_DATA` ·0 | — | — |
| `PartidaDeControl` ·1,079 | `NroPCG` · `FK_PartidaDeControl_PartidaControlGeneral` | `PartidaControlGeneral` ·57 |
|   | `CodProyecto` · `FK_PartidaDeControl_Proyecto1` | `Proyecto` ·234 |
| `PlanUtilizacionPeriodoRecurso` ·6,553 | — | — |
| `PlanUtilizacionRecurso` ·0 | — | — |
| `PlanUtilizacionRecursoCambio` ·0 | — | — |
| `Plantilla` ·1 | — | — |
| `PlantillaContenido` ·0 | — | — |
| `PlantillaCorreo` ·14 | — | — |
| `PlantillaCorreoDestinatario` ·0 | `NroPC` · `FK_PlantillaCorreoDestinatario_PlantillaCorreo` | `PlantillaCorreo` ·14 |
| `PlantillaCorreoVariable` ·9 | — | — |
| `PlantillaDetalle` ·5 | `CodPlantilla` · `FK_PlantillaDetalle_Plantilla` | `Plantilla` ·1 |
| `PlantillaDocumento` ·0 | — | — |
| `PlantillaFormula` ·11 | — | — |
| `PlantillaFormulaConcepto` ·1,388 | `NroConcepto` · `FK_PlantillaFormulaConcepto_Concepto` | `Concepto` ·982 |
|   | `NroPlantillaFormula` · `FK_PlantillaFormulaConcepto_PlantillaFormula` | `PlantillaFormula` ·11 |
| `PlantillaInsumo` ·0 | `CodInsumo` · `FK_PlantillaInsumo_Insumo` | `Insumo` ·10,254 |
| `PlantillaPlanilla` ·0 | — | — |
| `PlantillaReparto` ·0 | `CodIdentificador` · `FK_PlantillaReparto_Identificador` | `Identificador` ·1,638 |
| `PlantillaRepartoDetalle` ·0 | `CodInsumo` · `FK_PlantillaRepartoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `NroPlantillaReparto` · `FK_PlantillaRepartoDetalle_PlantillaReparto` | `PlantillaReparto` ·0 |
| `PlantillaRepartoTransferencia` ·0 | `NroPlantillaRepartoDetalle` · `FK_PlantillaRepartoTransferencia_PlantillaRepartoDetalle` | `PlantillaRepartoDetalle` ·0 |
| `PlantillaReporteCalidad` ·2 | — | — |
| `PlantillaSubcontrato` ·48 | — | — |
| `PredecesorSucesor` ·0 | `CodActividad` · `FK_PredecesorSucesor_SubactividadCronograma_` | `SubActividadCronograma` ·0 |
|   | `CodActividadPS` · `FK_PredecesorSucesor_SubactividadCronograma` | `SubActividadCronograma` ·0 |
|   | `CodProyecto` · `FK_PredecesorSucesor_SubactividadCronograma_` | `SubActividadCronograma` ·0 |
|   | `CodProyectoPS` · `FK_PredecesorSucesor_SubactividadCronograma` | `SubActividadCronograma` ·0 |
|   | `CodSubActividad` · `FK_PredecesorSucesor_SubactividadCronograma_` | `SubActividadCronograma` ·0 |
|   | `CodSubActividadPS` · `FK_PredecesorSucesor_SubactividadCronograma` | `SubActividadCronograma` ·0 |
|   | `TipoCronograma` · `FK_PredecesorSucesor_SubactividadCronograma_` | `SubActividadCronograma` ·0 |
|   | `TipoCronogramaPS` · `FK_PredecesorSucesor_SubactividadCronograma` | `SubActividadCronograma` ·0 |
| `PredecesorSucesorCambio` ·0 | — | — |
| `ProgramacionGestion` ·0 | `NroIG` · `FK_ProgramacionGestion_IndicadorGestion` | `IndicadorGestion` ·18 |
| `ProgramacionGestionCabecera` ·0 | `CodMoneda` · `FK_ProgramacionGestionCabecera_Moneda` | `Moneda` ·2 |
| `ProgramacionPago` ·0 | — | — |
| `ProgramacionPagoDetalle` ·0 | — | — |
| `ProgramacionPagoSaldo` ·0 | — | — |
| `Proyecto` ·234 | `CodIdentificador` · `FK_Proyecto_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_Proyecto_Moneda` | `Moneda` ·2 |
|   | `CodLugar` · `FK_Proyecto_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `ProyectoAccesorioSeries` ·0 | — | — |
| `ProyectoAccesorios` ·0 | — | — |
| `ProyectoAccion` ·0 | `CodProyecto` · `FK_ProyectoAccion_Proyecto` | `Proyecto` ·234 |
| `ProyectoBase` ·0 | `CodProyecto` · `FK_ProyectoBase_Proyecto` | `Proyecto` ·234 |
|   | `CodPresupuesto` · `FK_ProyectoBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_ProyectoBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_ProyectoBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `ProyectoCalendario` ·34 | `CodCalendario` · `FK_ProyectoCalendario_Calendario` | `Calendario` ·33 |
|   | `CodProyectoCalendario` · `FK_ProyectoCalendario_Calendario` | `Calendario` ·33 |
|   | `CodProyecto` · `FK_ProyectoCalendario_Proyecto` | `Proyecto` ·234 |
| `ProyectoCalidadSincronizacion` ·0 | — | — |
| `ProyectoCalidadSincronizacionConfiguracion` ·0 | — | — |
| `ProyectoCalidadSincronizacionDetalle` ·0 | — | — |
| `ProyectoCalidadSincronizacionHistorico` ·0 | — | — |
| `ProyectoCalidadSincronizacionPeriodo` ·0 | — | — |
| `ProyectoCalificacion` ·0 | — | — |
| `ProyectoCambio` ·2 | — | — |
| `ProyectoCertificadoPago` ·0 | `CodIdentificador` · `FK_ProyectoCertificadoPago_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_ProyectoCertificadoPago_Proyecto` | `Proyecto` ·234 |
| `ProyectoComisionxEtapa` ·0 | — | — |
| `ProyectoComplemento` ·8 | — | — |
| `ProyectoCreditos` ·0 | — | — |
| `ProyectoEjecutivoVentas` ·0 | — | — |
| `ProyectoEquipo` ·0 | `CodIdentificador` · `FK_ProyectoEquipo_Identificador` | `Identificador` ·1,638 |
|   | `CodEquipo` · `FK_ProyectoEquipo_InsumoActivo` | `Insumo` ·10,254 |
|   | `CodInsumo` · `FK_ProyectoEquipo_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_ProyectoEquipo_Moneda` | `Moneda` ·2 |
|   | `CodProyecto` · `FK_ProyectoEquipo_Proyecto` | `Proyecto` ·234 |
| `ProyectoEquipoHistorial` ·0 | — | — |
| `ProyectoEquipoOrden` ·0 | `CodEquipo` · `FK_ProyectoEquipoOrden_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_ProyectoEquipoOrden_ProyectoEquipo` | `ProyectoEquipo` ·0 |
| `ProyectoEquipoParte` ·0 | `NroInterferencia` · `FK_ProyectoEquipoParte_Interferencia` | `Interferencia` ·7 |
|   | `CodEquipo` · `FK_ProyectoEquipoParte_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_ProyectoEquipoParte_ProyectoEquipo` | `ProyectoEquipo` ·0 |
| `ProyectoEquipoParteInsumo` ·0 | `CodInsumo` · `FK_ProyectoEquipoParteInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodPartidaDeControl` · `FK_ProyectoEquipoParteInsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyecto` · `FK_ProyectoEquipoParteInsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
| `ProyectoEquipoParteResumen` ·0 | `CodEquipo` · `FK_ProyectoEquipoParteResumen_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_ProyectoEquipoParteResumen_ProyectoEquipo` | `ProyectoEquipo` ·0 |
| `ProyectoEquipoPeriodo` ·0 | `CodMoneda` · `FK_ProyectoEquipoPeriodo_Moneda` | `Moneda` ·2 |
|   | `CodEquipo` · `FK_ProyectoEquipoPeriodo_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_ProyectoEquipoPeriodo_ProyectoEquipo` | `ProyectoEquipo` ·0 |
| `ProyectoEscala` ·5 | — | — |
| `ProyectoEsquema` ·0 | `NroEsquemaPlanilla` · `FK_ProyectoEsquema_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoEsquema_Proyecto` | `Proyecto` ·234 |
| `ProyectoEsquemaConfiguracion` ·0 | `NroProyectoEsquemaPeriodo` · `FK_ProyectoEsquemaConfiguracion_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `ProyectoEsquemaHorasPeriodo` ·0 | — | — |
| `ProyectoEsquemaPeriodo` ·0 | `CodPeriodo` · `FK_ProyectoEsquemaPeriodo_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoPeriodo` · `FK_ProyectoEsquemaPeriodo_Periodo` | `Periodo` ·34,066 |
|   | `NroProyectoEsquema` · `FK_ProyectoEsquemaPeriodo_ProyectoEsquema` | `ProyectoEsquema` ·0 |
| `ProyectoEsquemaPeriodoHistorico` ·0 | `NroProyectoEsquemaPeriodo` · `FK_ProyectoEsquemaPeriodoHistorico_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `ProyectoEsquemaTiempoConcepto` ·0 | `NroConcepto` · `FK_ProyectoEsquemaTiempoConcepto_Concepto` | `Concepto` ·982 |
|   | `NroProyectoEsquema` · `FK_ProyectoEsquemaTiempoConcepto_ProyectoEsquema` | `ProyectoEsquema` ·0 |
| `ProyectoEsquemaUsuario` ·0 | — | — |
| `ProyectoEsquemaVacacionGanada` ·0 | `NroEsquemaPlanilla` · `FK_ProyectoEsquemaVacacionGanada_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoEsquemaVacacionGanada_Proyecto` | `Proyecto` ·234 |
| `ProyectoEstructuraTarea` ·226 | `CodProyecto` · `FK_ProyectoEstructuraTarea_Proyecto` | `Proyecto` ·234 |
| `ProyectoEtiqueta` ·0 | `CodProyecto` · `FK_ProyectoEtiqueta_Proyecto` | `Proyecto` ·234 |
| `ProyectoEvaluacionPersonal` ·0 | `NroCuestionarioEvaluacion` · `FK_ProyectoEvaluacionPersonal_CuestionarioEvaluacion` | `CuestionarioEvaluacion` ·0 |
|   | `NroEsquemaPlanilla` · `FK_ProyectoEvaluacionPersonal_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificadorEvaluador` · `FK_ProyectoEvaluacionPersonal_Evaluador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_ProyectoEvaluacionPersonal_Proyecto` | `Proyecto` ·234 |
|   | `NroTipoEvaluacionPersonal` · `FK_ProyectoEvaluacionPersonal_TipoEvaluacionPersonal` | `TipoEvaluacionPersonal` ·0 |
| `ProyectoEvaluacionPersonalDetalle` ·0 | `CodIdentificador` · `FK_ProyectoEvaluacionPersonalDetalle_Identificador` | `Identificador` ·1,638 |
|   | `NroProyectoEvaluacionPersonal` · `FK_ProyectoEvaluacionPersonalDetalle_ProyectoEvaluacionPersonal` | `ProyectoEvaluacionPersonal` ·0 |
| `ProyectoEvaluacionPersonalDetalleResultado` ·0 | `NroCuestionarioCriterioCalificacion` · `FK_ProyectoEvaluacionPersonalDetalleResultado_CuestionarioCriterioCalificacion` | `CuestionarioCriterioCalificacion` ·0 |
|   | `NroPEvaluacionPD` · `FK_ProyectoEvaluacionPersonalDetalleResultado_ProyectoEvaluacionPersonalDetalle` | `ProyectoEvaluacionPersonalDetalle` ·0 |
| `ProyectoEvaluacionPersonalRelacionado` ·0 | `NroCuestionarioCriterioCalificacion` · `FK_ProyectoEvaluacionPersonalRelacionado_Calificacion` | `CuestionarioCriterioCalificacion` ·0 |
|   | `NroProyectoEvaluacionPersonal` · `FK_ProyectoEvaluacionPersonalRelacionado_ProyectoEvaluacionPersonal` | `ProyectoEvaluacionPersonal` ·0 |
| `ProyectoGrupo` ·43 | `Correlativo` · `FK_ProyectoGrupo_Grupos` | `Grupos` ·608 |
|   | `Escenario` · `FK_ProyectoGrupo_Grupos` | `Grupos` ·608 |
|   | `Usuario` · `FK_ProyectoGrupo_Grupos` | `Grupos` ·608 |
| `ProyectoHerramienta` ·0 | `CodHerramienta` · `FK_ProyectoHerramienta_Insumo` | `Insumo` ·10,254 |
| `ProyectoIGVMixtoCabecera` ·0 | — | — |
| `ProyectoIGVMixtoDetalle` ·0 | `NroProyectoIGVMixtoCabecera` · `FK_ProyectoIGVMixtoDetalle_NroProyectoIGVMixtoCabecera` | `ProyectoIGVMixtoCabecera` ·0 |
| `ProyectoIdentificador` ·0 | — | — |
| `ProyectoIdentificadorEsquema` ·0 | — | — |
| `ProyectoIdentificadorEsquemaConcepto` ·0 | — | — |
| `ProyectoIdentificadorEsquemaProceso` ·0 | — | — |
| `ProyectoIdentificadorEsquemaProcesoHistoria` ·0 | — | — |
| `ProyectoIdentificadorPago` ·3 | — | — |
| `ProyectoMetaBase` ·0 | `CodProyecto` · `FK_ProyectoMetaBase_Proyecto` | `Proyecto` ·234 |
|   | `CodPresupuesto` · `FK_ProyectoMetaBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodPresupuestoBase` · `FK_ProyectoMetaBase_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_ProyectoMetaBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuestoBase` · `FK_ProyectoMetaBase_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_ProyectoMetaBase_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `ItemBase` · `FK_ProyectoMetaBase_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
| `ProyectoMetaOferta` ·0 | `CodProyecto` · `FK_ProyectoMetaOferta_Proyecto` | `Proyecto` ·234 |
|   | `CodPresupuesto` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodPresupuestoOferta` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuestoOferta` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `ItemOferta` · `FK_ProyectoMetaOferta_SubpresupuestoDetalle_` | `SubpresupuestoDetalle` ·6,675 |
| `ProyectoNube` ·0 | — | — |
| `ProyectoObrero` ·0 | `CodCriterio` · `FK_ProyectoObrero_CriterioHorasExtras` | `CriterioHorasExtras` ·0 |
|   | `CodProyecto` · `FK_ProyectoObrero_CriterioHorasExtras` | `CriterioHorasExtras` ·0 |
|   | `CodObrero` · `FK_ProyectoObrero_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_ProyectoObrero_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_ProyectoObrero_Moneda` | `Moneda` ·2 |
|   | `CodOcupacion` · `FK_ProyectoObrero_Ocupacion` | `Ocupacion` ·146 |
|   | `CodProyecto` · `FK_ProyectoObrero_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroAnalisisMarcacion` ·0 | `NroEsquemaPlanilla` · `FK_ProyectoObreroAnalisisMarcacion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoObreroAnalisisMarcacion_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroConceptoPeriodo` ·0 | `NroConcepto` · `FK_ProyectoObreroConceptoPeriodo_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ProyectoObreroConceptoPeriodo_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_ProyectoObreroConceptoPeriodo_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_ProyectoObreroConceptoPeriodo_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquemaPeriodo` · `FK_ProyectoObreroConceptoPeriodo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `ProyectoObreroDia` ·0 | — | — |
| `ProyectoObreroDistribucionCosto` ·0 | `CodProyectoDestino` · `FK_ProyectoObreroDistribucionCosto_ProyectoDestino` | `Proyecto` ·234 |
|   | `CodObrero` · `FK_ProyectoObreroDistribucionCosto_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_ProyectoObreroDistribucionCosto_ProyectoObrero` | `ProyectoObrero` ·0 |
| `ProyectoObreroHistoricoDatos` ·0 | — | — |
| `ProyectoObreroOcupacion` ·0 | — | — |
| `ProyectoObreroOrden` ·0 | `CodObrero` · `FK_ProyectoObreroOrden_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_ProyectoObreroOrden_ProyectoObrero` | `ProyectoObrero` ·0 |
| `ProyectoObreroPapeleta` ·0 | `NroConcepto` · `FK_ProyectoObreroPapeleta_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ProyectoObreroPapeleta_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoObreroPapeleta_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroPapeletaDetalle` ·0 | `CodIdentificador` · `FK_ProyectoObreroPapeletaDetalle_Identificador` | `Identificador` ·1,638 |
|   | `NroPOPapeleta` · `FK_ProyectoObreroPapeletaDetalle_ProyectoObreroPapeleta` | `ProyectoObreroPapeleta` ·0 |
| `ProyectoObreroPapeletaOrigenDestino` ·0 | `NroConceptoDestino` · `FK_ProyectoObreroPapeletaOrigenDestino_Destino` | `Concepto` ·982 |
|   | `NroConceptoOrigen` · `FK_ProyectoObreroPapeletaOrigenDestino_Origen` | `Concepto` ·982 |
|   | `NroPOPapeleta` · `FK_ProyectoObreroPapeletaOrigenDestino_ProyectoObreroPapeleta` | `ProyectoObreroPapeleta` ·0 |
| `ProyectoObreroQuintaProyectos` ·0 | — | — |
| `ProyectoOferta` ·0 | `CodProyecto` · `FK_ProyectoOferta_Proyecto` | `Proyecto` ·234 |
|   | `CodPresupuesto` · `FK_ProyectoOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_ProyectoOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_ProyectoOferta_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `ProyectoPagoValorizacion` ·0 | `CodMoneda` · `FK_ProyectoPagoValorizacion_Moneda` | `Moneda` ·2 |
|   | `CodProyecto` · `FK_ProyectoPagoValorizacion_Proyecto` | `Proyecto` ·234 |
| `ProyectoPaquetesVivienda` ·0 | — | — |
| `ProyectoPartida` ·0 | `CodDestinoEspecifico` · `FK_ProyectoPartida_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodProyectoDE` · `FK_ProyectoPartida_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodEspecialidad` · `FK_ProyectoPartida_EspecialidadControl` | `EspecialidadControl` ·2 |
|   | `CodProyectoEspecialidad` · `FK_ProyectoPartida_EspecialidadControl` | `EspecialidadControl` ·2 |
|   | `CodFrente` · `FK_ProyectoPartida_Frente` | `Frente` ·0 |
|   | `CodProyectoFrente` · `FK_ProyectoPartida_Frente` | `Frente` ·0 |
|   | `CodRevisor` · `FK_ProyectoPartida_Revisor` | `Identificador` ·1,638 |
|   | `CodSupervisor` · `FK_ProyectoPartida_Supervisor` | `Identificador` ·1,638 |
|   | `NroProtocolo` · `FK_ProyectoPartida_Protocolo` | `Protocolo` ·56 |
|   | `CodProyectoResponsable` · `FK_ProyectoPartida_Responsable` | `Responsable` ·0 |
|   | `CodResponsable` · `FK_ProyectoPartida_Responsable` | `Responsable` ·0 |
| `ProyectoPeriodo` ·1 | — | — |
| `ProyectoPeriodoCorreo` ·0 | — | — |
| `ProyectoPiloto` ·1 | — | — |
| `ProyectoPrecioDescuento` ·0 | — | — |
| `ProyectoPrecioLista` ·0 | — | — |
| `ProyectoPresupuesto` ·2 | `CodPresupuesto` · `FK_ProyectoPresupuesto_Presupuesto` | `Presupuesto` ·44 |
|   | `CodProyecto` · `FK_ProyectoPresupuesto_Proyecto` | `Proyecto` ·234 |
| `ProyectoProject` ·0 | — | — |
| `ProyectoProjectTarea` ·0 | — | — |
| `ProyectoProjectTareaDependencia` ·0 | — | — |
| `ProyectoRama` ·0 | `CodProyecto` · `FK_ProyectoRama_Proyecto` | `Proyecto` ·234 |
| `ProyectoRecurso` ·0 | — | — |
| `ProyectoTareadorActividad` ·0 | — | — |
| `ProyectoTareadorPartidaDeControl` ·0 | — | — |
| `ProyectoTerminal` ·0 | — | — |
| `ProyectoUnidadOperativa` ·0 | `CodProyecto` · `FK_ProyectoUnidadOperativa_Proyecto` | `Proyecto` ·234 |
|   | `CodUnidadOperativa` · `FK_ProyectoUnidadOperativa_UnidadOperativa` | `UnidadOperativa` ·13 |
| `ProyectoUsuario` ·14 | `CodProyecto` · `FK_ProyectoUsuario_Proyecto` | `Proyecto` ·234 |
|   | `CodUsuario` · `FK_ProyectoUsuario_Usuario` | `Usuario` ·14 |
| `ProyectoUsuarioControl` ·0 | — | — |
| `ProyectoUsuarioPedidoSincronizacion` ·0 | — | — |
| `ProyectoUsuarioPermiso` ·0 | — | — |
| `ProyectoUsuarioUO` ·0 | `CodProyecto` · `FK_ProyectoUsuarioUO_ProyectoUnidadOperativa` | `ProyectoUnidadOperativa` ·0 |
|   | `CodUnidadOperativa` · `FK_ProyectoUsuarioUO_ProyectoUnidadOperativa` | `ProyectoUnidadOperativa` ·0 |
|   | `CodRol` · `FK_ProyectoUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_ProyectoUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_ProyectoUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `ProyectoUsuario_Historial` ·835 | — | — |
| `ProyectoVector` ·0 | — | — |
| `Recurso` ·18 | — | — |
| `RecursoAlterno1` ·0 | — | — |
| `RecursoAlterno2` ·0 | — | — |
| `RecursoAlterno3` ·0 | — | — |
| `RecursoAlterno4` ·0 | — | — |
| `RecursoAlterno5` ·0 | — | — |
| `RecursoAnt` ·0 | — | — |
| `RecursoCategoria` ·0 | — | — |
| `RecursoControl` ·4 | — | — |
| `RecursoControlInsumo` ·2 | — | — |
| `RecursoEquivalente` ·0 | — | — |
| `RecursoInsumo` ·85 | — | — |
| `RecursoTmp` ·0 | — | — |
| `Tarea` ·209 | `CodProyecto` · `FK_Tarea_Proyecto` | `Proyecto` ·234 |
| `TareaApp` ·487 | — | — |
| `TareaAppTMP` ·0 | — | — |
| `TareaAutomatica` ·0 | — | — |
| `TareaK2` ·1,711 | — | — |
| `TareaK2_Log` ·4,008 | — | — |
| `TareaKF` ·0 | — | — |
| `TareaKF2` ·340 | — | — |
| `TareaKKF` ·0 | — | — |
| `TareaKPA2` ·102 | — | — |
| `TareaKPC` ·0 | — | — |
| `TareaKPC2` ·26 | — | — |
| `TareaKPCE2` ·3 | — | — |
| `TareaKPC_Confirmado` ·0 | — | — |
| `TareaKPC_SinConfirmar` ·0 | — | — |
| `TareaKPE` ·0 | — | — |
| `TareaKPE2` ·14 | — | — |
| `TareaMatriz` ·0 | — | — |
| `TareaWBS` ·0 | `CodProyecto` · `FK_TareaWBS_Proyecto` | `Proyecto` ·234 |
| `Tarea_Dif_Cabecera` ·0 | — | — |
| `Tarea_Dif_Detalle_K` ·0 | — | — |
| `Tarea_Dif_Detalle_KF` ·0 | — | — |
| `UsoRecurso` ·546,965 | — | — |
| `ValorizacionPieProyecto` ·0 | `CodIdentificador` · `FK_ValorizacionPieProyecto_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_ValorizacionPieProyecto_Proyecto` | `Proyecto` ·234 |

---

## M04 · Logística y compras

`[V]` 61 tablas · 104 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `CentroCompra` ·1 | `CodAtencion` · `FK_CentroCompra_Identificador` | `Identificador` ·1,638 |
| `CentroCompraNotificador` ·0 | `NroCC` · `FK_CentroCompraNotificador_CentroCompra` | `CentroCompra` ·1 |
| `CentroCompraProyecto` ·11 | `NroCC` · `FK__CentroCom__NroCC__664A5317` | `CentroCompra` ·1 |
|   | `CodProyecto` · `FK__CentroCom__CodPr__65562EDE` | `Proyecto` ·234 |
| `CentroCompraProyectoNotificador` ·0 | `NroCCP` · `FK_CentroCompraProyectoNotificador_CentroCompraProyecto` | `CentroCompraProyecto` ·11 |
| `CentroCompraProyectoUsuario` ·9 | `NroCCP` · `FK__CentroCom__NroCC__68329B89` | `CentroCompraProyecto` ·11 |
|   | `CodUsuario` · `FK__CentroCom__CodUs__673E7750` | `Usuario` ·14 |
| `CentroCompraProyectoUsuarioPermiso` ·243 | `NroCCPU` · `FK__CentroCom__NroCC__6A1AE3FB` | `CentroCompraProyectoUsuario` ·9 |
|   | `CodPermiso` · `FK__CentroCom__CodPe__6926BFC2` | `Permiso` ·27 |
| `CentroCompraUsuario` ·3 | `NroCC` · `FK__CentroCom__NroCC__6C032C6D` | `CentroCompra` ·1 |
|   | `CodUsuario` · `FK__CentroCom__CodUs__6B0F0834` | `Usuario` ·14 |
| `CompraPlus` ·0 | — | — |
| `Cotizacion` ·0 | `NroCCP` · `FK_Cotizacion_CentroCompraProyecto` | `CentroCompraProyecto` ·11 |
|   | `CodEstadoCotizacion` · `FK_Cotizacion_CotizacionEstado` | `CotizacionEstado` ·3 |
| `CotizacionAprobacion` ·0 | — | — |
| `CotizacionDetalle` ·0 | `NroCotizacion` · `FK__Cotizacio__NroCo__0D2F160E` | `Cotizacion` ·0 |
|   | `CodInsumo` · `FK__Cotizacio__CodIn__0C3AF1D5` | `Insumo` ·10,254 |
| `CotizacionDetallePedido` ·0 | `NroCD` · `FK__Cotizacio__NroCD__0F175E80` | `CotizacionDetalle` ·0 |
|   | `NroNPR` · `FK__CotizacionDetall__0E233A47` | `NotaPedidoRequisitoriaDetalle` ·13 |
|   | `Orden` · `FK__CotizacionDetall__0E233A47` | `NotaPedidoRequisitoriaDetalle` ·13 |
| `CotizacionEstado` ·3 | — | — |
| `CotizacionProveedor` ·0 | `CodFormaDePago` · `FK_CotizacionProveedor_FormaDePago` | `FormaDePago` ·32 |
|   | `CodIdentificador` · `FK_CotizacionProveedor_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_CotizacionProveedor_Moneda` | `Moneda` ·2 |
| `CotizacionProveedorDetalle` ·0 | `NroCD` · `FK__Cotizacio__NroCD__13DC139D` | `CotizacionDetalle` ·0 |
|   | `NroCP` · `FK__Cotizacio__NroCP__14D037D6` | `CotizacionProveedor` ·0 |
| `CotizacionProveedorEstado` ·9 | — | — |
| `CotizacionProveedorPedidoDetalle` ·0 | `NroCDP` · `FK_CotizacionProveedorPedidoDetalle_CotizacionDetallePedido` | `CotizacionDetallePedido` ·0 |
|   | `NroCPD` · `FK_CotizacionProveedorPedidoDetalle_CotizacionProveedorDetalle` | `CotizacionProveedorDetalle` ·0 |
| `Edicion` ·0 | `Mes` · `FK_EDICION_MES` | `Mes` ·0 |
| `GrupoAprobacion` ·5 | — | — |
| `GrupoAprobacionNotificador` ·1 | — | — |
| `GrupoAprobacionUsuario` ·8 | — | — |
| `Nota` ·0 | `Ano` · `FK_NOTA_EDICION` | `Edicion` ·0 |
|   | `Mes` · `FK_NOTA_EDICION` | `Edicion` ·0 |
|   | `COD_CLI` · `FK_NOTA_PROVEEDOR` | `Proveedor` ·0 |
| `NotaCompra` ·0 | `CodAlmacen` · `FK_NotaCompra_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_NotaCompra_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `NotaCompraDetalle` ·0 | `CodInsumo` · `FK_NotaCompraDetalle_Insumo` | `Insumo` ·10,254 |
|   | `NroNotaCompra` · `FK_NotaCompraDetalle_NotaCompra` | `NotaCompra` ·0 |
| `NotaPedidoRequisitoria` ·9 | `NroCC` · `FK_NotaPedidoRequisitoria_CentroCompra` | `CentroCompra` ·1 |
|   | `CodEstadoNPRPedido` · `FK_NotaPedidoRequisitoria_NotaPedidoRequisitoriaEstado` | `NotaPedidoRequisitoriaEstado` ·4 |
|   | `NroPedido` · `FK_NotaPedidoRequisitoria_Pedido` | `Pedido` ·9 |
| `NotaPedidoRequisitoriaDetalle` ·13 | `CodInsumo` · `FK__NotaPedid__CodIn__1994ECF3` | `Insumo` ·10,254 |
|   | `NroNotaCompra` · `FK__NotaPedid__NroNo__18A0C8BA` | `NotaCompra` ·0 |
|   | `NroNPR` · `FK__NotaPedid__NroNP__17ACA481` | `NotaPedidoRequisitoria` ·9 |
| `NotaPedidoRequisitoriaEstado` ·4 | — | — |
| `OrdenCompraDetallePedido` ·13 | `NroNPR` · `FK__OrdenCompraDetal__16B88048` | `NotaPedidoRequisitoriaDetalle` ·13 |
|   | `Orden` · `FK__OrdenCompraDetal__16B88048` | `NotaPedidoRequisitoriaDetalle` ·13 |
|   | `NroDetalle` · `FK__OrdenComp__NroDe__15C45C0F` | `OrdenDeCompraDetalle` ·13 |
| `OrdenCompraNotero` ·9 | `CodItem` · `FK_OrdenCompraNotero_Notero` | `Notero` ·508 |
|   | `CodNota` · `FK_OrdenCompraNotero_Notero` | `Notero` ·508 |
|   | `NroOrden` · `FK_OrdenCompraNotero_OrdenDeCompra` | `OrdenDeCompra` ·9 |
| `OrdenDeCompra` ·9 | `NroCC` · `FK__OrdenDeCo__NroCC__1A89112C` | `CentroCompra` ·1 |
|   | `NroCCP` · `FK_OrdenDeCompra_CentroCompraProyecto` | `CentroCompraProyecto` ·11 |
|   | `NroCP` · `FK__OrdenDeCo__NroCP__1F4DC649` | `CotizacionProveedor` ·0 |
|   | `CodFormaDePago` · `FK__OrdenDeCo__CodFo__1D657DD7` | `FormaDePago` ·32 |
|   | `CodProveedor` · `FK__OrdenDeCo__CodPr__1C71599E` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK__OrdenDeCo__CodMo__1E59A210` | `Moneda` ·2 |
|   | `CodEstado` · `FK__OrdenDeCo__CodEs__1B7D3565` | `OrdenDeCompraEstado` ·7 |
| `OrdenDeCompraAprobacion` ·16 | — | — |
| `OrdenDeCompraDetalle` ·13 | `NroCPD` · `FK__OrdenDeCo__NroCP__2041EA82` | `CotizacionProveedorDetalle` ·0 |
|   | `CodInsumo` · `FK__OrdenDeCo__CodIn__21360EBB` | `Insumo` ·10,254 |
|   | `NroOrden` · `FK__OrdenDeCo__NroOr__222A32F4` | `OrdenDeCompra` ·9 |
| `OrdenDeCompraEstado` ·7 | — | — |
| `OrdenDeCompraPie` ·1 | — | — |
| `OrdenDeCompraPieEmpresa` ·0 | `CodIdentificador` · `FK_OrdenDeCompraPieEmpresa_Identificador` | `Identificador` ·1,638 |
| `OrdenTrabajo` ·0 | `CodProyecto` · `FK_OrdenTrabajo_Proyecto` | `Proyecto` ·234 |
|   | `CodProyectoSubordinacion` · `FK_OrdenTrabajo_SubordinacionEQ` | `SubordinacionEQ` ·0 |
|   | `CodSubordinacionEQ` · `FK_OrdenTrabajo_SubordinacionEQ` | `SubordinacionEQ` ·0 |
|   | `CodProyectoSubordinacion` · `FK_OrdenTrabajo_SubordinacionMO` | `SubordinacionMO` ·0 |
|   | `CodSubordinacionMO` · `FK_OrdenTrabajo_SubordinacionMO` | `SubordinacionMO` ·0 |
| `OrdenTrabajoExcel` ·0 | — | — |
| `Pedido` ·9 | `NroCC` · `FK_Pedido_CentroCompra` | `CentroCompra` ·1 |
|   | `CodEstadoPedido` · `FK_Pedido_PedidoEstado` | `PedidoEstado` ·10 |
|   | `CodProyectoPC` · `FK_Pedido_Proyecto` | `Proyecto` ·234 |
|   | `CodAlmacen` · `FK_Pedido_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_Pedido_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `PedidoDeletedHistory` ·0 | — | — |
| `PedidoDetalle` ·13 | `CodInsumo` · `FK_PedidoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodPartidaDeControl` · `FK_PedidoDetalle_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyectoPC` · `FK_PedidoDetalle_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `NroPedido` · `FK_PedidoDetalle_Pedido` | `Pedido` ·9 |
| `PedidoDetalleDeletedHistory` ·0 | — | — |
| `PedidoDetalleDestino` ·0 | — | — |
| `PedidoDetalleEstado` ·5 | — | — |
| `PedidoDetalleSaldo` ·0 | — | — |
| `PedidoEstado` ·10 | — | — |
| `PedidoPlazoEntrega` ·168 | — | — |
| `PedidosTipo` ·4 | — | — |
| `Precio` ·0 | `CODIGO` · `FK_PRECIO_Insumo` | `Insumo` ·10,254 |
|   | `C_MONEDA` · `FK_PRECIO_Moneda` | `Moneda` ·2 |
|   | `Ano` · `FK_PRECIO_TITULOPRECIO` | `TituloPrecio` ·0 |
|   | `COD_CLI` · `FK_PRECIO_TITULOPRECIO` | `TituloPrecio` ·0 |
|   | `C_GRUPO` · `FK_PRECIO_TITULOPRECIO` | `TituloPrecio` ·0 |
|   | `Mes` · `FK_PRECIO_TITULOPRECIO` | `TituloPrecio` ·0 |
| `PrecioGeneral` ·0 | `CodInsumo` · `FK_PrecioGeneral_Insumo` | `Insumo` ·10,254 |
|   | `CodLugar` · `FK_PrecioGeneral_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `PrecioJerarquia` ·0 | — | — |
| `PrecioJerarquiaDetalle` ·0 | `CodInsumo` · `FK_PrecioJerarquiaDetalle_Insumo` | `Insumo` ·10,254 |
|   | `CodGrupo` · `FK_PrecioJerarquiaDetalle_PrecioJerarquia` | `PrecioJerarquia` ·0 |
| `PrecioParticularEstimada` ·0 | `CodPartida` · `FK_PrecioParticularEstimada_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_PrecioParticularEstimada_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PrecioParticularEstimada_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PrecioParticularEstimada_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_PrecioParticularEstimada_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_PrecioParticularEstimada_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `PrecioParticularInsumo` ·4,790 | `CodInsumo` · `FK_PrecioParticularInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PrecioParticularInsumo_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_PrecioParticularInsumo_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodLugar` · `FK_PrecioParticularInsumo_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `PrecioParticularInsumoPendiente` ·0 | — | — |
| `PrecioParticularInsumoPropio` ·94 | `CodInsumo` · `FK_PrecioParticularInsumoPropio_Insumo` | `Insumo` ·10,254 |
|   | `CodPresupuesto` · `FK_PrecioParticularInsumoPropio_Presupuesto` | `Presupuesto` ·44 |
| `PrecioParticularInsumoReporte` ·397 | — | — |
| `PrecioParticularPartida` ·2 | `CodPartida` · `FK_PrecioParticularPartida_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_PrecioParticularPartida_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_PrecioParticularPartida_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_PrecioParticularPartida_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_PrecioParticularPartida_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodLugar` · `FK_PrecioParticularPartida_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `PrecioParticularPartidaReporte` ·79 | — | — |
| `Proveedor` ·0 | — | — |
| `ProveedorRecurso` ·0 | `CodIdentificador` · `FK_ProveedorRecurso_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_ProveedorRecurso_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_ProveedorRecurso_Moneda` | `Moneda` ·2 |
| `TituloPrecio` ·0 | `Ano` · `FK_TITULO_EDICION` | `Edicion` ·0 |
|   | `Mes` · `FK_TITULO_EDICION` | `Edicion` ·0 |
|   | `COD_CLI` · `FK_TITULO_PROVEEDOR` | `Proveedor` ·0 |

---

## M05 · Almacén e inventario

`[V]` 63 tablas · 81 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `AlmacenVector` ·0 | — | — |
| `ArbolProyectoAlmacen` ·6 | — | — |
| `CierreAlmacen` ·23 | — | — |
| `CierreAlmacenDetalle` ·0 | `NroCierreAlmacen` · `FK_CierreAlmacenDetalle_CierreAlmacen` | `CierreAlmacen` ·23 |
| `CierreProyectoAlmacen` ·24 | — | — |
| `DevolucionGarantia` ·0 | — | — |
| `GuiaAlmacen` ·151 | `CodMovimiento` · `FK_GuiaAlmacen_GuiaAlmacenMovimiento` | `GuiaAlmacenMovimiento` ·55 |
|   | `CodIdentificador` · `FK_GuiaAlmacen_Identificador` | `Identificador` ·1,638 |
|   | `NroNotaCompra` · `FK_GuiaAlmacen_NotaCompra` | `NotaCompra` ·0 |
|   | `NroNPR` · `FK_GuiaAlmacen_NotaPedidoRequisitoria` | `NotaPedidoRequisitoria` ·9 |
|   | `NroOrden` · `FK_GuiaAlmacen_OrdenDeCompra1` | `OrdenDeCompra` ·9 |
|   | `CodAlmacen` · `FK_GuiaAlmacen_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_GuiaAlmacen_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodDocumento` · `FK_GuiaAlmacen_TipoDocumento` | `TipoDocumento` ·66 |
| `GuiaAlmacenAuditoria` ·0 | `NroGuia` · `FK_GuiaAlmacenAuditoria_GuiaAlmacen` | `GuiaAlmacen` ·151 |
| `GuiaAlmacenAuditoriaNota` ·0 | `NroGuia` · `FK_GuiaAlmacenAuditoriaNota_GuiaAlmacen` | `GuiaAlmacen` ·151 |
| `GuiaAlmacenAuditoriaRespuesta` ·0 | `CodAccion` · `FK_GuiaAlmacenAuditoriaRespuesta_Accion` | `Accion` ·4 |
|   | `NroGuia` · `FK_GuiaAlmacenAuditoriaRespuesta_GuiaAlmacen` | `GuiaAlmacen` ·151 |
| `GuiaAlmacenDetalle` ·1,310 | `NroGuia` · `FK_GuiaAlmacenDetalle_GuiaAlmacen` | `GuiaAlmacen` ·151 |
|   | `CodInsumo` · `FK_GuiaAlmacenDetalle_Insumo` | `Insumo` ·10,254 |
| `GuiaAlmacenDetalleImpuesto` ·0 | `NroGuia` · `FK_GuiaAlmacenDetalleImpuesto_GuiaAlmacenImpuesto` | `GuiaAlmacenImpuesto` ·0 |
|   | `CodInsumo` · `FK_GuiaAlmacenDetalleImpuesto_Insumo` | `Insumo` ·10,254 |
| `GuiaAlmacenDetallePedido` ·13 | `NroOCDP` · `FK_GuiaAlmacenDetallePedido_OrdenCompraDetallePedido` | `OrdenCompraDetallePedido` ·13 |
| `GuiaAlmacenFabricacion` ·1 | — | — |
| `GuiaAlmacenFabricacionActividad` ·0 | `NroGuia` · `FK_GuiaAlmacenFabricacionActividad_GuiaAlmacen` | `GuiaAlmacen` ·151 |
|   | `CodInsumo` · `FK_GuiaAlmacenFabricacionActividad_Insumo` | `Insumo` ·10,254 |
| `GuiaAlmacenFabricacionImpuesto` ·0 | — | — |
| `GuiaAlmacenImpuesto` ·0 | `CodMovimiento` · `FK_GuiaAlmacenImpuesto_GuiaAlmacenMovimiento` | `GuiaAlmacenMovimiento` ·55 |
|   | `CodIdentificador` · `FK_GuiaAlmacenImpuesto_Identificador` | `Identificador` ·1,638 |
|   | `NroNotaCompra` · `FK_GuiaAlmacenImpuesto_NotaCompra` | `NotaCompra` ·0 |
|   | `NroNPR` · `FK_GuiaAlmacenImpuesto_NotaPedidoRequisitoria` | `NotaPedidoRequisitoria` ·9 |
|   | `NroOrden` · `FK_GuiaAlmacenImpuesto_OrdenDeCompra1` | `OrdenDeCompra` ·9 |
|   | `CodAlmacen` · `FK_GuiaAlmacenImpuesto_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_GuiaAlmacenImpuesto_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodDocumento` · `FK_GuiaAlmacenImpuesto_TipoDocumento` | `TipoDocumento` ·66 |
| `GuiaAlmacenMovimiento` ·55 | — | — |
| `GuiaRemision` ·0 | — | — |
| `GuiaRemisionDetalle` ·0 | — | — |
| `GuiaRemisionMotivo` ·12 | — | — |
| `GuiaRemisionMotivoDetalle` ·0 | — | — |
| `GuiaRemisionNotificacion` ·0 | — | — |
| `GuiaRemisionRelacionado` ·0 | — | — |
| `GuiaSalida` ·0 | `CodOT` · `FK_GuiaSalida_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_GuiaSalida_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `GuiaSalidaActividadInsumo` ·0 | `CodInsumo` · `FK_GuiaSalidaActividad_Insumo` | `Insumo` ·10,254 |
| `GuiaSalidaActividadInsumoReporte` ·0 | — | — |
| `GuiaSalidaResumenReporte` ·0 | — | — |
| `GuiaSubcontrato` ·0 | `CodOT` · `FK_GuiaSubcontrato_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_GuiaSubcontrato_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `GuiaSubcontratoActividad` ·0 | — | — |
| `GuiaSubcontratoActividadReporte` ·0 | — | — |
| `GuiaSubcontratoResumenReporte` ·0 | — | — |
| `IngresosEgresos` ·0 | — | — |
| `Inventario` ·1 | — | — |
| `InventarioConteo` ·1 | — | — |
| `InventarioConteoEncargado` ·2 | `CodInventario` · `FK_InventarioConteo_Inventario` | `Inventario` ·1 |
| `InventarioConteoRecurso` ·0 | `CodInsumo` · `FK_InventarioConteoRecurso_Insumo` | `Insumo` ·10,254 |
| `InventarioConteoRecursoDiferencia` ·5 | — | — |
| `InventarioDiferenciaRecurso` ·0 | — | — |
| `InventarioPlus` ·0 | — | — |
| `InventarioProceso` ·1 | — | — |
| `InventarioProcesoConteoRecurso` ·5 | — | — |
| `InventarioProcesoRecurso` ·5 | — | — |
| `PlantillaRepartoGuiaAlmacen` ·0 | — | — |
| `PlantillaRepartoGuiaAlmacenGuiaReparto` ·0 | — | — |
| `PlantillaRepartoGuiaAlmacenOrigen` ·0 | — | — |
| `ProyectoAlmacen` ·93 | `CodAlmacenero` · `FK_ProyectoAlmacen_Identificador` | `Identificador` ·1,638 |
|   | `CodMonedaAlterna` · `FK_ProyectoAlmacen_Moneda1` | `Moneda` ·2 |
|   | `CodMonedaBase` · `FK_ProyectoAlmacen_Moneda` | `Moneda` ·2 |
|   | `CodProyecto` · `FK_ProyectoAlmacen_Proyecto` | `Proyecto` ·234 |
|   | `CodTipoAlmacen` · `FK_ProyectoAlmacen_TipoAlmacen` | `TipoAlmacen` ·6 |
| `ProyectoAlmacenIdentificador` ·0 | — | — |
| `ProyectoAlmacenInsumo` ·0 | `CodInsumo` · `FK_ProyectoAlmacenInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodAlmacen` · `FK_ProyectoAlmacenInsumo_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_ProyectoAlmacenInsumo_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodAlmacen` · `FK_ProyectoAlmacenInsumo_UbicacionAlmacen` | `UbicacionAlmacen` ·17 |
|   | `CodProyecto` · `FK_ProyectoAlmacenInsumo_UbicacionAlmacen` | `UbicacionAlmacen` ·17 |
|   | `CodUbicacion` · `FK_ProyectoAlmacenInsumo_UbicacionAlmacen` | `UbicacionAlmacen` ·17 |
| `ProyectoAlmacenUnidadOperativa` ·0 | `CodAlmacen` · `FK_ProyectoAlmacenUnidadOperativa_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_ProyectoAlmacenUnidadOperativa_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `ProyectoAlmacenUsuario` ·25 | `CodAlmacen` · `FK_ProyectoAlmacenUsuario_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_ProyectoAlmacenUsuario_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_ProyectoAlmacenUsuario_ProyectoUsuario` | `ProyectoUsuario` ·14 |
|   | `CodUsuario` · `FK_ProyectoAlmacenUsuario_ProyectoUsuario` | `ProyectoUsuario` ·14 |
| `ProyectoAlmacenUsuarioPermiso` ·332 | `CodPermiso` · `FK_ProyectoAlmacenUsuarioPermiso_Permiso` | `Permiso` ·27 |
|   | `CodAlmacen` · `FK_ProyectoAlmacenUsuarioPermiso_ProyectoAlmacenUsuario` | `ProyectoAlmacenUsuario` ·25 |
|   | `CodProyecto` · `FK_ProyectoAlmacenUsuarioPermiso_ProyectoAlmacenUsuario` | `ProyectoAlmacenUsuario` ·25 |
|   | `CodUsuario` · `FK_ProyectoAlmacenUsuarioPermiso_ProyectoAlmacenUsuario` | `ProyectoAlmacenUsuario` ·25 |
| `ProyectoAlmacenUsuarioPermiso_Historial` ·396 | — | — |
| `ProyectoAlmacenUsuarioUO` ·0 | `CodAlmacen` · `FK_ProyectoAlmacenUsuarioUO_ProyectoAlmacenUnidadOperativa` | `ProyectoAlmacenUnidadOperativa` ·0 |
|   | `CodProyecto` · `FK_ProyectoAlmacenUsuarioUO_ProyectoAlmacenUnidadOperativa` | `ProyectoAlmacenUnidadOperativa` ·0 |
|   | `CodUnidadOperativa` · `FK_ProyectoAlmacenUsuarioUO_ProyectoAlmacenUnidadOperativa` | `ProyectoAlmacenUnidadOperativa` ·0 |
|   | `CodRol` · `FK_ProyectoAlmacenUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_ProyectoAlmacenUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_ProyectoAlmacenUsuarioUO_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `ProyectoTipoAlmacen` ·95 | `CodProyecto` · `FK_ProyectoTipoAlmacen_Proyecto` | `Proyecto` ·234 |
|   | `CodTipoAlmacen` · `FK_ProyectoTipoAlmacen_TipoAlmacen` | `TipoAlmacen` ·6 |
| `Stock` ·1,217 | `CodIdentificador` · `FK_Stock_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_Stock_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_Stock_Moneda` | `Moneda` ·2 |
|   | `CodAlmacen` · `FK_Stock_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_Stock_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `StockDetalleRecurso` ·1 | — | — |
| `StockImpuesto` ·0 | `CodIdentificador` · `FK_StockImpuesto_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_StockImpuesto_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_StockImpuesto_Moneda` | `Moneda` ·2 |
|   | `CodAlmacen` · `FK_StockImpuesto_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_StockImpuesto_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `TalonarioProyectoAlmacen` ·0 | `CodAlmacen` · `FK_TalonarioProyectoAlmacen_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_TalonarioProyectoAlmacen_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `NroTalonario` · `FK_TalonarioProyectoAlmacen_Talonario` | `Talonario` ·0 |
| `TipoAlmacen` ·6 | — | — |
| `TipoAlmacenMovimiento` ·204 | `CodMovimiento` · `FK_TipoAlmacenMovimiento_GuiaAlmacenMovimiento` | `GuiaAlmacenMovimiento` ·55 |
|   | `CodTipoAlmacen` · `FK_TipoAlmacenMovimiento_TipoAlmacen` | `TipoAlmacen` ·6 |
| `TransferenciaPersonal` ·0 | `CodIdentificador` · `FK_TransferenciaPersonal_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_TransferenciaPersonal_Proyecto` | `Proyecto` ·234 |
| `UbicacionAlmacen` ·17 | — | — |

---

## M06 · Contabilidad

`[V]` 76 tablas · 89 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `AgrupamientoDocumento` ·1 | `NroCtaCte` · `FK_AgrupamientoDocumento_CuentaCorriente` | `CuentaCorriente` ·0 |
|   | `CodIdentificador` · `FK_AgrupamientoDocumento_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorDestino` · `FK_AgrupamientoDocumento_Empresa` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_AgrupamientoDocumento_Moneda` | `Moneda` ·2 |
|   | `CodDocumento` · `FK_AgrupamientoDocumento_TipoDocumento` | `TipoDocumento` ·66 |
| `AgrupamientoDocumentoAnticipo` ·1 | — | — |
| `AgrupamientoDocumentoAprobacion` ·0 | — | — |
| `AgrupamientoDocumentoDetalle` ·2 | `NroAD` · `FK_AgrupamientoDocumentoDetalle_AgrupamientoDocumento` | `AgrupamientoDocumento` ·1 |
|   | `NroD` · `FK_AgrupamientoDocumentoDetalle_RDocumento` | `RDocumento` ·1,340 |
| `AgrupamientoDocumentoRetencion` ·0 | `NroADD` · `FK_AgrupamientoDocumentoRetencion_AgrupamientoDocumentoDetalle` | `AgrupamientoDocumentoDetalle` ·2 |
| `AgrupamientoIdentificador` ·0 | — | — |
| `AsientoContable` ·6,491 | `NroCuentaGenerica` · `FK_AsientoContable_CuentaGenerica` | `CuentaGenerica` ·80 |
|   | `NroEsquemaContable` · `FK_AsientoContable_EsquemaContable` | `EsquemaContable` ·1 |
|   | `CodEmpresa` · `FK_AsientoContable_Identificador1` | `Identificador` ·1,638 |
|   | `CodIdentificador` · `FK_AsientoContable_Identificador` | `Identificador` ·1,638 |
|   | `CodSucursal` · `FK_AsientoContable_Identificador2` | `Identificador` ·1,638 |
|   | `NroIG` · `FK_AsientoContable_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `CodInsumo` · `FK_AsientoContable_Insumo` | `Insumo` ·10,254 |
|   | `CodMoneda` · `FK_AsientoContable_Moneda` | `Moneda` ·2 |
|   | `NroPeriodoContable` · `FK_AsientoContable_PeriodoContable` | `PeriodoContable` ·135 |
|   | `NroPlanContableDetalle` · `FK_AsientoContable_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `AsientoContableAuditoria` ·19 | — | — |
| `AsientoContableDatoCierre` ·0 | — | — |
| `AsientoContableDetalle` ·0 | — | — |
| `AsientoContableFiltro` ·0 | — | — |
| `AsientoContableInforme` ·0 | — | — |
| `AsientoContableMayor` ·0 | `NroEsquemaContable` · `FK_AsientoContableMayor_EsquemaContable` | `EsquemaContable` ·1 |
|   | `CodEmpresa` · `FK_AsientoContableMayor_Identificador` | `Identificador` ·1,638 |
|   | `CodSucursal` · `FK_AsientoContableMayor_Identificador1` | `Identificador` ·1,638 |
|   | `NroIG` · `FK_AsientoContableMayor_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `NroPlanContableDetalle` · `FK_AsientoContableMayor_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `AsientoContableProcesamiento` ·0 | — | — |
| `AsientoManual` ·1 | `CodMoneda` · `FK_AsientoManual_Moneda` | `Moneda` ·2 |
|   | `NroPeriodoContable` · `FK_AsientoManual_PeriodoContable` | `PeriodoContable` ·135 |
|   | `CodDocumento` · `FK_AsientoManual_TipoDocumento` | `TipoDocumento` ·66 |
| `AsientoManualDetalle` ·952 | `NroAM` · `FK_AsientoManualDetalle_AsientoManual` | `AsientoManual` ·1 |
|   | `CodIdentificador` · `FK_AsientoManualDetalle_Identificador` | `Identificador` ·1,638 |
|   | `NroPlanContableDetalle` · `FK_AsientoManualDetalle_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `AsientoManualTipo` ·0 | — | — |
| `BI_PeriodoContableDP` ·9 | — | — |
| `BI_SDP_PeriodoContable` ·0 | — | — |
| `CasillaPlanContableDetalle` ·0 | `NroCasilla` · `FK_CasillaPlanContableDetalle_Casilla` | `Casilla` ·0 |
|   | `NroPlanContableDetalle` · `FK_CasillaPlanContableDetalle_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `CentroCosto` ·0 | — | — |
| `CentroCostoTipo` ·5 | — | — |
| `CentroDeCosto` ·0 | `CodIdentificador` · `FK_CentroDeCosto_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_CentroDeCosto_Proyecto` | `Proyecto` ·234 |
| `CentroDeCostoVector` ·0 | — | — |
| `Contribuyente` ·29,181 | — | — |
| `ContribuyenteVigencia` ·0 | — | — |
| `Detraccion` ·41 | — | — |
| `DetraccionEmpresaPlanContable` ·0 | `NroDetraccion` · `FK_DetraccionEmpresaPlanContable_Detraccion` | `Detraccion` ·41 |
| `DetraccionPorcentaje` ·89 | `NroDetraccion` · `FK_DetraccionPorcentaje_Detraccion` | `Detraccion` ·41 |
| `EmpresaEsquemaContable` ·1 | — | — |
| `EmpresaParametroContable` ·2 | `CodIdentificador` · `FK_EmpresaParametroContable_Identificador` | `Identificador` ·1,638 |
|   | `NroCuentaAperturaFinal` · `FK_EmpresaParametroContable_PlanContablePeriodo_AF` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaAperturaInicio` · `FK_EmpresaParametroContable_PlanContablePeriodo_AI` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaCierreFinal` · `FK_EmpresaParametroContable_PlanContablePeriodo_CR` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaCierreGanancia` · `FK_EmpresaParametroContable_PlanContablePeriodo_CG` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaCierreInicio` · `FK_EmpresaParametroContable_PlanContablePeriodo_CI` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaCierrePerdida` · `FK_EmpresaParametroContable_PlanContablePeriodo_CP` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaDebe` · `FK_EmpresaParametroContable_PlanContablePeriodo_DCD` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaHaber` · `FK_EmpresaParametroContable_PlanContablePeriodo_DCH` | `PlanContablePeriodo` ·0 |
|   | `NroTipoOperacionApertura` · `FK_EmpresaParametroContable_TipoOperacionContable_Apertura` | `TipoOperacionContable` ·0 |
|   | `NroTipoOperacionCierre` · `FK_EmpresaParametroContable_TipoOperacionContable_Cierre` | `TipoOperacionContable` ·0 |
| `Impuesto` ·13 | — | — |
| `ImpuestoEmpresaPlanContable` ·0 | `CodIdentificador` · `FK_ImpuestoEmpresaPlanContable_Identificador` | `Identificador` ·1,638 |
|   | `NroImpuesto` · `FK_ImpuestoEmpresaPlanContable_Impuesto` | `Impuesto` ·13 |
|   | `NroCuentaDebe` · `FK_ImpuestoEmpresaPlanContable_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaHaber` · `FK_ImpuestoEmpresaPlanContable_PlanContablePeriodo1` | `PlanContablePeriodo` ·0 |
| `InformeContable` ·4 | — | — |
| `InsumoEmpresaPlanContable` ·10,254 | `CodIdentificador` · `FK_InsumoPlanContable_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_InsumoPlanContable_Insumo` | `Insumo` ·10,254 |
|   | `NroCuentaDebe` · `FK_InsumoPlanContable_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaHaber` · `FK_InsumoPlanContable_PlanContablePeriodo1` | `PlanContablePeriodo` ·0 |
| `LibroDiarioSimplificadoCuenta` ·0 | — | — |
| `Percepcion` ·19 | — | — |
| `Periodo` ·34,066 | `CodProyecto` · `FK_Periodo_Proyecto1` | `Proyecto` ·234 |
| `PeriodoCalendario` ·7,920 | — | — |
| `PeriodoContable` ·135 | `CodIdentificador` · `FK_PeriodoContable_Identificador` | `Identificador` ·1,638 |
| `PeriodoContableAuditoria` ·30 | — | — |
| `PeriodoContableControl` ·0 | — | — |
| `PeriodoContableGeneral` ·0 | — | — |
| `PeriodoContableHistorico` ·0 | `NroPeriodoContable` · `FK_PeriodoContableHistorico_PeriodoContable` | `PeriodoContable` ·135 |
| `PeriodoContableOperacion` ·0 | — | — |
| `PeriodoContableProceso` ·8 | — | — |
| `PeriodoNomina` ·0 | `CodProyecto` · `FK_PeriodoNomina_Proyecto1` | `Proyecto` ·234 |
| `PeriodoReporte` ·14 | — | — |
| `PeriodoSinActualizar` ·0 | — | — |
| `PeriodoUsuario` ·15 | — | — |
| `PeriodoVisible` ·0 | — | — |
| `Periodo_Historial` ·0 | — | — |
| `PlanContable` ·926 | — | — |
| `PlanContableAgrupacion` ·0 | — | — |
| `PlanContableAgrupacionPlanContable` ·0 | — | — |
| `PlanContableCatalogo` ·3 | — | — |
| `PlanContableCierre` ·0 | `NroPlanContableDetalle` · `FK_PlanContableCierre_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `PlanContableCierreDetalle` ·0 | `NroPlanContableCierre` · `FK_PlanContableCierreDetalle_PlanContableCierre` | `PlanContableCierre` ·0 |
|   | `NroPlanContableDetalle` · `FK_PlanContableCierreDetalle_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `PlanContableDetalle` ·5,063 | `NroCuentaGenerica` · `FK_PlanContableDetalle_CuentaGenerica` | `CuentaGenerica` ·80 |
|   | `CodMoneda` · `FK_PlanContableDetalle_Moneda` | `Moneda` ·2 |
|   | `BankAccount_ID` · `FK_PlanContableDetalle_OB_CuentaBanco` | `OB_CuentaBanco` ·1 |
|   | `NroCuentaReflejaDebe` · `FK_PlanContableDetalle_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
|   | `NroCuentaReflejaHaber` · `FK_PlanContableDetalle_PlanContableDetalle1` | `PlanContableDetalle` ·5,063 |
|   | `NroNaturalezaCuenta` · `FK_PlanContableDetalle_TablaTablas1` | `TablaTablas` ·17,070 |
|   | `NroTipoCuenta` · `FK_PlanContableDetalle_TablaTablas` | `TablaTablas` ·17,070 |
| `PlanContableDetalleSaldo` ·0 | — | — |
| `PlanContableDetalle_Historial` ·0 | — | — |
| `PlanContablePeriodo` ·0 | `CodIdentificador` · `FK_PlanContablePeriodo_Identificador` | `Identificador` ·1,638 |
|   | `NroCuentaAbono` · `FK_PlanContablePeriodo_PlanContablePeriodoAbono` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaCargo` · `FK_PlanContablePeriodo_PlanContablePeriodoCargo` | `PlanContablePeriodo` ·0 |
| `PlanContablePresupuesto` ·0 | — | — |
| `PlanContablePresupuestoInsumo` ·0 | — | — |
| `PlanContableProceso` ·0 | — | — |
| `RetencionPorcentaje` ·2 | — | — |
| `RubroContable` ·81 | — | — |
| `RubroContableCuenta` ·0 | — | — |
| `RubroContableProcedencia` ·36 | — | — |
| `RubroContableResultado` ·0 | `NroEsquemaContable` · `FK_RubroContableResultado_EsquemaContable` | `EsquemaContable` ·1 |
|   | `NroInformeContable` · `FK_RubroContableResultado_InformeContable` | `InformeContable` ·4 |
|   | `NroPeriodoContable` · `FK_RubroContableResultado_PeriodoContable` | `PeriodoContable` ·135 |
|   | `NroRubroContable` · `FK_RubroContableResultado_RubroContable` | `RubroContable` ·81 |
| `Tercero` ·14 | `CodIdentificador` · `FK_Tercero_Identificador` | `Identificador` ·1,638 |
|   | `NroTipoTercero` · `FK_Tercero_TipoTercero` | `TipoTercero` ·4 |
| `TerceroConceptoPeriodo` ·0 | `NroConcepto` · `FK_TerceroConceptoPeriodo_Concepto` | `Concepto` ·982 |
|   | `NroTercero` · `FK_TerceroConceptoPeriodo_Tercero` | `Tercero` ·14 |
| `TerceroConceptoPeriodoIndependiente` ·0 | `NroConcepto` · `FK_TerceroConceptoPeriodoIndependiente_Concepto` | `Concepto` ·982 |
|   | `NroTercero` · `FK_TerceroConceptoPeriodoIndependiente_Tercero` | `Tercero` ·14 |
| `TerceroConceptoValor` ·0 | `NroConcepto` · `FK_TerceroConceptoValor_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_TerceroConceptoValor_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroTercero` · `FK_TerceroConceptoValor_Tercero` | `Tercero` ·14 |
| `TipoDocumentoEmpresaPlanContable` ·0 | `CodIdentificador` · `FK_TipoDocumentoEmpresaPlanContable_Identificador` | `Identificador` ·1,638 |
|   | `NroCuentaDebe` · `FK_TipoDocumentoEmpresaPlanContable_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaHaber` · `FK_TipoDocumentoEmpresaPlanContable_PlanContablePeriodo1` | `PlanContablePeriodo` ·0 |
|   | `CodTipoDocumento` · `FK_TipoDocumentoEmpresaPlanContable_TipoDocumento` | `TipoDocumento` ·66 |
| `TipoOperacionContable` ·0 | `CodIdentificador` · `FK_TipoOperacionContable_Identificador` | `Identificador` ·1,638 |

---

## M07 · Tesorería y finanzas

`[V]` 75 tablas · 92 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Banco` ·0 | — | — |
| `Caja` ·0 | — | — |
| `CajaDetalle` ·0 | — | — |
| `Chequera` ·0 | — | — |
| `CompensacionDocumento` ·0 | `CodIdentificador` · `FK_CompensacionDocumento_Empresa` | `Identificador` ·1,638 |
|   | `CodIdentificadorReferencia` · `FK_CompensacionDocumento_Referencia` | `Identificador` ·1,638 |
|   | `CodSucursal` · `FK_CompensacionDocumento_Sucursal` | `Identificador` ·1,638 |
| `CompensacionDocumentoDetalle` ·0 | `NroCompensacionDocumento` · `FK_CompensacionDocumentoDetalle_CompensacionDocumento` | `CompensacionDocumento` ·0 |
|   | `NroDCP` · `FK_CompensacionDocumentoDetalle_RDocumentoCronogramaPago` | `RDocumentoCronogramaPago` ·1,390 |
| `CompensacionDocumentoPago` ·0 | `NroCompensacionDocumento` · `FK_CompensacionDocumentoPago_CompensacionDocumento` | `CompensacionDocumento` ·0 |
|   | `NroDCP` · `FK_CompensacionDocumentoPago_RDocumentoCronogramaPago` | `RDocumentoCronogramaPago` ·1,390 |
| `CompensacionRetencion` ·0 | — | — |
| `CreditoPersonal` ·0 | `NroEntidadCreditoMes` · `FK_CreditoPersonal_EntidadCreditoMes` | `EntidadCreditoMes` ·0 |
| `CuentaCorriente` ·0 | `CodBanco` · `FK_CuentaCorriente_IdentificadorBanco` | `Identificador` ·1,638 |
|   | `CodEmpresa` · `FK_CuentaCorriente_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_CuentaCorriente_Moneda` | `Moneda` ·2 |
|   | `NroCuenta` · `FK_CuentaCorriente_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroTipoCuentaBancaria` · `FK_CuentaCorriente_TipoCuentaBancaria` | `TipoCuentaBancaria` ·0 |
| `CuentaGenerica` ·80 | — | — |
| `Cuentas` ·2 | — | — |
| `EntidadCreditoMes` ·0 | `NroEECrediticia` · `FK_EntidadCreditoMes_EmpresaEntidadCrediticia` | `EmpresaEntidadCrediticia` ·0 |
|   | `NroProyectoEsquemaPeriodo` · `FK_EntidadCreditoMes_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `FondoRotatorio` ·0 | `CodIdentificador` · `FK_FondoRotatorio_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorResponsable` · `FK_FondoRotatorio_IdentificadorResponsable` | `Identificador` ·1,638 |
|   | `CodSucursal` · `FK_FondoRotatorio_Sucursal` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_FondoRotatorio_Moneda` | `Moneda` ·2 |
|   | `CodProyecto` · `FK_FondoRotatorio_Proyecto` | `Proyecto` ·234 |
| `FondoRotatorioDetalle` ·0 | `NroAD` · `FK_FondoRotatorioDetalle_AgrupamientoDocumento` | `AgrupamientoDocumento` ·1 |
|   | `NroFondoRotatorio` · `FK_FondoRotatorioDetalle_FondoRotatorio` | `FondoRotatorio` ·0 |
|   | `NroD` · `FK_FondoRotatorioDetalle_RDocumento` | `RDocumento` ·1,340 |
| `FondoRotatorioPago` ·0 | `NroFondoRotatorio` · `FK_FondoRotatorioPago_FondoRotatorio` | `FondoRotatorio` ·0 |
|   | `NroD` · `FK_FondoRotatorioPago_RDocumento` | `RDocumento` ·1,340 |
| `LiquidezAccesoEmpresa` ·0 | `CodIdentificador` · `FK_LiquidezAccesoEmpresa_Empresa` | `Identificador` ·1,638 |
| `LiquidezAccesoProyecto` ·0 | `CodProyecto` · `FK_LiquidezAccesoProyecto_Proyecto` | `Proyecto` ·234 |
| `LiquidezAccesoUsuario` ·0 | `CodIdentificador` · `FK_LiquidezAccesoUsuario_Identificador` | `Identificador` ·1,638 |
| `LiquidezEmpresa` ·0 | — | — |
| `LiquidezMultiproyecto` ·0 | — | — |
| `LiquidezProceso` ·0 | — | — |
| `LiquidezProyecto` ·0 | — | — |
| `MovimientoCajaBanco` ·0 | `NroCtaCte` · `FK_MovimientoCajaBanco_CuentaCorriente` | `CuentaCorriente` ·0 |
|   | `NroCtaCteDestino` · `FK_MovimientoCajaBanco_CuentaCorriente1` | `CuentaCorriente` ·0 |
|   | `CodBeneficiario` · `FK_MovimientoCajaBanco_Identificador` | `Identificador` ·1,638 |
|   | `CodCobrador` · `FK_MovimientoCajaBanco_Identificador1` | `Identificador` ·1,638 |
|   | `NroOperacionBancaria` · `FK_MovimientoCajaBanco_OperacionBancaria` | `OperacionBancaria` ·0 |
|   | `NroOperacionFinanciera` · `FK_MovimientoCajaBanco_OperacionFinanciera` | `OperacionFinanciera` ·0 |
|   | `CodDocumento` · `FK_MovimientoCajaBanco_TipoDocumento` | `TipoDocumento` ·66 |
|   | `NroVoucher` · `FK_MovimientoCajaBanco_Voucher` | `Voucher` ·0 |
| `OB_Banco` ·6 | `CodIdentificador` · `FK_Banco_Identificador` | `Identificador` ·1,638 |
| `OB_BancoConcepto` ·0 | — | — |
| `OB_BancoConceptoEquivalencia` ·0 | — | — |
| `OB_BancoConceptoFormato` ·0 | — | — |
| `OB_Caja` ·0 | `CodIdentificador` · `FK_OB_Caja_Identificador` | `Identificador` ·1,638 |
|   | `LibroCaja_ID` · `FK_OB_Caja_OB_LibroCaja` | `OB_LibroCaja` ·0 |
| `OB_CuentaBanco` ·1 | `CodIdentificador` · `FK_CuentaBanco_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_CuentaBanco_Moneda` | `Moneda` ·2 |
|   | `Banco_ID` · `FK_CuentaBanco_Banco` | `OB_Banco` ·6 |
| `OB_CuentaBancoPeriodo` ·12 | — | — |
| `OB_DetalleAsignacion` ·1 | `DetalleCaja_ID` · `FK_OB_DetalleAsignacion_OB_DetalleCaja` | `OB_DetalleCaja` ·0 |
|   | `Pago_ID` · `FK_OB_DetalleAsignacion_Pago` | `OB_Pago` ·1 |
|   | `NroDCP` · `FK_OB_DetalleAsignacion_RDocumentoCronogramaPago` | `RDocumentoCronogramaPago` ·1,390 |
| `OB_DetalleAsignacionProveedor` ·0 | — | — |
| `OB_DetalleCaja` ·0 | `Caja_ID` · `FK_DetalleCaja_OB_Caja` | `OB_Caja` ·0 |
| `OB_DetalleCajaLetra` ·0 | — | — |
| `OB_DetalleCajaNotaCredito` ·0 | — | — |
| `OB_DetalleLetra_Factura` ·0 | `NroDSPLetra` · `FK_OB_DetalleLetra_Factura_OB_DetalleSeleccionPagoLetra` | `OB_DetalleSeleccionPagoLetra` ·0 |
| `OB_DetallePlanillaCobranza` ·0 | — | — |
| `OB_DetalleSeleccionPago` ·4 | `SeleccionPago_ID` · `FK_OB_DetalleSeleccionPago_OB_SeleccionPago` | `OB_SeleccionPago` ·2 |
|   | `NroDCP` · `FK_OB_DetalleSeleccionPago_RDocumentoCronogramaPago` | `RDocumentoCronogramaPago` ·1,390 |
| `OB_DetalleSeleccionPagoLetra` ·0 | — | — |
| `OB_DetalleSeleccionPagoNotaCredito` ·0 | — | — |
| `OB_DetalleSeleccionPagoRelacionado` ·0 | — | — |
| `OB_EstadoBanco` ·0 | — | — |
| `OB_EstadoBancoDetalle` ·0 | — | — |
| `OB_EstadoBancoProceso` ·0 | — | — |
| `OB_Factura` ·0 | — | — |
| `OB_LibroCaja` ·0 | `CodIdentificadorEmpresa` · `FK_OB_LibroCaja_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorSucursal` · `FK_OB_LibroCaja_Identificador1` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_OB_LibroCaja_Moneda` | `Moneda` ·2 |
| `OB_LibroCaja_Integrantes` ·0 | `CodIdentificador` · `FK_OB_LibroCaja_Integrantes_Identificador` | `Identificador` ·1,638 |
|   | `LibroCaja_ID` · `FK_OB_LibroCaja_Integrantes_OB_LibroCaja` | `OB_LibroCaja` ·0 |
| `OB_Pago` ·1 | `BankAccount_ID` · `FK_OB_Pago_OB_CuentaBanco` | `OB_CuentaBanco` ·1 |
| `OB_PagoLetraDocumento` ·0 | — | — |
| `OB_Pago_H2H` ·0 | — | — |
| `OB_Pago_H2H_Credencial` ·0 | — | — |
| `OB_Pago_H2H_Credencial_Empresa` ·0 | — | — |
| `OB_Pago_H2H_Producto` ·4 | — | — |
| `OB_Pago_H2H_Producto_Empresa` ·0 | — | — |
| `OB_Pago_TabuladoRecurso` ·0 | — | — |
| `OB_SeleccionPago` ·2 | `CodIdentificador` · `FK_OB_SeleccionPago_Identificador` | `Identificador` ·1,638 |
|   | `CodSucursal` · `FK_OB_SeleccionPago_Identificador1` | `Identificador` ·1,638 |
|   | `BankAccount_ID` · `FK_OB_SeleccionPago_OB_CuentaBanco` | `OB_CuentaBanco` ·1 |
| `OB_SeleccionPagoNotificacion` ·0 | — | — |
| `OperacionBancaria` ·0 | — | — |
| `OperacionFinanciera` ·0 | `CodIdentificador` · `FK_OperacionFinanciera_Identificador` | `Identificador` ·1,638 |
|   | `NroTipoOperacion` · `FK_OperacionFinanciera_TipoOperacionContable` | `TipoOperacionContable` ·0 |
| `Pago` ·3 | `CodMoneda` · `FK__Pago__CodMoneda__4F5ECEBE` | `Moneda` ·2 |
|   | `CodProyecto` · `FK__Pago__CodProyect__4C826213` | `Proyecto` ·234 |
|   | `CodDocumento` · `FK__Pago__CodDocumen__4D76864C` | `TipoDocumento` ·66 |
| `PagoAdelanto` ·0 | `CodAdelanto` · `FK__PagoAdelanto__56FFF086` | `Adelanto` ·0 |
|   | `CodProyecto` · `FK__PagoAdelanto__56FFF086` | `Adelanto` ·0 |
|   | `CodPago` · `FK__PagoAdelanto__560BCC4D` | `Pago` ·3 |
|   | `CodProyecto` · `FK__PagoAdelanto__560BCC4D` | `Pago` ·3 |
| `PagoCobranzaDetallado` ·0 | — | — |
| `PagoCobranzaDetalle` ·0 | — | — |
| `PagoCobranzaRecurso` ·0 | — | — |
| `PagoDevolucion` ·0 | — | — |
| `PagoDevolucionGarantia` ·0 | — | — |
| `PagoInsumo` ·0 | `CodInsumo` · `FK__PagoDetal__CodIn__523B3B69` | `Insumo` ·10,254 |
|   | `CodPago` · `FK__PagoDetalle__532F5FA2` | `Pago` ·3 |
|   | `CodProyecto` · `FK__PagoDetalle__532F5FA2` | `Pago` ·3 |
| `PagoLiquidacion` ·0 | `CodPago` · `FK__PagoLiquidacion__5DACEE15` | `Pago` ·3 |
|   | `CodProyecto` · `FK__PagoLiquidacion__5DACEE15` | `Pago` ·3 |
|   | `CodLiquidacion` · `FK__PagoLiquidacion__5EA1124E` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK__PagoLiquidacion__5EA1124E` | `SubcontratoLiquidacion` ·1 |
| `PagoPagoaCuenta` ·0 | `CodPago` · `FK__PagoPagoaCuenta__59DC5D31` | `Pago` ·3 |
|   | `CodProyecto` · `FK__PagoPagoaCuenta__59DC5D31` | `Pago` ·3 |
|   | `CodPagoaCuenta` · `FK__PagoPagoaCuenta__5AD0816A` | `PagoaCuenta` ·0 |
|   | `CodProyecto` · `FK__PagoPagoaCuenta__5AD0816A` | `PagoaCuenta` ·0 |
| `PagoRetencion` ·0 | — | — |
| `PagoaCuenta` ·0 | `CodProyecto` · `FK__PagoaCuen__CodPr__3787452D` | `Proyecto` ·234 |
| `PagoaCuentaInsumo` ·0 | `CodInsumo` · `FK__PagoaCuen__CodIn__3A63B1D8` | `Insumo` ·10,254 |
|   | `CodPagoaCuenta` · `FK__PagoaCuentaInsum__3B57D611` | `PagoaCuenta` ·0 |
|   | `CodProyecto` · `FK__PagoaCuentaInsum__3B57D611` | `PagoaCuenta` ·0 |
| `Voucher` ·0 | `NroPeriodoContable` · `FK_Voucher_PeriodoContable` | `PeriodoContable` ·135 |
|   | `NroTipoOperacion` · `FK_Voucher_TipoOperacionContable` | `TipoOperacionContable` ·0 |
| `VoucherDetalle` ·0 | `NroCentroDeCosto` · `FK_VoucherDetalle_CentroDeCosto` | `CentroDeCosto` ·0 |
|   | `NroCtaCte` · `FK_VoucherDetalle_CuentaCorriente` | `CuentaCorriente` ·0 |
|   | `CodIdentificador` · `FK_VoucherDetalle_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_VoucherDetalle_Moneda` | `Moneda` ·2 |
|   | `NroCuenta` · `FK_VoucherDetalle_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroCuentaDestino` · `FK_VoucherDetalle_PlanContablePeriodoDestino` | `PlanContablePeriodo` ·0 |
|   | `CodDocumento` · `FK_VoucherDetalle_TipoDocumento` | `TipoDocumento` ·66 |
|   | `NroVoucher` · `FK_VoucherDetalle_Voucher` | `Voucher` ·0 |

---

## M08 · Facturación y ventas

`[V]` 38 tablas · 42 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `ErrorFacturacionElectronica` ·1,763 | — | — |
| `FacturacionElectronicaConfiguracion` ·0 | `CodDocumento` · `FK_FacturacionElectronicaConfiguracion_TipoDocumento` | `TipoDocumento` ·66 |
| `LibroElectronicoRectifica` ·0 | — | — |
| `LibrosElectronicosCab` ·0 | — | — |
| `LibrosElectronicosDet` ·0 | — | — |
| `RDocumento` ·1,340 | `NroCentroDeCosto` · `FK_RDocumento_CentroDeCosto` | `CentroDeCosto` ·0 |
|   | `CodFormaDePago` · `FK_RDocumento_FormaDePago` | `FormaDePago` ·32 |
|   | `CodIdentificador` · `FK_RDocumento_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorDestino` · `FK_RDocumento_Identificador1` | `Identificador` ·1,638 |
|   | `NroMetodoPago` · `FK_RDocumento_MetodoPago` | `MetodoPago` ·3 |
|   | `CodMoneda` · `FK_RDocumento_Moneda` | `Moneda` ·2 |
|   | `NroMotivoPago` · `FK_RDocumento_MotivoPago` | `MotivoPago` ·4 |
|   | `CodProyecto` · `FK_RDocumento_Proyecto` | `Proyecto` ·234 |
|   | `CodTipoDocumento` · `FK_RDocumento_TipoDocumento` | `TipoDocumento` ·66 |
| `RDocumentoAnexoTransaccional` ·0 | `NroD` · `FK_RDocumentoAnexoTransaccional_RDocumento` | `RDocumento` ·1,340 |
|   | `NroSustentoTributario` · `FK_RDocumentoAnexoTransaccional_SustentoTributario` | `SustentoTributario` ·10 |
| `RDocumentoAprobacion` ·0 | — | — |
| `RDocumentoAsiento` ·0 | `NroIG` · `FK_RDocumentoAsiento_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `NroCuenta` · `FK_RDocumentoAsiento_PlanContablePeriodo` | `PlanContablePeriodo` ·0 |
|   | `NroD` · `FK_RDocumentoAsiento_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoAuxiliar` ·0 | — | — |
| `RDocumentoCargo` ·0 | `NroD` · `FK_RDocumentoCargo_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoComplemento` ·1,391 | — | — |
| `RDocumentoCronogramaPago` ·1,390 | `NroD` · `FK_RDocumentoCronogramaPago_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoCronogramaPagoNota` ·0 | — | — |
| `RDocumentoDescarga` ·0 | — | — |
| `RDocumentoDetalle` ·1,721 | `NroIG` · `FK_RDocumentoDetalle_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `CodInsumo` · `FK_RDocumentoDetalle_Insumo` | `Insumo` ·10,254 |
|   | `NroPCG` · `FK_RDocumentoDetalle_PartidaControlGeneral` | `PartidaControlGeneral` ·57 |
|   | `NroD` · `FK_RDocumentoDetalle_RDocumento` | `RDocumento` ·1,340 |
|   | `CodTipoDocumento` · `FK_RDocumentoDetalle_TipoDocumento` | `TipoDocumento` ·66 |
| `RDocumentoDetalleAuxiliar` ·0 | `NroDAuxiliar` · `FK_RDocumentoDetalleAuxiliar_RDocumentoAuxiliar` | `RDocumentoAuxiliar` ·0 |
| `RDocumentoDetalleDeducible` ·0 | `CodInsumo` · `FK_RDocumentoDetalleDeducible_Insumo` | `Insumo` ·10,254 |
|   | `NroDD` · `FK_RDocumentoDetalleDeducible_DD` | `RDocumentoDetalle` ·1,721 |
| `RDocumentoDetalleImpresion` ·28 | — | — |
| `RDocumentoDetalleIndicadorGestion` ·1,596 | `NroIG` · `FK_RDocumentoDetalleIndicadorGestion_IndicadorGestion` | `IndicadorGestion` ·18 |
|   | `CodInsumo` · `FK_RDocumentoDetalleIndicadorGestion_Insumo` | `Insumo` ·10,254 |
| `RDocumentoElectronico` ·0 | `NroD` · `FK_RDocumentoElectronico_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoElectronicoBaja` ·0 | — | — |
| `RDocumentoElectronicoBajaDetalle` ·0 | `NroD` · `FK_RDocumentoElectronicoBajaDetalle_RDocumento` | `RDocumento` ·1,340 |
|   | `NroDElectronicoBaja` · `FK_RDocumentoElectronicoBajaDetalle_RDocumentoElectronicoBaja` | `RDocumentoElectronicoBaja` ·0 |
| `RDocumentoElectronicoEnvio` ·0 | — | — |
| `RDocumentoElectronicoEnvioRobot` ·0 | — | — |
| `RDocumentoElectronicoResumen` ·0 | — | — |
| `RDocumentoElectronicoResumenContenido` ·0 | — | — |
| `RDocumentoElectronicoResumenDetalle` ·0 | `NroD` · `FK_RDocumentoElectronicoResumenDetalle_RDocumento` | `RDocumento` ·1,340 |
|   | `NroDElectronicoResumen` · `FK_RDocumentoElectronicoResumenDetalle_RDocumentoElectronicoResumen` | `RDocumentoElectronicoResumen` ·0 |
| `RDocumentoElectronicoResumenLinea` ·0 | `NroDElectronicoResumen` · `FK_RDocumentoElectronicoResumenLinea_RDocumentoElectronicoResumen` | `RDocumentoElectronicoResumen` ·0 |
| `RDocumentoImpuesto` ·1,327 | `NroD` · `FK_RDocumentoImpuesto_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoPagoGalac` ·0 | — | — |
| `RDocumentoProveedor` ·0 | — | — |
| `RDocumentoQR` ·0 | — | — |
| `RDocumentoRecepcion` ·0 | `CodEmpresa` · `FK_RDocumentoRecepcion_Identificador` | `Identificador` ·1,638 |
|   | `CodProveedor` · `FK_RDocumentoRecepcion_Identificador1` | `Identificador` ·1,638 |
|   | `NroD` · `FK_RDocumentoRecepcion_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoRelacionado` ·125 | `NroBoletaNomina` · `FK_RDocumentoRelacionado_BoletaNomina` | `BoletaNomina` ·0 |
|   | `NroICB` · `FK_RDocumentoRelacionado_IdentificadorCuentaBanco` | `IdentificadorCuentaBanco` ·0 |
|   | `NroD` · `FK_RDocumentoRelacionado_RDocumento` | `RDocumento` ·1,340 |
| `RDocumentoRelacionadoComplemento` ·5 | — | — |
| `RDocumentoRelacionadoDetalle` ·0 | `NroRDR` · `FK_RDocumentoRelacionadoDetalle_RDocumentoRelacionado` | `RDocumentoRelacionado` ·125 |
|   | `NroRDR_NC` · `FK_RDocumentoRelacionadoDetalle_RDocumentoRelacionado1` | `RDocumentoRelacionado` ·125 |
| `RDocumentoTabulado` ·0 | — | — |

---

## M09 · Planillas y personal

`[V]` 103 tablas · 92 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `AFPNetAfiliado` ·0 | — | — |
| `AFPNetConfiguracion` ·0 | — | — |
| `AFPNetPlanilla` ·0 | — | — |
| `AFPNetPlanillaEdicion` ·0 | — | — |
| `BoletaContenido` ·0 | `NroTipoBoletaNomina` · `FK_BoletaContenido_TipoBoletaNomina` | `TipoBoletaNomina` ·0 |
| `BoletaContenidoControlCambios` ·0 | — | — |
| `BoletaNomina` ·0 | `NroBoletaContenido` · `FK_BoletaNomina_BoletaContenido` | `BoletaContenido` ·0 |
| `BoletaNominaDetalle` ·0 | `NroBoletaNomina` · `FK_BoletaNominaDetalle_BoletaNomina` | `BoletaNomina` ·0 |
|   | `NroConcepto` · `FK_BoletaNominaDetalle_Concepto` | `Concepto` ·982 |
| `BoletaNominaDetallePeriodo` ·0 | `NroBoletaNomina` · `FK_BoletaNominaDetallePeriodo_BoletaNomina` | `BoletaNomina` ·0 |
|   | `NroConcepto` · `FK_BoletaNominaDetallePeriodo_Concepto` | `Concepto` ·982 |
| `BoletaNominaDetallePeriodoProyecto` ·0 | `NroBoletaNomina` · `FK_BoletaNominaDetallePeriodoProyecto_BoletaNomina` | `BoletaNomina` ·0 |
|   | `NroConcepto` · `FK_BoletaNominaDetallePeriodoProyecto_Concepto` | `Concepto` ·982 |
| `BoletaNominaFacturacion` ·0 | `NroBoletaContenido` · `FK_BoletaNominaFacturacion_BoletaContenido` | `BoletaContenido` ·0 |
|   | `NroEsquemaPlanilla` · `FK_BoletaNominaFacturacion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroPlanillaPagoConfiguracion` · `FK_BoletaNominaFacturacion_PlanillaPagoConfiguracion` | `PlanillaPagoConfiguracion` ·63 |
|   | `CodProyecto` · `FK_BoletaNominaFacturacion_Proyecto` | `Proyecto` ·234 |
|   | `NroD` · `FK_BoletaNominaFacturacion_RDocumento` | `RDocumento` ·1,340 |
|   | `NroTipoBoletaNomina` · `FK_BoletaNominaFacturacion_TipoBoletaNomina` | `TipoBoletaNomina` ·0 |
| `BoletaNominaFormato` ·26 | — | — |
| `BoletaNominaImpresion` ·0 | `NroBoletaNomina` · `FK_BoletaNominaImpresion_BoletaNomina` | `BoletaNomina` ·0 |
| `CapacitacionAplicaciones` ·0 | — | — |
| `CapacitacionAsistencia` ·0 | — | — |
| `CapacitacionCurso` ·0 | — | — |
| `CapacitacionCursoAsistencia` ·0 | — | — |
| `CapacitacionCursoParticipante` ·0 | — | — |
| `CapacitacionCursosInteres` ·0 | — | — |
| `CapacitacionEmpresa` ·0 | — | — |
| `CapacitacionEncuesta` ·0 | — | — |
| `CapacitacionEncuestaPreguntas` ·0 | — | — |
| `CapacitacionFichaDatos` ·0 | — | — |
| `Cargo` ·14 | — | — |
| `CompensacionLaboral` ·0 | `NroConcepto` · `FK_CompensacionLaboral_Concepto` | `Concepto` ·982 |
|   | `CodIdentificador` · `FK_CompensacionLaboral_Identificador` | `Identificador` ·1,638 |
|   | `NroProyectoEsquemaPeriodo` · `FK_CompensacionLaboral_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `CompensacionLaboralPapeleta` ·0 | `NroCompensacionLaboral` · `FK_CompensacionLaboralPapeleta_CompensacionLaboral` | `CompensacionLaboral` ·0 |
|   | `NroPOPapeleta` · `FK_CompensacionLaboralPapeleta_ProyectoObreroPapeleta` | `ProyectoObreroPapeleta` ·0 |
| `Concepto` ·982 | — | — |
| `ConceptoAuditoria` ·123 | — | — |
| `ConceptoEsquema` ·1,100 | `NroConcepto` · `FK_ConceptoEsquema_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ConceptoEsquema_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `ConceptoGenerico` ·264 | — | — |
| `ConceptoModo` ·0 | — | — |
| `ConceptoPDT` ·171 | — | — |
| `ConceptoPiePpto` ·2 | — | — |
| `ConceptoProyectoPropio` ·0 | `NroConcepto` · `FK_ConceptoProyectoPropio_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ConceptoProyectoPropio_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ConceptoProyectoPropio_Proyecto` | `Proyecto` ·234 |
| `ConfiguracionEstructuraPDT` ·40 | — | — |
| `ContratoCambio` ·0 | `CodProyecto` · `FK_ContratoCambio_Proyecto` | `Proyecto` ·234 |
| `ContratoProvias` ·0 | — | — |
| `DescuentoPersonal` ·0 | `NroConcepto` · `FK_DescuentoPersonal_Concepto` | `Concepto` ·982 |
|   | `NroConceptoPorcentaje` · `FK_DescuentoPersonal_ConceptoPorcentaje` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_DescuentoPersonal_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_DescuentoPersonal_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorBeneficiario` · `FK_DescuentoPersonal_IdentificadorBeneficiario` | `Identificador` ·1,638 |
|   | `CodIdentificadorDispone` · `FK_DescuentoPersonal_IdentificadorDispone` | `Identificador` ·1,638 |
|   | `CodIdentificadorEmpresa` · `FK_DescuentoPersonal_Empresa` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_DescuentoPersonal_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquemaPeriodoAl` · `FK_DescuentoPersonal_ProyectoEsquemaPeriodoAl` | `ProyectoEsquemaPeriodo` ·0 |
|   | `NroProyectoEsquemaPeriodoDel` · `FK_DescuentoPersonal_ProyectoEsquemaPeriodoDel` | `ProyectoEsquemaPeriodo` ·0 |
|   | `NroTipoDescuento` · `FK_DescuentoPersonal_TipoDescuento` | `TipoDescuento` ·0 |
| `DescuentoPersonalConcepto` ·0 | `NroConcepto` · `FK_DescuentoPersonalConcepto_Concepto` | `Concepto` ·982 |
| `EmpresaIncidenteLaboral` ·0 | `CodIdentificador` · `FK_EmpresaIncidenteLaboral_Identificador` | `Identificador` ·1,638 |
| `EsquemaCombinacion` ·0 | — | — |
| `EsquemaContable` ·1 | — | — |
| `EsquemaCuentaContable` ·0 | `NroEsquemaContable` · `FK_EsquemaCuentaContable_EsquemaContable` | `EsquemaContable` ·1 |
|   | `NroPlanContableDetalle` · `FK_EsquemaCuentaContable_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `EsquemaPlanilla` ·5 | — | — |
| `EsquemaPlanillaAsignacion` ·0 | `CodCategoria` · `FK_EsquemaPlanillaAsignacion_Categoria` | `Categoria` ·7 |
|   | `NroEsquemaPlanilla` · `FK_EsquemaPlanillaAsignacion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodOcupacion` · `FK_EsquemaPlanillaAsignacion_Ocupacion` | `Ocupacion` ·146 |
| `EsquemaPlanillaConceptoGrupo` ·12 | `NroEsquemaPlanilla` · `FK_EsquemaPlanillaConceptoGrupo_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `EsquemaPlanillaFeriado` ·0 | `NroEsquemaPlanilla` · `FK_EsquemaPlanillaFeriado_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `IdentificadorPDT` ·15 | — | — |
| `IncidenteLaboral` ·0 | `CodIdentificadorAprueba` · `FK_IncidenteLaboral_IdentificadorAprueba` | `Identificador` ·1,638 |
|   | `CodIdentificadorReporta` · `FK_IncidenteLaboral_IdentificadorReporta` | `Identificador` ·1,638 |
|   | `NroMotivoHabilitacion` · `FK_IncidenteLaboral_MotivoHabilitacion` | `TablaTablas` ·17,070 |
|   | `NroMotivoIncidente` · `FK_IncidenteLaboral_MotivoIncidente` | `TablaTablas` ·17,070 |
|   | `NroNivelGravedad` · `FK_IncidenteLaboral_NivelGravedad` | `TablaTablas` ·17,070 |
| `PDTDHAlta` ·0 | — | — |
| `PDTDHBaja` ·0 | — | — |
| `PDTDatosCuentaRemuneracion` ·0 | — | — |
| `PDTDatosTercero` ·0 | — | — |
| `PDTDetalleTercero` ·0 | — | — |
| `PDTEstablecimiento` ·0 | — | — |
| `PDTEstablecimientoFormacion` ·0 | — | — |
| `PDTEstablecimientoTrabajador` ·0 | — | — |
| `PDTEstudiosConcluidos` ·0 | — | — |
| `PDTIngresoPensionista` ·0 | — | — |
| `PDTIngresoTrabajador` ·0 | — | — |
| `PDTJornadaLaboral` ·0 | — | — |
| `PDTPensionista` ·0 | — | — |
| `PDTPeriodo` ·0 | — | — |
| `PDTPeriodoBaja` ·0 | — | — |
| `PDTPrestadorFormativa` ·0 | — | — |
| `PDTPrestadorFormativaPago` ·0 | — | — |
| `PDTPrestadorRentaCuarta` ·0 | — | — |
| `PDTPrestadorRentaCuartaDetalle` ·0 | — | — |
| `PDTPrincipalTrabajador` ·0 | — | — |
| `PDTRenta5taOtraEmpresa` ·0 | — | — |
| `PDTTrabajador` ·0 | — | — |
| `PDTTrabajadorOtrasCondiciones` ·0 | — | — |
| `PDTTrabajadorSubsidios` ·0 | — | — |
| `PersonalTipoDescuento` ·0 | `CodIdentificador` · `FK_PersonalTipoDescuento_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorEmpresa` · `FK_PersonalTipoDescuento_Empresa` | `Identificador` ·1,638 |
|   | `NroTipoDescuento` · `FK_PersonalTipoDescuento_TipoDescuento` | `TipoDescuento` ·0 |
| `PersonalTipoPrestamo` ·0 | — | — |
| `PlanillaConceptoEntrada` ·0 | `NroConcepto` · `FK_PlanillaConceptoEntrada_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_PlanillaConceptoEntrada_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_PlanillaConceptoEntrada_Proyecto` | `Proyecto` ·234 |
| `PlanillaFechaEntrada` ·5 | `NroConcepto` · `FK_PlanillaFechaEntrada_Concepto` | `Concepto` ·982 |
| `PlanillaFechaValor` ·0 | — | — |
| `PlanillaFechaValorConcepto` ·0 | `NroConcepto` · `FK_PlanillaFechaValorConcepto_Concepto` | `Concepto` ·982 |
| `PlanillaGrupoProcesoPeriodo` ·0 | `NroEsquemaPlanilla` · `FK_PlanillaGrupoProcesoPeriodo_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroGrupoProcesoNomina` · `FK_PlanillaGrupoProcesoPeriodo_GrupoProcesoNomina` | `GrupoProcesoNomina` ·0 |
|   | `CodProyecto` · `FK_PlanillaGrupoProcesoPeriodo_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquemaPeriodo` · `FK_PlanillaGrupoProcesoPeriodo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaGrupoProcesoPeriodoDetalle` ·0 | `NroPlanillaGrupoProcesoPeriodo` · `FK_PlanillaGrupoProcesoPeriodoDetalle_PlanillaGrupoProcesoPeriodo` | `PlanillaGrupoProcesoPeriodo` ·0 |
|   | `NroProcesoNomina` · `FK_PlanillaGrupoProcesoPeriodoDetalle_ProcesoNomina` | `ProcesoNomina` ·11 |
|   | `NroProyectoEsquemaPeriodoAl` · `FK_PlanillaGrupoProcesoPeriodoDetalle_ProyectoEsquemaPeriodoAl` | `ProyectoEsquemaPeriodo` ·0 |
|   | `NroProyectoEsquemaPeriodoDel` · `FK_PlanillaGrupoProcesoPeriodoDetalle_ProyectoEsquemaPeriodoDel` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaLetra` ·0 | `NroCtaCte` · `FK_PlanillaLetra_CuentaCorriente` | `CuentaCorriente` ·0 |
|   | `CodIdentificador` · `FK_PlanillaLetra_Identificador` | `Identificador` ·1,638 |
| `PlanillaLetraDetalle` ·0 | `NroPlanillaLetra` · `FK_PlanillaLetraDetalle_PlanillaLetra` | `PlanillaLetra` ·0 |
| `PlanillaObreroEntrada` ·0 | `NroEsquemaPlanilla` · `FK_PlanillaObreroEntrada_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `PlanillaObreroEntradaHistorico` ·0 | — | — |
| `PlanillaObreroPeriodo` ·0 | `NroProyectoEsquemaPeriodo` · `FK_PlanillaObreroPeriodo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaObreroPeriodoEntrada` ·0 | `NroProyectoEsquemaPeriodo` · `FK_PlanillaObreroPeriodoEntrada_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaObreroPeriodoEntradaHistorico` ·0 | — | — |
| `PlanillaPagoConfiguracion` ·63 | `NroConcepto` · `FK_PlanillaPagoConfiguracion_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_PlanillaPagoConfiguracion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_PlanillaPagoConfiguracion_Identificador` | `Identificador` ·1,638 |
|   | `CodInsumo` · `FK_PlanillaPagoConfiguracion_Insumo` | `Insumo` ·10,254 |
|   | `CodDocumento` · `FK_PlanillaPagoConfiguracion_TipoDocumento` | `TipoDocumento` ·66 |
| `PlanillaPagoConfiguracionProceso` ·0 | `NroPlanillaPagoConfiguracion` · `FK_PlanillaPagoConfiguracionProceso_PlanillaPagoConfiguracion` | `PlanillaPagoConfiguracion` ·63 |
|   | `NroProcesoNomina` · `FK_PlanillaPagoConfiguracionProceso_ProcesoNomina` | `ProcesoNomina` ·11 |
| `PlanillaPeriodoEntrada` ·0 | `NroProyectoEsquemaPeriodo` · `FK_PlanillaPeriodoEntrada_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaProcesoHistorial` ·0 | — | — |
| `PlanillaProcesoIdentificador` ·0 | `NroProyectoEsquemaPeriodo` · `FK_PlanillaProcesoIdentificador_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
|   | `NroPIEP` · `FK_PlanillaProcesoIdentificador_ProyectoIdentificadorEsquemaProceso` | `ProyectoIdentificadorEsquemaProceso` ·0 |
| `PlanillaProcesoIdentificadorPeriodo` ·0 | `NroProyectoEsquemaPeriodoAfe` · `FK_PlanillaProcesoIdentificadorPeriodo_ProyectoEsquemaPeriodo_Afe` | `ProyectoEsquemaPeriodo` ·0 |
|   | `NroProyectoEsquemaPeriodoCal` · `FK_PlanillaProcesoIdentificadorPeriodo_ProyectoEsquemaPeriodo_Cal` | `ProyectoEsquemaPeriodo` ·0 |
| `PlanillaProcesoIdentificadorPeriodoConcepto` ·0 | `NroConcepto` · `FK_PlanillaProcesoIdentificadorPeriodoConcepto_Concepto` | `Concepto` ·982 |
|   | `NroPlanillaProcesoIdentificador` · `FK_PlanillaProcesoIdentificadorPeriodoConcepto_PlanillaProcesoIdentificador` | `PlanillaProcesoIdentificador` ·0 |
| `PlanillaProcesoPeriodo` ·0 | `NroProcesoNomina` · `FK_PlanillaProcesoPeriodo_ProcesoNomina` | `ProcesoNomina` ·11 |
|   | `NroProyectoEsquemaPeriodo` · `FK_PlanillaProcesoPeriodo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `PrestamoPersonal` ·0 | — | — |
| `PrestamoPersonalAprobacion` ·0 | `CodIdentificador` · `FK_PrestamoPersonalAprobacion_Identificador` | `Identificador` ·1,638 |
| `PrestamoPersonalCronograma` ·0 | — | — |
| `PrestamoPersonalDocumento` ·0 | `NroPrestamoPersonal` · `FK_PrestamoPersonalDocumento_PrestamoPersonal` | `PrestamoPersonal` ·0 |
|   | `NroD` · `FK_PrestamoPersonalDocumento_RDocumento` | `RDocumento` ·1,340 |
| `VacacionFraccionamiento` ·0 | — | — |
| `VacacionProgramada` ·0 | — | — |
| `VacacionProgramadaPapeleta` ·0 | — | — |

---

## M10 · Asistencia y tareo

`[V]` 60 tablas · 70 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `ConceptoTareoHora` ·0 | `NroConcepto` · `FK_ConceptoTareoHora_Concepto` | `Concepto` ·982 |
| `ConfiguracionTareoConcepto` ·0 | `NroConcepto` · `FK_ConfiguracionTareoConcepto_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ConfiguracionTareoConcepto_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ConfiguracionTareoConcepto_Proyecto` | `Proyecto` ·234 |
| `ConfiguracionTareoHora` ·0 | `NroConcepto` · `FK_ConfiguracionTareoHora_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_ConfiguracionTareoHora_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `Horario` ·231 | `CodCalendario` · `FK_Horario_Calendario` | `Calendario` ·33 |
|   | `CodProyecto` · `FK_Horario_Calendario` | `Calendario` ·33 |
| `HorarioAsistencia` ·4 | `NroConcepto` · `FkNroConceptoHorarioAsistencia` | `ConceptoGenerico` ·264 |
| `HorarioBase` ·7 | — | — |
| `HorarioCiclico` ·7 | — | — |
| `HorarioCondicion` ·0 | — | — |
| `HorarioCondicionDetalle` ·0 | `NroConcepto` · `FkNroConceptoHorarioCondicionDetalle` | `ConceptoGenerico` ·264 |
|   | `NroHorarioCondicion` · `FkNroHorarioCondicionHorarioCondicionDetalle` | `HorarioCondicion` ·0 |
| `HorarioDetalle` ·10 | `NroConcepto` · `FkNroConceptoHorarioDetalle` | `ConceptoGenerico` ·264 |
| `HorarioDiario` ·4 | `NroConcepto` · `FkNroConceptoHorarioDiario` | `ConceptoGenerico` ·264 |
| `HorarioEspecial` ·0 | `CodCalendario` · `FK_HorarioEspecial_Calendario` | `Calendario` ·33 |
|   | `CodProyecto` · `FK_HorarioEspecial_Calendario` | `Calendario` ·33 |
| `HorarioFeriado` ·0 | — | — |
| `HorarioFlexible` ·0 | — | — |
| `HorarioGlobal` ·5 | — | — |
| `HorarioTipo` ·2 | — | — |
| `HorarioTurno` ·3 | — | — |
| `MarcacionAuditoria` ·0 | — | — |
| `Permiso` ·27 | — | — |
| `ProyectoEsquemaPeriodoTareo` ·0 | `CodProyecto` · `FK_ProyectoEsquemaPeriodoTareo_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquema` · `FK_ProyectoEsquemaPeriodoTareo_ProyectoEsquema` | `ProyectoEsquema` ·0 |
| `ProyectoObreroTareo` ·0 | `NroInterferencia` · `FK_ProyectoObreroTareo_Interferencia` | `Interferencia` ·7 |
|   | `CodPartidaDeControl` · `FK_ProyectoObreroTareo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyecto` · `FK_ProyectoObreroTareo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodObrero` · `FK_ProyectoObreroTareo_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_ProyectoObreroTareo_ProyectoObrero` | `ProyectoObrero` ·0 |
| `ProyectoObreroTareoAdicional` ·0 | `CodProyecto` · `FK_ProyectoObreroTareoAdicional_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroTareoDetalle` ·0 | `NroEsquemaPlanilla` · `FK_ProyectoObreroTareoDetalle_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoObreroTareoDetalle_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroTareoDia` ·0 | `NroEsquemaPlanilla` · `FK_ProyectoObreroTareoDia_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ProyectoObreroTareoDia_Proyecto` | `Proyecto` ·234 |
| `ProyectoObreroTareoInsumo` ·0 | `CodInsumo` · `FK_ProyectoObreroTareoInsumo_Insumo` | `Insumo` ·10,254 |
|   | `CodPartidaDeControl` · `FK_ProyectoObreroTareoInsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyecto` · `FK_ProyectoObreroTareoInsumo_PartidaDeControl` | `PartidaDeControl` ·1,079 |
| `ProyectoObreroTareoResumen` ·0 | `CodObrero` · `FK_ProyectoObreroTareoResumen_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_ProyectoObreroTareoResumen_ProyectoObrero` | `ProyectoObrero` ·0 |
| `ProyectoTareo` ·1 | — | — |
| `ProyectoTareoObrero` ·48 | — | — |
| `ProyectoTareoPC` ·2 | — | — |
| `ProyectoTareoSincronizacion` ·0 | — | — |
| `ProyectoTareoSincronizacionHistorico` ·0 | — | — |
| `ResumenInterferenciaTareo` ·0 | `NroInterferencia` · `FK_ResumenInterferenciaTareo_Interferencia` | `Interferencia` ·7 |
| `ResumenPartidaDeControlTareo` ·0 | — | — |
| `Tareo` ·0 | `CodOT` · `FK_Tareo_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_Tareo_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `TareoActividad` ·0 | — | — |
| `TareoActividadDia` ·0 | `NroParteMObra` · `FK_TareoActividadDia_ParteMObra` | `ParteMObra` ·0 |
|   | `NroTActividadGrupo` · `FK_TareoActividadDia_TareoActividadGrupo` | `TareoActividadGrupo` ·0 |
| `TareoActividadGrupo` ·0 | `CodProyecto` · `FK_TareoActividadGrupo_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquemaPeriodo` · `FK_TareoActividadGrupo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
|   | `CodProyectoSubordinacion` · `FK_TareoActividadGrupo_SubordinacionMO` | `SubordinacionMO` ·0 |
|   | `CodSubordinacionMO` · `FK_TareoActividadGrupo_SubordinacionMO` | `SubordinacionMO` ·0 |
| `TareoActividadIdentificador` ·0 | `NroEsquemaPlanilla` · `FK_TareoActividadIdentificador_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_TareoActividadIdentificador_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_TareoActividadIdentificador_Proyecto` | `Proyecto` ·234 |
| `TareoActividadObrero` ·0 | `NroOTActividad` · `FK_TareoActividadObrero_OTActividad` | `OTActividad` ·0 |
|   | `CodObrero` · `FK_TareoActividadObrero_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyectoObrero` · `FK_TareoActividadObrero_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodOT` · `FK_TareoActividadObrero_Tareo` | `Tareo` ·0 |
|   | `CodProyecto` · `FK_TareoActividadObrero_Tareo` | `Tareo` ·0 |
|   | `CodTareo` · `FK_TareoActividadObrero_Tareo` | `Tareo` ·0 |
| `TareoActividadObreroHora` ·0 | — | — |
| `TareoActividadObreroReporte` ·0 | — | — |
| `TareoActividadPeriodo` ·0 | `CodProyecto` · `FK_TareoActividadPeriodo_Proyecto` | `Proyecto` ·234 |
|   | `NroProyectoEsquemaPeriodo` · `FK_TareoActividadPeriodo_ProyectoEsquemaPeriodo` | `ProyectoEsquemaPeriodo` ·0 |
| `TareoMovilEnvio` ·0 | — | — |
| `TareoMovilEnvioActividadDia` ·0 | — | — |
| `TareoMovilEnvioDetalle` ·0 | — | — |
| `TareoMovilEnvioDia` ·0 | — | — |
| `TareoMovilEnvioHistorial` ·0 | — | — |
| `TareoMovilEnvioTMP` ·0 | — | — |
| `TareoMultiproyecto` ·0 | `NroEsquemaPlanilla` · `FK_TareoMultiproyecto_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_TareoMultiproyecto_Proyecto` | `Proyecto` ·234 |
|   | `CodProyectoSubordinacion` · `FK_TareoMultiproyecto_SubordinacionMO` | `SubordinacionMO` ·0 |
|   | `CodSubordinacionMO` · `FK_TareoMultiproyecto_SubordinacionMO` | `SubordinacionMO` ·0 |
| `TareoMultiproyectoDestinoDia` ·0 | — | — |
| `TareoMultiproyectoDestinoDiaPersona` ·0 | — | — |
| `TareoMultiproyectoDetallado` ·0 | — | — |
| `TareoMultiproyectoDetalle` ·0 | `NroActividad` · `FK_TareoMultiproyectoDetalle_Actividad` | `Actividad` ·653 |
|   | `CodProyecto` · `FK_TareoMultiproyectoDetalle_Proyecto` | `Proyecto` ·234 |
|   | `NroTareoMultiproyecto` · `FK_TareoMultiproyectoDetalle_TareoMultiproyecto` | `TareoMultiproyecto` ·0 |
| `TareoMultiproyectoIdentificador` ·0 | `NroEsquemaPlanilla` · `FK_TareoMultiproyectoIdentificador_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_TareoMultiproyectoIdentificador_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_TareoMultiproyectoIdentificador_Proyecto` | `Proyecto` ·234 |
| `TareoMultiproyectoIdentificadorProyecto` ·0 | `NroEsquemaPlanilla` · `FK_TareoMultiproyectoIdentificadorProyecto_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FK_TareoMultiproyectoIdentificadorProyecto_Identificador` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FK_TareoMultiproyectoIdentificadorProyecto_Proyecto` | `Proyecto` ·234 |
|   | `CodProyectoDestino` · `FK_TareoMultiproyectoIdentificadorProyecto_ProyectoDestino` | `Proyecto` ·234 |
| `TareoResumenReporte` ·0 | — | — |
| `TarjetaDeCredito` ·0 | — | — |
| `TiempoProcesado` ·0 | — | — |
| `TiempoProcesadoAsignado` ·0 | — | — |
| `TiempoResumen` ·0 | `NroConcepto` · `FkNroConceptoTiempoResumen` | `ConceptoGenerico` ·264 |
|   | `NroEsquemaPlanilla` · `FkNroEsquemaPlanillaTiempoResumen` | `EsquemaPlanilla` ·5 |
|   | `CodIdentificador` · `FkCodIdentificadorTiempoResumen` | `Identificador` ·1,638 |
|   | `CodProyecto` · `FkCodProyectoTiempoResumen` | `Proyecto` ·234 |

---

## M11 · Subcontratos

`[V]` 35 tablas · 61 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `SubContratoRecursoFactorReajusteHistoria` ·2 | — | — |
| `Subcontrato` ·1 | `CodResponsable` · `FK__Subcontra__CodRe__07D8320B` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK__Subcontra__CodMo__06E40DD2` | `Moneda` ·2 |
|   | `CodProyecto` · `FK__Subcontra__CodPr__1FAFBB9C` | `Proyecto` ·234 |
| `SubcontratoActividad` ·0 | — | — |
| `SubcontratoCartaFianza` ·0 | `CodIdentificador` · `FK__Subcontra__CodId__293925D6` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK__Subcontra__CodMo__2845019D` | `Moneda` ·2 |
|   | `CodExpediente` · `FK__SubcontratoCarta__2750DD64` | `SubcontratoExpediente` ·0 |
|   | `CodProyecto` · `FK__SubcontratoCarta__2750DD64` | `SubcontratoExpediente` ·0 |
|   | `TipoCartaFianza` · `FK__Subcontra__TipoC__2A2D4A0F` | `TipoCartaFianza` ·3 |
| `SubcontratoCartaFianzaCorreo` ·0 | `CodCartaFianza` · `FK_SubcontratoCartaFianzaCorreo_SubcontratoCartaFianza` | `SubcontratoCartaFianza` ·0 |
|   | `CodExpediente` · `FK_SubcontratoCartaFianzaCorreo_SubcontratoCartaFianza` | `SubcontratoCartaFianza` ·0 |
|   | `CodProyecto` · `FK_SubcontratoCartaFianzaCorreo_SubcontratoCartaFianza` | `SubcontratoCartaFianza` ·0 |
| `SubcontratoCartaFianzaInsumo` ·0 | `CodInsumo` · `FK__Subcontra__CodIn__4431D73E` | `Insumo` ·10,254 |
|   | `CodCartaFianza` · `FK__SubcontratoCarta__433DB305` | `SubcontratoCartaFianza` ·0 |
|   | `CodExpediente` · `FK__SubcontratoCarta__433DB305` | `SubcontratoCartaFianza` ·0 |
|   | `CodProyecto` · `FK__SubcontratoCarta__433DB305` | `SubcontratoCartaFianza` ·0 |
| `SubcontratoCodigo` ·46,578 | — | — |
| `SubcontratoConcepto` ·15 | — | — |
| `SubcontratoDisenoResumen` ·12 | `CodProyecto` · `FK_SubcontratoDisenoResumen_Subcontrato` | `Subcontrato` ·1 |
|   | `CodSC` · `FK_SubcontratoDisenoResumen_SubcontratoConcepto` | `SubcontratoConcepto` ·15 |
| `SubcontratoEstadoCuenta` ·0 | `CodEstadoCuentaMovimiento` · `FK__Subcontra__CodEs__2DFDDAF3` | `EstadoCuentaMovimiento` ·11 |
|   | `CodProyecto` · `FK__Subcontra__CodPr__2D09B6BA` | `Proyecto` ·234 |
| `SubcontratoExpediente` ·0 | `CodMoneda` · `FK__Subcontra__CodMo__0C9CE728` | `Moneda` ·2 |
|   | `CodProyecto` · `FK__Subcontra__CodPr__0BA8C2EF` | `Proyecto` ·234 |
| `SubcontratoFiltro` ·4 | — | — |
| `SubcontratoImpuesto` ·1 | `NroImpuesto` · `FK_SubcontratoImpuesto_Impuesto` | `Impuesto` ·13 |
|   | `CodProyecto` · `FK_SubcontratoImpuesto_Subcontrato` | `Subcontrato` ·1 |
| `SubcontratoImpuestoValorizacion` ·1 | `CodProyecto` · `FK_SubcontratoImpuestoValorizacion_Subcontrato` | `Subcontrato` ·1 |
| `SubcontratoLiquidacion` ·1 | `CodProyecto` · `FK__Subcontra__CodPr__3E3442BC` | `Proyecto` ·234 |
| `SubcontratoLiquidacionAdelanto` ·0 | `CodAdelanto` · `FK__SubcontratoLiqui__45D56484` | `Adelanto` ·0 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__45D56484` | `Adelanto` ·0 |
|   | `CodLiquidacion` · `FK__SubcontratoLiqui__44E1404B` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__44E1404B` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionConcepto` ·0 | `CodSC` · `FK_SubcontratoLiquidacionConcepto_SubcontratoConcepto` | `SubcontratoConcepto` ·15 |
|   | `CodLiquidacion` · `FK_SubcontratoLiquidacionConcepto_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK_SubcontratoLiquidacionConcepto_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionDeduccion` ·0 | `CodInsumo` · `FK__Subcontra__CodIn__4204D3A0` | `Insumo` ·10,254 |
|   | `CodLiquidacion` · `FK__SubcontratoLiqui__4110AF67` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__4110AF67` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionDetalle` ·1 | `CodDestinoEspecifico` · `FK_SubcontratoLiquidacionDetalle_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodProyectoDE` · `FK_SubcontratoLiquidacionDetalle_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodPartidaDeControl` · `FK_SubcontratoLiquidacionDetalle_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyectoPC` · `FK_SubcontratoLiquidacionDetalle_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodLiquidacion` · `FK__SubcontratoLiqui__29EB22B2` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__29EB22B2` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionDetalleAuditoria` ·1 | — | — |
| `SubcontratoLiquidacionDetalleDistribucion` ·0 | — | — |
| `SubcontratoLiquidacionEC` ·0 | — | — |
| `SubcontratoLiquidacionImpuesto` ·1 | `CodLiquidacion` · `FK_SubcontratoLiquidacionImpuesto_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK_SubcontratoLiquidacionImpuesto_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionPago` ·2 | `CodPago` · `FK_SubcontratoLiquidacionPago_Pago` | `Pago` ·3 |
|   | `CodProyecto` · `FK_SubcontratoLiquidacionPago_Pago` | `Pago` ·3 |
|   | `CodLiquidacion` · `FK_SubcontratoLiquidacionPago_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK_SubcontratoLiquidacionPago_SubcontratoLiquidacion` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionPagoaCuenta` ·0 | `CodPagoaCuenta` · `FK__SubcontratoLiqui__49A5F568` | `PagoaCuenta` ·0 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__49A5F568` | `PagoaCuenta` ·0 |
|   | `CodLiquidacion` · `FK__SubcontratoLiqui__48B1D12F` | `SubcontratoLiquidacion` ·1 |
|   | `CodProyecto` · `FK__SubcontratoLiqui__48B1D12F` | `SubcontratoLiquidacion` ·1 |
| `SubcontratoLiquidacionResumen` ·12 | — | — |
| `SubcontratoOrdenServicio` ·1 | `NroOrden` · `FK_SubcontratoOrdenServicio_OrdenDeCompra` | `OrdenDeCompra` ·9 |
|   | `CodProyecto` · `FK_SubcontratoOrdenServicio_Subcontrato` | `Subcontrato` ·1 |
| `SubcontratoOrdenTrabajo` ·0 | `CodProyecto` · `FK_SubcontratoOrdenTrabajo_Subcontrato` | `Subcontrato` ·1 |
| `SubcontratoRecurso` ·1 | `CodDestinoEspecifico` · `FK_SubcontratoRecurso_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodProyectoDE` · `FK_SubcontratoRecurso_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodInsumo` · `FK_SubcontratoRecurso_Insumo` | `Insumo` ·10,254 |
|   | `CodPartidaDeControl` · `FK_SubcontratoRecurso_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyectoPC` · `FK_SubcontratoRecurso_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyecto` · `FK_SubcontratoRecurso_Subcontrato` | `Subcontrato` ·1 |
| `SubcontratoRecursoActividad` ·0 | — | — |
| `SubcontratoRecursoAdicionalDeductivo` ·0 | — | — |
| `SubcontratoRecursoDistribucion` ·0 | — | — |
| `SubcontratoRecursoTemporal` ·0 | — | — |
| `SubcontratoResumen` ·0 | — | — |
| `SubcontratoWBS` ·1 | `CodProyecto` · `FK_SubcontratoWBS_Subcontrato` | `Subcontrato` ·1 |

---

## M12 · Activos y equipos

`[V]` 21 tablas · 42 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Activo` ·0 | `CodIdentificador` · `FK_Activo_Identificador` | `Identificador` ·1,638 |
| `EquipoEstadoCalidad` ·0 | — | — |
| `Interferencia` ·7 | — | — |
| `InterferenciaEQ` ·0 | — | — |
| `InterferenciaMO` ·0 | — | — |
| `OTActividad` ·0 | `CodPresupuesto` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `CodProyecto` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `CodSubpartida` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `CodSubpresupuesto` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `CodTarea` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `Item` · `FK_OTActividad_Actividad` | `Actividad` ·653 |
|   | `CodOT` · `FK_OTActividad_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_OTActividad_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `OTActividadAvance` ·0 | `NroOTActividad` · `FK_OTActividadAvance_OTActividad` | `OTActividad` ·0 |
|   | `CodOT` · `FK_OTActividadAvance_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_OTActividadAvance_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `OTActividadRecurso` ·0 | `CodInsumo` · `FK_OTActividadRecurso_Insumo` | `Insumo` ·10,254 |
| `OTActividadRecursoReporte` ·0 | — | — |
| `OTActividadTemporal` ·0 | — | — |
| `OTInterferenciaEQ` ·0 | `CodInterferenciaEQ` · `FK_OTInterferenciaEQ_InterferenciaEQ` | `InterferenciaEQ` ·0 |
|   | `CodOT` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodParte` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodPresupuesto` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodProyecto` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodSubpartida` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodSubpresupuesto` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `CodTarea` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
|   | `Item` · `FK_OTInterferenciaEQ_ParteEquipoActividad` | `ParteEquipoActividad` ·0 |
| `OTInterferenciaMO` ·0 | `CodInterferenciaMO` · `FK_OTInterferenciaMO_InterferenciaMO` | `InterferenciaMO` ·0 |
|   | `CodOT` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodPresupuesto` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodProyecto` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodSubpartida` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodSubpresupuesto` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodTarea` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `CodTareo` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
|   | `Item` · `FK_OTInterferenciaMO_TareoActividad` | `TareoActividad` ·0 |
| `ParteEquipo` ·0 | `CodOT` · `FK_ParteEquipo_OrdenTrabajo` | `OrdenTrabajo` ·0 |
|   | `CodProyecto` · `FK_ParteEquipo_OrdenTrabajo` | `OrdenTrabajo` ·0 |
| `ParteEquipoActividad` ·0 | — | — |
| `ParteEquipoActividadEquipo` ·0 | `CodEquipo` · `FK_ParteEquipoActividadEquipo_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyectoEquipo` · `FK_ParteEquipoActividadEquipo_ProyectoEquipo` | `ProyectoEquipo` ·0 |
| `ParteEquipoActividadEquipoReporte` ·0 | — | — |
| `ParteEquipoResumenReporte` ·0 | — | — |
| `ParteMObra` ·0 | `NroEsquemaPlanilla` · `FK_ParteMObra_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_ParteMObra_Proyecto` | `Proyecto` ·234 |
|   | `CodProyectoSubordinacion` · `FK_ParteMObra_SubordinacionMO` | `SubordinacionMO` ·0 |
|   | `CodSubordinacionMO` · `FK_ParteMObra_SubordinacionMO` | `SubordinacionMO` ·0 |
| `ParteMObraActividad` ·0 | `NroParteMObra` · `FK_ParteMObraActividad_ParteMObra` | `ParteMObra` ·0 |
| `ParteMObraDetallado` ·0 | — | — |
| `ParteMObraDetalle` ·0 | `CodIdentificador` · `FK_ParteMObraDetalle_Identificador` | `Identificador` ·1,638 |
|   | `NroParteMObra` · `FK_ParteMObraDetalle_ParteMObra` | `ParteMObra` ·0 |

---

## M13 · Inmobiliaria

`[V]` 34 tablas · 19 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Abonos_Sperant` ·0 | — | — |
| `AgrupamientoNotaDeVenta` ·0 | `CodIdentificador` · `FK_AgrupamientoNotaDeVenta_Identificador` | `Identificador` ·1,638 |
|   | `CodIdentificadorDestino` · `FK_AgrupamientoNotaDeVenta_IdentificadorDestino` | `Identificador` ·1,638 |
| `AgrupamientoNotaDeVentaDetalle` ·0 | `NroANV` · `FK_AgrupamientoNotaDeVentaDetalle_AgrupamientoNotaDeVenta` | `AgrupamientoNotaDeVenta` ·0 |
|   | `NroNotaDeVenta` · `FK_AgrupamientoNotaDeVentaDetalle_NotaDeVenta` | `NotaDeVenta` ·0 |
| `InmobiliariaClientes` ·0 | — | — |
| `InmobiliariaConceptosGenerales` ·0 | — | — |
| `InmobiliariaPrototipo` ·0 | — | — |
| `InmobiliariaProyectos` ·0 | — | — |
| `Letras_Sperant` ·0 | — | — |
| `NotaDeVenta` ·0 | `NroANV` · `FK_NotaDeVenta_AgrupamientoNotaDeVenta` | `AgrupamientoNotaDeVenta` ·0 |
|   | `CodFormaDePago` · `FK_NotaDeVenta_FormaDePago` | `FormaDePago` ·32 |
|   | `CodEmpresa` · `FK_NotaDeVenta_Identificador1` | `Identificador` ·1,638 |
|   | `CodIdentificador` · `FK_NotaDeVenta_Identificador` | `Identificador` ·1,638 |
|   | `CodMoneda` · `FK_NotaDeVenta_Moneda` | `Moneda` ·2 |
|   | `CodTipoDocumento` · `FK_NotaDeVenta_TipoDocumento` | `TipoDocumento` ·66 |
| `NotaDeVentaAnulacion` ·0 | — | — |
| `NotaDeVentaAnulacionConcepto` ·0 | — | — |
| `NotaDeVentaAnulacionConceptoDevolucion` ·0 | — | — |
| `NotaDeVentaCoPropietario` ·0 | `CodIdentificador` · `FK_NotaDeVentaCoPropietario_Identificador` | `Identificador` ·1,638 |
|   | `NroNotaDeVenta` · `FK_NotaDeVentaCoPropietario_NotaDeVenta` | `NotaDeVenta` ·0 |
| `NotaDeVentaDescripcionRecursoPropio` ·0 | `NroNotaDeVenta` · `FK_NotaDeVentaDescripcionRecursoPropio_NotaDeVenta` | `NotaDeVenta` ·0 |
| `NotaDeVentaDetallado` ·0 | — | — |
| `NotaDeVentaDetalle` ·0 | `CodInsumo` · `FK_NotaDeVentaDetalle_Insumo` | `Insumo` ·10,254 |
|   | `NroNotaDeVenta` · `FK_NotaDeVentaDetalle_NotaDeVenta` | `NotaDeVenta` ·0 |
| `NotaDeVentaEdicion` ·0 | — | — |
| `NotaDeVentaEquivalencia` ·0 | — | — |
| `NotaDeVentaGratuito` ·0 | — | — |
| `NotaDeVentaGrupo` ·0 | — | — |
| `NotaDeVentaModalidad` ·0 | — | — |
| `NotaDeVentaModulo` ·0 | — | — |
| `NotaDeVentaNotificacion` ·0 | — | — |
| `NotaDeVentaPagoDetalle` ·0 | `NroNotaDeVenta` · `FK_NotaDeVentaPagoDetalle_NotaDeVenta` | `NotaDeVenta` ·0 |
| `NotaDeVentaPagoDetalleRecurso` ·0 | `CodInsumo` · `FK_NotaDeVentaPagoDetalleRecurso_Insumo` | `Insumo` ·10,254 |
|   | `NroNVPagoDetalle` · `FK_NotaDeVentaPagoDetalleRecurso_NotaDeVentaPagoDetalle` | `NotaDeVentaPagoDetalle` ·0 |
| `NotaDeVentaPagoDetalleSeguimiento` ·0 | `NroNVPagoDetalle` · `FK_NotaDeVentaPagoDetalleSeguimiento_NotaDeVentaPagoDetalle` | `NotaDeVentaPagoDetalle` ·0 |
| `NotaDeVentaRecurso` ·0 | — | — |
| `NotaDeVentaVersion` ·0 | — | — |
| `ProyectoPrototipo` ·0 | — | — |
| `ProyectoPrototipoAccesorios` ·0 | — | — |
| `ProyectoPrototipoCostoxEtapa` ·0 | — | — |
| `ProyectoSperant` ·0 | — | — |
| `RegistrarCobroSperant` ·0 | — | — |
| `RegistroIdentificadorNotaDeVenta` ·0 | — | — |

---

## M14 · Temporales y respaldo

`[V]` 307 tablas · 3 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `ActividadPeriodoCronogramaCopia` ·0 | — | — |
| `ActividadPeriodoInsumoCronogramaCopia` ·0 | — | — |
| `AsientoContableCopia` ·0 | — | — |
| `AsientoContableCopiaDetalle` ·0 | — | — |
| `BitacoraEliminacion` ·0 | — | — |
| `BitacoraEnvios` ·1 | — | — |
| `BitacoraEnviosAgrupacion` ·6 | — | — |
| `BitacoraEnviosLOG` ·87 | — | — |
| `BitacoraLOG` ·121 | — | — |
| `BitacoraRecepcion` ·0 | — | — |
| `BitacoraRecepcionAgrupacion` ·0 | — | — |
| `BitacoraRecepcionSucesos` ·0 | — | — |
| `BitacoraRecodificacion` ·0 | — | — |
| `ESQUEMA_BASEDATOS_J` ·14,589 | — | — |
| `ESQUEMA_BASEDATOS_J_DIFF_LEFT` ·304 | — | — |
| `ESQUEMA_BASEDATOS_J_ESQ` ·18,698 | — | — |
| `ESQUEMA_BASEDATOS_J_EXE` ·304 | — | — |
| `ESQUEMA_BASEDATOS_J_EXE_ERR` ·552 | — | — |
| `ESQUEMA_BASEDATOS_J_LOAD` ·18,750 | — | — |
| `ESQUEMA_BASEDATOS_J_MOD` ·2,541 | — | — |
| `ESQUEMA_BASEDATOS_J_MOD_ACC` ·2,541 | — | — |
| `GuiaAlmacenDetalleCopia` ·0 | — | — |
| `LibrosElectronicosCab_Copia` ·0 | — | — |
| `LibrosElectronicosDet_Copia` ·0 | — | — |
| `Log_AuditTrail` ·0 | — | — |
| `Log_AuditTrail_Det` ·0 | — | — |
| `Log_Configuration` ·30 | — | — |
| `Log_Variables` ·4 | — | — |
| `Logo` ·5 | — | — |
| `OrdenCompraDetallePedido_Copia` ·13 | — | — |
| `PeriodoContableCopia` ·0 | — | — |
| `Periodo_Copia` ·420 | — | — |
| `PlantillaFormulaConcepto_BK` ·3,145 | — | — |
| `PlantillaFormula_BK` ·14 | — | — |
| `PlantillaRepartoGuiaAlmacen_Copia` ·0 | — | — |
| `RDocumentoDetalleCopia` ·1,721 | — | — |
| `RDocumentoRelacionadoCopia` ·9 | — | — |
| `TmpAC` ·0 | — | — |
| `TmpAdelantoMateriales` ·18 | — | — |
| `TmpAgrupamiento` ·0 | — | — |
| `TmpAnalisisPorMetrado` ·0 | — | — |
| `TmpAnaliticoCuentas` ·0 | — | — |
| `TmpAnaliticoCuentasResumen` ·0 | — | — |
| `TmpAnaliticoPorCuentas` ·0 | — | — |
| `TmpAsientoContable` ·0 | — | — |
| `TmpAsientoContableArchivado` ·0 | — | — |
| `TmpAsientoContableRefleja` ·0 | — | — |
| `TmpAsientoContable_Resumen` ·0 | — | — |
| `TmpAsientoContable_Trazabilidad` ·0 | — | — |
| `TmpAsientoDiferencia` ·0 | — | — |
| `TmpAsientoDiferenciaTraslacion` ·0 | — | — |
| `TmpAsientoManual` ·37 | — | — |
| `TmpAsignacionClasificacion` ·385 | — | — |
| `TmpAsignacionHorario` ·0 | — | — |
| `TmpAuxiliarAC` ·0 | — | — |
| `TmpB2KCopia` ·5 | — | — |
| `TmpBalanceComprobacion` ·193 | — | — |
| `TmpBalanceComprobacion_Listar` ·0 | — | — |
| `TmpBalanceGeneral` ·0 | — | — |
| `TmpBalanceGeneral_Columnas` ·0 | — | — |
| `TmpCPP` ·772 | — | — |
| `TmpCalendarioProyecto` ·0 | — | — |
| `TmpCambioTitularidad` ·0 | — | — |
| `TmpCategoria` ·0 | — | — |
| `TmpCertificadoPago` ·0 | — | — |
| `TmpCierre` ·0 | — | — |
| `TmpClasificacion` ·2 | — | — |
| `TmpComparativoMateriales` ·0 | — | — |
| `TmpComparativoNominas` ·0 | — | — |
| `TmpCompensaciones` ·0 | — | — |
| `TmpCompuesta` ·6 | — | — |
| `TmpConceptoGenerico` ·0 | — | — |
| `TmpConciliacionBancaria` ·0 | — | — |
| `TmpConsolidadoValorizacion` ·0 | — | — |
| `TmpCotizacionDetalle` ·3 | — | — |
| `TmpCotizacionProveedor` ·0 | — | — |
| `TmpCotizacionProveedorDetalle` ·0 | — | — |
| `TmpCuadroComparativoDetalle` ·0 | — | — |
| `TmpCxP` ·0 | — | — |
| `TmpDetallePendientePago` ·0 | — | — |
| `TmpDetalleSeleccionPagoDesdoble` ·0 | — | — |
| `TmpDetalleSeleccionPagoProveedor` ·0 | — | — |
| `TmpDetalleSeleccionPagoProveedorDetalle` ·0 | — | — |
| `TmpDetalleSeleccionRecursosEgresoPC` ·0 | — | — |
| `TmpDocTributo` ·0 | — | — |
| `TmpDocumentoDetallePedido` ·0 | — | — |
| `TmpDocumentoGeneral` ·0 | — | — |
| `TmpDocumentoRelacionado` ·3 | — | — |
| `TmpDocumentos` ·0 | — | — |
| `TmpDocumentosTributo` ·0 | — | — |
| `TmpEntregaInmueble` ·0 | — | — |
| `TmpEntregaInmuebleAsiento` ·0 | — | — |
| `TmpEstadoBancoDetalle` ·0 | — | — |
| `TmpEstadoCuenta` ·0 | — | — |
| `TmpEstadoFinanciero` ·0 | — | — |
| `TmpFactorReajusteDetallado` ·0 | — | — |
| `TmpFecha` ·0 | — | — |
| `TmpFlujoEfectivo` ·0 | — | — |
| `TmpGrupoRegistroAgrupado` ·0 | — | — |
| `TmpGrupoRegistroCampos` ·0 | — | — |
| `TmpGrupos` ·0 | — | — |
| `TmpGruposCampos` ·0 | — | — |
| `TmpGuia` ·0 | — | — |
| `TmpGuiaAC` ·0 | — | — |
| `TmpGuiaAM` ·0 | — | — |
| `TmpGuiaAlmacen` ·0 | — | — |
| `TmpGuiaAlmacenDetalle` ·0 | — | — |
| `TmpGuiaAsiento` ·0 | — | — |
| `TmpGuiaAsiento2` ·0 | — | — |
| `TmpGuiaAsiento2AM` ·0 | — | — |
| `TmpGuiaAsiento3` ·0 | — | — |
| `TmpGuiaAsientoC` ·0 | — | — |
| `TmpGuiaNE` ·0 | — | — |
| `TmpGuiaVoucher` ·0 | — | — |
| `TmpGuiaVoucher2` ·0 | — | — |
| `TmpGuiaVoucher2AM` ·0 | — | — |
| `TmpHorarioDiario` ·0 | — | — |
| `TmpHorarioGlobal` ·0 | — | — |
| `TmpIdentificadorAsistencia` ·0 | — | — |
| `TmpIdentificadorIC` ·0 | — | — |
| `TmpIdentificadorSG` ·0 | — | — |
| `TmpImportacionExcelError` ·1,178 | — | — |
| `TmpIndicadorGestion` ·0 | — | — |
| `TmpInformeContableFiltro` ·0 | — | — |
| `TmpInformeContableResultado` ·0 | — | — |
| `TmpInformeContableResultadoResumen` ·0 | — | — |
| `TmpIngresoOrdenCompra` ·0 | — | — |
| `TmpIngresoOrdenCompraGuias` ·0 | — | — |
| `TmpInsumo` ·0 | — | — |
| `TmpLE_BalanceComprobacion` ·0 | — | — |
| `TmpLectora` ·0 | — | — |
| `TmpLibroDiario` ·0 | — | — |
| `TmpLibroDiario_BIMONEDA` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_10` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_11` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_12` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_13` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_14` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_15` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_2` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_3` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_4` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_5` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_6` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_7` ·0 | — | — |
| `TmpLibroRegistro_Formato_3_8` ·0 | — | — |
| `TmpLiquidacion` ·0 | — | — |
| `TmpMarcacionAnalisis` ·0 | — | — |
| `TmpNRO` ·31 | — | — |
| `TmpNRO_Detalle` ·0 | — | — |
| `TmpNaturalezaFuncionDetalle` ·0 | — | — |
| `TmpNomina` ·0 | — | — |
| `TmpNominaAsiento` ·0 | — | — |
| `TmpNominaAsientoProyecto` ·0 | — | — |
| `TmpNominaBoleta` ·0 | — | — |
| `TmpNotaDeVenta` ·0 | — | — |
| `TmpNotaDeVenta_Anulacion` ·0 | — | — |
| `TmpNotaDeVenta_EnCola` ·0 | — | — |
| `TmpNotaDeVenta_Financiera` ·0 | — | — |
| `TmpNotaDeVenta_Financiera_EnCola` ·0 | — | — |
| `TmpOrdenCompraDetalleSG` ·0 | — | — |
| `TmpOrdenCompraSG` ·0 | — | — |
| `TmpOrdenDeCompra` ·2 | — | — |
| `TmpOrdenDeCompraComplemento` ·0 | — | — |
| `TmpOrdenDeCompraDetalle` ·5 | — | — |
| `TmpOrdenPagoErrado` ·0 | — | — |
| `TmpPCD1` ·0 | — | — |
| `TmpPCD2` ·0 | — | — |
| `TmpPagoCR` ·0 | — | — |
| `TmpPagoCRDetalle` ·0 | — | — |
| `TmpPagoDetallePedido` ·0 | — | — |
| `TmpPagoMesNuevo` ·0 | — | — |
| `TmpPagoRetencionErrado` ·0 | — | — |
| `TmpPartidaComparacionInsumo` ·0 | — | — |
| `TmpPartidaPropia` ·1,295 | — | — |
| `TmpPedido` ·2 | — | — |
| `TmpPedidoCompra` ·0 | — | — |
| `TmpPedidoDetalle` ·24 | — | — |
| `TmpPedidoPendiente` ·0 | — | — |
| `TmpPeriodoContable` ·0 | — | — |
| `TmpPeriodoEvaluacion` ·0 | — | — |
| `TmpPeriodoSuma` ·0 | — | — |
| `TmpPeriodoVisible` ·34 | — | — |
| `TmpPieValorizacion` ·0 | — | — |
| `TmpPlanContableDetalle` ·7 | — | — |
| `TmpPlanificacionDiaria` ·0 | — | — |
| `TmpPlanillaPagoConfiguracion` ·0 | — | — |
| `TmpPlanillaPagoPersonal` ·0 | — | — |
| `TmpPlantillaRepartoTransferencia` ·0 | — | — |
| `TmpPortafolio` ·0 | — | — |
| `TmpPortafolioDetalle` ·0 | — | — |
| `TmpPreciosGenerales` ·0 | — | — |
| `TmpPresupuestoAgrupacion` ·0 | — | — |
| `TmpPresupuestoPartidaNoUtilizada` ·0 | — | — |
| `TmpProveedor` ·0 | — | — |
| `TmpProyecto` ·0 | — | — |
| `TmpProyectoAsiento` ·0 | — | — |
| `TmpProyectoGrupo` ·0 | `Correlativo` · `FK_TmpProyectoGrupo_Grupos` | `Grupos` ·608 |
|   | `Escenario` · `FK_TmpProyectoGrupo_Grupos` | `Grupos` ·608 |
|   | `Usuario` · `FK_TmpProyectoGrupo_Grupos` | `Grupos` ·608 |
| `TmpProyectoObreroPapeleta` ·0 | — | — |
| `TmpProyectoObreroPapeletaDetalle` ·0 | — | — |
| `TmpProyectoPresupuestoEquivalencia` ·0 | — | — |
| `TmpProyectoRO` ·0 | — | — |
| `TmpRDocumento` ·0 | — | — |
| `TmpRDocumento01S10` ·0 | — | — |
| `TmpRDocumento01S10Comprobante` ·0 | — | — |
| `TmpRDocumento01S10Detalle` ·0 | — | — |
| `TmpRDocumento01S10DetalleCronograma` ·0 | — | — |
| `TmpRDocumentoCronograma` ·0 | — | — |
| `TmpRDocumentoDetalleCuenta` ·0 | — | — |
| `TmpRDocumentoDetalleIndicadorGestion` ·212 | — | — |
| `TmpRDocumentoPlanillaPagoDesdoble` ·0 | — | — |
| `TmpRDocumentoTransferencias` ·0 | — | — |
| `TmpRatioConsumo` ·0 | — | — |
| `TmpRecurso` ·0 | — | — |
| `TmpRecursoNRO` ·0 | — | — |
| `TmpRecursoProveedor` ·0 | — | — |
| `TmpRecursoStock` ·1 | — | — |
| `TmpRecursosOrdenCompra` ·0 | — | — |
| `TmpRefleja` ·0 | — | — |
| `TmpRegistroCompra_0801` ·0 | — | — |
| `TmpRegistroCompra_0802` ·0 | — | — |
| `TmpRegistroCompra_1301` ·0 | — | — |
| `TmpRegistroCompra_1401` ·0 | — | — |
| `TmpRegularizacion` ·5 | — | — |
| `TmpReporteActaCabecera` ·0 | — | — |
| `TmpReporteActaDetalle` ·0 | — | — |
| `TmpReporteActivoInsumosDetalle` ·0 | — | — |
| `TmpReporteAnalisisPrecioUnitario` ·3,797 | — | — |
| `TmpReporteAnticiposReembolsos` ·0 | — | — |
| `TmpReporteBalanceComprobacion` ·0 | — | — |
| `TmpReporteBalanceGeneral` ·0 | — | — |
| `TmpReporteBalanceGeneral14Columnas` ·0 | — | — |
| `TmpReporteBoletasPago` ·0 | — | — |
| `TmpReporteBoletasPagoFormato` ·0 | — | — |
| `TmpReporteCalificacionPersonalFormato` ·0 | — | — |
| `TmpReporteCalificacionPersonalResumen` ·0 | — | — |
| `TmpReporteConsolidadoAnalisis` ·0 | — | — |
| `TmpReporteConsolidadoConcepto` ·0 | — | — |
| `TmpReporteConsolidadoNominaTabulado` ·0 | — | — |
| `TmpReporteDEInsumosDetalle` ·0 | — | — |
| `TmpReporteEstadoFinanciero` ·0 | — | — |
| `TmpReporteFondoRotatorio` ·0 | — | — |
| `TmpReporteFrenteInsumosDetalle` ·0 | — | — |
| `TmpReporteInsumoClasificacionDetalle` ·0 | — | — |
| `TmpReporteInventarioBalance` ·0 | — | — |
| `TmpReporteKardexResumen` ·0 | — | — |
| `TmpReporteKardexResumenGeneral` ·0 | — | — |
| `TmpReporteLibroCaja` ·0 | — | — |
| `TmpReporteLibroMayor` ·0 | — | — |
| `TmpReporteLibroMayorComparativo` ·0 | — | — |
| `TmpReporteLibroMayorGeneral` ·0 | — | — |
| `TmpReporteLibroMayorMovimientos` ·0 | — | — |
| `TmpReporteMayorAnalitico` ·0 | — | — |
| `TmpReportePCInsumosDetalle` ·13 | — | — |
| `TmpReporteParteMObra` ·0 | — | — |
| `TmpReporteParteMultiproyecto` ·0 | — | — |
| `TmpReportePersonaInsumosDetalle` ·0 | — | — |
| `TmpReporteRecursoControlDetalle` ·0 | — | — |
| `TmpReporteRecursoDeduccion` ·0 | — | — |
| `TmpReporteResumenConciliacion` ·0 | — | — |
| `TmpReporteResumenTipoMovimiento` ·0 | — | — |
| `TmpReporteSaldosPorCentroDeCosto` ·0 | — | — |
| `TmpReporteSaldosPorCuenta` ·0 | — | — |
| `TmpReporteTalonario` ·0 | — | — |
| `TmpReporteTotalesRecursoGuia` ·0 | — | — |
| `TmpReporteUOInsumosDetalle` ·0 | — | — |
| `TmpReporteVistaNomina` ·0 | — | — |
| `TmpReporteVistaNominaAcumulado` ·0 | — | — |
| `TmpReporteVistaNominaTabulado` ·0 | — | — |
| `TmpReporteVistaNominaTabuladoColumna` ·0 | — | — |
| `TmpReporteVoucher` ·2 | — | — |
| `TmpResumenValorizacion` ·0 | — | — |
| `TmpRubroContable` ·0 | — | — |
| `TmpSLR` ·0 | — | — |
| `TmpSaldoListadoAnalitico` ·0 | — | — |
| `TmpSaldoListadoComparativoAnio` ·0 | — | — |
| `TmpSaldoListadoComparativoMes` ·0 | — | — |
| `TmpSaldoListadoGeneral` ·0 | — | — |
| `TmpSeguimiento` ·0 | — | — |
| `TmpStockAnterior` ·0 | — | — |
| `TmpStockFecha` ·0 | — | — |
| `TmpStockKardexDetalle` ·0 | — | — |
| `TmpStockKardexDetalleGeneral` ·0 | — | — |
| `TmpSubcontratoEquipoSemana` ·0 | — | — |
| `TmpSubpartidas` ·50 | — | — |
| `TmpTareoMultiproyectoPeriodo` ·0 | — | — |
| `TmpTecho` ·43 | — | — |
| `TmpTerminal` ·0 | — | — |
| `TmpTrazabilidad` ·0 | — | — |
| `TmpTributo` ·0 | — | — |
| `TmpValorizacionPartidaDeControl` ·6 | — | — |
| `Tmp_CuentaLS` ·0 | — | — |
| `Tmp_LibroSimplificado` ·0 | — | — |
| `Tmp_OB_DetalleAsignacion` ·5 | — | — |
| `Tmp_Pago` ·0 | — | — |
| `Tmp_PagoDetalle` ·0 | — | — |
| `Tmp_Procesar_AsientoContable_Liquidacion` ·0 | — | — |
| `Tmp_Procesar_AsientoContable_Liquidacion_Doc` ·0 | — | — |
| `Tmp_RregistroCompras` ·451 | — | — |
| `tmpAC_Documento` ·0 | — | — |
| `tmpAC_DocumentoAsiento` ·0 | — | — |
| `tmpAC_PagoNomina` ·0 | — | — |
| `tmpAC_PagoNominaDocumento` ·0 | — | — |
| `tmpEstructura` ·0 | — | — |
| `tmpProyectoEquipoParte` ·0 | — | — |
| `tmpValidaDocumentosElectDB` ·0 | — | — |
| `tmp_script_actualizadata` ·0 | — | — |

---

## M15 · Metadatos del sistema

`[V]` 66 tablas · 19 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Actualizacion` ·586 | — | — |
| `ActualizacionDatos` ·1 | — | — |
| `ActualizacionDetalle` ·63,831 | — | — |
| `ActualizacionSesion` ·21 | — | — |
| `Agrupacion` ·11 | — | — |
| `AgrupacionTabla` ·406 | — | — |
| `CampoDelReporte` ·1,043 | — | — |
| `CampoListaEspecificacion` ·4 | — | — |
| `CampoListaEspecificacionDetalle` ·20 | `NroCampoListaEspecificacion` · `FK_CampoListaEspecificacionDetalle_CampoListaEspecificacion` | `CampoListaEspecificacion` ·4 |
| `CamposVisibles` ·0 | — | — |
| `CatalogoAsignacionContable` ·0 | `NroCuentaGenerica` · `FK_CatalogoAsignacionContable_CuentaGenerica` | `CuentaGenerica` ·80 |
|   | `NroEsquemaContable` · `FK_CatalogoAsignacionContable_EsquemaContable` | `EsquemaContable` ·1 |
|   | `NroPlanContableDetalle` · `FK_CatalogoAsignacionContable_PlanContableDetalle` | `PlanContableDetalle` ·5,063 |
| `CatalogoAsignacionContableAuditoria` ·0 | — | — |
| `CatalogoConfiguracion` ·453 | — | — |
| `CatalogoConfiguracion2` ·872 | — | — |
| `CatalogoDimension` ·0 | — | — |
| `Escenario` ·1,074 | — | — |
| `EscenarioBarra` ·60 | — | — |
| `EscenarioControlActualizacion` ·15 | — | — |
| `EscenarioDimension` ·25 | — | — |
| `EscenarioRegistro` ·3 | — | — |
| `EscenariosUnicos` ·537 | — | — |
| `EstructuraCodificacion` ·1,307 | — | — |
| `EstructuraItem` ·127 | `CodPresupuesto` · `FK_EstructuraItem_Presupuesto` | `Presupuesto` ·44 |
| `EstructuraItemGeneral` ·10 | — | — |
| `EstructuraSubpartida` ·127 | `CodPresupuesto` · `FK_EstructuraSubpartida_Presupuesto` | `Presupuesto` ·44 |
| `EstructuraSubpartidaGeneral` ·10 | — | — |
| `EstructuraTareaGeneral` ·6 | — | — |
| `Filtro` ·0 | `CodProyecto` · `FK_Filtro_Proyecto` | `Proyecto` ·234 |
| `FiltroDetalle` ·0 | `CodFiltro` · `FK_FiltroDetalle_Filtro` | `Filtro` ·0 |
|   | `CodProyecto` · `FK_FiltroDetalle_Filtro` | `Filtro` ·0 |
| `FiltroDetalleSuma` ·1,436 | — | — |
| `FiltroDetalleSumaRecurso` ·6,267 | `CodFiltro` · `FK_FiltroDetalleSumaRecurso_FiltroDetalleSuma` | `FiltroDetalleSuma` ·1,436 |
|   | `CodPeriodo` · `FK_FiltroDetalleSumaRecurso_FiltroDetalleSuma` | `FiltroDetalleSuma` ·1,436 |
|   | `CodProyecto` · `FK_FiltroDetalleSumaRecurso_FiltroDetalleSuma` | `FiltroDetalleSuma` ·1,436 |
|   | `CodTarea` · `FK_FiltroDetalleSumaRecurso_FiltroDetalleSuma` | `FiltroDetalleSuma` ·1,436 |
| `FiltroVista` ·0 | — | — |
| `FiltroVistaCuenta` ·0 | — | — |
| `GestorPermisos` ·532 | — | — |
| `IndiceCopiaDeSeguridad` ·327 | — | — |
| `IndicePorTabla` ·0 | — | — |
| `IndiceUnificado` ·85 | — | — |
| `IndiceUnificadoAreas` ·13 | — | — |
| `IndiceUnificadoGrupo` ·1 | — | — |
| `Procedimiento` ·2,614 | — | — |
| `ReporteResumenSubcontrato` ·0 | — | — |
| `ReporteValorizacion` ·0 | — | — |
| `ReporteValorizacionDetalle` ·28 | — | — |
| `SysCampo` ·254 | — | — |
| `SysCampoUsuario` ·184 | — | — |
| `Syscatalogo` ·637 | — | — |
| `Tabla` ·584 | — | — |
| `TablaCalidad` ·4 | — | — |
| `TablaPrincipalLog` ·0 | — | — |
| `TablaPrincipalLog_ConfigS10` ·944 | — | — |
| `TablaPrincipalLog_ConfigS10_BK` ·668 | — | — |
| `TablaReglas` ·0 | — | — |
| `TablaTablas` ·17,070 | — | — |
| `TablaTablasS10` ·0 | — | — |
| `TableroPC` ·0 | — | — |
| `TableroRecurso` ·1,164 | — | — |
| `TableroUN` ·0 | — | — |
| `TableroUnidadNegocio` ·0 | — | — |
| `VistaAsistencia` ·0 | `NroEsquemaPlanilla` · `FK_VistaAsistencia_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `VistaAsistenciaConcepto` ·0 | `NroConcepto` · `FK_VistaAsistenciaConcepto_ConceptoGenerico` | `ConceptoGenerico` ·264 |
|   | `NroVistaAsistencia` · `FK_VistaAsistenciaConcepto_VistaAsistencia` | `VistaAsistencia` ·0 |
| `VistaNomina` ·10 | `NroEsquemaPlanilla` · `FK_VistaNomina_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `VistaNominaConcepto` ·132 | `NroConcepto` · `FK_VistaNominaConcepto_Concepto` | `Concepto` ·982 |
|   | `NroVistaNomina` · `FK_VistaNominaConcepto_VistaNomina` | `VistaNomina` ·10 |
| `sysDecimalesBase` ·0 | — | — |
| `sysMenu` ·0 | — | — |
| `sysdiagrams` ·0 | — | — |
| `sysparametros` ·113 | — | — |

---

## ZZ · Sin clasificar

`[V]` 393 tablas · 258 relaciones salientes

| Tabla · filas | Relación (columna · restricción) | Tabla referenciada · filas |
|---|---|---|
| `Accion` ·4 | — | — |
| `Acta` ·0 | `CodProyecto` · `FK_Acta_Proyecto` | `Proyecto` ·234 |
|   | `NroTipoActa` · `FK_Acta_TipoActa` | `TipoActa` ·3 |
| `ActaAcuerdo` ·0 | `NroActa` · `FK_ActaAcuerdo_Acta` | `Acta` ·0 |
|   | `CodProyectoResponsable` · `FK_ActaAcuerdo_Responsable` | `Responsable` ·0 |
|   | `CodResponsable` · `FK_ActaAcuerdo_Responsable` | `Responsable` ·0 |
|   | `NroRestriccion` · `FK_ActaAcuerdo_Restriccion` | `Restriccion` ·0 |
| `ActaCorreo` ·0 | `NroActa` · `FK_ActaCorreo_Acta` | `Acta` ·0 |
| `ActaIdentificador` ·0 | — | — |
| `ActaResponsable` ·0 | `NroActa` · `FK_ActaResponsable_Acta` | `Acta` ·0 |
|   | `CodProyectoResponsable` · `FK_ActaResponsable_Responsable` | `Responsable` ·0 |
|   | `CodResponsable` · `FK_ActaResponsable_Responsable` | `Responsable` ·0 |
| `ActualizaSatelite` ·0 | — | — |
| `Adelanto` ·0 | — | — |
| `AdelantoEfectivo` ·0 | — | — |
| `AdelantoEfectivoAmortizacion` ·0 | `NroAdelantoEfectivo` · `FK_AdelantoEfectivoAmortizacion_AdelantoEfectivo` | `AdelantoEfectivo` ·0 |
| `AdelantoEfectivoDeduccion` ·0 | `NroAdelantoEfectivo` · `FK_AdelantoEfectivoDeduccion_AdelantoEfectivo` | `AdelantoEfectivo` ·0 |
| `AdelantoInsumo` ·0 | `CodAdelanto` · `FK__AdelantoInsumo__34AAD882` | `Adelanto` ·0 |
|   | `CodProyecto` · `FK__AdelantoInsumo__34AAD882` | `Adelanto` ·0 |
|   | `CodInsumo` · `FK__AdelantoI__CodIn__33B6B449` | `Insumo` ·10,254 |
| `AdelantoMaterial` ·0 | `CodProyecto` · `FK_AdelantoMaterial_Proyecto` | `Proyecto` ·234 |
|   | `CodPresupuesto` · `FK_AdelantoMaterial_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_AdelantoMaterial_Subpresupuesto` | `Subpresupuesto` ·33 |
| `AdelantoMaterialAmortizacion` ·0 | `NroAMD` · `FK_AdelantoMaterialAmortizacion_AdelantoMaterialDetalle` | `AdelantoMaterialDetalle` ·0 |
|   | `CodPeriodo` · `FK_AdelantoMaterialAmortizacion_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoPeriodo` · `FK_AdelantoMaterialAmortizacion_Periodo` | `Periodo` ·34,066 |
| `AdelantoMaterialDeduccion` ·0 | — | — |
| `AdelantoMaterialDetalle` ·0 | `NroAM` · `FK_AdelantoMaterialDetalle_AdelantoMaterial` | `AdelantoMaterial` ·0 |
| `AdelantoMaterialPartida` ·0 | `NroAMD` · `FK_AdelantoMaterialPartida_AdelantoMaterialDetalle` | `AdelantoMaterialDetalle` ·0 |
|   | `CodPresupuesto` · `FK_AdelantoMaterialPartida_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `CodSubpresupuesto` · `FK_AdelantoMaterialPartida_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
|   | `Item` · `FK_AdelantoMaterialPartida_SubpresupuestoDetalle` | `SubpresupuestoDetalle` ·6,675 |
| `Aduana` ·30 | — | — |
| `AgentePercepcion` ·0 | — | — |
| `AgenteRetencion` ·0 | — | — |
| `Alerta` ·37 | — | — |
| `Aplicabilidad` ·0 | — | — |
| `AppActivacion` ·0 | — | — |
| `AppEmpresa` ·0 | — | — |
| `AppMovil` ·0 | — | — |
| `AppMovilDetail` ·0 | — | — |
| `Arbol` ·13 | — | — |
| `ArbolCentroCompra` ·1 | — | — |
| `ArbolCompras` ·0 | — | — |
| `ArbolEmpresaHistorial` ·0 | — | — |
| `ArbolPreciosReporte` ·0 | — | — |
| `ArbolProyecto` ·6 | — | — |
| `ArchivoAyuda` ·0 | — | — |
| `ArchivoAyudaEscenario` ·0 | `NroArchivoAyuda` · `FkNroArchivoAyudaArchivoAyudaEscenario` | `ArchivoAyuda` ·0 |
|   | `NroEscenarioUnico` · `FkNroEscenarioUnicoArchivoAyudaEscenario` | `EscenariosUnicos` ·537 |
| `Area` ·0 | — | — |
| `AsignacionProtocoloCalidad` ·0 | — | — |
| `Auditoria` ·0 | — | — |
| `AuditoriaFileStorage` ·20 | — | — |
| `BI_Calendario` ·0 | — | — |
| `BI_CalendarioFC` ·0 | — | — |
| `BI_Empresa` ·0 | — | — |
| `BI_EmpresaDP` ·1 | — | — |
| `BI_EmpresaFC` ·0 | — | — |
| `BI_FiltroTablero` ·0 | — | — |
| `BI_FlujoCajaDiarioGeneral` ·0 | — | — |
| `BI_FlujoCajaDiarioPCG` ·0 | — | — |
| `BI_Gasto` ·0 | `CodEmpresa` · `FK_BI_Gasto_BI_Empresa` | `BI_Empresa` ·0 |
|   | `CodIdentificador` · `FK_BI_Gasto_BI_Identificador` | `BI_Identificador` ·0 |
|   | `CodInsumo` · `FK_BI_Gasto_BI_Insumo` | `BI_Insumo` ·0 |
|   | `CodProyecto` · `FK_BI_Gasto_BI_Proyecto` | `BI_Proyecto` ·0 |
|   | `CodFormaDePago` · `FK_BI_Gasto_FormaDePago` | `FormaDePago` ·32 |
| `BI_GastoFamilia` ·0 | `CodIdentificador` · `FK_BI_GastoFamilia_BI_Identificador` | `BI_Identificador` ·0 |
|   | `CodInsumo` · `FK_BI_GastoFamilia_BI_Insumo` | `BI_Insumo` ·0 |
|   | `CodProyecto` · `FK_BI_GastoFamilia_BI_Proyecto` | `BI_Proyecto` ·0 |
| `BI_GastoPartidaControlGeneral` ·0 | — | — |
| `BI_GastoPartidaDeControl` ·0 | `CodPartidaDeControl` · `FK_BI_GastoPartidaDeControl_BI_PartidaDeControl` | `BI_PartidaDeControl` ·0 |
|   | `CodProyectoPC` · `FK_BI_GastoPartidaDeControl_BI_PartidaDeControl` | `BI_PartidaDeControl` ·0 |
| `BI_Identificador` ·0 | — | — |
| `BI_Insumo` ·0 | — | — |
| `BI_InsumoFC` ·0 | — | — |
| `BI_LucroMargen` ·8 | — | — |
| `BI_PartidaDeControl` ·0 | — | — |
| `BI_Proyecto` ·0 | — | — |
| `BI_ProyectoDP` ·5 | — | — |
| `BI_ProyectoFC` ·0 | — | — |
| `BI_ProyectoVectorDP` ·0 | — | — |
| `BI_Proyecto_Calendario` ·1 | — | — |
| `BI_RDocumento_Proveedor` ·11 | — | — |
| `BI_RDocumento_Proveedor_Detalle` ·14 | — | — |
| `BI_SDP_Calendario` ·0 | — | — |
| `BI_SDP_Empresa` ·0 | — | — |
| `BI_SDP_Proveedor` ·0 | — | — |
| `BI_SDP_Proyecto` ·0 | — | — |
| `BI_SDP_RDocumento` ·0 | — | — |
| `BI_SDP_Retraso` ·0 | — | — |
| `BibliotecaConfiguracion` ·1 | — | — |
| `Bono` ·0 | — | — |
| `CabeceraPieValorizacion` ·0 | — | — |
| `CabeceraReporte` ·0 | `CodPresupuesto` · `FK_CabeceraReporte_Presupuesto` | `Presupuesto` ·44 |
| `CalculoK` ·0 | `CodPresupuesto` · `FK_CalculoK_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_CalculoK_Subpresupuesto` | `Subpresupuesto` ·33 |
| `Calificacion` ·0 | — | — |
| `CalificacionCriterio` ·0 | — | — |
| `CalificacionCuentaNomina` ·0 | — | — |
| `CalificacionEscala` ·0 | — | — |
| `CalificacionGYM` ·0 | — | — |
| `CambioInmuebleRecursos` ·0 | — | — |
| `CambioTitularidad` ·0 | — | — |
| `Canal` ·0 | — | — |
| `CapacidadPropuesta` ·0 | — | — |
| `CarpetaPersonal` ·0 | — | — |
| `CarreterasProvias` ·0 | — | — |
| `Casilla` ·0 | `NroEsquemaContable` · `FK_Casilla_EsquemaContable` | `EsquemaContable` ·1 |
| `CasillaGenerico` ·9 | — | — |
| `CasillaTemporal` ·0 | — | — |
| `CasillaTemporalDetalle` ·0 | — | — |
| `Categoria` ·7 | — | — |
| `CategoriaTipoDocumento` ·0 | — | — |
| `CausaIncumplimiento` ·33 | — | — |
| `CentralizacionUsuarios` ·0 | — | — |
| `CertificadoAcceso` ·0 | — | — |
| `Chat` ·0 | — | — |
| `ComparativoContabilidadRO` ·0 | — | — |
| `ComponenteCAD` ·0 | — | — |
| `ConsolidadoValorizacion` ·0 | — | — |
| `Contacto` ·0 | `CodTratamiento` · `FK_Contacto_Tratamiento` | `Tratamiento` ·6 |
|   | `CodLugar` · `FK_Contacto_UbicacionGeografica` | `UbicacionGeografica` ·2,115 |
| `ControlCambioActividad` ·0 | `CodPartida` · `FK__ControlCambioAct__6A463876` | `Partida` ·9,113 |
|   | `CodPartida` · `FK__ControlCambioAct__6952143D` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK__ControlCambioAct__6952143D` | `Partida` ·9,113 |
|   | `CodPresupuestoHistorico` · `FK__ControlCambioAct__6A463876` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK__ControlCambioAct__6952143D` | `Partida` ·9,113 |
|   | `PropioPartidaHistorico` · `FK__ControlCambioAct__6A463876` | `Partida` ·9,113 |
|   | `CodProyecto` · `FK__ControlCa__CodPr__685DF004` | `Proyecto` ·234 |
| `Corporacion` ·0 | — | — |
| `CorporacionAplicacion` ·0 | — | — |
| `CorporacionAplicacionAuditoria` ·0 | — | — |
| `CorporacionDetalle` ·0 | — | — |
| `CorporacionProyecto` ·0 | — | — |
| `CorporacionProyectoNube` ·0 | — | — |
| `CorreoMasivo` ·6 | — | — |
| `CorreoMasivoReg` ·0 | — | — |
| `Costos2KUser` ·0 | — | — |
| `CriterioHorasExtras` ·0 | `CodProyecto` · `FK_CriterioHorasExtras_Proyecto` | `Proyecto` ·234 |
| `CriterioHorasExtrasDetalle` ·0 | `CodCriterio` · `FK_CriterioHorasExtrasDetalle_CriterioHorasExtras` | `CriterioHorasExtras` ·0 |
|   | `CodProyecto` · `FK_CriterioHorasExtrasDetalle_CriterioHorasExtras` | `CriterioHorasExtras` ·0 |
| `Cubo` ·0 | — | — |
| `CuboCampo` ·0 | — | — |
| `CuboUsuario` ·0 | — | — |
| `CuestionarioCriterio` ·0 | `NroTipoCalificacionEscala` · `FK_CuestionarioCriterio_TipoCalificacionEscala` | `TipoCalificacionEscala` ·0 |
| `CuestionarioCriterioCalificacion` ·0 | `NroCuestionarioCriterio` · `FK_CuestionarioCriterioCalificacion_CuestionarioCriterio` | `CuestionarioCriterio` ·0 |
| `CuestionarioEvaluacion` ·0 | — | — |
| `CuestionarioEvaluacionDetalle` ·0 | `NroCuestionarioCriterio` · `FK_CuestionarioEvaluacionDetalle_CuestionarioCriterio` | `CuestionarioCriterio` ·0 |
|   | `NroCuestionarioEvaluacion` · `FK_CuestionarioEvaluacionDetalle_CuestionarioEvaluacion` | `CuestionarioEvaluacion` ·0 |
| `Cupones` ·0 | — | — |
| `CuponesDestinatariosAlertas` ·0 | — | — |
| `DatoDynamics` ·0 | — | — |
| `DatoDynamicsProgramacion` ·0 | — | — |
| `DctoIngresoEgreso` ·2 | `CodMovimiento` · `FK_DctoIngresoEgreso_GuiaAlmacenMovimiento` | `GuiaAlmacenMovimiento` ·55 |
|   | `CodDocumento` · `FK_DctoIngresoEgreso_TipoDocumento` | `TipoDocumento` ·66 |
| `DeclaracionDeterminativa` ·0 | — | — |
| `DestinoEspecifico` ·0 | `CodProyecto` · `FK_DestinoEspecifico_Proyecto` | `Proyecto` ·234 |
| `DestinoEspecificoGeneral` ·0 | — | — |
| `DestinoEspecificoMatriz` ·0 | — | — |
| `DestinoEspecificoProyectoEtiqueta` ·0 | `CodDestinoEspecifico` · `FK_DestinoEspecificoProyectoEtiqueta_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodProyecto` · `FK_DestinoEspecificoProyectoEtiqueta_DestinoEspecifico` | `DestinoEspecifico` ·0 |
|   | `CodEtiqueta` · `FK_DestinoEspecificoProyectoEtiqueta_ProyectoEtiqueta` | `ProyectoEtiqueta` ·0 |
|   | `CodProyecto` · `FK_DestinoEspecificoProyectoEtiqueta_ProyectoEtiqueta` | `ProyectoEtiqueta` ·0 |
| `DestinoEspecificoProyectoEtiquetaAccion` ·0 | `CodDestinoEspecifico` · `FK_DestinoEspecificoProyectoEtiquetaAccion_DestinoEspecificoProyectoEtiqueta` | `DestinoEspecificoProyectoEtiqueta` ·0 |
|   | `CodEtiqueta` · `FK_DestinoEspecificoProyectoEtiquetaAccion_DestinoEspecificoProyectoEtiqueta` | `DestinoEspecificoProyectoEtiqueta` ·0 |
|   | `CodProyecto` · `FK_DestinoEspecificoProyectoEtiquetaAccion_DestinoEspecificoProyectoEtiqueta` | `DestinoEspecificoProyectoEtiqueta` ·0 |
|   | `CodAccion` · `FK_DestinoEspecificoProyectoEtiquetaAccion_ProyectoAccion` | `ProyectoAccion` ·0 |
|   | `CodProyecto` · `FK_DestinoEspecificoProyectoEtiquetaAccion_ProyectoAccion` | `ProyectoAccion` ·0 |
| `DiaNoLaborable` ·0 | `CodCalendario` · `FK_DiaNoLaborable_Calendario` | `Calendario` ·33 |
|   | `CodProyecto` · `FK_DiaNoLaborable_Calendario` | `Calendario` ·33 |
| `DiagramaCambio` ·0 | — | — |
| `Division` ·0 | — | — |
| `DocPorPagarEmpresaRobot` ·0 | — | — |
| `EntregaInmueble` ·0 | — | — |
| `EntregaInmuebleRecursos` ·0 | — | — |
| `EnvioMateriales` ·0 | `CodPresupuesto` · `FK_EnvioMateriales_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_EnvioMateriales_Subpresupuesto` | `Subpresupuesto` ·33 |
| `EnvioMaterialesDetalle` ·0 | `NroEM` · `FK_EnvioMaterialesDetalle_EnvioMateriales` | `EnvioMateriales` ·0 |
|   | `CodInsumo` · `FK_EnvioMaterialesDetalle_Insumo` | `Insumo` ·10,254 |
| `EnvioPedido` ·0 | `NroPedido` · `FK_EnvioPedido_Pedido` | `Pedido` ·9 |
| `Envios` ·0 | — | — |
| `EquivalenciaInsumoS10Costos` ·0 | `CodInsumoS10` · `FK_EquivalenciaInsumoS10Costos_Insumo` | `Insumo` ·10,254 |
| `ErrorDeImportacion` ·0 | — | — |
| `EspecificacionDinamica` ·2 | — | — |
| `EspecificacionDinamicaDetalle` ·11 | `NroEspecificacionDinamica` · `FK_EspecificacionDinamicaDetalle_EspecificacionDinamica` | `EspecificacionDinamica` ·2 |
| `EstiloAscendente` ·3 | — | — |
| `Etiqueta` ·0 | — | — |
| `EvaluacionCaracteristica` ·72 | — | — |
| `EvaluacionCaracteristicaEspecificacion` ·37 | `NroEspecificacionDinamica` · `FK_EvaluacionCaracteristicaEspecificacion_EspecificacionDinamica` | `EspecificacionDinamica` ·2 |
| `EvaluacionCaracteristicaObservacion` ·0 | — | — |
| `EvaluacionCaracteristicaObservacionEliminacion` ·0 | — | — |
| `EvaluacionCaracteristicaObservacionHistory` ·0 | — | — |
| `EvaluacionCaracteristicaValorDinamica` ·0 | `NroEvaluacionCaracteristicaEspecificacion` · `FK_EvaluacionCaracteristicaEspecificacion` | `EvaluacionCaracteristicaEspecificacion` ·37 |
| `EvaluacionCaracteristicaValorDinamicaDetalle` ·0 | `NroCampoListaEspecificacionDetalle` · `FK_CampoListaEspecificacionDetalle` | `CampoListaEspecificacionDetalle` ·20 |
|   | `NroEspecificacionDinamicaDetalle` · `FK_EspecificacionDinamicaDetalle` | `EspecificacionDinamicaDetalle` ·11 |
|   | `NroEvaluacionCaracteristicaValorDinamica` · `FK_EvaluacionCaracteristicaValorDinamica` | `EvaluacionCaracteristicaValorDinamica` ·0 |
| `FactorDeReajuste` ·0 | — | — |
| `Fase` ·0 | — | — |
| `Favorito` ·2 | — | — |
| `FavoritoColor` ·16 | — | — |
| `FavoritoDetalle` ·14 | `CodFavorito` · `FK_FavoritoDetalle_Favorito` | `Favorito` ·2 |
|   | `CodPartida` · `FK_FavoritoDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_FavoritoDetalle_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_FavoritoDetalle_Partida` | `Partida` ·9,113 |
|   | `CodTitulo` · `FK_FavoritoDetalle_Titulo` | `Titulo` ·390 |
| `FavoritoItemDeControl` ·1 | — | — |
| `FavoritoItemDeControlDetalle` ·4 | `CodFavoritoItemDeControl` · `FK_FavoritoItemDeControlDetalle_FavoritoItemDeControl` | `FavoritoItemDeControl` ·1 |
|   | `Usuario` · `FK_FavoritoItemDeControlDetalle_FavoritoItemDeControl` | `FavoritoItemDeControl` ·1 |
|   | `CodItemDeControl` · `FK_FavoritoItemDeControlDetalle_ItemDeControl` | `ItemDeControl` ·10 |
| `Fecha` ·0 | — | — |
| `FileStorage` ·0 | — | — |
| `Filtrado` ·0 | — | — |
| `FiltradoGrupo` ·31 | — | — |
| `Flujo` ·0 | `CodUnidadOperativaOrigen` · `FK_Flujo_UnidadOperativa` | `UnidadOperativa` ·13 |
| `FlujoCajaDocumento` ·0 | — | — |
| `FlujoCajaDocumentoRecurso` ·0 | — | — |
| `FlujoEconomico` ·0 | — | — |
| `FlujoTrabajo` ·0 | `IdFlujoTrabajoPredeterminado` · `FK_FlujoTrabajo_FlujoTrabajoPredeterminado` | `FlujoTrabajoPredeterminado` ·0 |
|   | `IdTablaCondicion` · `FK_FlujoTrabajo_TablaTablasS10` | `TablaTablasS10` ·0 |
|   | `IdTablaEstado` · `FK_FlujoTrabajo_TablaTablasS101` | `TablaTablasS10` ·0 |
|   | `IdTablaSituacion` · `FK_FlujoTrabajo_TablaTablasS102` | `TablaTablasS10` ·0 |
|   | `IdUnidadOperativaRolUsuario` · `FK_FlujoTrabajo_UnidadOperativaRolUsuario` | `UnidadOperativaRolUsuario` ·0 |
| `FlujoTrabajoCarpetaPersonal` ·0 | `IdCarpetaPersonal` · `FK_FlujoTrabajoCarpetaPersonal_CarpetaPersonal` | `CarpetaPersonal` ·0 |
|   | `IdFlujoTrabajo` · `FK_FlujoTrabajoCarpetaPersonal_FlujoTrabajo` | `FlujoTrabajo` ·0 |
| `FlujoTrabajoIdentificador` ·0 | `IdFlujoTrabajo` · `FK_FlujoTrabajoIdentificador_FlujoTrabajo` | `FlujoTrabajo` ·0 |
| `FlujoTrabajoPredeterminado` ·0 | `IdUnidadOperativa` · `FK_FlujoTrabajoPredeterminado_OrganizacionUnidadOperativa` | `OrganizacionUnidadOperativa` ·0 |
| `FormaDeFacturacion` ·0 | — | — |
| `FormaDePago` ·32 | — | — |
| `FormaDePagoDetalle` ·8 | `CodFormaDePago` · `FK_FormaDePagoDetalle_FormaDePago` | `FormaDePago` ·32 |
| `FormaDePagoValorizacion` ·0 | `CodMoneda` · `FK_FormaDePagoValorizacion_Moneda` | `Moneda` ·2 |
| `FrecuenciaPago` ·0 | — | — |
| `FuncionEPP` ·0 | — | — |
| `FuncionEPPDetalle` ·0 | `NroFuncionEPP` · `FK_FuncionEPPDetalle_FuncionEPP` | `FuncionEPP` ·0 |
|   | `CodInsumo` · `FK_FuncionEPPDetalle_Insumo` | `Insumo` ·10,254 |
| `Gasto` ·122 | `CodUnidad` · `FK_Gasto_Unidad` | `Unidad` ·83 |
| `GastoEquipo` ·0 | — | — |
| `GastoEquipoDetalle` ·0 | — | — |
| `GastoPresupuesto` ·0 | `CodGasto` · `FK_GastoPresupuesto_Gasto` | `Gasto` ·122 |
|   | `CodPresupuesto` · `FK_GastoPresupuesto_Presupuesto` | `Presupuesto` ·44 |
| `GastoPresupuestoDetalle` ·0 | `CodMoneda` · `FK_GastoPresupuestoDetalle_Moneda` | `Moneda` ·2 |
| `Gaveta` ·0 | — | — |
| `GrillaBarra` ·0 | — | — |
| `HISTORIAL` ·0 | — | — |
| `HistorialCambiosCarpeta` ·4 | — | — |
| `HistorialSatelite` ·0 | — | — |
| `Inconsistencias` ·0 | — | — |
| `InconsistenciasContabilidad` ·0 | — | — |
| `KeyDisk` ·0 | — | — |
| `KeyDiskLicencia` ·0 | — | — |
| `KeyDiskRecurso` ·0 | — | — |
| `Keylok` ·0 | — | — |
| `KeylokAlquiler` ·0 | — | — |
| `KeylokRecurso` ·0 | — | — |
| `KeylokRecursoFacturas` ·0 | `CodKeylok` · `FK_KeylokRecursoFacturas_KeylokRecurso` | `KeylokRecurso` ·0 |
|   | `CodRecurso` · `FK_KeylokRecursoFacturas_KeylokRecurso` | `KeylokRecurso` ·0 |
| `Lectora` ·0 | `NroTerminal` · `FkNroTerminalLectora` | `Terminal` ·0 |
| `LineaBalanceCarpeta` ·0 | — | — |
| `LineaBalanceConfiguracion` ·0 | — | — |
| `LinkBaseNubeApp` ·0 | — | — |
| `LugarDeEnvio` ·5 | — | — |
| `LugarDeEnvioAgrupacion` ·13 | — | — |
| `LugarDeEnvioReglas` ·0 | — | — |
| `Marca` ·0 | — | — |
| `MatrizConceptoConfiguracion` ·1 | `NroConcepto` · `FK_MatrizConceptoConfiguracion_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_MatrizConceptoConfiguracion_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
| `MatrizConceptoValor` ·0 | `NroConcepto` · `FK_MatrizConceptoValor_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_MatrizConceptoValor_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_MatrizConceptoValor_Proyecto` | `Proyecto` ·234 |
| `MatrizConceptoValorHistorico` ·0 | `NroConcepto` · `FK_MatrizConceptoValorHistorico_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_MatrizConceptoValorHistorico_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `CodProyecto` · `FK_MatrizConceptoValorHistorico_Proyecto` | `Proyecto` ·234 |
| `Mensaje` ·0 | — | — |
| `MensajeArchivoBinario` ·0 | — | — |
| `MensajeAuditoria` ·0 | — | — |
| `MensajePaquetesEnvio` ·0 | — | — |
| `Mes` ·0 | — | — |
| `MetodoPago` ·3 | — | — |
| `Modalidad` ·0 | — | — |
| `Modelo` ·0 | `NroMarca` · `FK_Modelo_Marca` | `Marca` ·0 |
|   | `NroTipoEquipo` · `FK_Modelo_TipoEquipo` | `TipoEquipo` ·0 |
| `ModeloCarga` ·0 | — | — |
| `MotivoPago` ·4 | — | — |
| `Ocupacion` ·146 | — | — |
| `OptimizedViewColumns` ·0 | — | — |
| `OptimizedViewJoins` ·0 | — | — |
| `OptimizedViewJoins_ConditionColumns` ·0 | — | — |
| `OptimizedViewJoins_Query` ·0 | — | — |
| `OptimizedViewJoins_QueryColumns` ·0 | — | — |
| `OptimizedViews` ·0 | — | — |
| `OrganizacionRol` ·0 | — | — |
| `OrganizacionUnidadOperativa` ·0 | — | — |
| `OrganizadorDocumento` ·82 | — | — |
| `OrganizadorRegistro` ·70 | — | — |
| `OutlookMensajes` ·11 | — | — |
| `Paquete` ·0 | `CodRolRegistrador` · `FK_Paquete_UnidadOperativaRolRegistrador` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativaRegistrador` · `FK_Paquete_UnidadOperativaRolRegistrador` | `UnidadOperativaRol` ·1 |
|   | `CorrelativoRegistrador` · `FK_Paquete_UnidadOperativaRolRegistrador` | `UnidadOperativaRol` ·1 |
| `PaqueteDocumento` ·0 | `CodFichaDocumento` · `FK_PaqueteDocumento_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodPaquete` · `FK_PaqueteDocumento_Paquete` | `Paquete` ·0 |
| `PaqueteDocumentoOrganizadorUsuario` ·0 | — | — |
| `PaqueteRegistro` ·0 | `CodRol` · `FK_PaqueteRegistro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `CodUnidadOperativa` · `FK_PaqueteRegistro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
|   | `Correlativo` · `FK_PaqueteRegistro_UnidadOperativaRol` | `UnidadOperativaRol` ·1 |
| `PaqueteRegistroDetalle` ·0 | `CodPaqueteRegistro` · `FK_PaqueteRegistroDetalle_PaqueteRegistro` | `PaqueteRegistro` ·0 |
|   | `CodRegistro` · `FK_PaqueteRegistroDetalle_Registro` | `Registro` ·4 |
| `PaqueteRegistroOrganizadorUsuario` ·0 | `CodOrganizadorRegistro` · `FK_PaqueteRegistroOrganizadorUsuario_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
|   | `Usuario` · `FK_PaqueteRegistroOrganizadorUsuario_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
|   | `CodPaqueteRegistro` · `FK_PaqueteRegistroOrganizadorUsuario_PaqueteRegistro` | `PaqueteRegistro` ·0 |
| `Paso` ·0 | `CodFlujo` · `FK_Paso_Flujo` | `Flujo` ·0 |
|   | `CodUnidadOperativa` · `FK_Paso_UnidadOperativa` | `UnidadOperativa` ·13 |
| `PasoAccion` ·0 | `CodAccion` · `FK_PasoAccion_Accion` | `Accion` ·4 |
|   | `CodFlujo` · `FK_PasoAccion_Paso` | `Paso` ·0 |
|   | `CodPaso` · `FK_PasoAccion_Paso` | `Paso` ·0 |
| `PasoInstruccion` ·0 | `CodFichaDocumento` · `FK_PasoInstruccion_FichaDocumento` | `FichaDocumento` ·0 |
|   | `CodFlujo` · `FK_PasoInstruccion_Paso` | `Paso` ·0 |
|   | `CodPaso` · `FK_PasoInstruccion_Paso` | `Paso` ·0 |
| `PieSubpresupuesto` ·115 | `CodPresupuesto` · `FK_PieSubpresupuesto_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_PieSubpresupuesto_Subpresupuesto` | `Subpresupuesto` ·33 |
| `PieSubpresupuestoAnalisis` ·0 | — | — |
| `PieValorizacion` ·6 | `CodProyecto` · `FK_PieValorizacion_Proyecto` | `Proyecto` ·234 |
| `Piloto` ·1 | — | — |
| `PortalEmpleadoEnvio` ·0 | — | — |
| `PortalProveedorEnvio` ·0 | — | — |
| `Postal` ·50 | — | — |
| `Precalificacion` ·0 | — | — |
| `PrincipalLogAsync` ·0 | — | — |
| `Prioridad` ·10 | — | — |
| `Procedencia` ·31 | — | — |
| `Proceso` ·0 | — | — |
| `ProcesoNomina` ·11 | `NroPlantillaFormula` · `FK_ProcesoNomina_PlantillaFormula` | `PlantillaFormula` ·11 |
| `Producto` ·0 | — | — |
| `ProductoRecurso` ·0 | — | — |
| `Protocolo` ·56 | — | — |
| `ProtocoloDetalle` ·36 | `NroEvaluacionCaracteristica` · `FK_ProtocoloDetalle_EvaluacionCaracteristica` | `EvaluacionCaracteristica` ·72 |
|   | `NroProtocolo` · `FK_ProtocoloDetalle_Protocolo` | `Protocolo` ·56 |
| `ProtocoloDetalleEstadoCalidad` ·0 | — | — |
| `ProtocoloNoConformidad` ·10 | `NroProtocolo` · `FK_ProtocoloNoConformidad_Protocolo` | `Protocolo` ·56 |
| `ProyeccionPC` ·0 | — | — |
| `ProyeccionPCResultadoPeriodo` ·0 | — | — |
| `ProyeccionPartidaDeControlRecursoControl` ·0 | — | — |
| `ProyeccionPartidaDeControlRecursoControlPeriodo` ·0 | — | — |
| `ProyeccionRC` ·0 | — | — |
| `ProyeccionResultado` ·0 | — | — |
| `ProyeccionSaldo` ·0 | `CodPeriodo` · `FK_ProyeccionSaldo_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_ProyeccionSaldo_Periodo` | `Periodo` ·34,066 |
| `PublicacionIndiceUnificado` ·0 | — | — |
| `PublicacionIndiceUnificadoDetalle` ·0 | `CodIndiceUnificado` · `FK_PublicacionIndiceUnificadoDetalle_IndiceUnificado` | `IndiceUnificado` ·85 |
|   | `Ano` · `FK_PublicacionIndiceUnificadoDetalle_PublicacionIndiceUnificado` | `PublicacionIndiceUnificado` ·0 |
|   | `Mes` · `FK_PublicacionIndiceUnificadoDetalle_PublicacionIndiceUnificado` | `PublicacionIndiceUnificado` ·0 |
| `ReajustePeriodo` ·0 | `CodPeriodo` · `FK_ReajustePeriodo_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_ReajustePeriodo_Periodo` | `Periodo` ·34,066 |
| `ReajustePeriodoProgramado` ·0 | `CodPeriodo` · `FK_ReajustePeriodoProgramado_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_ReajustePeriodoProgramado_Periodo` | `Periodo` ·34,066 |
|   | `CodPresupuesto` · `FK_ReajustePeriodoProgramado_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_ReajustePeriodoProgramado_Subpresupuesto` | `Subpresupuesto` ·33 |
| `Reajustes` ·0 | `CodPeriodo` · `FK_Reajustes_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_Reajustes_Periodo` | `Periodo` ·34,066 |
|   | `CodPresupuesto` · `FK_Reajustes_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_Reajustes_Subpresupuesto` | `Subpresupuesto` ·33 |
| `RealGyM` ·0 | `CodPartidaDeControl` · `FK_RealGyM_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodProyectoGrupo` · `FK_RealGyM_PartidaDeControl` | `PartidaDeControl` ·1,079 |
|   | `CodPeriodo` · `FK_RealGyM_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoGrupo` · `FK_RealGyM_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_RealGyM_Proyecto` | `Proyecto` ·234 |
|   | `NroRecursoControl` · `FK_RealGyM_RecursoControl` | `RecursoControl` ·4 |
| `Recosteo` ·46 | `CodInsumo` · `FK_Recosteo_Insumo` | `Insumo` ·10,254 |
|   | `CodAlmacen` · `FK_Recosteo_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
|   | `CodProyecto` · `FK_Recosteo_ProyectoAlmacen` | `ProyectoAlmacen` ·93 |
| `RecosteoEmpresaRobot` ·0 | — | — |
| `RecosteoIgvEmpresaRobot` ·0 | — | — |
| `ReglaDocumento` ·2 | `CodOrganizadorDocumento` · `FK_ReglaDocumento_OrganizadorDocumento` | `OrganizadorDocumento` ·82 |
|   | `Usuario` · `FK_ReglaDocumento_OrganizadorDocumento` | `OrganizadorDocumento` ·82 |
| `ReglaDocumentoCriterio` ·2 | `CodReglaDocumento` · `FK_ReglaDocumentoCriterio_ReglaDocumento` | `ReglaDocumento` ·2 |
|   | `Usuario` · `FK_ReglaDocumentoCriterio_ReglaDocumento` | `ReglaDocumento` ·2 |
| `ReglaIndicadorGestion` ·0 | — | — |
| `ReglaRegistro` ·1 | `CodOrganizadorRegistro` · `FK_ReglaRegistro_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
|   | `Usuario` · `FK_ReglaRegistro_OrganizadorRegistro` | `OrganizadorRegistro` ·70 |
| `ReglaRegistroCriterio` ·1 | `CodReglaRegistro` · `FK_ReglaRegistroCriterio_ReglaRegistro` | `ReglaRegistro` ·1 |
|   | `Usuario` · `FK_ReglaRegistroCriterio_ReglaRegistro` | `ReglaRegistro` ·1 |
| `Reparto` ·0 | `CodInsumoR` · `FK_Reparto_Insumo` | `Insumo` ·10,254 |
|   | `CodPartidaR` · `FK_Reparto_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoR` · `FK_Reparto_Partida` | `Partida` ·9,113 |
|   | `PropioPartidaR` · `FK_Reparto_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_Reparto_Presupuesto` | `Presupuesto` ·44 |
| `RepartoDetalle` ·0 | `CodPartida` · `FK_RepartoDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_RepartoDetalle_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_RepartoDetalle_Partida` | `Partida` ·9,113 |
|   | `CodPresupuesto` · `FK_RepartoDetalle_Presupuesto` | `Presupuesto` ·44 |
|   | `NroReparto` · `FK_RepartoDetalle_Reparto` | `Reparto` ·0 |
| `RepartoSubpartida` ·0 | — | — |
| `RepartoUtilidad` ·0 | — | — |
| `RepartoUtilidadConfiguracionConcepto` ·0 | `NroConcepto` · `FK_RepartoUtilidadConfiguracionConcepto_Concepto` | `Concepto` ·982 |
|   | `NroEsquemaPlanilla` · `FK_RepartoUtilidadConfiguracionConcepto_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroProcesoNomina` · `FK_RepartoUtilidadConfiguracionConcepto_ProcesoNomina` | `ProcesoNomina` ·11 |
| `RepartoUtilidadDetalle` ·0 | — | — |
| `RepartoUtilidadEsquemaPlanilla` ·0 | `NroEsquemaPlanilla` · `FK_RepartoUtilidadEsquemaPlanilla_EsquemaPlanilla` | `EsquemaPlanilla` ·5 |
|   | `NroRepartoUtilidad` · `FK_RepartoUtilidadEsquemaPlanilla_RepartoUtilidad` | `RepartoUtilidad` ·0 |
| `ReplicacionEmpresa` ·0 | — | — |
| `Reserva` ·0 | — | — |
| `ResultaOperProyectoRobot` ·0 | — | — |
| `ResultadoCategoria` ·0 | — | — |
| `ResultadoCategoriaPresupuesto` ·0 | — | — |
| `ResultadoCategoriaPresupuestoRecurso` ·0 | — | — |
| `ResultadoCategoriaRecurso` ·0 | — | — |
| `ResultadoGestion` ·0 | `NroIG` · `FK_ResultadoGestion_IndicadorGestion` | `IndicadorGestion` ·18 |
| `ResultadoGestionEstructura` ·0 | — | — |
| `ResultadoGestionGeneral` ·0 | — | — |
| `ResultadoOperativo` ·31 | — | — |
| `ResultadoOperativoPC` ·4 | — | — |
| `ResultadoOperativoPartidaControl` ·93 | — | — |
| `ResultadoOperativoPeriodo` ·4 | — | — |
| `ResultadoOperativoPeriodoActividad` ·0 | — | — |
| `ResultadoOperativoPeriodoProyecto` ·0 | — | — |
| `ResultadoOperativoPeriodoProyectoRecurso` ·0 | — | — |
| `ResultadoOperativoPeriodoRecurso` ·8 | — | — |
| `ResultadoOperativoProyeccion` ·0 | — | — |
| `ResultadoOperativoRecurso` ·2 | — | — |
| `ResultadoOperativoRecursoSubcontrato` ·0 | — | — |
| `ResultadoOperativoTipo` ·31 | — | — |
| `ResultadoOperativoTipoGrupo` ·0 | — | — |
| `ResultadoOperativoTipoPresupuesto` ·0 | — | — |
| `ResultadoOperativoTipoProyecto` ·0 | — | — |
| `ResultadoOperativoTipoRecurso` ·0 | — | — |
| `ResultadoOperativoTipoRecursoPresupuesto` ·0 | — | — |
| `ResultadoPieSubpresupuesto` ·117 | `CodPresupuesto` · `FK_ResultadoPieSubpresupuesto_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_ResultadoPieSubpresupuesto_Subpresupuesto` | `Subpresupuesto` ·33 |
| `ResultadoVentaProyecto` ·0 | — | — |
| `ResumenCostoPlanilla` ·0 | — | — |
| `ResumenDestinoEspecificoParte` ·0 | — | — |
| `ResumenEquiposReporte` ·0 | — | — |
| `ResumenInterferenciaParteEquipo` ·0 | `NroInterferencia` · `FK_ResumenInterferenciaParteEquipo_Interferencia` | `Interferencia` ·7 |
| `ResumenMaterialesReporte` ·0 | — | — |
| `ResumenObrerosReporte` ·0 | — | — |
| `ResumenPartidaDeControlParte` ·65 | — | — |
| `ResumenPeriodoInsumoCronograma` ·0 | `CodInsumo` · `FK_ResumenPeriodoInsumoCronograma_Insumo` | `Insumo` ·10,254 |
|   | `CodPeriodo` · `FK_ResumenPeriodoInsumoCronograma_Periodo` | `Periodo` ·34,066 |
|   | `CodProyectoRecurso` · `FK_ResumenPeriodoInsumoCronograma_Periodo` | `Periodo` ·34,066 |
|   | `CodProyecto` · `FK_ResumenPeriodoInsumoCronograma_Tarea` | `Tarea` ·209 |
|   | `CodTarea` · `FK_ResumenPeriodoInsumoCronograma_Tarea` | `Tarea` ·209 |
| `ResumenSubcontratosReporte` ·0 | — | — |
| `Rubro` ·0 | — | — |
| `RubroIdentificador` ·7 | — | — |
| `RubroInsumo` ·0 | — | — |
| `RubroRecurso` ·0 | — | — |
| `Ruta` ·0 | — | — |
| `RutaUsuario` ·0 | `NroRuta` · `FK_RutaUsuario_Ruta` | `Ruta` ·0 |
|   | `CodUsuario` · `FK_RutaUsuario_Usuario` | `Usuario` ·14 |
| `S10Robot_LibroDiario` ·0 | — | — |
| `S10Robot_LibroDiario_Periodo` ·0 | — | — |
| `SeguridadOpciones` ·0 | — | — |
| `Servicio` ·0 | — | — |
| `ServicioSignalRAgente` ·0 | — | — |
| `ServicioSignalr` ·0 | — | — |
| `ServicioTransporteConfiguracion` ·1 | — | — |
| `ServicioTransporteEnvio` ·0 | — | — |
| `ServicioTransporteIdentificacion` ·2 | — | — |
| `ServicioTransporteSincronizacion` ·0 | — | — |
| `SistemaLicitacion` ·0 | — | — |
| `SistemaRetencionPension` ·0 | — | — |
| `SubActividadCronograma` ·0 | `CodActividad` · `FK_SubactividadCronograma_ActividadCronograma` | `ActividadCronograma` ·0 |
|   | `CodProyecto` · `FK_SubactividadCronograma_ActividadCronograma` | `ActividadCronograma` ·0 |
| `SubActividadCronogramaCambio` ·0 | — | — |
| `SubActividadCronogramaEtiqueta` ·0 | `CodEtiqueta` · `FK_SubActividadCronogramaEtiqueta_Etiqueta` | `Etiqueta` ·0 |
|   | `CodProyecto` · `FK_SubActividadCronogramaEtiqueta_Etiqueta` | `Etiqueta` ·0 |
|   | `CodActividad` · `FK_SubActividadCronogramaEtiqueta_SubActividadCronograma` | `SubActividadCronograma` ·0 |
|   | `CodProyecto` · `FK_SubActividadCronogramaEtiqueta_SubActividadCronograma` | `SubActividadCronograma` ·0 |
|   | `CodSubActividad` · `FK_SubActividadCronogramaEtiqueta_SubActividadCronograma` | `SubActividadCronograma` ·0 |
|   | `TipoCronograma` · `FK_SubActividadCronogramaEtiqueta_SubActividadCronograma` | `SubActividadCronograma` ·0 |
| `SubActividadCronogramaEtiquetaCambio` ·0 | — | — |
| `SubActividadCronogramaPeriodoEtiqueta` ·0 | — | — |
| `SubActividadCronogramaTarea` ·4 | — | — |
| `SubActividadCronogramaTareaHistorial` ·0 | — | — |
| `SubactividadCronogramaCI` ·0 | `NroCI` · `FK_SubactividadCronogramaCI_CausaIncumplimiento` | `CausaIncumplimiento` ·33 |
| `SubactividadCronogramaCumplimiento` ·0 | — | — |
| `SubactividadCronogramaTareaEstadoCalidad` ·0 | — | — |
| `SubordinacionEQ` ·0 | `CodCargo` · `FK_SubordinacionEQ_Cargo` | `Cargo` ·14 |
|   | `CodProyecto` · `FK_SubordinacionEQ_Proyecto` | `Proyecto` ·234 |
| `SubordinacionEQDetalle` ·0 | `CodEquipo` · `FK_SubordinacionEQDetalle_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_SubordinacionEQDetalle_ProyectoEquipo` | `ProyectoEquipo` ·0 |
|   | `CodProyecto` · `FK_SubordinacionEQDetalle_SubordinacionEQ` | `SubordinacionEQ` ·0 |
|   | `CodSubordinacionEQ` · `FK_SubordinacionEQDetalle_SubordinacionEQ` | `SubordinacionEQ` ·0 |
| `SubordinacionMO` ·0 | `CodCargo` · `FK_SubordinacionMO_Cargo` | `Cargo` ·14 |
|   | `CodProyecto` · `FK_SubordinacionMO_Proyecto` | `Proyecto` ·234 |
| `SubordinacionMODetalle` ·0 | `CodObrero` · `FK_SubordinacionMODetalle_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_SubordinacionMODetalle_ProyectoObrero` | `ProyectoObrero` ·0 |
|   | `CodProyecto` · `FK_SubordinacionMODetalle_SubordinacionMO` | `SubordinacionMO` ·0 |
|   | `CodSubordinacionMO` · `FK_SubordinacionMODetalle_SubordinacionMO` | `SubordinacionMO` ·0 |
| `SubordinacionSC` ·0 | `CodProyecto` · `FK__Subordina__CodPr__5A204D06` | `Proyecto` ·234 |
| `SubordinacionSCDetalle` ·0 | `CodSubcontrato` · `FK__Subordina__CodSu__5DF0DDEA` | `Proyecto` ·234 |
|   | `CodProyecto` · `FK__SubordinacionSCD__5CFCB9B1` | `SubordinacionSC` ·0 |
|   | `CodSubordinacionSC` · `FK__SubordinacionSCD__5CFCB9B1` | `SubordinacionSC` ·0 |
| `SubpartidaDistribucion` ·0 | `CodPresupuesto` · `FK_SubpartidaDistribucion_Subpresupuesto` | `Subpresupuesto` ·33 |
|   | `CodSubpresupuesto` · `FK_SubpartidaDistribucion_Subpresupuesto` | `Subpresupuesto` ·33 |
| `SubpartidaDistribucionDetalle` ·0 | — | — |
| `SubpartidaInsumoPeriodo` ·0 | `CodPresupuesto` · `FK_SubpartidaInsumoPeriodo_Presupuesto` | `Presupuesto` ·44 |
| `SubproyectoActividad` ·0 | `CodPresupuesto` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
|   | `CodProyecto` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
|   | `CodSubpartida` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
|   | `CodSubpresupuesto` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
|   | `CodTarea` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
|   | `Item` · `FK_SubproyectoActividad_Actividad` | `Actividad` ·653 |
| `SubproyectoPartida` ·0 | `CodInsumo` · `FK_SubproyectoPartida_Insumo` | `Insumo` ·10,254 |
|   | `CodPartida` · `FK_SubproyectoPartida_Partida` | `Partida` ·9,113 |
|   | `CodPresupuestoPartida` · `FK_SubproyectoPartida_Partida` | `Partida` ·9,113 |
|   | `PropioPartida` · `FK_SubproyectoPartida_Partida` | `Partida` ·9,113 |
|   | `CodProyecto` · `FK_SubproyectoPartida_Proyecto` | `Proyecto` ·234 |
| `Sunat` ·0 | — | — |
| `Support` ·0 | — | — |
| `SustentoTributario` ·10 | — | — |
| `Talonario` ·0 | `BankAccount_ID` · `FK_Talonario_OB_CuentaBanco` | `OB_CuentaBanco` ·1 |
|   | `CodDocumento` · `FK_Talonario_TipoDocumento` | `TipoDocumento` ·66 |
|   | `NroTDS` · `FK_Talonario_TipoDocumentoSunat` | `TipoDocumentoSunat` ·50 |
| `TalonarioDetalle` ·0 | `NroTalonario` · `FK_TalonarioDetalle_Talonario` | `Talonario` ·0 |
| `TalonarioDocumento` ·0 | `NroTalonario` · `FK_TalonarioDocumento_Talonario` | `Talonario` ·0 |
|   | `CodDocumento` · `FK_TalonarioDocumento_TipoDocumento` | `TipoDocumento` ·66 |
| `TalonarioProyecto` ·0 | `CodProyecto` · `FK_TalonarioProyecto_Proyecto` | `Proyecto` ·234 |
|   | `NroTalonario` · `FK_TalonarioProyecto_Talonario` | `Talonario` ·0 |
| `TalonarioUsuario` ·0 | `NroTalonario` · `FK_TalonarioUsuario_Talonario` | `Talonario` ·0 |
|   | `CodUsuario` · `FK_TalonarioUsuario_Usuario` | `Usuario` ·14 |
| `TechoIncremento` ·0 | — | — |
| `TechoIncrementoHistoria` ·0 | — | — |
| `TechoIncrementoProcesado` ·0 | — | — |
| `TechoLibre` ·0 | — | — |
| `Terminal` ·0 | — | — |
| `Titular` ·1 | — | — |
| `TopfilterAndGraphTable` ·0 | — | — |
| `Trace_Configuration` ·0 | — | — |
| `Trace_Procesos` ·0 | — | — |
| `TramosProvias` ·0 | — | — |
| `TransactionAuditConfig` ·39 | — | — |
| `Tratamiento` ·6 | — | — |
| `VectorCaracteristica` ·0 | — | — |
| `VectorEspecificacion` ·0 | — | — |
| `VinculoFamiliarSustento` ·12 | — | — |
| `VinculoMedicionManual` ·0 | — | — |
| `XPDeletedObject` ·0 | — | — |
| `XPOVersion` ·0 | — | — |
| `XPObjectType` ·0 | — | — |
| `dtEsquema_7777777` ·0 | — | — |
| `dtproperties` ·0 | — | — |
| `vMarcaciones` ·0 | — | — |
| `vMarcacionesControl` ·0 | — | — |

---

## Nota final

`[V]` Este documento es un volcado directo del catálogo de SQL Server. Cada fila corresponde
a una restricción de llave foránea declarada en la base.

`[S]` **Lo que no muestra:** las relaciones que existen en la lógica de la aplicación pero no
están declaradas en la base. Hay al menos un caso verificado — el subsistema de aprobaciones,
cuyas tablas `GrupoAprobacion` y `OrdenDeCompraAprobacion` **no tienen ni una sola relación
declarada**, ni entrante ni saliente, pese a operar sobre las órdenes de compra.

`[S]` Esa ausencia es en sí misma un hallazgo: significa que ese subsistema funciona por
convención en el código y no por integridad garantizada por el motor.
