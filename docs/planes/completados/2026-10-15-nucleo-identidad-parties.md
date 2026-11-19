# Plan: 1.3 · Identidad: parties y roles

- **Estado:** terminado
- **Módulo(s):** Nucleo (M1; toca M4 y M5)
- **Etapa:** 1 · tarea 1.3 · rama `nucleo-identidad-parties`
- **Creado:** 2026-10-15 · **Actualizado:** 2026-10-16

## Objetivo

Toda persona u organización existe una sola vez (`(tipo_documento_id, numero_documento)` único en la base,
con el número normalizado) y puede cumplir varios papeles con vigencia. Los demás módulos y los procesos
automáticos (OECE, padrón SUNAT) la buscan o la crean solo por `PartiesService`. Una persona con permiso la
registra, edita, desactiva y le asigna roles por acciones registradas, y su ficha tiene la pestaña de historial.

## Contexto que leí

- `AGENTS.md`, `docs/planes/mapa-etapas.md` (fila 1.3), `docs/planes/plantilla.md`.
- DBML v2 (`origin/main`, con el índice único del PR #10): M1 (l.27-144), `users` (l.461), `cuentas` (l.511).
- `docs/diseno/06-Arquitectura-Nucleo-Datos.md` §4 (M1), §8.5 (`tipos_documento_identidad`), §13 (`users`).
- `docs/diseno/v4/parte-2-crm.md` l.36-70 y 238-262 (crear cuenta por RUC, duplicados);
  `parte-7-transversal.md` l.154-160 (pestaña de historial), l.177 (padrón SUNAT), l.196 (migración: duplicados por RUC).
- `docs/convenciones/base-de-datos.md` y `codigo.md` completos.
- Planes completados: 1.1 (deuda l.160-165), 1.2 (D1, D2 y deuda l.342-344), 1.2b (E1, E5, E6), etapa 0 (l.111-113).
- Código: `Enums/Catalogo.php`, `EsCatalogo`, `TipoDocumento`, migración `…000007`, `HistorialCambiosService`,
  `NucleoServiceProvider`, `NucleoSeeder`, `Filament/Pages/Catalogos.php` (búsqueda con `unaccent`, l.90).
- `docs/decisiones/pendientes.md`: nada bloquea la tarea. Ver D9 (DNI y RUC 10), que no está en la lista.

## Deuda de tareas anteriores: a qué tarea va

| Deuda | Dónde | Por qué |
|---|---|---|
| Crear `tipos_documento_identidad` (plan 1.2, D1) | **1.3** | Es la FK de `parties` |
| Pestaña de historial en Filament (plan 1.1) | **1.3** | `parties` es la primera ficha sensible |
| `users.party_id` | 1.4 | Se agrega junto con `unidad_organizativa_id` y `ultimo_acceso`, en una sola migración que completa `users`. La 1.3 deja `parties` lista para recibirla |
| `User` a `Nucleo\Models` y prueba de que nadie fuera del núcleo escribe sus modelos | 1.4 | Va con completar `users` |
| Morph map (`enforceMorphMap`) | 1.4 | Obliga a mapear todos los modelos con relación polimórfica, `User` incluido (spatie guarda `model_type`): se hace junto con el traslado de `User`. Sin datos reales, la 1.3 no pierde nada |

## Decisiones (propuestas, por aprobar)

- **D1 · Alcance: parties, roles y sus dos catálogos.** Tablas `tipos_documento_identidad`, `roles_party`,
  `parties` y `party_roles`. `party_contactos`, `party_sedes` y `party_cuentas_bancarias` (también M1) **no**
  entran: sin pantalla ni consumidor serían tablas sin uso. **Decidido (2026-10-15):** van en una tarea 1.3b
  aparte, justo después de esta (como la 1.2b).
- **D2 · El DBML v2 gana al doc 06** (**aprobada 2026-10-15** con dos ajustes, abajo) (como D2 de la 1.2): `tipo` (`natural | juridica`) en vez de
  `es_persona_natural`; `party_roles.rol_party_id` contra el catálogo `roles_party` en vez de texto; sin `notas`
  ni `valida_modulo11`. Ajustes al DBML:
  - Las cuatro tablas llevan `auditoria()`; `parties` y los dos catálogos llevan `version` (se editan en pantalla).
  - `codigo` único en los dos catálogos.
  - `parties`: CHECK `tipo IN ('natural','juridica')`; CHECK «natural ⇒ `nombres` y `apellidos` no nulos»;
    CHECK `numero_documento ~ '^[0-9A-Z]{1,20}$'` (garantiza la normalización en la base: un proceso
    automático no puede colar `20123456789 ` y saltarse la unicidad).
  - `party_roles`: CHECK `hasta IS NULL OR hasta >= desde` y **restricción de exclusión** (aprobado 2026-10-15, en
    vez del índice único parcial): dos periodos del mismo rol de una misma party no se cruzan.
    `EXCLUDE USING gist (party_id WITH =, rol_party_id WITH =, daterange(desde, hasta, '[]') WITH &&)`.
    `hasta` cuenta como último día vigente (`'[]'`) y nulo es sin fin; un periodo nuevo empieza al día siguiente
    del cierre. Coherente con D8: un `hasta` futuro también bloquea. Necesita la extensión `btree_gist` (viene con
    PostgreSQL, igual que `unaccent`): migración propia antes de `party_roles`. La violación (SQLSTATE `23P01`) se
    traduce a `ErrorDeNegocio`, como la unicidad y la FK.
  - **RUC siempre con dígito verificador** (aprobado 2026-10-15): aunque no va `valida_modulo11`, el servicio valida
    el módulo 11 de todo RUC antes de guardarlo, por cualquier camino (pantalla, acción, `obtenerOCrear()` de los
    procesos automáticos). Un RUC inválido del OECE o del padrón se rechaza, no se guarda.
  - Notas de `estado_sunat`/`condicion_sunat` corregidas en el DBML: el estado es ACTIVO, BAJA…; la condición,
    HABIDO, NO HABIDO… (el DBML las tiene cruzadas; el doc 06 y SUNAT, al revés). Texto libre como lo reporta
    SUNAT, sin CHECK.
- **D3 · Los dos catálogos entran a `Catalogo`** (**aprobada 2026-10-15** con un ajuste, abajo) (casos `TiposDocumentoIdentidad` y `RolesParty`): se editan en
  Configuración → Catálogos con las acciones genéricas de la 1.2b, sin código nuevo de pantalla. Su `codigo` es
  inmutable y es lo que usa el código (`'6'` RUC, `'cliente'`). Datos iniciales en `CatalogosSeeder`:
  - Tipos de documento de identidad (catálogo 06 de SUNAT): `0` doc. tributario de no domiciliado sin RUC
    (proveedores del exterior, importaciones), `1` DNI (8), `4` carné de extranjería, `6` RUC (11),
    `7` pasaporte.
  - Roles: `cliente`, `proveedor`, `aliado`, `entidad` (entidad pública contratante), `colaborador`, `banco`
    (para elegir el banco de una cuenta bancaria sin buscar entre todos los proveedores). Agente de aduanas y
    otros: son datos, se agregan en pantalla cuando hagan falta.
  - **Códigos del sistema protegidos** (aprobado 2026-10-15): los que el código usa no se desactivan ni se borran
    desde la pantalla, igual que la moneda local en la 1.2. Tipos de documento `1` (DNI) y `6` (RUC); roles
    `cliente`, `proveedor`, `entidad`, `colaborador` y `banco`. Se declaran en `Catalogo::codigosDelSistema()`;
    `EdicionCatalogosService` generaliza `rechazarSiEsMonedaLocal()` a «rechazar si es del sistema» (la moneda
    local sigue saliendo del parámetro) y la pantalla oculta esas dos acciones en esas filas (`->hidden()`).
    `longitud` de DNI y RUC tampoco se cambia (aprobado 2026-10-15): la validación de esos dos es la de SUNAT, fija
    en `DocumentoIdentidad`. La edición rechaza el cambio y la pantalla deshabilita el campo en esas filas.
  - **`entidad` y `cliente`** (decidido 2026-10-15): `entidad` = organismo público que contrata por el Estado, aunque
    nunca se le haya vendido (lo asignará la convocatoria del OECE); `cliente` = hay relación comercial. Una
    municipalidad a la que se vende tiene los dos. En la etapa 2, `cuentas.es_entidad_publica` se deriva del rol
    `entidad` y no se guarda aparte (anotado en el DBML).
- **D4 · Normalización y validación del documento** (**aprobada 2026-10-15**) en una clase del núcleo
  (`DocumentoIdentidad`), por la que pasa todo número antes de guardarse, por cualquier camino:
  - Normaliza: quita espacios, guiones y puntos; mayúsculas. El número es siempre texto: nunca se convierte a entero
    (perdería los ceros iniciales).
  - Valida: DNI (`1`) exactamente 8 dígitos; RUC (`6`) 11 dígitos, prefijo 10, 15, 16, 17 o 20 y módulo 11 (pesos
    5-4-3-2-7-6-5-4-3-2; resto 10 → 0, 11 → 1); los demás, la `longitud` del catálogo si la tiene, si no 1 a 20 de
    `[0-9A-Z]`. Las reglas de DNI y RUC son fijas (SUNAT), no configurables.
  - Deduce el tipo: DNI, CE, pasaporte y RUC 10/15/16/17 ⇒ natural; RUC 20 ⇒ jurídica; `0` y tipos agregados ⇒ lo
    elige quien registra.
  - Natural: `nombres` y `apellidos` obligatorios y `razon_social` la arma el servicio («apellidos nombres», orden de
    SUNAT, sin cambiar mayúsculas). Jurídica: `razon_social` obligatoria.
  - Da la pareja DNI ↔ RUC 10 para el aviso de D9.
  - **Regla adicional (aprobada 2026-10-15):** CHECK en `parties`: jurídica ⇒ `nombres` y `apellidos` nulos.
  - **Prueba del cero inicial (pedida 2026-10-15):** DNI `01234567` y su RUC 10 `10012345670` (suma 111, resto 1,
    11 − 1 = 10 ⇒ dígito 0). Verifica que el cero no se pierde al normalizar, al calcular la pareja en los dos
    sentidos, al guardar y releer de la base, y que el aviso de D9 encuentra la pareja.
- **D5 · El documento no se edita** (**aprobada 2026-10-15**). Tipo y número de documento son la identidad: cambiarlos
  es otra party. **El tipo de persona tampoco se edita** (sale del documento; en el tipo `0` se elige al crear y queda).
  Sí se editan (acción `editar`, con `version`): en una jurídica, `razon_social` (cambio de denominación) y
  `nombre_comercial`; en una natural, `nombres` y `apellidos`, y el servicio rearma `razon_social` (nunca se escribe a
  mano, D4); además dirección, ubigeo, teléfono y correo. Un número mal escrito se corrige borrando la party, que
  solo se puede si **nunca se usó**: sus propios roles no cuentan como uso y se borran con ella en la misma
  transacción (queda en la bitácora); decide la FK de cualquier otra tabla (como E1 de la 1.2b), y si la base lo
  impide, el mensaje ofrece desactivar. Si ya se usó, solo se desactiva. Permisos `parties.borrar` y
  `parties.desactivar` por separado.
- **D6 · `PartiesService` es la API pública de M1** (**aprobada 2026-10-15** con dos ajustes, incluidos aquí). Los
  demás módulos guardan solo el id (regla 2). Lo que devuelve son objetos de solo lectura de `Nucleo/Parties/`, nunca
  modelos: `ResumenParty` (`id`, `etiqueta`, `activa`) y `PartyObtenida` (lo mismo + `creada`).
  - `buscarPorDocumento(codigoTipo, numero): ?ResumenParty` (normaliza antes; activa o no).
  - `obtenerOCrear(datos): PartyObtenida`, idempotente: inserta en transacción anidada y, si choca con la unicidad,
    lee la existente. **Si existe pero está desactivada, la devuelve sin reactivarla y con `activa = false`**: quien
    llama decide (aprobado 2026-10-15). No asigna roles ni pisa datos de una existente (el padrón tendrá su propio
    método de actualización cuando se construya).
  - `registrar(datos)` (devuelve id y la pareja de D9), `posibleMismaPersona(codigoTipo, numero): ?ResumenParty`.
  - `editar(id, datos, version)`, `editarTributarios(id, datos, version)`, `desactivar`, `activar`, `borrar` (D5).
  - `asignarRol(partyId, codigoRol, desde)`, `cerrarRol(partyId, codigoRol, hasta)`,
    `tieneRol(partyId, codigoRol, fecha = hoy): bool`.
  - **Selectores (aprobado 2026-10-15, reemplaza `opciones()`, que con miles de entidades del OECE no sirve):**
    `buscar(texto, codigoRol = null, limite = 20): list<ResumenParty>` solo activas; busca por número (prefijo del
    número normalizado) o por razón social y nombre comercial (sin acentos ni mayúsculas, D11); primero la
    coincidencia exacta de número, luego por razón social; mínimo 2 caracteres; `limite` tope 50.
    `etiquetaDe(id): ?string` da la etiqueta del valor ya guardado aunque esté inactivo (regla de AGENTS.md sobre
    selectores), con «(inactiva)» al final. Etiqueta: **«razón social · número de documento»**. Índice
    `numero_documento varchar_pattern_ops` para la búsqueda por prefijo.
  - Errores de negocio claros: RUC inválido, duplicado, versión cambiada, en uso, periodos que se cruzan.
- **D7 · Banderas tributarias con permiso aparte** (**aprobada 2026-10-15**; como E6 de la 1.2b): `es_agente_retencion`,
  `es_agente_percepcion` y `es_buen_contribuyente` deciden retenciones y percepciones al pagar o cobrar. Las cambia
  la acción `nucleo.parties.editar-tributarios` (permiso `parties.editar-tributarios`, con `version`, a la bitácora);
  `editar` no las toca. Nacen en `false`: ni `registrar` ni `obtenerOCrear()` las reciben. `estado_sunat` y
  `condicion_sunat` no se editan en pantalla (la ficha las muestra en solo lectura): las llenará el padrón con su
  propio método. Las marcas manuales mandan sobre el padrón (doc 06 §4.1).
- **D8 · `party_roles` sin `es_activo`.** El DBML trae `desde`, `hasta` y `es_activo`: dos maneras de decir que un
  rol terminó. **Decidido (2026-10-15):** se quita `es_activo` y manda `hasta` (vigente en un día = `hasta` nulo o igual o posterior a ese día: el día `hasta` cuenta).
  Se corrige el DBML.
- **D9 · DNI y RUC 10 de la misma persona.** El RUC 10 contiene el DNI (`10` + DNI + dígito). Con la unicidad por
  documento, un colaborador con DNI que emite recibos con su RUC 10 serían dos parties. **Decidido (2026-10-15):**
  se sigue el DBML (una party por documento) y se **avisa sin bloquear**:
  - Al registrar un RUC que empieza con `10`, si existe una party con el DNI que contiene (dígitos 3 a 10).
  - Al registrar un DNI, si existe la party con el RUC 10 que lo contiene. Ese RUC se calcula
    (`10` + DNI + dígito verificador), así que la búsqueda es exacta y usa el índice único.
  - `DocumentoIdentidad` da el documento pareja (`dniDeRuc10()` y `ruc10DeDni()`), y
    `PartiesService::posibleMismaPersona(codigoTipo, numero): ?ResumenParty` devuelve la party pareja, si existe.
  - La pantalla muestra el aviso en el paso del documento, antes de seguir, con enlace a la party existente;
    la persona decide si continúa.
  - Sin interfaz: la acción `registrar` devuelve el id creado y la party pareja si existe, así una API futura
    también puede avisar. `obtenerOCrear()` (procesos automáticos) no avisa: no hay persona que lo lea.
  - Se anota en `pendientes.md` si la misma persona debería ser una sola party (con Contabilidad y RRHH); el
    aviso no lo resuelve, solo evita el duplicado sin querer.
- **D10 · Bitácora y pestaña de historial** (**aprobada 2026-10-15**). `parties` y `party_roles` usan `RegistraCambios`
  (`version` fuera; no hay campos sensibles que excluir). La pestaña es un componente genérico de `Nucleo/Filament`
  que recibe uno o varios modelos; `HistorialCambiosService` gana un método que lee varios a la vez, por fecha. En la
  ficha de party: cambios de la party y de sus roles en una sola línea de tiempo, la más reciente primero. Cada fila:
  fecha y hora en Lima, quién («Sistema» si fue un proceso), evento, campo con su nombre legible, anterior y nuevo
  legibles (booleanos «Sí»/«No», FK como el nombre al que apunta); en los roles, de qué rol se trata. Nombres de
  campo desde `etiquetasBitacora()` de cada modelo; si falta uno, se muestra la columna (nada desaparece). La ve
  quien tiene `parties.ver`. Una party borrada no tiene ficha: su historial queda en `auditoria_cambios`, sin
  pantalla de consulta en esta tarea.
- **D11 · Búsqueda sin acentos como scope del núcleo** (**aprobada 2026-10-15**; lo pide `base-de-datos.md` §
  Búsquedas y hoy está escrita dentro de `Catalogos.php`, l.90): trait `BuscaSinAcentos` en `Nucleo/Models/Concerns`
  con `scopeBuscarTexto($consulta, $texto, $columnas)`: `unaccent(col) ilike unaccent(?)` con el texto como
  parámetro, `%` y `_` del usuario escapados, texto vacío no filtra. Catálogos pasa a usarlo (mismo comportamiento,
  con prueba); `PartiesService::buscar()` también, sobre `razon_social` y `nombre_comercial`. El número de documento
  no pasa por el scope: se normaliza (`DocumentoIdentidad`) y se busca por prefijo con su índice (D6).
  **Fuera:** índice para la razón social; si con el OECE la búsqueda se vuelve lenta, `pg_trgm` con una función
  `unaccent` inmutable propia (posible mejora, no se construye).
- **D12 · Pantalla «Personas y empresas»** (**aprobada 2026-10-15** con dos detalles, incluidos aquí). `PartyResource`,
  panel `app`, `/personas`, fuera del clúster Configuración; entrada de la barra lateral (modo paneles) y `CajaModulo`
  propia (modo modular) con permiso `parties.ver`; colores de `docs/convenciones/interfaz.md`.
  - **Lista:** tipo, razón social, documento, roles vigentes, activa. Buscador único (nombre o número, D6/D11);
    filtros por rol, tipo de persona y activas/inactivas (por defecto, activas).
  - **Crear en dos pasos.** Paso 1, documento: número inválido ⇒ error ahí; ya existe y activa ⇒ «Ya está
    registrada» con enlace, no sigue; **ya existe e inactiva ⇒ «Ya está registrada, pero está inactiva», con enlace
    y la acción Activar para quien tiene `parties.desactivar`** (aprobado 2026-10-15); pareja D9 ⇒ aviso con enlace
    y sigue. **Después de validar el número, el paso 1 consulta la fuente de padrón** (ver «Preparado para lo
    diferido») y precarga el paso 2 con lo que devuelva. Paso 2, datos: tipo deducido (se elige solo en `0`);
    natural: nombres y apellidos; jurídica: razón social y nombre comercial; dirección fiscal, ubigeo (selector con
    buscador), teléfono, correo; roles iniciales opcionales (desde hoy). Sin marcas tributarias (D7).
  - **Ficha** con cabecera (razón social, documento, estado) y pestañas Datos (solo lectura, con marcas tributarias y
    datos de SUNAT; acciones Editar, Editar datos tributarios, Desactivar/Activar y Borrar con confirmación),
    Roles (periodos vigentes y cerrados; «Asignar rol» y «Cerrar») e Historial (D10). Pestañas ampliables para la
    1.3b. Versión leída al abrir (`fillForm`); acciones por fila ocultas con `->hidden()`.
  - Todo por `AccionFilament`, sin reglas en la pantalla. Permisos: `parties.ver`, `parties.crear`, `parties.editar`,
    `parties.editar-tributarios`, `parties.desactivar`, `parties.borrar`, `parties.asignar-roles` (asignar y cerrar).
    `PermisosSeeder` los crea; el usuario de desarrollo los recibe todos; los roles, en la 1.4.

## Preparado para lo diferido

- **Historial y restricciones por campo (requisito para la 1.4, pedido 2026-10-15):** cuando existan las
  restricciones por campo, la pestaña de historial **oculta los cambios de los campos que el usuario no puede ver en
  la ficha**; si no, filtraría datos restringidos (costos, márgenes, sueldos). Se filtra en el servidor, antes de
  armar las filas (no basta con ocultarlas en el navegador, `codigo.md` § Filament). Se enchufa así: el componente
  pide los campos visibles a una interfaz del núcleo (`CamposVisibles::de(User, Model): ?list<string>`, nulo = todos)
  con una sola implementación en la 1.3 que permite todo; la 1.4 la reemplaza por la que lee `restricciones_campo`.
  Con prueba en la 1.3: una implementación de prueba que oculta un campo y la pestaña no muestra sus cambios.

- **`users.party_id` (1.4):** `parties` existe, `PartiesService::obtenerOCrear()` da el id y `buscar()` el selector; la 1.4 solo agrega la columna.
- **Padrón SUNAT y convocatorias del OECE:** entran por `obtenerOCrear()`; `estado_sunat`/`condicion_sunat` ya existen
  y el servicio los acepta; la consulta irá detrás de una interfaz (patrón `FuenteTipoCambio`) cuando se construya.
- **Precarga desde el padrón de SUNAT en «Crear» (pedido 2026-10-15; diseño del CRM, «Nueva cuenta»):** interfaz
  `FuentePadronContribuyentes::consultar(codigoTipo, numero): ?DatosPadron` (razón social, dirección, ubigeo,
  estado y condición) con una sola implementación en la 1.3, `SinPadron`, que devuelve `null`. El paso 1 la llama
  después de validar el número (fuera de toda transacción, como `FuenteTipoCambio`) y precarga el paso 2 si hay
  datos. Construir la integración = otra implementación y enlazarla en el provider. Sin fuente de prueba en la 1.3
  (decisión del desarrollador, ver bitácora): la prueba de la precarga va con la integración. Esa integración
  también debe decidir qué hacer con `estado_sunat`/`condicion_sunat` de `DatosPadron` (hoy el alta no los guarda)
  y con el nombre completo de un RUC 10 (pendiente en `pendientes.md`).
- **Marcas tributarias contra el padrón (pedido 2026-10-15, para cuando se construya la integración):** si una marca
  manual (retención, percepción, buen contribuyente) no coincide con lo que publica SUNAT, el sistema **avisa** a
  quien tiene `parties.editar-tributarios` y **no la cambia solo**. Se enchufa así: el método del padrón compara y
  emite un evento (p. ej. `MarcaTributariaDifiereDelPadron`, con id de party y marca); el aviso lo da el mecanismo
  de alertas idempotentes (1.10), con clave por party + marca + valor del padrón para no repetirlo. La 1.3 solo
  deja las marcas separadas en su propia acción y permiso, que es a quien se avisa.
- **Cuentas del CRM (etapa 2):** «cuenta» = party con rol `cliente` + fila en `cuentas`; el CRM usa `buscarPorDocumento`,
  `obtenerOCrear` y `asignarRol`.
- **Contactos, sedes y cuentas bancarias (1.3b):** la ficha deja las pestañas como lista ampliable y
  `PartiesService` es el lugar de sus métodos.
- **Unir DNI y RUC 10 en una party (D9):** si se decide, `posibleMismaPersona()` ya encuentra la pareja; el aviso
  pasaría a bloqueo o a fusión sin cambiar la búsqueda.

## Pasos

- [x] 1. Migración `tipos_documento_identidad` y `roles_party`; modelos, factories, casos en `Catalogo`, seeder.
- [x] 2. `DocumentoIdentidad` (normalizar, validar, deducir tipo, documento pareja DNI ↔ RUC 10) con pruebas
      unitarias (RUC válido e inválido, DNI, CE, pareja en los dos sentidos, RUC 20 sin pareja, DNI `01234567` ↔
      RUC `10012345670` sin perder el cero).
- [x] 3. Migración `btree_gist`; migración `parties` y `party_roles` con CHECKs, índices y exclusión; modelos con `ConAuditoria` y `RegistraCambios`; factories.
- [x] 4. `PartiesService` (D6) con pruebas: unicidad (también con número sin normalizar), `obtenerOCrear` concurrente,
      bloqueo optimista, borrar usada vs. no usada, roles (periodos que se cruzan rechazados también saltándose el servicio, cerrar, reabrir al día siguiente),
      RUC inválido rechazado también por `obtenerOCrear`, `obtenerOCrear` sobre una desactivada (la devuelve
      inactiva y no la reactiva), `buscar` (por número y por razón social sin acentos, solo activas, límite, orden) y
      `etiquetaDe` de una inactiva,
      `posibleMismaPersona` (D9), y el DNI `01234567` guardado y releído igual, con su aviso hacia `10012345670`.
- [x] 5. Acciones registradas (`registrar`, `editar`, `editar-tributarios`, `desactivar`, `activar`, `borrar`,
      `asignar-rol`, `cerrar-rol`) con pruebas sin interfaz, incluidos los rechazos por permiso; `registrar`
      devuelve la party pareja (D9) y crea igual.
- [x] 6. Trait `BuscaSinAcentos` (D11) y `Catalogos.php` pasa a usarlo; `PartiesService::buscar()` (D6) con sus pruebas
      (pasa aquí desde el paso 4: necesita el scope).
- [x] 7. Componente de historial (D10) con prueba, incluida la interfaz `CamposVisibles` (prueba que oculta un campo).
- [x] 8. **Roles iniciales al registrar (decidido 2026-10-15, opción A):** campo de lista en el mecanismo de acciones
      (`TipoCampo` nuevo, `Campo::listaDe()`, validación de cada elemento en `EjecutorAcciones`, selector múltiple en
      `AccionFilament`) y `registrar` con los roles en **una sola transacción**: si un rol falla no se registra nada,
      con el aviso «No se registró nada: el rol X ya no está disponible». El campo aparece solo si el usuario también
      tiene `parties.asignar-roles`, y **la acción lo exige** cuando llegan roles (prueba sin pantalla).
      `PartyResource` (D12) y `FuentePadronContribuyentes`/`SinPadron` con pruebas de pantalla (crear, duplicado,
      duplicado inactivo con Activar según permiso, aviso DNI ↔ RUC 10 que deja continuar,
      editar con versión leída al abrir, roles, historial).
- [x] 9. DBML y documentos: ajustes de D2 y D8, D9 en `pendientes.md`, fila 1.3b en el mapa, `codigo.md` (lo que M1 da a otros módulos).
- [x] 10. Revisión de `revisor-arquitectura`.
- [x] 11. `composer check` en verde (corre también en este entorno: PHP 8.5.4 y el PostgreSQL de Docker).

## Criterio de aceptación

- No se pueden crear dos parties con el mismo tipo y número, ni por pantalla, ni por servicio, ni con el número escrito
  distinto (`20123456789` y ` 20123456789 `); la base lo rechaza aunque se salte el servicio.
- Dos `obtenerOCrear()` simultáneos con el mismo RUC devuelven el mismo id.
- Un RUC con dígito verificador incorrecto se rechaza con mensaje claro.
- Dos periodos del mismo rol de una party no se cruzan (la base lo rechaza aunque se salte el servicio); cerrarlo y
  reabrirlo al día siguiente deja los dos periodos.
- Ningún camino guarda un RUC con dígito verificador incorrecto.
- La ficha muestra en Historial quién cambió qué, cuándo y el valor anterior.
- Registrar el RUC `10` + DNI + dígito de una persona ya registrada por DNI muestra el aviso con enlace a ella y
  deja crear; lo mismo al registrar el DNI de un RUC 10 existente. Un RUC 20 nunca avisa.
- Un DNI con cero inicial (`01234567`) conserva el cero en todo el recorrido y su RUC 10 (`10012345670`) lo encuentra.

## Bitácora

- 2026-10-15 — Rama `nucleo-identidad-parties` desde `origin/main` (incluye PR #10). Plan escrito; decisiones
  D1–D12 presentadas al desarrollador. Decididas D1 (contactos, sedes y cuentas a la 1.3b), D8 (sin `es_activo`)
  y D9 (dos parties y pendiente). Las demás, a la espera de aprobación. Sin código todavía.
- 2026-10-15 — D9 ampliada por el desarrollador: aviso (no bloqueo) DNI ↔ RUC 10 en los dos sentidos. Sigue la
  espera de aprobación de D2–D7 y D10–D12.
- 2026-10-15 — D2 aprobada con dos ajustes: exclusión con `daterange` en `party_roles` (en vez del índice parcial) y
  módulo 11 en todo RUC por cualquier camino.
- 2026-10-15 — D3 aprobada con códigos del sistema protegidos (DNI, RUC, cliente, proveedor, entidad, colaborador,
  banco). Pendiente: el desarrollador preguntó la diferencia entre `entidad` y `cliente`.
- 2026-10-15 — Aprobado: longitud de DNI y RUC no editable; `entidad` se mantiene como rol (opción 1) y la nota en
  `cuentas.es_entidad_publica` del DBML ya está escrita.
- 2026-10-15 — D4 aprobada, con CHECK jurídica ⇒ sin nombres ni apellidos y prueba del cero inicial
  (`01234567` ↔ `10012345670`, dígito calculado y verificado).
- 2026-10-15 — D5 aprobada, con el tipo de persona no editable. Confirmado: razón social (jurídica), nombres y
  apellidos (natural, la razón social se rearma) sí se editan.
- 2026-10-15 — **Limitación conocida:** una party ya usada con el número mal escrito (sobre todo un DNI, que no tiene
  dígito verificador que atrape el error) solo se puede desactivar y crear de nuevo bien; sus documentos viejos
  quedan colgados de la party desactivada. Si en la práctica pasa seguido, evaluar una acción «corregir documento»
  con permiso especial, motivo obligatorio y bitácora.
- 2026-10-15 — D6 aprobada con dos ajustes: `buscar()` con límite y etiqueta «razón social · número» en vez de
  `opciones()`; `obtenerOCrear()` devuelve una desactivada sin reactivarla e indica que está inactiva.
- 2026-10-15 — D6 aprobada con los agregados (objetos de resultado, `etiquetaDe`, índice por prefijo). D7 aprobada.
  Anotado en «Preparado para lo diferido»: diferencia entre marca manual y padrón ⇒ aviso al permiso tributario,
  nunca cambio automático.
- 2026-10-15 — D10 aprobada. Requisito para la 1.4: el historial oculta los cambios de campos restringidos; queda
  preparado con la interfaz `CamposVisibles` (ver «Preparado para lo diferido»). Al cerrar, llevarlo a la deuda
  del plan y a la fila 1.4 del mapa.
- 2026-10-15 — Aprobadas la interfaz `CamposVisibles` con su prueba y D11.
- 2026-10-15 — D12 aprobada con dos detalles: duplicado inactivo con Activar según permiso, y paso 1 preparado para
  la precarga del padrón (`FuentePadronContribuyentes` + `SinPadron`). **Todas las decisiones aprobadas**; empieza
  el paso 1.
- 2026-10-15 — **Paso 1 terminado.** Migración `…2026_10_15_000001` (CHECK `longitud BETWEEN 1 AND 20` y de `version`),
  modelos `TipoDocumentoIdentidad` y `RolParty`, factories, casos en `Catalogo` (constantes `Catalogo::DNI` y
  `Catalogo::RUC`, `codigosDelSistema()`, `camposFijosDelSistema()`, `esDelSistema()`), seeder. Códigos de rol en
  minúsculas (regex en `campoCodigo`). `EdicionCatalogosService`: `rechazarSiEsMonedaLocal` pasa a
  `rechazarSiEsDelSistema` (moneda local + códigos del sistema) y `esDelSistema()` público para la pantalla;
  rechaza cambiar un campo fijo solo si cambia de verdad (`isDirty`). `AccionFilament::desde()` gana un cuarto
  parámetro `$soloLectura` (campos deshabilitados que se envían igual); Catálogos lo usa para la longitud de DNI y
  RUC y oculta desactivar/borrar en lo del sistema, también la moneda local (antes solo lo rechazaba el servicio).
  Pruebas: `CatalogosIdentidadTest` (23) y datasets ampliados en `CatalogosIntegridadTest`, `CatalogoDefinicionTest`
  y `CatalogosSeederTest`. **Hallazgo:** este entorno sí corre `composer check` (PHP 8.5.4, Postgres en Docker);
  en verde: 441 pruebas.
- 2026-10-15 — **Paso 2 terminado.** `Nucleo/Parties/DocumentoIdentidad` (objeto de valor: `de()`, `normalizar()`,
  `digitoVerificadorRuc()`, `tipoPersona()`, `pareja()`), enum `TipoPersona` y excepción `DocumentoIdentidadInvalido`.
  Códigos de CE (`4`) y pasaporte (`7`) como constantes de la clase: solo sirven para deducir el tipo; si se
  desactivan en el catálogo no se rompe nada. Normaliza con `/[\s.\-]+/u`; la `Ñ` no pasa (la base solo acepta
  `[0-9A-Z]`). `DocumentoIdentidadTest` (42 pruebas), con RUC de resto 10 (`10012345670`) y resto 11
  (`20000003001`). Guardar y releer el DNI `01234567` va en el paso 4 (necesita la tabla). `composer check` en
  verde: 483 pruebas.
- 2026-10-15 — **Paso 3 terminado.** Migraciones `…000002` (btree_gist) y `…000003` (parties, party_roles). CHECKs:
  `parties_tipo_check`, `parties_numero_documento_check`, `parties_razon_social_check` (no en blanco; agregado),
  `parties_natural_nombres_check`, `parties_juridica_nombres_check`, versiones; exclusión `party_roles_sin_cruce`;
  índice `parties_numero_documento_prefijo_index` (`varchar_pattern_ops`). El índice único (tipo, número) sirve de
  índice a la FK `tipo_documento_id` (el script lo acepta). **Ajuste a D2:** `party_roles` lleva `version`: lo exige
  `base-de-datos.md` para toda tabla editada desde pantalla (cerrar un rol), y la convención manda sobre la
  decisión. Modelos `Party` y `PartyRol` (`ConAuditoria` + `RegistraCambios`, `version` fuera de la bitácora);
  factories (`PartyFactory` natural con DNI por defecto, `juridica()` con RUC 20 válido, `inactiva()`).
  `PartiesIntegridadTest` (29), incluido el DNI `01234567` guardado y releído. `composer check` en verde: 512.
- 2026-10-15 — **Paso 4 terminado.** `PartiesService` con `registrar`, `obtenerOCrear`, `buscarPorDocumento`,
  `posibleMismaPersona`, `etiquetaDe`, `editar`, `editarTributarios`, `desactivar`/`activar`, `borrar`,
  `asignarRol`, `cerrarRol(partyId, codigoRol, hasta, versionLeida)` y `tieneRol`. Entradas y salidas en
  `Nucleo/Parties/`: `DatosParty`, `ResumenParty`, `PartyObtenida`, `PartyRegistrada`; errores en `PartyRechazada`.
  - `tieneRol` usa `desde <= día AND (hasta IS NULL OR hasta >= día)`: el día `hasta` cuenta, como la exclusión
    `'[]'`. Sin fecha, hoy en Lima (`config('app.timezone')`). Prueba pedida: `hasta` 31/03 ⇒ true; 01/04 ⇒ false.
  - `obtenerOCrear` inserta primero (punto de guardado) y, si choca con la unicidad, lee la existente. **La prueba
    de carrera es determinista**, no con dos conexiones: `RefreshDatabase` envuelve cada prueba en una transacción
    y una segunda conexión no vería la fila. Se simula dejando la fila del «otro proceso» antes de llamar; como no
    se pregunta antes de insertar, es exactamente el camino de la carrera, y se verifica que la transacción
    externa sigue sana. Una prueba con procesos paralelos de verdad queda para la 1.6 (numeración), que la
    necesita igual y tendrá que resolver la base sin transacción.
  - `cerrarRol` cierra el periodo con `hasta` nulo (solo puede haber uno: dos abiertos se cruzan) y exige su `version`.
  - Natural: si llega `razonSocial` se rechaza (D4: la arma el sistema). **Pregunta abierta para el OECE/padrón:**
    esas fuentes dan el nombre completo de una persona con RUC 10, sin separar nombres y apellidos, y la base exige
    los dos en una natural. Hay que decidir antes de construir esa integración (no bloquea la 1.3).
  - `buscar()` pasa al paso 6. `PartiesServiceTest` (46). `composer check` en verde: 558.
- 2026-10-15 — **Paso 5 terminado.** Acciones en `Acciones/Parties/` (base `AccionParty`, clave
  `nucleo.parties.<operacion>`): `registrar` (`parties.crear`), `editar`, `editar-tributarios`, `desactivar` y
  `activar` (`parties.desactivar`), `borrar`, `asignar-rol` y `cerrar-rol` (`parties.asignar-roles`). Tipo de
  documento y rol se eligen por id del catálogo (`Campo::elementoDe`) y la acción pasa el código al servicio.
  `parties.ver` va en `PermisosSeeder::DE_LECTURA`. **Ajuste al servicio:** `EjecutorAcciones` entrega todos los
  campos declarados (los que faltan, nulos), así que `editar` acepta vacíos los campos del otro tipo de persona y
  solo rechaza si traen valor; y `editar` por acción reemplaza todos los datos generales (un campo vacío se
  borra: el formulario manda lo que se ve). Los roles iniciales al registrar (D12) no van en la acción: no hay
  campo de lista en `Campo`; la pantalla llamará `asignar-rol` después (se decide en el paso 8).
  Mapa de etapas: la fila 1.6 pide que su prueba de concurrencia real cubra también `obtenerOCrear`.
  `AccionesPartiesTest` (25). `composer check` en verde: 584.
- 2026-10-15 — **Paso 6 terminado.** `BusquedaSinAcentos::aplicar()` (clase: PHP depreca llamar estáticos de un
  trait) y el trait `BuscaSinAcentos` con `scopeBuscarTexto`, en `EsCatalogo` y `Party`. Escapa `%`, `_` y `\`.
  Catálogos lo usa (antes `codigo` se buscaba con `ilike` sin escapar). `PartiesService::buscar()`: activas,
  prefijo del número normalizado con `like` (índice `varchar_pattern_ops`) o razón social / nombre comercial sin
  acentos; con rol, vigente hoy; número exacto primero, luego razón social; mínimo 2 caracteres; tope 50.
  `BusquedaSinAcentosTest` (11). `composer check` en verde: 595.
- 2026-10-15 — Roles iniciales: el desarrollador elige la opción A (campo de lista, una sola operación). Va en el paso 8.
- 2026-10-15 — **Paso 7 terminado.** `Nucleo/Historial/`: interfaz `DescribibleEnBitacora` (etiquetas, sujeto,
  valor legible) con el trait `DescribeBitacora` (— para vacío, Sí/No, fechas d/m/Y); `HistorialLegible::filas(User,
  modelos)` arma una fila por campo, la más reciente primero, campos en el orden de las etiquetas, sin `id` ni
  marcas de auditoría; al crear y borrar, solo los campos con valor; autor «Sistema» si fue el usuario de sistema.
  `HistorialCambiosService::deVarios()`. `EventoAuditoria::etiqueta()` (Creó, Cambió, Borró). `Party` y `PartyRol`
  describen su bitácora (FK como nombre: tipo de documento, ubigeo, rol; «sin fin» para `hasta` vacío).
  `Nucleo/Seguridad/CamposVisibles` + `TodosLosCamposVisibles` (enlazada en el provider; la 1.4 la cambia).
  `Filament/PestanaHistorial::make(fn ($record) => [...])` devuelve la pestaña con la vista
  `nucleo::filament.historial`; su uso dentro de una ficha se prueba en el paso 8. `HistorialLegibleTest` (6),
  incluida la implementación de prueba de `CamposVisibles` que oculta el teléfono. `composer check` en verde: 601.
- 2026-10-15 — **Paso 8, parte 1 (sin pantalla).** `TipoCampo::ListaCatalogo` y `Campo::listaDe()` (reglas
  `array` y, por elemento, `integer|distinct|exists`; `reglasDeElementos()`; vacío llega como `[]`); el ejecutor
  valida `campo.*`; `AccionFilament` lo dibuja como selector múltiple. Mensajes `array` y `distinct` en
  `lang/es`; `TraduccionesValidacionTest` revisa también las reglas por elemento. `PartiesService::registrar
  (datos, roles)` en una transacción: si un rol falla, «No se registró nada: el rol X ya no está disponible».
  `RegistrarParty` con campo `roles`: si llegan roles, exige además `parties.asignar-roles` (si no,
  `AccionRechazada` «No tienes permiso para «Asignar rol».», sin registrar nada). Historial (pedido 2026-10-15):
  la creación de un rol es una sola fila («Asignó rol Cliente desde 04/10/2026», con «hasta» si ya lo tiene) por
  `resumenCreacionBitacora()`; sin la fila «Persona o empresa». `composer check` en verde: 610.
- 2026-10-15 — Padrón (pedido 2026-10-15): no se integra ni se simula en esta tarea; el paso 2 queda preparado
  (interfaz y `SinPadron`), sin fuente de prueba que devuelva datos.
- 2026-10-15 — **Paso 8, parte 2 (pantalla). Paso 8 terminado.** `Filament/Resources/Parties/`: `PartyResource`
  (`/personas`, lista con buscador por nombre sin acentos o comienzo del número, filtros por rol vigente, tipo y
  activas; ficha con pestañas Datos, Roles e Historial), `ListarParties`, `NuevaParty` (asistente de dos pasos),
  `VerParty` (acciones arriba: Editar, Datos tributarios, y en «Más»: Desactivar/Activar, Borrar) y el componente
  Livewire `RolesDeParty` (periodos con «Asignar rol» y «Cerrar»). Caja «Personas y empresas» en el modo modular.
  - Paso 1 del alta: valida con `PartiesService::documento()` (nuevo, mismas reglas que registrar); si ya existe,
    `Halt` con aviso «Ya está registrada» / «…pero está inactiva» y la acción «Activarla» (solo con
    `parties.desactivar`); si hay pareja DNI ↔ RUC 10, aviso en el paso 2 y sigue. Llama
    `FuentePadronContribuyentes` (hoy `SinPadron`, sin fuente de prueba: decisión del desarrollador).
  - **Desviación de D12 («todo por AccionFilament»):** el alta es un asistente propio que ejecuta
    `nucleo.parties.registrar` por `EjecutorAcciones` (la misma puerta, mismo permiso y validación); `AccionFilament`
    no arma asistentes de dos pasos. El resto sí usa `AccionFilament`.
  - `AccionFilament::desde()` gana un quinto parámetro `$ocultos` (campos que no se muestran y llegan vacíos): al
    editar una natural no se ofrece la razón social, ni nombres y apellidos en una jurídica.
  - Toda acción de la ficha toma la versión al abrir (también Desactivar/Activar/Borrar, de solo confirmación).
  - Scopes nuevos en `Party`: `buscarNombreODocumento` y `conRolVigente` (los usan `buscar()` y la lista).
  - Limitación: tras asignar o cerrar un rol en la pestaña Roles, la pestaña Historial se ve al recargar la ficha.
  - `PartiesPanelTest` (23). `composer check` en verde: 633.
- 2026-10-15 — **Revisión del desarrollador: cajas del inicio modular de distinto tamaño.** Cada caja crecía con su
  texto. Ahora toda caja sale de una sola vista (`nucleo::filament.widgets.caja-modulo`) dentro de la grilla
  `tq-cajas-modulos` (columnas `minmax(14rem, 1fr)`, filas de 8.5rem); nombre en una línea y descripción en dos con
  «…», texto completo en `title`. Regla nueva en `docs/convenciones/interfaz.md` («Inicio modular»).
  `CajasModulosTest` (4): misma clase en todas las cajas (cortas, largas, sin descripción), alto fijo en el CSS,
  `title` completo y una sola forma de dibujar la caja. Verificado por HTML y CSS, no a la vista (no hay navegador
  en este entorno). `composer check` en verde: 637.
- 2026-10-15 — **Revisión del desarrollador: nuevo diseño de las cajas.** Ícono arriba y nombre debajo, centrados;
  la descripción solo en el tooltip de Filament (`x-tooltip`, sin `title`). Caja compacta de tamaño fijo (columnas
  de 9rem, filas de 6.5rem), nombre en dos líneas como máximo con «…». Aspecto de tarjeta con la clase `fi-section`
  de Filament (fondo, borde, modo oscuro); ícono en el color primario. Regla de `interfaz.md` y `CajasModulosTest`
  (5) actualizados. Verificado por HTML y CSS. `composer check` en verde: 638.
- 2026-10-16 — **Paso 9 terminado.** DBML: las cuatro tablas de M1 como en las migraciones (`version`, auditoría en
  los catálogos, `codigo` único, CHECKs, índice por prefijo, exclusión en `party_roles`, sin `es_activo` en
  `party_roles` (D8), `estado_sunat`/`condicion_sunat` con las notas corregidas). `pendientes.md`: D9 (DNI y RUC 10)
  y la pregunta de nombres y apellidos desde el OECE y el padrón. Mapa: fila 1.3b y la deuda de la 1.4 (`users.party_id`,
  `User` a Nucleo, morph map, `CamposVisibles` real con el historial filtrado). `codigo.md`: lo que da
  `PartiesService`, el `23P01` y `PestanaHistorial`; `base-de-datos.md` § Búsquedas: el scope `buscarTexto` y la
  búsqueda por prefijo del número.
- 2026-10-16 — **Paso 10 terminado.** `revisor-arquitectura`: ningún bloqueante, 5 por corregir y 9 menores. Corregidos:
  - El resumen «Asignó rol X desde…» se armaba con todos los valores aunque `CamposVisibles` ocultara uno: ahora
    solo se usa si la persona ve todos los campos; si no, filas por campo (prueba en `HistorialLegibleTest`).
  - «Vigente» tenía tres definiciones (servicio, scope de `Party` y la lista en Filament): ahora una sola,
    `PartyRol::scopeVigenteEl`, y la lista carga la relación `Party::rolesVigentes()`.
  - `codigo.md`: `Campo::listaDe()` y los parámetros `$soloLectura` y `$ocultos` de `AccionFilament::desde()`.
  - `RolesDeParty`: `partyId` con `#[Locked]` y `parties.ver` en `mount()` (prueba).
  - Alta: un `ValidationException` del ejecutor ahora se avisa y se muestra bajo su campo. El caso del revisor (rol
    borrado entre pasos) ya lo detenía el selector de Filament (`data.roles.0`, se ve bajo el campo): queda la
    prueba y el `catch` como red para reglas que el formulario no repita.
  - Notas: «vigente» en el DBML y D8 (el día `hasta` cuenta); D9 devuelve `?ResumenParty`; el paso 8 y «Preparado
    para lo diferido» ya no prometen una fuente de padrón de prueba; la integración del padrón debe decidir qué
    hace con `estado_sunat`/`condicion_sunat` de `DatosPadron`.
  - **No se corrigen (aceptados):** campos del alta escritos a mano (hoy coinciden con `RegistrarParty::campos()`
    campo por campo); la pantalla lee `version` del modelo dentro del propio módulo; paleta y contraste de la
    insignia «Natural» (`info`): el desarrollador la revisó en pantalla el 2026-10-16 y se lee bien; una
    consulta por fila en el historial (precargar si una ficha se vuelve lenta); `DROP EXTENSION` en el `down()`
    (las migraciones se revierten en orden inverso); índice de `party_id` cubierto también por la exclusión
    (lo exige el script de migraciones).
- 2026-10-16 — **Paso 11 terminado. Tarea terminada.** `composer check` en verde: pint y phpstan sin errores,
  15 migraciones cumplen, 641 pruebas. Sigue la 1.3b (contactos, sedes y cuentas bancarias).
- 2026-10-16 — **Revisión del desarrollador: cajas por área.** Las cajas quedaron bien, pero en modo oscuro no se
  veían sus límites. Ahora el inicio modular agrupa las cajas en secciones Núcleo, CRM y ERP (enum
  `AreaModulo`, campo obligatorio `area` en `CajaModulo`), cada una con título, frase corta y línea separadora.
  Cada área tiene su color de marca (azul, malva y menta), que pinta el borde superior de la caja, el ícono, un
  tinte del fondo y el título. `malva` se declara en el proveedor del panel. Regla en `interfaz.md` y
  `CajasModulosTest` (8). Verificado por HTML y CSS, no a la vista.
