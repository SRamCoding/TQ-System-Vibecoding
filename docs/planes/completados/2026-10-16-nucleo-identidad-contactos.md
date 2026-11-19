# Plan: 1.3b · Identidad: contactos, sedes y cuentas bancarias

- **Estado:** terminado
- **Módulo(s):** Nucleo (M1; toca M4 y M5)
- **Etapa:** 1 · tarea 1.3b · rama `nucleo-identidad-contactos`
- **Creado:** 2026-10-16 · **Actualizado:** 2026-10-16

## Objetivo

Cada party puede tener personas de contacto, sedes y cuentas bancarias. Una persona con permiso las registra,
edita y cierra desde tres pestañas nuevas de la ficha, por acciones registradas que también corren sin pantalla.
Cuando un contacto cambia de institución, su vínculo se cierra y se abre otro: nada se borra y el historial queda
completo. La base garantiza: un solo contacto principal, una sola cuenta principal por moneda, una sola cuenta de
detracciones, la misma cuenta de banco nunca en dos parties y la sede apuntando solo a un contacto de la misma party.

## Contexto que leí

- `AGENTS.md`, `docs/planes/mapa-etapas.md` (fila 1.3b), `docs/planes/plantilla.md`.
- Plan 1.3 cerrado (`completados/2026-10-15-nucleo-identidad-parties.md`): D1, D2, D8, «Preparado para lo diferido» y bitácora.
- DBML v2 l.102-155 (las tres tablas) y quién las usa: `cotizaciones.contacto_id` (l.621),
  `actividad_participantes.party_contacto_id` (l.791), `cobranzas` y `pagos.cuenta_bancaria_id` (l.1973, l.2295).
- `docs/diseno/06-Arquitectura-Nucleo-Datos.md` §4.3-4.5.
- Diseño del CRM: `Contactos.dc.html` («Contactos con rotación»: «cuando alguien rota, no se borra: se cierra su
  vínculo con esta entidad y se abre uno nuevo»), `Cuenta.dc.html` (sedes · contactos), `CuentaNueva.dc.html`.
- `docs/convenciones/base-de-datos.md` (auditoría, integridad, catálogos, búsquedas). `parte-8` no trae números
  para estas tablas; `parte-7` l.37-43 (datos personales).
- Código: `PartiesService` (541 líneas), `PartyResource`, `RolesDeParty`, `PermisosSeeder`, `ParametrosService`.
- `docs/decisiones/pendientes.md`: nada bloquea. Ver D5 (Banco de la Nación) por si contabilidad lo corrige.

## Deuda de tareas anteriores

| Deuda | Dónde | Por qué |
|---|---|---|
| Pestañas de contactos, sedes y cuentas en la ficha (plan 1.3, «Preparado») | **1.3b** | Es esta tarea |
| Historial de la ficha tras cambiar un rol se ve al recargar (limitación 1.3) | **1.3b** | Las pestañas nuevas tienen el mismo problema: se resuelve una vez para todas (paso 6) |

## Decisiones (propuestas, por aprobar)

- **D1 · Contactos con rotación (el DBML no la trae).** Cada fila de `party_contactos` es **un vínculo**: esta
  persona en esta organización. Se agregan:
  - `hasta date` (nulo = vigente; el día `hasta` cuenta, como en `party_roles`). Reemplaza a `es_activo`, igual que
    D8 de la 1.3: «inactivo» y «vínculo cerrado» serían lo mismo dicho dos veces.
  - `contacto_anterior_id` (FK a la misma tabla, nulo, **único**): en el vínculo nuevo, de dónde viene. Así la fila
    cerrada puede mostrar «ahora en GORE Lambayeque» y la nueva «antes en Municipalidad de Pimentel».
  - `desde date` (nulo = no se sabe; **aprobado 2026-10-16**): desde cuándo está en esa institución. CHECK
    `hasta IS NULL OR desde IS NULL OR hasta >= desde`.
  - «Trasladar» = cerrar aquí y abrir en la otra party en una sola transacción. **Aprobado 2026-10-16:** nombres,
    apellidos, teléfono y correo llegan precargados y editables (el correo institucional suele cambiar); el
    cargo nuevo se pide (obligatorio en el traslado); `desde` del vínculo nuevo se precarga con el día siguiente
    al cierre, editable.
  - **Por qué no hacer del contacto una party natural:** `parties` exige número de documento y casi nunca se
    conoce el DNI de un contacto. Si se quiere después, se agrega `persona_party_id` nulo sin tocar lo demás.
- **D2 · «Principal» garantizado en la base.**
  - Contactos: índice único parcial `(party_id) WHERE es_principal` y CHECK «principal ⇒ `hasta` nulo»: cerrar
    un vínculo le quita la marca. Marcar otro como principal desmarca al anterior en la misma transacción.
  - Cuentas: único `(party_id, moneda_id) WHERE es_principal` (una principal en soles y otra en dólares) y CHECK
    «principal ⇒ activa».
  - Sedes: sin «principal» (el DBML no la tiene).
  - **Aprobado 2026-10-16, con tres ajustes:**
    - **Detracciones aparte.** El DBML ya la distingue con `es_detraccion` (no hay «tipo de cuenta»; con dos clases
      basta el booleano). CHECK «detracción ⇒ no principal»: la principal por moneda se elige solo entre las
      cuentas normales. Una sola de detracciones activa por party (único parcial, D5). Nunca es cuenta de pago
      normal: `cuentaPrincipal()` y `cuentasDePago()` la excluyen y solo `cuentaDetracciones()` la devuelve
      (cuando existan `pagos`, etapa 3, el pago normal la rechaza).
    - **Sin contacto principal, la ficha lo muestra:** aviso en la pestaña Contactos (y en Datos) cuando la party
      tiene contactos vigentes y ninguno es principal.
    - **Dos personas marcan principal a la vez:** el servicio bloquea los contactos (o cuentas) de la party antes
      de cambiar la marca, y si aun así la base rechaza (SQLSTATE `23505` en el índice parcial) se traduce a
      `ErrorDeNegocio`: «Otra persona acaba de cambiar el contacto principal. Vuelve a abrir la ficha.» Con prueba.
- **D3 · Sin `es_fiscal` en sedes.** La dirección fiscal ya vive en `parties.direccion_fiscal` (la llena el padrón);
  repetirla en una sede sería tener dos verdades. Las sedes son los demás lugares (sucursales, almacenes, puntos de
  entrega). Tampoco va `es_entrega` del doc 06: el DBML no la tiene y nadie la usa todavía (llega con despachos,
  etapa 2, como una columna nueva).
  - **Aprobado 2026-10-16, con dos agregados:**
    - `codigo_establecimiento varchar(4)` opcional: el anexo SUNAT del establecimiento, para las guías de
      remisión; lo podrá llenar el padrón. CHECK `^[0-9]{4}$` y único por party cuando tiene valor.
    - **La dirección de una obra es del proyecto (etapa 2), no una sede de la party.** Las sedes no se usan como
      direcciones de obra. Va en la nota del DBML de `party_sedes` y en «Preparado para lo diferido».
- **D4 · Sede y su contacto, de la misma party, garantizado en la base.** `party_sedes.contacto_id` (el DBML la
  llama `contacto_party_id`, pero apunta a `party_contactos`: se renombra para que no confunda) con FK compuesta
  `(party_id, contacto_id) → party_contactos(party_id, id)`. Si se cierra el vínculo del contacto, la sede queda
  sin contacto en la misma transacción. `nombre` único por party sin importar mayúsculas. Latitud y longitud:
  las dos o ninguna, CHECK de rango (-90..90, -180..180).
  - **Aprobado 2026-10-16, con tres precisiones:**
    - El nombre se compara **sin mayúsculas ni acentos** («Almacén» = «almacen»), como `buscarTexto` (D11 de la 1.3).
      `unaccent()` no se puede usar en un índice (PostgreSQL no la declara inmutable), así que una migración crea
      ~~la función del núcleo `texto_comparable(text)`~~ (cambiado en el paso 1: ver bitácora) = `lower(unaccent('unaccent', $1))`, marcada `IMMUTABLE`,
      y el índice usa esa función. Queda disponible para otras tablas.
    - Único **solo entre las sedes activas**: `UNIQUE (party_id, texto_comparable(nombre)) WHERE es_activa`. Una
      sede desactivada no bloquea su nombre; reactivarla con un nombre ya tomado se rechaza con mensaje claro.
    - Latitud y longitud `decimal(10,7)`, en PHP como texto (nunca `float`, regla 4).
- **D5 · Cuentas bancarias: protección contra fraude.** Cambiar la cuenta de un proveedor es el fraude clásico, así que:
  - **El número no se edita.** Banco, número y moneda son fijos; si cambian, se desactiva la cuenta y se registra
    otra. El CCI solo se puede completar si estaba vacío. Lo único editable son las marcas.
  - Única en la base `(banco_party_id, numero_cuenta)` y `cci` único: la misma cuenta no puede quedar a nombre de
    dos parties sin que alguien lo note.
  - Número normalizado a solo dígitos (CHECK `^[0-9]{1,30}$`; los guiones se quitan al guardar). CCI: CHECK de
    exactamente 20 dígitos. Sus dígitos de control **no** se validan en esta tarea (no tengo el algoritmo
    confirmado): queda el método `PartyCuentaBancaria::cciValido()` que hoy acepta todo, para activarlo cuando se confirme.
  - El banco debe tener el rol `banco` vigente (lo revisa el servicio; la base no puede).
  - Detracciones: solo en el Banco de la Nación y en moneda local; una sola activa por party (único parcial).
    El Banco de la Nación se reconoce por un parámetro `parties.ruc_banco_detracciones` = `20100030595` (dígito
    verificado), editable si contabilidad dice otra cosa.
  - Permisos propios: `parties.ver-cuentas-bancarias` y `parties.gestionar-cuentas-bancarias`.
  - **Aprobado 2026-10-16 (5a-5e), con un ajuste en 5b:** la misma cuenta sí puede estar en dos parties si son la
    pareja DNI ↔ RUC 10 de la D9 (la misma persona); en cualquier otro caso la base la rechaza. Cómo lo garantiza
    la base (por confirmar): ver «5b en la base» abajo. CCI: el cálculo se confirmará con los CCI de Techquk de
    sus facturas (anotado en `pendientes.md`). Aprobación o aviso de cuentas nuevas: pregunta a Gerencia en
    `pendientes.md`.
  - **5b en la base (propuesta).** `parties` gana una «clave de persona» de dos columnas (`persona_tipo_id`,
    `persona_numero`), que llena el servicio: para un RUC 10 es el DNI que lleva dentro; para todo lo demás, su
    propio documento. Un CHECK solo permite esos dos valores. `party_cuentas_bancarias` copia la clave por una FK
    compuesta hacia `parties(id, persona_tipo_id, persona_numero)` y una restricción de exclusión
    `EXCLUDE USING gist (banco_party_id WITH =, numero_cuenta WITH =, persona_tipo_id WITH <>)` (y lo mismo con
    `persona_numero`): la misma cuenta en el mismo banco solo puede repetirse con la misma clave de persona.
    Más `UNIQUE (party_id, banco_party_id, numero_cuenta)`. Igual para `cci`. Cuenta también las desactivadas.
    Migración nueva sobre `parties` (no se edita la de la 1.3).
  - **5b en la base: aprobado 2026-10-16, con el tipo dentro de la clave.** La clave es tipo + número («DNI:01234567»),
    guardada en dos columnas (`persona_tipo_id` FK al catálogo y `persona_numero`): un DNI y un carné de extranjería
    con el mismo número no comparten clave. El RUC 10 toma `DNI` + el DNI que lleva dentro. CHECK:
    `(persona_tipo_id = tipo_documento_id AND persona_numero = numero_documento) OR (numero_documento ~ '^10[0-9]{9}$'
    AND persona_numero = substring(numero_documento, 3, 8))`. Hueco que queda: la base no sabe qué id tiene el DNI,
    así que en la segunda rama el tipo lo pone el servicio (escribir otro exige saltarse el servicio y solo podría
    usar el DNI que el RUC ya lleva dentro). Prueba de que el servicio pone el DNI.
  - **La clave depende de que el documento no se edite** (D5 de la 1.3). Si algún día existe «corregir documento»
    (limitación anotada en la bitácora de la 1.3), debe recalcular la clave en la misma transacción, y la
    exclusión de cuentas lo revisará al hacerlo. Anotado también en «Preparado para lo diferido».
  - Eventos `CuentaBancariaRegistrada` y `CuentaBancariaDesactivada` (con ids, sin números): hoy sin oyente. Ahí
    se enchufan el aviso (1.10) y, si gerencia lo pide, la aprobación de cuentas nuevas (1.7).
- **D6 · Las tres tablas como `parties`:** `auditoria()`, `version` (se editan desde pantalla), bitácora campo por
  campo (`RegistraCambios`) y descripción legible para la pestaña de historial. Se borra solo lo que nadie usa (la
  FK decide); lo usado se cierra o desactiva.
  - **Aprobado 2026-10-16, con una condición:** en la pestaña Historial, las filas de `party_cuentas_bancarias` solo
    las ve quien tiene `parties.ver-cuentas-bancarias`. Se filtra en el servidor, antes de armar las filas (la
    ficha no le pasa esos modelos a `HistorialLegible` si falta el permiso). Prueba: un usuario sin el permiso abre
    la ficha y el HTML del historial no contiene el número ni el CCI de la cuenta.
- **D7 · Dónde viven los métodos.** El mapa dice «en `PartiesService`», pero ese archivo ya tiene 541 líneas y
  crecería al triple. Propuesta: `PartiesService` sigue siendo la puerta para los demás módulos con las
  **lecturas** (`contactosVigentes`, `sedesActivas`, `cuentaPrincipal(party, moneda)`, `cuentaDetracciones(party)`),
  y las escrituras van en tres servicios del núcleo: `ContactosPartyService`, `SedesPartyService` y
  `CuentasBancariasService`. Si prefieres todo en `PartiesService`, se hace así.
  - **Aprobado 2026-10-16, con dos precisiones:**
    - Otro módulo que necesite crear o cambiar contactos, sedes o cuentas lo hace por las **acciones registradas**
      (por su clave, `nucleo.parties.…`, con `EjecutorAcciones`: permiso y validación incluidos), nunca llamando a
      los tres servicios. Prueba de arquitectura en `tests/Arch/ArquitecturaTest.php`: fuera de `Nucleo`, nadie usa
      `ContactosPartyService`, `SedesPartyService` ni `CuentasBancariasService`. (Corregido en el paso 4: la regla
      «no usa internos de otros módulos» exime al núcleo, así que otro módulo sí podría usar sus clases de acción;
      lo normal es ejecutarlas por su clave con `EjecutorAcciones`, que es la misma puerta.)
    - `cuentaPrincipal()` y `cuentasDePago()` llevan anotado que, si Gerencia decide la aprobación de cuentas
      nuevas (`pendientes.md`), ahí se agrega el filtro «solo aprobadas», en un solo lugar.
  - El mapa (fila 1.3b) se corrige en el paso 7.
- **D8 · Permisos de contactos y sedes:** `parties.gestionar-contactos` y `parties.gestionar-sedes` (vender y
  despachar son áreas distintas). Ver ambos: `parties.ver`. Los datos de contacto son datos personales: nunca
  en registros ni mensajes de error (regla 10).
  - **Aprobado 2026-10-16.** Agregado a `pendientes.md` (Asistencia Legal): cómo atender un pedido de eliminar
    datos personales de un contacto (derechos ARCO) si el contacto usado no se borra y la bitácora los conserva;
    opción a evaluar, anonimizar.
- **D9 · Contactos en cualquier party**, natural o jurídica (una persona con RUC 10 puede tener un asistente).
  **Aprobado 2026-10-16.**

## Preparado para lo diferido

- **Contacto como persona registrada:** si se pide, columna `persona_party_id` nula (D1).
- **Dígitos de control del CCI:** `PartyCuentaBancaria::cciValido()` existe y se llama; hoy acepta todo (D5).
- **Aviso o aprobación de cuentas nuevas (1.10 y 1.7):** los eventos de D5 ya se emiten.
- **Punto de entrega en despachos (etapa 2):** `es_entrega` como columna nueva en `party_sedes` (D3).
- **Dirección de obra (etapa 2):** vive en el proyecto, no en `party_sedes` (D3). Despachos a obra apuntan al proyecto.
- **Guías de remisión:** `party_sedes.codigo_establecimiento` ya existe; el padrón lo llenará cuando se integre.
- **«Corregir documento» (si llega):** recalcula la clave de persona de la party en la misma transacción (D5, 5b).
- **Unir DNI y RUC 10 (pendiente D9 de la 1.3):** la clave de persona ya dice qué parties son la misma persona.
- **Derechos ARCO de un contacto (pendiente, Asistencia Legal):** los datos personales del contacto viven solo en
  `party_contactos` (nombres, apellidos, cargo, área, teléfono, correo) y en sus filas de `auditoria_cambios`;
  ninguna otra tabla los copia (la sede guarda el id, no el nombre). Un traslado deja dos filas enlazadas por
  `contacto_anterior_id`: anonimizar debe recorrer la cadena. Así, si se decide anonimizar, el cambio toca un
  solo lugar.
- **Mapa de sedes:** latitud y longitud ya se guardan; el mapa es pantalla aparte.
- **CRM (etapa 2):** `cotizaciones.contacto_id` y `actividad_participantes` usarán `contactosVigentes()`; la
  pantalla «Contactos con rotación» del CRM es esta misma pestaña con los colores del CRM.

## Pasos

- [x] 1. Migraciones: función `texto_comparable` (D4); clave de persona en `parties` (5b, con CHECK y llenado de las
      filas existentes); las tres tablas con CHECKs, únicos parciales, FK compuestas (sede → contacto, cuenta →
      clave de persona), exclusión de cuentas y autorreferencia del contacto. Modelos (`ConAuditoria`,
      `RegistraCambios`, `DescribeBitacora`), factories y prueba de integridad: la base rechaza lo prohibido aunque
      se salte el servicio (incluida la misma cuenta en dos personas distintas y aceptada en la pareja DNI ↔ RUC 10).
- [x] 2. Permisos (D5, D8) y parámetro `parties.ruc_banco_detracciones`. (La clave de persona ya la llena el modelo
      `Party` al crear, con su prueba: paso 1.)
- [x] 3. Servicios (D7) con pruebas: contactos (agregar, editar con bloqueo optimista, principal único y choque
      simultáneo con mensaje de negocio, cerrar vínculo que quita principal y sale de las sedes, trasladar con
      cargo nuevo y `desde` precargado, borrar usado vs. no usado); sedes (nombre repetido sin acentos solo entre
      activas, reactivar con nombre tomado, coordenadas, código de establecimiento); cuentas (número normalizado,
      duplicada en otra persona vs. pareja DNI ↔ RUC 10, banco sin rol, detracción fuera del Banco de la Nación o
      en dólares, detracción nunca principal ni de pago, CCI solo si estaba vacío, eventos). Lecturas en
      `PartiesService`, con la nota del filtro «solo aprobadas».
- [x] 4. Acciones registradas (con `/nueva-accion`) y pruebas sin interfaz, incluidos los rechazos por permiso.
      Prueba de arquitectura: fuera de `Nucleo`, nadie usa los tres servicios.
- [x] 5. Pestañas Contactos, Sedes y Cuentas bancarias en la ficha (componentes como `RolesDeParty`), con la
      versión tomada al abrir, aviso «sin contacto principal», «ahora en…»/«antes en…», y pruebas de pantalla
      (Cuentas oculta sin permiso).
- [x] 6. Historial de la ficha con los cambios de las tres tablas, cuentas solo con permiso (filtrado en el servidor,
      prueba de que no salen los números) y refrescado tras cada acción (deuda 1.3).
- [x] 7. DBML y documentos: columnas y notas de D1-D5 y la clave de persona, `codigo.md` (lo que M1 da a otros
      módulos), mapa (fila 1.3b: dónde viven los métodos).
- [x] 8. Revisión de `revisor-arquitectura`.
- [x] 9. `composer check` en verde y `/cerrar-tarea`.

## Criterio de aceptación

- Cerrar el vínculo de un contacto principal lo deja sin marca y fuera de las sedes; trasladarlo deja dos filas
  enlazadas y la ficha de cada party muestra de dónde viene o a dónde fue.
- Dos contactos principales en una party, dos cuentas principales en la misma moneda, dos de detracciones activas
  o la misma cuenta en dos parties: la base los rechaza aunque se salte el servicio.
- Una sede no puede apuntar al contacto de otra party (la base lo rechaza).
- El número de una cuenta no cambia por ningún camino; `191-1234567-0-12` se guarda `1911234567012`.
- Quien no tiene `parties.ver-cuentas-bancarias` no ve la pestaña ni los números.
- El historial de la ficha muestra quién agregó, cambió o cerró cada contacto, sede y cuenta.

## Bitácora

- 2026-10-16 — `nucleo-identidad-parties` ya estaba integrada en `origin/main` (PR #12): `main` local actualizado
  y rama `nucleo-identidad-contactos` creada desde él. Plan escrito; D1-D9 por aprobar. Sin código.
- 2026-10-16 — D1 aprobada con dos ajustes: traslado con datos precargados y editables y cargo nuevo obligatorio;
  fecha `desde` opcional además de `hasta`.
- 2026-10-16 — D2 aprobada con tres ajustes: la de detracciones (`es_detraccion`, ya en el DBML) nunca es principal
  ni cuenta de pago normal; aviso en la ficha si no hay contacto principal; choque al marcar principal a la vez,
  con mensaje de negocio.
- 2026-10-16 — D3 aprobada con dos agregados: código de establecimiento SUNAT opcional en sedes; la dirección de
  obra es del proyecto, no una sede. Pregunta abierta: ¿SUNAT usa `0000` para el domicilio fiscal? Si sí, ninguna
  sede podría usarlo. No se agrega hasta confirmarlo.
- 2026-10-16 — D4 aprobada con tres precisiones: nombre único sin mayúsculas ni acentos (función inmutable
  `texto_comparable`), solo entre sedes activas; coordenadas `decimal(10,7)`.
- 2026-10-16 — D5 aprobada (5a-5e). 5b con excepción para la pareja DNI ↔ RUC 10; su garantía en la base («clave de
  persona» + exclusión) por confirmar. `pendientes.md`: aprobación/aviso de cuentas nuevas (Gerencia) y
  dígitos de control del CCI.
- 2026-10-16 — 5b en la base aprobado: clave de persona con tipo y número; el RUC 10 toma DNI + su DNI. Anotado que
  un futuro «corregir documento» debe recalcularla.
- 2026-10-16 — D6 aprobada: el historial de cuentas bancarias solo con `parties.ver-cuentas-bancarias`, filtrado en
  el servidor y con prueba.
- 2026-10-16 — D7 aprobada: otros módulos escriben solo por acciones registradas (prueba de arquitectura); el filtro
  «solo aprobadas», si llega, va en `cuentaPrincipal()`/`cuentasDePago()`.
- 2026-10-16 — D8 aprobada. `pendientes.md`: derechos ARCO de un contacto (Asistencia Legal).
- 2026-10-16 — D9 aprobada. **Todas las decisiones aprobadas**; pasos ajustados a lo decidido. Queda abierta la
  pregunta del anexo `0000` (no bloquea). Empieza el paso 1.
- 2026-10-16 — **D4 cambiada en el paso 1 (decidido por el desarrollador):** la convención prohíbe funciones en la
  base (`base-de-datos.md` y `verificar-migraciones.php` rechaza `CREATE FUNCTION`), así que no hay `texto_comparable`.
  En su lugar, columna `party_sedes.nombre_comparable`, que el modelo calcula **con la base** al guardar
  (`SELECT lower(unaccent(?))`, para que PHP y la base nunca difieran) y un CHECK `nombre_comparable =
  lower(unaccent(nombre))` que la verifica (PostgreSQL acepta `unaccent` en un CHECK; probado). El índice único
  parcial va sobre esa columna.
- 2026-10-16 — **Paso 1 terminado.** Migraciones `…2026_10_16_000001` (clave de persona en `parties`, con relleno de
  las filas existentes, CHECK `parties_clave_persona_check` y `UNIQUE (id, persona_tipo_id, persona_numero)` como
  destino de la llave compuesta) y `…000002` (las tres tablas). Modelos `PartyContacto`, `PartySede`,
  `PartyCuentaBancaria` (auditoría, bitácora sin `version` ni campos calculados, descripción legible) y
  relaciones nuevas en `Party`; factories. Desviaciones y detalles:
  - **La clave de persona la llena el modelo `Party` al crear** (no el servicio): así también la tienen las
    factories y cualquier camino por modelo. Sin el DNI en el catálogo, usa la propia (el CHECK lo admite y solo
    impide compartir cuentas). La cuenta la copia del titular al crear; la llave compuesta
    `(party_id, persona_tipo_id, persona_numero)` lleva `ON UPDATE CASCADE` (un futuro «corregir documento»
    actualizaría las cuentas solo).
  - Exclusión de cuentas: cuatro restricciones (`…_numero_persona_excl`, `…_numero_persona_num_excl`,
    `…_cci_persona_excl`, `…_cci_persona_num_excl`): tipo y número de la clave por separado, con `<>` de btree_gist.
  - `cci varchar(20)` (DBML: 30), con CHECK de 20 dígitos. Único `(party_id, banco, numero)` con nombre corto
    `party_cuentas_bancarias_cuenta_unique` (el automático pasaba de 63 caracteres y PostgreSQL lo cortaba).
  - `contacto_party_id` del DBML es `party_sedes.contacto_id`, con FK compuesta `(party_id, contacto_id)` a
    `party_contactos (party_id, id)`.
  - El anexo SUNAT es único entre las sedes **activas** de la party (como el nombre).
  - `ContactosSedesCuentasIntegridadTest` (44). Ajustadas dos pruebas que insertan parties por query builder
    (`PartiesIntegridadTest`, `PartiesServiceTest`): ahora mandan la clave de persona. `composer check` en verde: 688.
- 2026-10-16 — **Paso 2 terminado.** `parties.ver-cuentas-bancarias` en `PermisosSeeder::DE_LECTURA` (no es de una
  acción). Los permisos de gestión (`parties.gestionar-contactos`, `-sedes`, `-cuentas-bancarias`) no se
  declaran aquí: el seeder los toma de las acciones del paso 4. Parámetro `parties.ruc_banco_detracciones`
  (texto, `20100030595`, editable) y `ParametrosService::rucBancoDetracciones()`, con prueba de que el valor
  inicial pasa el módulo 11. Si alguien lo cambia por un RUC que no existe, ninguna cuenta podrá ser de
  detracciones (el servicio lo rechaza con mensaje claro, paso 3). Pedido del desarrollador: prueba del nombre
  de sede con ñ, ü y tildes en mayúscula (`Almacén Ñaña`, `PIÚRA`, `AGÜERO`…), que guarda por modelo y
  verifica valor y choque; pasa. `composer check` en verde: 697.
- 2026-10-16 — **Pedido del desarrollador sobre `parties.ruc_banco_detracciones`** (ajuste al paso 2):
  - Al cambiarlo se valida: dígito verificador correcto y una party registrada, activa y con el rol `banco`
    vigente. Mecanismo general: `DefinicionParametro` gana `$validador` (clase `ValidadorParametro` con
    `normalizar()` y `rechazo()`), que `ParametrosService::cambiar()` llama antes de guardar; se guarda el valor
    normalizado (`209-9999999-0` ⇒ `20999999990`). Implementación: `Parties/ValidadorBancoDetracciones`. El valor
    inicial lo siembra el código y no pasa por el validador.
  - Cambiarlo exige el permiso de dinero: `DefinicionParametro` gana `$esSensible`; acción nueva
    `nucleo.cambiar-parametro-sensible` («Cambiar parámetro de dinero», permiso `parametros.editar-sensibles`), y
    «Cambiar parámetro» rechaza los sensibles. Mismo diseño que los campos de dinero de los catálogos (plan 1.2b,
    E6): permiso aparte (`parametros.…`, no el `catalogos.editar-sensibles`, que es de otra pantalla). En la
    pantalla de parámetros, cada fila muestra solo la acción que le corresponde.
  - Solo este parámetro es sensible. **Pregunta abierta:** ¿el IGV también debería serlo?
  - `ParametroBancoDetraccionesTest` (10). RUC de prueba inventados (regla 10). `composer check` en verde: 707.
- 2026-10-16 — **Paso 3 terminado.** Servicios internos `ContactosPartyService` (agregar, editar, marcarPrincipal,
  cerrarVinculo, trasladar, borrar), `SedesPartyService` (agregar, editar, desactivar, activar, borrar) y
  `CuentasBancariasService` (registrar, completarCci, marcarPrincipal, desactivar, activar, borrar). Entradas
  `DatosContacto`, `DatosSede`, `DatosCuentaBancaria`; salidas `ResumenContacto`, `ResumenSede`,
  `ResumenCuentaBancaria`; errores `ContactoRechazado`, `SedeRechazada`, `CuentaBancariaRechazada` (sin datos
  personales ni el número de cuenta en los mensajes, con prueba). Lecturas en `PartiesService`:
  `contactosVigentes`, `sedesActivas`, `cuentaPrincipal`, `cuentasDePago`, `cuentaDetracciones` (las dos de pago
  con la nota del filtro «solo aprobadas»). Detalles y desviaciones:
  - «Principal»: el servicio bloquea la fila de la party y desmarca al anterior por modelo (queda en la bitácora).
    El choque del índice parcial se traduce a mensaje de negocio; la prueba simula a la otra persona guardando
    justo antes (`PartyContacto::saving`) y verifica que la transacción de afuera sigue sana.
  - Trasladar: si el vínculo sigue abierto exige `hasta` y lo cierra; si ya estaba cerrado (se supo después a
    dónde fue) no hace falta. `desde` nuevo: el dado o el día siguiente al cierre. Cargo nuevo obligatorio; datos
    nuevos editables. Si algo falla, nada queda a medias (prueba).
  - Agregar un contacto, sede o cuenta a una party desactivada se rechaza (como asignar un rol).
  - **Evento nuevo, no previsto en D5:** `CuentaBancariaActivada`. Reactivar una cuenta la vuelve a dejar recibir
    pagos: es el mismo punto de fraude que registrarla. Los tres eventos llevan ids (cuenta, party, usuario) y
    `ShouldDispatchAfterCommit`.
  - `PartiesService::borrar` de una party nunca usada se lleva también sus sedes, cuentas y contactos (por
    modelo, sedes antes que contactos), como ya hacía con sus roles.
  - Coordenadas: se acepta coma decimal (`-6,7714`); más de 7 decimales se rechaza (no se redondea).
  - Ubigeo y contacto de una sede: se validan solo si cambian (lo que ya tenía se respeta, como en `parties`).
  - Pruebas: `ContactosPartyServiceTest` (21), `SedesPartyServiceTest` (17), `CuentasBancariasServiceTest` (22).
    `composer check` en verde: 767.
- 2026-10-16 — **Parámetros de dinero (pedido del desarrollador):** criterio «es de dinero todo parámetro que cambia
  un monto, una tasa o a dónde va el dinero». Marcados los cuatro del núcleo: `tributario.igv_porcentaje` (tasa),
  `tipo_cambio.dias_tolerancia` (decide qué tipo de cambio se usa y con eso los montos convertidos),
  `general.moneda_local` (no editable, marcado igual por si un día se habilita) y `parties.ruc_banco_detracciones`.
  «Cambiar parámetro» ya no aplica a ninguno del núcleo (queda para los de otros módulos); sus pruebas usan un
  parámetro de prueba. Si un parámetro no es editable, el rechazo dice eso y no «es de dinero». Mensaje
  nuevo: «es un parámetro de dinero (cambia un monto, una tasa o a dónde va el dinero)». Anotado en el mapa
  (reglas entre etapas): desde la etapa 2, cada documento guarda la tasa de IGV que usó al emitirse.
- 2026-10-16 — **Paso 4 terminado.** 17 acciones en `Acciones/Parties/`, clave `nucleo.parties.<operacion>`, con tres
  bases (`AccionContacto`, `AccionSede`, `AccionCuentaBancaria`) que fijan el permiso del grupo:
  - Contactos (`parties.gestionar-contactos`): `agregar-contacto`, `editar-contacto`, `marcar-contacto-principal`,
    `cerrar-vinculo-contacto`, `trasladar-contacto` (cargo nuevo obligatorio en la validación), `borrar-contacto`.
  - Sedes (`parties.gestionar-sedes`): `agregar-sede`, `editar-sede`, `desactivar-sede`, `activar-sede`, `borrar-sede`.
  - Cuentas (`parties.gestionar-cuentas-bancarias`): `registrar-cuenta-bancaria` (moneda por id del catálogo, la
    acción pasa el código), `completar-cci`, `marcar-cuenta-principal`, `desactivar-cuenta-bancaria`,
    `activar-cuenta-bancaria`, `borrar-cuenta-bancaria`.
  - Editar contacto o sede reemplaza todo con lo que llega: un campo ausente se borra (como `EditarParty`).
  - Prueba de arquitectura: los tres servicios solo se usan dentro de `App\Modules\Nucleo` (`toOnlyBeUsedIn`);
    verificado que falla con una clase de prueba fuera del núcleo (borrada).
  - `AccionesContactosSedesCuentasTest` (18). `CatalogosSeederTest` con los tres permisos nuevos. `composer check`
    en verde: 789.
- 2026-10-16 — **Pregunta del desarrollador: ¿se revisa el permiso si otro módulo llama `ejecutar()` de la clase?** No:
  permiso, validación y autor de la auditoría los aplica solo `EjecutorAcciones`. Regla de arquitectura nueva:
  `App\\Modules\\Nucleo\\Acciones` solo se usa dentro del núcleo; fuera, las acciones del núcleo se piden por su
  clave (el ejecutor, o `AccionFilament::desde('clave')`). La regla encontró un uso real:
  `AppPanelProvider` pasaba la clase `CambiarModoNavegacion` (por `AccionFilament`, que sí usa el ejecutor);
  ahora pasa la clave `nucleo.cambiar-modo-navegacion`. Verificada con una clase temporal fuera del núcleo (borrada).
- 2026-10-16 — **Paso 5 terminado.** Componentes Livewire `ContactosDeParty`, `SedesDeParty` y `CuentasBancariasDeParty`
  (como `RolesDeParty`: `partyId` con `#[Locked]`, permiso en `mount()`), con sus vistas. Pestañas nuevas en la ficha:
  Contactos, Sedes y Cuentas bancarias (esta solo con `parties.ver-cuentas-bancarias`, revisado también en el
  componente). Detalles:
  - `AccionFilament::desde()` gana un sexto parámetro `$controles` (campo ⇒ control propio, solo presentación):
    selectores con buscador para el banco (parties con rol `banco` vigente), la institución de destino del traslado
    y el contacto de una sede (`SelectoresParty`; el contacto ofrece los vigentes más el que la sede ya tiene).
  - Contactos: vigentes primero (el principal arriba), columna «Vínculo» con «antes en…» / «ahora en…», encabezado
    «Sin contacto principal» cuando corresponde; en Datos, la línea «Contacto principal». Trasladar precarga
    nombres, área, teléfono, correo y decisor, el cierre en hoy y el cargo vacío (obligatorio). **Ajuste a D1:**
    `desde` del vínculo nuevo no se precarga con un día fijo (quedaría mal si la persona cambia la fecha de cierre):
    va vacío con la ayuda «Si lo dejas vacío, el día siguiente al cierre», que es lo que hace el servicio.
  - Cada acción de fila toma la versión al abrir; las que no aplican se ocultan con `->hidden()` (AGENTS.md).
  - Cada acción avisa «Guardado.» y emite `ficha-party-cambiada` (lo usará el historial, paso 6).
  - Borrar party: su aviso dice que se lleva roles, contactos, sedes y cuentas.
  - `FichaPartyPestanasTest` (15). `composer check` en verde: 805. Verificado por pruebas, no a la vista.
- 2026-10-16 — **Paso 6 terminado.** `PartyResource::modelosDelHistorial()` arma la línea de tiempo: la party, sus roles,
  contactos y sedes, y sus cuentas **solo con `parties.ver-cuentas-bancarias`** (sin el permiso ni se leen: sus
  números no llegan a la página). Detalles:
  - **Agregado no previsto:** lo ya borrado sigue en el historial. `HistorialCambiosService::borradosDe(clase,
    columna, valor)` rearma, sin guardarlas, las filas borradas con los valores que la bitácora guardó al borrarlas
    (`valores_anteriores ->> 'party_id'`). Sin esto, un contacto o una cuenta registrados y borrados desaparecían de
    la ficha (seguían solo en `auditoria_cambios`). Se aplica también a los roles.
  - La creación de cada parte es una sola fila, como los roles: «Agregó contacto Luis Fernández · Jefe de Logística ·
    principal», «Agregó sede …», «Registró cuenta Banco … 1911234567012 en PEN · CCI …». Solo si la persona ve todos
    los campos (`CamposVisibles`); si no, filas por campo.
  - **Deuda de la 1.3 resuelta:** `VerParty` escucha `ficha-party-cambiada` (lo emiten las cuatro pestañas, también
    Roles) y se vuelve a dibujar: el historial y la línea «Contacto principal» quedan al día sin recargar.
  - `HistorialFichaPartyTest` (5). `composer check` en verde: 810.
- 2026-10-16 — **Revisión del desarrollador en pantalla (antes del paso 7):**
  - **El historial se veía sin estilos** (columnas pegadas, sin líneas). Causa: el panel usa el CSS ya compilado de
    Filament y la vista propia `historial.blade.php` usaba utilidades de Tailwind que esa hoja no trae. Ahora el
    historial es una tabla de Filament: clase base `Nucleo\Filament\TablaHistorial` (componente Livewire,
    `->records()` con paginación de 25) y `HistorialDeParty` (dice qué modelos leer, revisa `parties.ver`, escucha
    `ficha-party-cambiada`). `PestanaHistorial::make(clase, fn ($record) => id)`. La vista vieja se borró.
    Revisadas las demás vistas propias: solo el historial tenía el problema (las cajas del inicio usan su
    `<style>` con clases `tq-`). Regla nueva en `interfaz.md`, «Vistas propias».
  - **El alta de la party en una sola fila:** `Party::resumenCreacionBitacora()` («Registró persona natural · DNI
    … · razón social»). `HistorialLegibleTest` actualizado.
  - **«Personas Y Empresas»:** Filament pone mayúscula en cada palabra del nombre de un Resource (`Str::ucwords`;
    también salía «Tipos De Cambio»). Trait `Nucleo\Filament\EtiquetasEnEspanol` en los tres Resources y
    `EtiquetasEnEspanolTest`, que exige el trait en todo Resource. El resto de textos ya cumplía (búsqueda en
    todo `app/`). Regla nueva en `interfaz.md`, «Mayúsculas».
  - `codigo.md`: cómo se arma ahora la pestaña de historial. `composer check` en verde: 816. Verificado por
    pruebas (la tabla trae `fi-ta-table`), no a la vista.
- 2026-10-16 — **Paso 7 terminado.** DBML: las tres tablas como en las migraciones (desde/hasta y
  `contacto_anterior_id` en contactos, `nombre_comparable`, `contacto_id` con FK compuesta y `codigo_establecimiento`
  en sedes, sin `es_fiscal`; clave de persona copiada, `cci varchar(20)`, exclusiones y notas en cuentas; `version`
  en las tres) y la clave de persona en `parties`. `codigo.md`: lecturas de `PartiesService` y escritura solo por
  acciones; acciones del núcleo por su clave fuera de él; `$controles` de `AccionFilament`; parámetros de dinero.
  Mapa: 1.3b ✅ y dónde viven los métodos. `pendientes.md`: el anexo `0000` (Contabilidad).
- 2026-10-16 — **Paso 8 terminado.** `revisor-arquitectura`: 1 bloqueante, 6 por corregir y 7 menores. Corregidos:
  - **Bloqueante:** «falta contacto principal» vivía en Filament. Ahora `PartiesService::faltaContactoPrincipal()`.
  - **Carrera sede ↔ cierre del contacto:** `SedesPartyService` bloquea el contacto elegido antes que la sede, en el
    mismo orden que `cerrar()` (contacto, luego sedes). Sin prueba de concurrencia real (la de la 1.6 la cubrirá
    con procesos paralelos; hoy `RefreshDatabase` no lo permite).
  - **`hasta` futuro:** **cambio a D1**: un contacto está vigente si no tiene `hasta` (scope `vigentes()`;
    reemplaza a `vigenteEl`) y `hasta` nunca es posterior a hoy (al cerrar, trasladar o agregar). Antes, cerrar
    con fecha futura le quitaba principal y sedes pero seguía «vigente». `desde` queda informativo: un vínculo que
    empieza mañana (traslado) ya cuenta como vigente y puede ser el principal allá.
  - **Número de cuenta por ningún camino:** guardia `updating` en `PartyCuentaBancaria` (banco, número, moneda,
    titular, clave de persona y un CCI ya registrado), con prueba por modelo.
  - **Permiso de dinero en el servicio:** `ParametrosService::cambiar()` rechaza los de dinero y
    `cambiarDeDinero()` los que no lo son (uno no editable sigue diciendo «no se puede cambiar»). Las acciones
    solo eligen cuál llamar.
  - **Regla 10 en los registros:** `bootstrap/app.php` registra todo `QueryException` solo con SQLSTATE, conexión y
    el primer punto del código propio (sin SQL, valores ni DETAIL). Cubre también lo que venía de `PartiesService`.
    Prueba `RegistroErroresBaseTest`.
  - **Choque simultáneo en cuentas:** prueba como la de contactos.
  - Menores: orden de bloqueo único (parties por id, luego contacto) también en `trasladar()`; las cuentas de pago
    excluyen bancos desactivados o sin el rol vigente y `cuentaDetracciones()` exige que el banco siga siendo el del
    parámetro (con pruebas); el selector de contacto busca el actual solo en la party; documentos alineados
    (`CuentaBancariaActivada`, `PartyCuentaBancaria::cciValido()`, quién calcula `nombre_comparable`); nota en
    `docs/operaciones/despliegue.md` para revisar el CHECK con `unaccent` al actualizar PostgreSQL.
  - **No se corrigen (aceptados):** el historial se arma completo en cada página y `borradosDe()` filtra por
    `valores_anteriores ->> 'party_id'` sin índice: precargar o indexar si una ficha se vuelve lenta. La fila 1.3b
    del mapa ya estaba marcada ✅: queda, porque la tarea cierra en este mismo paso.
  - `composer check` en verde: 828.
- 2026-10-16 — **Paso 9 terminado. Tarea terminada.** `composer check` en verde (pint, phpstan, 17 migraciones, 828
  pruebas). Plan a `completados/`. Sigue la 1.3c (animación entre modos) o la 1.4 (seguridad), según el mapa.
  Abierto para otras tareas: aprobación de cuentas nuevas (Gerencia), dígitos del CCI, anexo `0000`, derechos
  ARCO (Asistencia Legal), todos en `pendientes.md`; el aviso de cuentas va en la 1.10 por sus eventos.
- 2026-10-16 — PR #13 abierto por el desarrollador. Aprobado el cambio a D1 (el cierre de un vínculo nunca es futuro).
  Regla nueva en `AGENTS.md`: `Livewire::test()` no dibuja los componentes hijos; se prueban directamente.
