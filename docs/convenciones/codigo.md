# Convenciones de código

## General

- `declare(strict_types=1);` en todo archivo. Clases `final` por defecto, salvo modelos.
- Dominio en español, framework en su idioma. **Nombre de clase = nombre de archivo, y namespace =
  ruta, con mayúsculas exactas** (producción corre en Linux; lo verifica `tests/Arch/NombresTest.php`).
- Sin `dd()`, `dump()`, `ray()`, `var_dump()`. Sin `DB::raw` con valores interpolados.

## Modelos

`$fillable` explícito; casts para todo lo que no sea texto; sin lógica de negocio más allá de
relaciones, scopes y accesores simples.

**Auditoría (M4).** Todo modelo de una tabla con `auditoria()` usa `Nucleo\Models\Concerns\ConAuditoria`,
que llena `created_by` y `updated_by` (un valor puesto a mano se sobrescribe). Los de tablas sensibles
usan además `RegistraCambios`: bitácora campo por campo, con `save()` y `delete()` en transacción;
`camposSinBitacora()` es obligatorio (sueldos, salud) y los `$hidden` nunca entran. La bitácora solo
es inmutable a través de su modelo: nada escribe `auditoria_cambios` por query builder. El autor lo decide `ContextoAuditoria`: actor fijado por el ejecutor
de acciones › usuario con sesión › usuario de sistema en consola y cola › si no, se rechaza el cambio.
Un `update()` masivo por query builder no dispara eventos: en tablas sensibles, guardar por modelo.

## Servicios: la API pública de un módulo

- Un servicio por capacidad (`RecepcionService`), no por tabla.
- Lo atómico va en `DB::transaction()` **dentro del servicio**, corto y **sin llamadas externas**
  (SUNAT, IA, correo): esas van después del commit, por evento.
- Excepciones de dominio con mensaje en español entendible por el usuario: **extienden
  `Nucleo\Excepciones\ErrorDeNegocio`**, y así `AccionFilament` las muestra como aviso sin código extra.
- **Bloqueo optimista:** el servicio recibe la `version` que la persona leyó, bloquea la fila
  (`lockForUpdate`), compara y lanza `VersionDesactualizada` si cambió (patrón: `ParametrosService::cambiar()`).
- **Violaciones de la base como error de negocio:** la unicidad (`UniqueConstraintViolationException`) y la
  llave foránea (`QueryException` con SQLSTATE `23503`, dentro de un `DB::transaction` anidado: PostgreSQL
  invalida la transacción ante el error) se traducen a un `ErrorDeNegocio` con mensaje claro. Patrón:
  `EdicionCatalogosService::crear()` y `borrar()`. Una restricción de exclusión (SQLSTATE `23P01`) se
  traduce igual (`PartiesService::asignarRol()`).
- **Lo que da el núcleo a los demás módulos:** parámetros con `ParametrosService` (`decimal('grupo.clave')`
  devuelve string; se declaran con `RegistroParametros` en el provider del módulo; **es de dinero** (`esSensible`,
  acción y permiso `parametros.editar-sensibles`) todo parámetro que cambia un monto, una tasa o a dónde va el
  dinero, y una regla de negocio propia va en un `ValidadorParametro`); catálogos con
  `CatalogosService` y el enum `Catalogo` (id por código, activos, opciones), nunca con sus modelos; los
  escribe solo el núcleo (`EdicionCatalogosService`, ADR 0013), y los campos editables de cada uno están en
  `Catalogo::campos()`;
  tipo de cambio con `TipoCambioService::del()` (moneda local = 1; si falta el del día, el último hasta
  `tipo_cambio.dias_tolerancia` días antes, 4 según ADR 0012; más atrás es error).
  Personas y organizaciones con `PartiesService`: los demás módulos guardan solo el `party_id` y nunca
  usan los modelos `Party` ni `PartyRol`. Devuelve objetos de solo lectura de `Nucleo/Parties/`
  (`ResumenParty`, `PartyObtenida`, `PartyRegistrada`). `buscarPorDocumento()` y `obtenerOCrear()` (idempotente,
  para procesos automáticos: devuelve una party desactivada sin reactivarla) normalizan y validan el número con
  `DocumentoIdentidad` (RUC siempre con módulo 11); `buscar(texto, rol)` alimenta los selectores (solo activas,
  tope 50) y `etiquetaDe(id)` da la etiqueta del valor ya guardado aunque esté inactivo; roles con
  `asignarRol()`, `cerrarRol()` y `tieneRol(party, rol, fecha)` (`hasta` es el último día vigente).
  Contactos, sedes y cuentas bancarias (plan 1.3b): **se leen** con `PartiesService::contactosVigentes()`,
  `sedesActivas()`, `cuentaPrincipal(party, moneda)`, `cuentasDePago()` (las dos nunca dan la de detracciones) y
  `cuentaDetracciones()`, que devuelven `ResumenContacto`, `ResumenSede` y `ResumenCuentaBancaria`; **se escriben**
  solo por sus acciones registradas (`nucleo.parties.agregar-contacto`, `…-sede`, `registrar-cuenta-bancaria`…),
  nunca con `ContactosPartyService`, `SedesPartyService` ni `CuentasBancariasService`, que son internos (lo
  impide `tests/Arch`). Un documento que guarda una cuenta o un contacto guarda su id.
  **Los datos de Techquk** (plan 1.15) se piden a `EmpresaService::datos()` (`DatosEmpresa`: razón social, RUC,
  dirección, contacto, redes y logo) y se dibujan con `nucleo::empresa.encabezado`; nunca se lee la ficha ni el
  parámetro `empresa.ficha` directamente. La ficha elegida no se desactiva ni se borra.
- **Integraciones externas** detrás de una interfaz con una implementación real (`FuenteTipoCambio` →
  `FuenteSunat`): la consulta va antes y fuera de la transacción, con `connectTimeout`/`timeout`, y su
  falla es un `ErrorDeNegocio` con mensaje claro. Tiempos por debajo del límite del servidor web y tope de
  tamaño si se descarga un archivo. Las pruebas usan `Http::fake`; `tests/Pest.php` bloquea toda llamada
  sin simular (`Http::preventStrayRequests()`).

## Acciones registradas

- Cada acción de usuario es una clase en `Acciones/` que extiende `Nucleo\RegistroAcciones\Accion`:
  `clave()` (`modulo.accion`), `nombre()`, `campos()` (`Campo::texto|entero|decimal|fecha|booleano|opcion`,
  `Campo::elementoDe()` para elegir un elemento de un catálogo, por id, y `Campo::listaDe()` para varios: llega
  como lista de ids, `[]` si está vacía, y el ejecutor valida cada elemento), `permiso()` y `ejecutar()`, que
  solo llama al servicio. El provider del módulo la registra en `RegistroAcciones`.
- **Un catálogo con «Otro»** (`Catalogo::codigoOtro()`: orígenes comerciales, redes sociales; plan 2.0, D5): el campo
  lleva su detalle, `Campo::elementoDe($nombre, $etiqueta, $catalogo, detalle: 'origen_detalle')` más un
  `Campo::texto('origen_detalle', …)->opcional()`; la pantalla lo pide solo con «Otro». El servicio lo valida con
  `CatalogosService::detalleDeOtro($catalogo, $id, $detalle, 'el origen')` (con «Otro», obligatorio y con el mínimo de
  los motivos; sin él, nulo) y guarda el resultado. El módulo informa lo escrito con una `Catalogos\FuenteDeOtros`
  registrada en `RegistroOtrosDeCatalogo`, para «Lo más escrito en «Otro»».
- **`Campo::listaDeIds()`** elige varios registros de una tabla que no es catálogo, por id (los permisos de un rol).
  **`Campo::listaDeTextos($nombre, $etiqueta, $maximo)`** es para varios textos cortos que **no son ids** de ninguna
  tabla, como las claves de los módulos ocultos de la barra (plan 1.4f), que son código y no filas. Llega como lista
  de strings, `[]` si está vacía; el ejecutor valida cada elemento (texto, sin repetir, hasta `$maximo`) y **qué
  valores valen lo decide el servicio**, no el campo. En Filament se dibuja con `TagsInput` salvo que la pantalla pase
  su propio control. Si los valores salen de una tabla, usa `listaDe()` o `listaDeIds()`, no este. Para reemplazar
  una lista entera, `->opcional()->conReglas('present')`: vacía vale y omitirla se rechaza.
- **Una clase, varias acciones:** si la misma operación se repite sobre varias cosas, la clase recibe el
  parámetro por constructor y se registra **una instancia por cada una**, con clave propia
  (`registrar($this->app->make(Clase::class, ['catalogo' => $c]))`). Patrón: `Acciones/Catalogos/`.
- Se ejecuta **siempre** por `EjecutorAcciones`: rechaza al usuario de sistema y a los inactivos, revisa
  el permiso, valida, deja pasar solo los campos declarados ya convertidos a su tipo y corre a nombre
  del usuario (auditoría). Llamar `ejecutar()` de la clase se salta todo eso: **fuera del núcleo, una acción del
  núcleo se pide por su clave** (`ejecutar('nucleo.parties.agregar-contacto', …)` o `AccionFilament::desde('clave')`),
  nunca por su clase (lo impide `tests/Arch`).
- Filament la dibuja con `AccionFilament::desde($accion, $datosFijos, $alTerminar, $soloLectura, $ocultos, $controles, $deApoyo)` y
  no agrega reglas: `$datosFijos` recibe por inyección lo que pida (`$record` en una fila); `$alTerminar`
  recibe lo que devolvió la acción; `$soloLectura` da los campos que se muestran deshabilitados pero se envían
  igual (la longitud de DNI y RUC); `$ocultos`, los que no se muestran y llegan vacíos (la razón social al
  editar una persona natural); `$controles` cambia el control de un campo, por ejemplo un selector con buscador
  para un id de otra tabla (`SelectoresParty`: el banco, el contacto de una sede); `$deApoyo` agrega campos solo de
  presentación que nunca llegan a la acción (`dehydrated(false)`) y que un control lee con `$get`, por ejemplo lo que
  se mostró al abrir la ventana «Elegir módulos» (plan 1.4f). Estos cuatro solo ocultan, bloquean, cambian el control
  o lo apoyan: la regla sigue en el servicio. Un campo `->deControl()` (la versión leída) va oculto y la pantalla lo llena **al
  abrir** el formulario con `fillForm()`: si se tomara al guardar, el bloqueo optimista nunca saltaría.
  **Toda acción se prueba sin interfaz.**
- Una acción que reemplaza datos marca con `present` los campos que se pueden vaciar
  (`->opcional()->conReglas('present')`): omitirlos sin pantalla debe fallar, no borrar. Vacío a propósito sí vale
  (`'permisos' => []` deja el rol sin permisos); olvidarse del campo, no. Patrón: `CambiarPermisosDeRol`,
  `DesignarJefatura`.
- Su permiso lo crea `PermisosSeeder` a partir de `permiso()`: no se agrega a mano.
- La misma clase servirá a la API móvil y al agente de voz. Ver `/nueva-accion`.

## Estados y efectos

- **La máquina de estados se declara en código** (plan 1.5): una `DefinicionDocumento` por tipo de documento, con
  sus `EstadoDefinido` y `TransicionDefinida` (desde, hacia, motivo, aprobación, del sistema, efectos, condiciones,
  acción propia). El módulo la registra en `RegistroDocumentos` desde su provider, junto con sus efectos
  (`efecto(clave, clase)`). `SincronizadorEstados` la copia a `estados` y `transiciones_estado` en cada despliegue:
  no pisa el nombre ni el color editados por pantalla y falla si la definición choca con los datos (un estado con
  documentos, un efecto vivo que nadie registra). El código de un estado o transición nunca cambia.
- **Cambios de estado solo vía `TransicionesService`** (M3): `crear()` deja el estado inicial y su primera fila del
  historial; `transicionar()` es de una persona; `transicionarComoSistema()`, de código del módulo o del mecanismo de
  aprobaciones, con quién la originó. En una transacción bloquea la fila, compara la `version` leída, valida la
  transición desde el estado actual, el permiso con los ámbitos del documento (`ambitoDePermisos()`; basta uno), las
  `CondicionTransicion`, el motivo y, al anular, los `RevisionAntesDeAnular`; cambia el estado, escribe
  `historial_estados` y corre los efectos. El modelo rechaza un `estado_id` puesto fuera del servicio
  (`ContextoTransicion`).
- **Una transición de persona tiene permiso `<clave>.<codigo>` y su `AccionTransicion`**, registrada sola
  (`erp.compras.orden.anular`). Las del sistema y las que requieren aprobación no tienen permiso ni acción. La que
  necesita más datos se marca `accionPropia` y su módulo escribe la acción, que llama al servicio: el servicio
  revisa todo igual; si la acción propia guarda datos, los guarda y transiciona en la misma transacción, para que un
  rechazo los deshaga. Si el dato solo lo puede escribir un efecto (un campo derivado, en un estado no editable), la
  acción lo deja en un pedido `scoped` mientras corre la transición y el efecto lo lee; sin pedido, el efecto falla
  (plan 2.9: `PedidoTraerAlProyecto` y `HacerVigente`). Lo que el efecto rechaza no lo ve la barra de estados, que
  solo corre las condiciones: si se puede, va en una `CondicionTransicion`. Un efecto puede pedir
  `transicionarComoSistema()` de otro documento (reemplazar la versión anterior). Solo `AccionTransicion` declara `permisoPorAmbito()` (prueba en `tests/Arch`): el ejecutor
  le deja el permiso al servicio, que lo revisa con los ámbitos. Por eso el botón de una transición sale de
  `barraDeEstados()`, no de `AccionFilament`, que lo muestra según el permiso global.
- Las `CondicionTransicion` solo leen: también corren al armar la barra y el mensaje de edición bloqueada.
- **Cada efecto es una clase en `Efectos/`** con una sola responsabilidad (`DespachoEjecutaCostoEnPartida`):
  - `Efecto` (interno: stock, imputación, otro documento) corre en la transacción. `aplicar()` devuelve lo que hizo
    (ids, partidas, montos como texto decimal) y se guarda en `efectos_aplicados.datos`; `revertir()` recibe esos
    datos y no recalcula con los de hoy. Si uno falla, no cambia nada.
  - `EfectoExterno` (correo, aviso, servicio externo): `preparar()` en la transacción; `ejecutar()` y `revertir()`
    en cola después del commit (`EjecutarEfectoExterno`, `RevertirEfectoExterno`), **fuera de toda transacción**
    (`EfectosExternosService`): una transacción corta reserva la fila del efecto (`reservado_hasta`), el envío corre
    sin transacción con el id del efecto como clave de idempotencia, y otra transacción corta confirma o, si falló,
    libera la reserva para el reintento. Si el worker muere, la reserva vence y otro intento lo retoma. La garantía
    es **al menos una vez**: puede repetirse, nunca perderse (con correo SMTP, un Message-ID estable reduce los
    duplicados pero no los elimina). La reserva dura más que el `timeout` de los jobs y menos que el `retry_after`
    de la cola (con prueba). Un efecto **reservado sin confirmar cuenta como salido al exterior**: se revierte al
    anular e impide «Reabrir» (`TransicionesService::salioAlExterior()`). Si se agotan los intentos, el job queda
    en `failed_jobs` y avisa al responsable; el estado del documento no se deshace.
  - Lo que no se deshace (un aviso enviado) lo dice en `revertir()`, no lo omite.
- **Anular revierte todos los efectos vivos del documento, en orden inverso al que se aplicaron** (del cambio de
  estado más reciente al más antiguo y, dentro de cada uno, del último efecto al primero), con los datos que cada
  efecto guardó al aplicarse. *Interpretación del v4* (parte 6: «revierte los efectos de la última transición»):
  al pie de la letra, anular una orden «Enviada» dejaría reservado en la partida el comprometido que puso
  «Aprobar». Con un solo paso dado, las dos lecturas coinciden (plan 1.5, D4). Anular siempre exige motivo y va a
  un estado `es_cancelacion`, final y no editable.
- **No se anula un documento del que dependen documentos vivos** (la orden con una recepción o una factura): las
  revisiones `RevisionAntesDeAnular` las declara su definición o el módulo del documento dependiente
  (`RegistroDocumentos::revisionAntesDeAnular()`), corren dentro del bloqueo, y el mensaje nombra los documentos que
  lo impiden (`DocumentoQueImpide`). Quien crea un documento dependiente bloquea la fila del origen (plan 1.5, D4).
- **Un estado con `es_editable = false` no admite cambios en los datos del documento**, vengan de la pantalla, de
  una acción, de un servicio o de un job: lo rechaza el modelo (trait `TieneEstados` en la cabecera,
  `ParteDeDocumento` en las líneas, que también impide agregarlas o borrarlas), no la pantalla (regla 6; plan 1.5,
  D5). El estado se lee de la base al guardar, con bloqueo compartido: el servicio que agrega una línea lo hace en
  su transacción, y así espera a una aprobación en curso. Un documento con estados no se borra nunca: se anula. **«Por aprobar», en cualquier documento, es no editable:** la
  definición rechaza una transición con `requiere_aprobacion` cuyo estado de origen sea editable (plan 1.5, D7). Un
  campo derivado (`camposDerivados()`, lo que otro documento o un efecto del propio documento mantiene al día, como el
  proyecto y la vigencia del presupuesto) solo cambia dentro de un efecto
  (`ContextoEfectos`), en cualquier estado (plan 1.5, D8). El mensaje nombra solo las salidas que esa persona puede
  usar. Nada escribe esas tablas con SQL a mano ni con actualización masiva de Eloquent (prueba en `tests/Arch`).
- **Motivos de catálogo:** cada transición que exige motivo tiene su lista (`tipos_motivo` con el código de la
  transición), siempre con «Otro», que obliga a escribir el detalle (`motivos.minimo_detalle_otro`), y un
  comentario opcional. Se editan desde pantalla con `MotivosService`; nunca se borran. Dos motivos de una lista no
  se llaman igual sin contar mayúsculas, tildes ni espacios: lo garantiza la base (`nombre_comparable`).
  Una lista que un módulo declara suelta (`RegistroDocumentos::familiaDeMotivos()`) puede nacer con sus motivos
  (`MotivoInicial`, plan 2.6): el sincronizador crea cada uno solo si ni su código ni su nombre están, así no pisa lo
  que se cambió en Configuración; la marca `es_cambio_cliente` se da ahí.
- **Lecturas para pantallas:** `EstadosService::barraDeEstados(documento, usuario)` (estados en orden, salidas que
  esa persona puede usar, lo que espera aprobación) e `historialDe(documento)`;
  `MotivosService::detallesDeOtro(familia)` para ver qué «Otro» conviene sumar a la lista (otros módulos suman los
  suyos con `FuenteDeMotivos`). El color de un estado se elige de `TonoEstado` y siempre va con su nombre.
- **Patrón «Reabrir»**, para corregir un error después de aprobar (un monto mal escrito) sin editar el documento
  aprobado. Cada definición decide si lo ofrece y desde qué estados:
  - Es una transición desde un estado aprobado hacia un estado editable, con permiso y motivo obligatorio.
  - Revierte los efectos vivos aplicados desde que el documento dejó ese estado editable (libera el comprometido),
    en orden inverso, y el documento tiene que aprobarse de nuevo.
  - Solo se permite si nada salió al exterior (no se envió al proveedor: ningún `EfectoExterno` ejecutado) y no hay
    documentos posteriores vivos (las mismas `RevisionAntesDeAnular`). Si no, se anula y se crea otro.
  - En pantalla, «Reabrir» y «Anular» van en el menú «Más» de la cabecera, con confirmación.
  - Ningún documento lo usa todavía (plan 1.5, D5): el primero que lo ofrezca lo construye sobre el mismo servicio,
    que ya sabe revertir los efectos vivos desde un punto del historial.
- Aprobaciones y transiciones bloquean la fila del documento (`lockForUpdate`) y validan el estado
  **dentro** del bloqueo. Unicidad (ronda, orden) en la base: una firma por paso (la misma persona puede firmar
  dos pasos si la jefatura sube; plan 1.7).
- Un documento con un paso que requiere aprobación (plan 1.7) declara en su definición `montoParaAprobacion()` (monto y
  moneda; las reglas van en soles), `unidadDeAprobacion()` (si alguna regla pide «su jefatura») y
  `transicionAlObservar()` (la transición del sistema que lo devuelve a editable). Nunca ejecuta esa transición ni
  escribe firmas: al llegar al estado se abre la ronda sola, y la última firma ejecuta la transición
  (`AprobacionesService`). Quién firma lo dicen las reglas de Configuración → Aprobaciones, nunca un permiso.
  - Si el sistema lleva un documento a un estado con aprobación (`transicionarComoSistema`), pasa `originadoPor`:
    esa persona queda como quien pidió la aprobación y no firma su propio paso. Sin ella, queda el usuario de sistema
    y nadie real queda excluido.
- Stock: descuento atómico (`UPDATE … WHERE stock >= cantidad`), nunca leer-restar-guardar.
- Correlativos solo vía el servicio de numeración, tomados lo más tarde posible en la transacción.
  - Un documento que se numera lo declara en su definición (`numeracion()`: `new FormatoNumeracion('REQ')`; si su
    periodo sale de su propia fecha, `fechaDeNumeracion()`; si va por proyecto, `ambitoDeNumeracion()`, con el código del proyecto, que entra en el número), y su tabla
    lleva `numero` `NOT NULL` y único. `TransicionesService::crear` lo llena: nunca a mano (plan 1.6).
  - **Documentos con versiones que comparten número** (planes 2.9 y 2.22, D2: `PRE-2026-0001` v1, v2…;
    `COT-2026-0184` v1, v2…): la definición declara `numeracion()` y `versionesCompartenNumero()`. La tabla lleva
    `numero` y `numero_version` `NOT NULL`, únicos juntos (no `numero` solo). `TransicionesService::crear` numera la
    versión 1 (el servicio no le pone número); en las siguientes, el servicio copia el número de la anterior y el motor
    comprueba que esa versión exista.
  - `NumeracionService::siguiente` falla fuera de una transacción: tomado afuera, un fallo dejaría un hueco.
  - En mensajes, un documento se nombra por `DefinicionDocumento::identificador()` (su número), nunca por su id.

## Eventos y jobs

- Eventos en pasado y en español (`RecepcionConfirmada`), con IDs, no modelos serializados.
  Los que dependen de la transacción usan `ShouldDispatchAfterCommit`.
- **Jobs idempotentes:** cada alerta o recálculo tiene una clave única (`alertas_emitidas.clave`);
  tareas programadas con `withoutOverlapping()`. Un reintento nunca duplica una alerta ni un costo.
- **Alertas (plan 1.10).** Un aviso que el sistema manda solo (por una revisión o por un fallo) es una alerta: el
  módulo la declara en su provider con `RegistroAlertas::registrar(new DefinicionAlerta(...))` (nombre, grupo de
  avisos, ícono único, textos de fábrica con sus datos `{dato}`, roles por defecto, si es obligatoria) y la emite con
  `AlertasService::emitir($clave, $hecho, $datos, ...)`, donde `$hecho` la hace única (`2026-10-27`, `efecto:15`):
  repetirla no la repite. Lo que revisa cada mañana va en una `FuenteAlertas` registrada con `registrarFuente()`, que
  usa solo los servicios de su módulo. Roles, textos y anticipación los edita Configuración → Alertas; un texto nunca
  pierde sus datos. Un aviso puntual de un documento (no programado) sigue con `NotificacionesService::avisar()`.
  Dentro de un `failed()`, el aviso va en `try`: si falla, queda en el registro y no tapa el error original. Una
  alerta de control que se emite en la transacción de su hecho (la autoaprobación) va **sin** `try` a propósito: si el
  aviso no queda, la firma tampoco.
- **Nada externo dentro de una transacción, tampoco en un job.** Un job que envía algo afuera reserva su trabajo en
  una transacción corta, envía sin transacción y confirma o libera en otra corta, con una reserva que vence si el
  worker muere (patrón de `EfectosExternosService`). Sus intentos son «al menos una vez»: lleva una clave de
  idempotencia para que el otro lado descarte duplicados.
- Cola con driver de base de datos. Los jobs fallidos quedan en `failed_jobs` y avisan al responsable.

## Adjuntos y avisos

- **Adjuntos (plan 1.8).** Un modelo que lleva archivos implementa `ConAdjuntos` (quién los ve y quién los cambia) y
  muestra la pestaña `ArchivosDeRegistro`. Se agregan, reemplazan y quitan solo por `AdjuntosService` (acciones
  `nucleo.adjuntos.*`): nunca se escribe en el disco `adjuntos` ni en la tabla desde otro lado. El archivo nunca es
  público: se descarga por `filament.app.adjuntos.descargar`. Un tipo de adjunto obligatorio se exige poniendo
  `TieneAdjuntosObligatorios` en la transición que corresponde. Un adjunto sensible nunca sale a un servicio externo
  (`ia.md`).
- **Avisos (plan 1.8).** Un módulo declara en su provider sus grupos (`GrupoAvisos`, que suman su preferencia de correo
  en «Mis preferencias») y sus avisos (`DefinicionAviso`), y avisa solo con `NotificacionesService::avisar()`, con una
  clave única que identifica el hecho (`orden:15:por-aprobar`): repetir el mismo aviso con ella no repite el aviso ni
  el correo. Nunca `$user->notify()` para un aviso de negocio, ni correos sueltos. El título y el texto viajan por
  correo: nunca llevan datos sensibles (sueldos, salud); «Abrir» lleva al sistema, que revisa permisos.
- Un campo de archivo de una acción (`Campo::archivo`) desde la pantalla solo acepta el archivo subido; sin interfaz,
  una ruta de las carpetas temporales de subida, nunca otra del servidor.

## Imputación

- **Toda línea de costo tiene destino (regla 3; plan 1.9).** El módulo que crea una línea con costo le da su destino con
  `ImputacionService::destinar()` en la misma transacción, y sus efectos mueven los montos con `mover()` por
  diferencias: comprometido al aprobar, ejecutado al despachar o dar conformidad, pagado al aplicar el pago, y lo mismo
  en negativo al anular. Nunca se escribe `imputaciones` desde otro lado. Hoy el destino es un `CentroDeCosto`; la
  etapa 2 suma la partida de control como otro `DestinoImputacion` (y su columna en `COLUMNAS_DESTINO`), sin cambiar a
  quien imputa (la partida llegó en la 2.1: `DestinoPartida`, por `PartidasService::destino()`). Una línea se valoriza
  siempre con el tipo de cambio de venta de su fecha (el de las compras): la diferencia de cambio al pagar es de la
  contabilidad, no del costo.
- **Cómo usa el selector de imputación un documento con costo (plan 2.2; costura C12 del ADR 0020):**
  - En la acción que agrega o cambia una línea, suma `...EleccionImputacion::campos()` a sus campos y, en Filament, pasa
    `controles: SelectorImputacion::controles(monto: '…', moneda: '…', fecha: '…')` a `AccionFilament::desde()` con los
    nombres de sus campos de la línea. El componente solo presenta: muestra el saldo con la línea, avisa al 90 % y
    pide el motivo si excede.
  - Su servicio, **en una sola transacción**, guarda la línea, pide el destino a `FuenteDeDestinos::destino(
    EleccionImputacion::desdeDatos($datos))` y llama a `ImputacionService::destinar()`. Si el destino falla, la línea no
    queda: una línea sin destino es imposible también desde la API.
  - Al enviar, llama a `ExcesoDePartidaService::alEnviar($lineas)` dentro de la transacción de la transición (si el
    envío se frena, sus avisos se descartan), y por eso su transición «enviar» es de **acción
    propia** (`accionPropia: true`): la acción genérica de la transición no la llamaría y la ronda saldría sin la regla
    del exceso. Al aprobar, en el efecto que sube el comprometido y
    **antes** de `mover()`, a `alAprobar($lineas)` (bloquea las partidas en orden de id y avisa la alerta después del
    commit). Las dos marcan las líneas que exceden y rechazan si una no tiene motivo y comentario; al aprobar, también
    un exceso que no venía marcado del envío. Las marcas no se revierten al anular: solo cuentan al abrir una ronda.
  - En su definición declara `lineasDeCosto($documento)`: las reglas de aprobación con la condición «exceso de
    partida» entran solo si alguna excede, y si ninguna aplica, el envío se rechaza (el exceso nunca pasa sin su regla).
  - **El gasto no presupuestado (plan 2.6) no le pide nada más al documento.** Es una línea suya cargada a la PC-99
    («gasto directo con proyecto» en el selector, con motivo de su lista y comentario obligatorios); no tiene tabla ni
    estados propios: su numeración y sus estados son los del documento. Las mismas llamadas de arriba bastan: las reglas
    con la condición «Solo si alguna línea va a la PC-99» entran solas (sin una activa, el envío se rechaza), y
    `alEnviar` y `alAprobar` avisan de esas líneas después del commit (alertas y evento `PosibleAdicionalRegistrado`,
    C11). Moverlo después a otra partida es una reclasificación
    (acción `erp.presupuestos.reclasificar-gasto`, que se completa en la tarea 2.8); nunca un `destinar()` nuevo.
  - **La firma del exceso o de la PC-99 nunca la da quien pidió el documento** (planes 2.2 y 2.6; desarrollador,
    2026-11-06). Sus reglas no admiten tope de autoaprobación (lo exigen el servicio y la base). Si la misma jefatura
    firma por la regla del monto y por la del exceso o la PC-99, queda una sola firma y lleva la regla sin
    autoaprobación: el tope de la regla del monto no la abre. La autoaprobación de la regla del monto **en otra unidad**
    sigue valiendo, porque la firma del exceso la da otra persona. Ejemplo: la regla del monto la firma la Jefatura A con
    tope de S/ 1,000 y la de la PC-99, la Gerencia B; quien pidió es jefe de A y autoaprueba la firma de A por el tope,
    pero la de B la firma su jefe. Tampoco firma como firmante normal: si quien pidió es el jefe de la unidad que firma,
    la firma sube a la unidad de arriba (`ResolutorDeFirmante`, plan 1.7).
  - En pruebas, `PartidasEnMemoria` y `ProyectosEnMemoria` bastan para el formulario (`DestinosDePresupuestos` solo
    usa C1 y C2); para imputar de verdad, `Tests\Costuras\ArbolDePrueba`. Ejemplo completo: la orden de prueba de
    `tests/Modules/Erp/Presupuestos/Fixtures/`.
- **El catálogo (C4, plan 2.60):** un módulo lee productos solo por `CatalogoService` (`buscar()` para los selectores,
  `resumen()` para mostrar lo guardado, aunque esté descontinuado) y guarda la llave a `productos`. En pruebas,
  `$this->app->instance(CatalogoService::class, $falsa = new CatalogoEnMemoria)` y `$falsa->agregar(...)`; con la
  base, `Producto::factory()` (estados `conSerie`, `servicio`, `descontinuado`).

## Plantillas

ADR 0022; plan 2.11. Un módulo suma un tipo de plantilla para uno de sus documentos así:

- **Su definición:** una clase que extiende `Nucleo/Plantillas/DefinicionPlantilla`, registrada desde su provider en
  `RegistroPlantillas`. Clave `<area>.<modulo>.<cosa>` (ADR 0021); `permisoUsar()` es el de crear el documento (quien
  lo tiene ve las de la empresa); `permisoPublicar()`, uno por tipo con su área (varios tipos de un módulo pueden
  compartirlo); los dos los declara el módulo en `RegistroPermisos`, y van a `CatalogosSeederTest`.
- **Su contenido es suyo:** `capturar()` lo saca del documento en su `formatoActual()`; `validar()` dice qué le falta;
  `proponer()` arma la **propuesta editable** con una copia y **no crea nada**. Nunca clientes ni personas; montos solo
  si el tipo declara `grupoSensible()` (ADR 0016), y como texto decimal, nunca `float`. Un formato nuevo sube
  `formatoActual()` y sigue sabiendo leer los anteriores: las versiones guardadas no se modifican.
- **Sus rechazos explican qué hacer:** `PlantillaRechazada::conMotivo(…)` o el error de negocio de su módulo (el
  árbol usa la comprobación única de la 2.9b: «El árbol de PRY-… (v1, en borrador) ya tiene partidas: sigue armándolo
  en su pestaña, o desactiva las que sobren…»), nunca un error genérico.
- **Su documento guarda de dónde salió:** columna `plantilla_version_id` (FK con índice a `plantilla_versiones`, nula
  si no vino de una plantilla), que la acción de crear recibe de `PropuestaDesdePlantilla::$plantillaVersionId`; la
  pantalla lo muestra con `PlantillasService::origen()` («Videovigilancia v3»).
- **Su pantalla:** pone `@include('nucleo::filament.plantillas.panel', ['tipo' => …, 'documentoId' => …, 'alCerrar' =>
  '…'])` dentro de su `.tq-catalogos`. Usar una plantilla va por uno de dos caminos: escuchar
  `#[On('plantilla-elegida')]` (`plantillaId`, `tipo`) y llamar a `PlantillasService::aplicar()` con su contexto, o,
  si el documento ya tiene un panel para armarlo desde varios orígenes, ofrecerla ahí y pasar `'ofrecerUsar' => false`
  al panel, que entonces solo administra (el árbol: «Armar el árbol» → «Desde una plantilla», plan 2.11, D9). Si entre
  abrir y confirmar la plantilla tiene otra versión, se rechaza (D10): la acción recibe la versión revisada. Las
  acciones `nucleo.plantillas.*` no tienen permiso propio: el servicio revisa el del tipo.
- **Ejemplo completo:** el árbol de partidas, `Erp/Presupuestos/Plantillas/PlantillaArbol` con la acción
  `erp.presupuestos.armar-arbol-desde-plantilla`.
- **En pruebas:** `tests/Modules/Nucleo/Fixtures/TipoPlantillaDePrueba` es un tipo de ejemplo completo.

## Tarjetas de «Mien»

Plan 1.4l. Un módulo aporta tarjetas al tablero personal registrando una `TarjetaMien` en `RegistroTarjetasMien` desde su
provider (clave única, permiso obligatorio: `null` a propósito si es para todos) y un componente que extiende
`ComponenteTarjetaMien`. El componente vive en su módulo y solo escribe `contenido()`, con datos de los servicios de
**su propio** módulo (regla 2): nunca consulta modelos de otro. La base pone la carga diferida, el «No se pudo cargar»
(ante un error al montar, en un gancho, en una acción, al leer o al dibujar) y **revisa el permiso en cada petición**
(`boot()` final: la marca de la carga diferida no queda atada a la persona); una tarjeta usa `mount()` o `hydrate()`,
nunca `boot()` ni `render()`. Su vista se dibuja dentro de Livewire (puede usar `$this`) y escapa todo con `{{ }}`: la
base la imprime tal cual. Sus errores no llevan datos sensibles (regla 10): van al registro. Su prueba monta el
componente por su nombre, `Livewire::test('mien.<clave>', [...])`, porque la página no lo dibuja al abrir. **Por
decidir con la 1.7:** hoy el permiso se revisa con `can()`, como la barra; una tarjeta que dependa de un rol acotado a
un ámbito (`SeguridadService::puede`) necesitará revisarlo así.

## Pestañas de la ficha de personas y empresas

Plan 1.4m. Un módulo suma una pestaña a la ficha registrando una `PestanaFicha` en `RegistroPestanasFicha` desde su
provider (clave única, título, permiso obligatorio —`null` a propósito si es para todos—, componente, ícono propio que
no se repita y orden) y un componente que extiende `ComponentePestanaFicha`: recibe `$partyId`, lee de los servicios de
**su propio** módulo (regla 2) y no reescribe `boot()`, que revisa el permiso en cada petición. Su prueba monta el
componente por su nombre, `Livewire::test('ficha.<clave>', ['partyId' => …])`. Las reglas de ficha incompleta viven
solo en `Parties/ReglasFichaCompleta`: una regla nueva se agrega ahí, con su prueba.

## Estado de pantalla en la sesión

**Excepción a «toda acción de usuario está registrada»** (regla 7): lo que solo recuerda cómo dejó la persona una
pantalla durante su sesión (el estado del panel de aplicaciones, plan 1.4p; la ventana «Elegir módulos», plan 1.4f) se
guarda en la sesión de Laravel con un método del componente, sin acción, porque no escribe en las tablas del sistema
ni sirve fuera de la interfaz. Se valida contra su lista de valores y se borra al iniciar sesión (listener de `Login`).
Si un día tiene que seguir a la persona a otro equipo, deja de ser estado de pantalla: pasa a una preferencia con su
acción (`nucleo.cambiar-preferencia`).

## Modo desarrollo

Plan 1.4i. **Excepción a «toda acción de usuario está registrada»** (regla 7, D6): prender el modo, simular la fecha y
elegir variantes son métodos de sus componentes, no acciones de `EjecutorAcciones`, porque no escriben en las tablas
del sistema (solo en la sesión) y no existen fuera del equipo del desarrollador (registrarlas las pondría en el catálogo de producción). Todo
lo del modo pregunta a `Desarrollo\ModoDesarrollo` (`disponible()`, `activo()`), y cada entrada lo vuelve a revisar
(`boot()` con 404): nunca solo la vista. **Lo decorativo pide la hora a `ModoDesarrollo::ahoraDecorativo()`; lo del
negocio, nunca**: sigue con `Calendario::ahora()`.

## Contraseñas

Plan 1.4h. **Una sola regla, en `ClaveService::regla()`** (registrada con `Password::defaults()`): cualquier camino que
ponga una contraseña la usa (restablecerla, renovarla, cambiarla, el primer administrador); nunca una regla suelta.
La contraseña la cambian `ClaveService` y `SeguridadService::establecerClave()`, por acciones registradas, y el
restablecimiento de Filament («¿Olvidaste tu contraseña?», con la misma regla; al terminar, el evento `PasswordReset`
pasa por `ClaveService::alRestablecer()`). Nunca va a la bitácora, a los registros ni a un mensaje de error: todo
parámetro que la lleve (o lleve el código) se marca `#[\SensitiveParameter]`, para que no aparezca en un rastro de
error. El código por correo se guarda cifrado en la caché, con su vencimiento, y se pide y verifica bajo un candado por
persona. **Un middleware que debe correr también en las peticiones de Livewire se declara persistente** (por ejemplo,
`authMiddleware([...], isPersistent: true)`) y, si filtra por pantalla, revisa los componentes de la petición antes
que la ruta: Livewire lo reaplica con la ruta de la página de origen. En pruebas, la contraseña de las cuentas es
`UserFactory::CLAVE`, que cumple la regla.

## Preferencias por usuario

Plan 1.4g. Lo que ve la persona: `interfaz.md`, «Preferencias».

1. **Una preferencia nueva se declara en `RegistroPreferencias`** (`Nucleo/Preferencias/`) desde el provider de su
   módulo: clave (`grupo.nombre`), grupo, nombre, ayuda, tipo (`siNo`, `numero`, `opcion`, `lista`), defecto y
   validación (límites, opciones fijas o calculadas por persona, `soloSi()`), y si es `soloEnEscritorio()` o
   `sinMostrar()`. No se crea una columna ni un método suelto.
2. **Solo `PreferenciasUsuarioService` las lee y las escribe** (`valor()`, `siNo()`, `numero()`, `opcion()`,
   `cambiar()`, `restablecer()`), siempre con acciones registradas: `nucleo.cambiar-preferencia` (cambia solo las de
   quien la ejecuta) y `nucleo.restablecer-preferencias` (un grupo). Una acción propia solo si tiene lógica propia
   (`nucleo.mostrar-barra-modulos`) o es de una lista.
3. **El valor se guarda como texto en `preferencias_usuario` y el registro lo convierte a su tipo; nunca `float`.**
   Sin fila vale el defecto (volver al defecto borra la fila). Un valor guardado que ya no vale (cambiaron las
   opciones, se perdió el permiso) cuenta como el defecto, sin romper nada.
4. **En `preferencias_usuario` nunca van datos personales** (cumpleaños, teléfono, documento): van en «Mis datos».
5. **Una lista** (un conjunto de claves o ids) **vive en su propia tabla**, con una fila por elemento y su unicidad en
   la base. El registro la declara con tipo lista y su módulo dueño implementa `FuenteDeLista` (valores, opciones,
   restablecer, la acción que la guarda y sus datos, calculando lo no elegido contra lo mostrado al abrir).
6. **Pruebas obligatorias:** cada preferencia registrada tiene un defecto válido; una clave no registrada se rechaza;
   «Restablecer» vuelve a los defectos; nadie cambia las preferencias de otra persona.
7. **Toda animación nuestra respeta el ajuste del sistema y «reducir animaciones»** (`html.tq-sin-animaciones` junto a
   `prefers-reduced-motion`); lo vigila una prueba que recorre las vistas.
8. **Una preferencia no guarda estado de trabajo** (borradores, último registro abierto, pestaña actual): eso no va en
   `preferencias_usuario`.

## Registros

- Canal `daily` con plazo de conservación (`LOG_DAILY_DAYS`, a definir con TI).
- **Nunca** sueldos, datos médicos, contraseñas, tokens ni contenido de documentos en registros o
  mensajes de error: solo identificadores.

## Filament

- Cada módulo declara sus pantallas **para un panel concreto** en `RegistroPantallas` desde el
  `register()` de su provider (`registrar('app', paginas: […], recursos: […])`), y su `CajaModulo` para el
  modo modular. No usar `Panel::configureUsing()`: se aplica a todo panel, incluidos portales futuros. Pantallas de un mismo tema en un clúster (tiene pestañas
  propias: sirve sin barra lateral). Un Resource no contiene reglas de negocio: llama acciones y servicios.
- Un Resource de solo lectura declara `canCreate`/`canEdit`/`canDelete`/`canDeleteAny` en `false`: por
  defecto Filament los permite a quien puede ver la pantalla.
- Permisos por `Policy` + `spatie/laravel-permission`, **solo por roles**: ninguna acción da un permiso directo a un
  usuario. Un rol puede ser global o acotado a un ámbito (`permisos_por_ambito`); se pregunta con
  `SeguridadService::puede($usuario, $permiso, $ambito)`, y una lista se filtra en la base con
  `ambitosDonde()->filtrar($consulta, $columna)`, no registro por registro.
- **Nombres de permisos y acciones** (ADR 0021; plan 2.20a, N7): en los módulos de negocio llevan el prefijo de su área,
  `<area>.<modulo>.<verbo>` (`crm.cuentas.ver`, `erp.compras.orden.anular`); el permiso y la acción se llaman igual,
  salvo cuando varias acciones son una misma tarea y comparten permiso (las cuatro del árbol de partidas usan
  `erp.presupuestos.arbol.editar`; plan 2.1b, D1; en el núcleo, `tipos_cambio.registrar`). El núcleo sigue sin prefijo en sus permisos (`parties.ver`). Roles y permisos agrupa los de negocio por los dos
  primeros tramos («CRM · Cuentas»). Los permisos que no son acciones (ver, poder ser responsable) los declara el
  módulo en su provider, con nombre legible y grupo, en el registro de permisos de módulo (N8); nunca se agregan a
  mano a una lista del núcleo. Qué lleva el prefijo de área (`erp.`, `crm.`) en un módulo de negocio (plan 2.1b, D3 y
  D4):
  - **Lleva área:** permisos, acciones, claves de documento (`erp.proyectos.proyecto`; de ella salen los permisos de
    sus transiciones y sus listas de motivos), efectos (`erp.presupuestos.crear-pc99`), claves de alertas
    (`erp.presupuestos.partida-excedida`) y listas de motivos (`erp.presupuestos.exceso_partida`).
  - **No lleva área:** los grupos de parámetros y de avisos (`presupuestos.alerta_partida_porcentaje`, grupo de avisos
    `presupuestos`): van por tema y en snake_case, porque el núcleo no admite punto en ellos.
  - `PermisosConAreaTest` vigila las acciones y sus permisos, las claves de documento con los permisos de sus
    transiciones, y los efectos que usa alguna transición; revisa el área, no el módulo. Van por convención, porque sus
    registros no guardan desde qué módulo se declararon: las alertas, las listas de motivos sueltas y los permisos de
    `RegistroPermisos` (ver, responsable).
- **Lo propio y lo de todos** (ADR 0021; plan 2.20, D7; construido en la 2.20a, N5): cuando unos editan solo lo suyo y otros
  todo, son dos permisos, `<cosa>.editar` y `<cosa>.editar-propias`, donde «propia» = soy su responsable (el
  ejecutivo de una cuenta). Se pregunta siempre con `SeguridadService::puedeSobrePropio($usuario, $permisoTodas,
  $permisoPropias, $responsableUserId)`, y una lista se restringe en la base con su pareja,
  `SeguridadService::filtrarPropios()`; nunca se repite la regla en cada servicio. Tener los dos es tener el de todas,
  no un cruce de funciones. Cambiar de responsable es otra acción con su propio permiso (`crm.cuentas.reasignar`):
  `editar-propias` nunca lo cambia.
- **Campos sensibles** (ADR 0016): el modelo los declara junto a sus columnas en `camposSensibles()`
  (`columna => GrupoSensible`), y los que suenan sensibles sin serlo, en `camposNoSensibles()` con su motivo. Están
  ocultos salvo para quien tiene `campos.ver-<grupo>`. Lo oculto no se envía al navegador: no basta con ocultarlo a
  la vista. `CamposSensiblesTest` falla si una columna con nombre sospechoso (`RevisorCamposSensibles::SOSPECHOSOS`)
  no está declarada.
- **Un valor calculado hereda el grupo de los campos de donde sale**: un margen, un total o una cifra de reporte o
  de tablero calculada con costos es `costos` (o `margen`) y se oculta igual, en accessors, columnas de tabla,
  exportaciones y tarjetas de tablero.
- Ficha con historial: una clase que extiende `Nucleo\Filament\TablaHistorial` (tabla de Filament) y dice en
  `modelos()` de qué registros leer (que implementan `DescribibleEnBitacora`; las partes ya borradas, con
  `HistorialCambiosService::borradosDe()`), y `PestanaHistorial::make(MiHistorial::class, fn ($record) => $record->id)`
  arma la pestaña. Ejemplo: `HistorialDeParty`. El alta de cada modelo se lee en una sola fila
  (`resumenCreacionBitacora()`). Los campos que el usuario no puede ver
  se filtran en el servidor con `Nucleo\Seguridad\CamposVisibles` (`CamposVisiblesPorPermiso`).
- Textos de interfaz en español.

## Pruebas (Pest, contra PostgreSQL)

- Todo servicio y toda acción tienen pruebas del camino feliz y de sus rechazos.
- **Cada flujo tiene su escenario de aceptación del v4** (`docs/diseno/v4/parte-8-criterios-aceptacion.md`)
  como prueba, con los números exactos (margen 1,327.00; penalidad 205.96; ejecutado 1,248.00…).
  Un flujo no está terminado hasta que su escenario pasa.
- Toda regla «si pasa X, el sistema impide Y» tiene una prueba que intenta romperla.
- Concurrencia de correlativos y de aprobaciones: hay pruebas que la simulan.
- Las pruebas nunca llaman servicios externos reales (SUNAT, IA, correo): usan dobles.
- Factories por módulo (`<Modulo>/database/factories`, enlazadas con `#[UseFactory]`); datos de prueba
  inventados, nunca reales.
- Pest comparte las funciones globales entre archivos: una prueba no usa una función definida en otro
  archivo (pasa en el conjunto y falla sola). Cada archivo debe pasar por separado.
- **La suite completa corre en paralelo** (`composer check`: 6 procesos, cada uno con su base `techquk_test_test_N`,
  que Laravel crea y reutiliza). Una prueba no puede depender del orden ni compartir algo fuera de su base (un archivo
  fijo, una caché que no sea `array`): en paralelo, otra la pisaría. Las de `tests/Concurrencia` usan su propia base.

## Commits

En español, imperativo, con el módulo: `Compras: agrega aprobación con motivo de exceso`.
