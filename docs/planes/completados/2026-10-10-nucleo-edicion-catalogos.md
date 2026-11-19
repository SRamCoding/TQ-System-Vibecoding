# Plan: 1.2b · Edición de catálogos y parámetros desde la pantalla

- **Estado:** terminado
- **Módulo(s):** Nucleo (M5, M9)
- **Etapa:** 1 · tarea 1.2b · rama `etapa-1-edicion-catalogos`
- **Creado:** 2026-10-10 · **Actualizado:** 2026-10-10

## Objetivo

Una persona con permiso agrega, edita, desactiva, reactiva y (si nunca se usó) borra elementos de los nueve
catálogos desde Configuración, y edita valor y descripción de los parámetros, siempre por acciones
registradas con bloqueo optimista y bitácora de cambios. Ningún despliegue pisa lo editado por pantalla.
Reemplaza la decisión D7 de la 1.2 (catálogos solo lectura) y ajusta D4 (borrar lo nunca usado).

## Contexto que leí

- `AGENTS.md`, `docs/planes/plantilla.md`, `docs/planes/mapa-etapas.md` (fila 1.2b).
- Plan 1.2 (`completados/2026-10-09-nucleo-catalogos-parametros.md`): D4 (l.61-67), D7 (l.89-104).
- `docs/convenciones/codigo.md`: Servicios y bloqueo optimista (l.26-31), catálogos por `CatalogosService` (l.32-36),
  Acciones registradas (l.45-58), Filament (l.85-97), Pruebas (l.100-105).
- `docs/diseno/v4/parte-7-transversal.md`, matriz de permisos: Configuración, Ger. General **A**, Administración **E**.
- `docs/decisiones/pendientes.md`: nada bloquea. `0010-migraciones-editables.md`: no hay `.claude/PRODUCCION`, así que
  las migraciones de la 1.2 todavía se pueden editar.
- Código: migraciones `N/database/migrations/…000005` a `…000008`, modelos de catálogo (`EsCatalogo`, `ConAuditoria`),
  `Parametro` (`RegistraCambios`, `version`), `ParametrosService::cambiar()`, `UbigeosService::aplicar()` (l.45-73),
  `RegistroAcciones`, `EjecutorAcciones`, `Campo`, `AccionFilament`, `CambiarParametro`, `PermisosSeeder`,
  `CatalogosSeeder` (`oficial()` l.106 con `updateOrCreate`, `delNegocio()` l.117), `ParametrosSeeder` (l.84-88),
  `Filament/Pages/Catalogos.php`, `ParametroResource` (l.85-98), `Enums/Catalogo.php`. (`N` = `app/Modules/Nucleo`.)

Hallazgos que condicionan el diseño:

- Ningún catálogo tiene `version` ni `RegistraCambios`; solo `parametros`.
- Nada traduce una violación de FK (23503) a un error de negocio.
- FK que ya apuntan a catálogos: `tipos_cambio.moneda_id` y `cubso.unidad_medida_id`. Nada más todavía.
- `UbigeosService::aplicar()` compara solo contra los activos: reactiva lo que alguien desactivó a mano y desactiva
  lo que alguien agregó a mano.
- `RegistroAcciones::registrar()` recibe una clase, no una instancia.

## Decisiones

Del desarrollador (2026-10-10, al cerrar la 1.2):

- **E1 · Borrar:** se borra de verdad solo lo que **nunca se usó**. Si ya está en algún documento, la FK lo impide
  y se ofrece desactivar.
- **E2 · Parámetros:** desde la pantalla se editan **valor y descripción**. Crear y borrar siguen en código
  (`RegistroParametros`).
- **E3 · Alcance:** los nueve catálogos: monedas, unidades de medida, ubigeos, tipos de documento, formas de pago,
  líneas de solución, CUBSO, tipos de penalidad y tipos de trabajo.
- **E4 · Dónde:** tarea nueva tras el PR de la 1.2.

Del desarrollador (2026-10-10, al planificar):

- **E5 · Forma: base genérica.** Cinco clases de acción (`crear`, `editar`, `desactivar`, `activar`, `borrar`)
  parametrizadas por `Catalogo`. Se registra **una instancia por catálogo y operación**, con clave
  `nucleo.<catalogo>.<operacion>` (p. ej. `nucleo.monedas.crear`). Así cada acción tiene sus campos tipados y su
  clave propia en el registro, sin 45 clases. `RegistroAcciones` pasa a aceptar instancias además de clases.
- **E6 · Campos que mueven dinero: permiso aparte.** `tipos_documento.signo`, `tipos_documento.afecta_cuenta_corriente`
  y `monedas.decimales` se marcan como sensibles en la definición del catálogo. `editar` no los incluye. Una acción
  `nucleo.<catalogo>.editar-sensibles` (solo en monedas y tipos de documento) los cambia con permiso
  `catalogos.editar-sensibles`. `crear` en esos dos catálogos fija los sensibles, así que exige ese permiso.
  El resto usa `catalogos.editar`.
- **E7 · Ubigeos: columna `origen`** (`inei` | `manual`, `NOT NULL`, CHECK, enum `OrigenUbigeo`). La actualización
  del INEI nunca desactiva los manuales. Si el INEI publica un código que ya existe como manual, pasa a `inei`.
  Lo desactivado a mano no se reactiva solo.
- **E8 · `codigo` inmutable.** Se fija al crear: el código del sistema y los seeders buscan por código.
  Para corregirlo se borra (si nunca se usó) o se crea otro.

Propuestas del plan inicial que se adoptan sin consulta (son consecuencia directa de E1–E2):

- **E9 · Seeders sin pisar:** `CatalogosSeeder::oficial()` pasa a `firstOrCreate`, como `delNegocio()`.
  `ParametrosSeeder` pone `descripcion` solo al crear. `es_editable` y `tipo` siguen mandados por el código.
- **E10 · Bloqueo optimista** en los nueve catálogos: columna `version` (int, default 1, CHECK `>= 1`). Mismo
  patrón que `ParametrosService::cambiar()`: `editar`, `editar-sensibles`, `desactivar`, `activar` y `borrar`
  reciben la versión leída como campo `deControl()`.
- **E11 · Bitácora:** los nueve modelos usan `RegistraCambios` (`camposSinBitacora` = `version`). La configuración
  mueve dinero y debe dejar rastro de quién cambió qué.

## Pasos

Un commit al cerrar cada paso.

- [x] 1. **Migraciones** (se editan las de la 1.2, ADR 0010): `version` en los nueve catálogos (macro `version()`
  junto a `auditoria()` si la repetición lo justifica) y `ubigeos.origen` con CHECK. Pruebas de restricciones en
  `CatalogosIntegridadTest` (CHECK de `version` y de `origen`).
- [x] 2. **Modelos y definición de catálogos:** `RegistraCambios` en los nueve modelos; `version` fuera de `fillable`.
  Enum `OrigenUbigeo`. Definición por catálogo (en `Catalogo` o en `N/Catalogos/DefinicionCatalogo`): campos
  editables como `list<Campo>`, cuáles son sensibles, y `codigo` solo al crear. Para `cubso.unidad_medida_id` hace
  falta un `Campo` que elija un elemento de otro catálogo (validación contra activos por `CatalogosService`).
  Revisar si `RegistraCambios` registra las altas masivas del seeder de ubigeos (~1 900 filas): si sí, decidir si se
  aceptan o el seeder las exime.
- [x] 3. **Servicio `EdicionCatalogosService`:** `crear`, `editar` (rechaza `codigo` y sensibles), `editarSensibles`,
  `desactivar`, `activar`, `borrar`. Transacción + `lockForUpdate` + `VersionDesactualizada`, `version++`.
  `borrar` usa un savepoint (PostgreSQL aborta la transacción ante un error) y traduce 23503 a un nuevo
  `ElementoCatalogoEnUso` (ErrorDeNegocio) que sugiere desactivar. Un código duplicado (23505) se traduce a
  `CodigoCatalogoDuplicado`. Ubigeos creados por pantalla nacen con `origen = manual`.
  Pruebas: `EdicionCatalogosServiceTest`, con caso feliz y rechazo por operación, y el borrado de una moneda con
  `tipos_cambio` (rechazado) frente a una sin uso (borrada).
- [x] 4. **Acciones genéricas:** `RegistroAcciones` acepta instancias. Clases `CrearElementoCatalogo`,
  `EditarElementoCatalogo`, `EditarSensiblesCatalogo`, `DesactivarElementoCatalogo`, `ActivarElementoCatalogo` y
  `BorrarElementoCatalogo` (constructor con `Catalogo`), registradas por catálogo en `NucleoServiceProvider`.
  Permisos `catalogos.editar` y `catalogos.editar-sensibles`, que `PermisosSeeder` crea solos.
  Pruebas: `AccionesCatalogoTest` (sin interfaz, por `EjecutorAcciones`, con un dataset de los nueve catálogos),
  `RegistroAccionesTest` (instancias, claves únicas), y rechazo de `editar` sin el permiso sensible.
- [x] 5. **Parámetros:** `ParametrosService::cambiar()` acepta `descripcion` (opcional; no vacía si viene).
  `CambiarParametro` suma el campo `descripcion`. `ParametrosSeeder` deja de actualizar `descripcion` en
  parámetros existentes. Pruebas en `CambiarParametroTest`, `ParametrosServiceTest` y la del seeder (no pisa
  la descripción editada).
- [x] 6. **Seeders y ubigeos:** `CatalogosSeeder::oficial()` → `firstOrCreate`. El seeder de ubigeos pone
  `origen = inei`. `UbigeosService::aplicar()` compara contra todas las filas: nuevos = códigos inexistentes;
  renombrados = cambio de nombre (conserva `es_activo`); retirados = solo `inei` y activos; un manual que aparece en
  el padrón pasa a `inei`. Pruebas: `CatalogosSeederTest` (no pisa una moneda editada), `UbigeosServiceTest`
  (manual sobrevive, desactivado a mano no se reactiva, manual → inei).
- [x] 7. **Pantallas:** en `Filament/Pages/Catalogos.php`, acción de cabecera «Agregar» y acciones de fila editar,
  editar sensibles, desactivar/activar (según estado) y borrar (con confirmación), todas por `AccionFilament::desde`
  con `fillForm` que captura `version` **al abrir**. `ParametroResource`: `descripcion` en el formulario y en
  `fillForm`. Pruebas en `ConfiguracionPanelTest`: se ve y funciona con permiso, no se ve sin él, y un conflicto de
  versión muestra el aviso.
- [x] 8. **Documentación:** ADR 0013 «Catálogos editables por pantalla» (reemplaza D7 y ajusta D4 de la 1.2;
  E5–E8). `docs/convenciones/codigo.md`: catálogos (l.32-36), patrón de acción parametrizada por instancia, y
  traducción de 23503/23505. `docs/convenciones/base-de-datos.md`: `version` en tablas editables por pantalla.
  Actualizar el docblock de `CatalogosSeeder` (l.25-26).
- [x] 9. Revisión del subagente `revisor-arquitectura` (cambia estructura: migraciones, registro de acciones,
  permisos) y atender lo que señale.
- [x] 10. `composer check` en verde, `/cerrar-tarea`, plan a `completados/` y fila 1.2b del mapa con ✅.

## Criterio de aceptación

- `EdicionCatalogosServiceTest`, `AccionesCatalogoTest`, `CambiarParametroTest`, `CatalogosSeederTest`,
  `UbigeosServiceTest`, `ConfiguracionPanelTest` y `CatalogosIntegridadTest` en verde, cada archivo solo y en conjunto.
- En Configuración → Catálogos, alguien con `catalogos.editar` agrega una forma de pago, la edita, la desactiva y la
  reactiva. Borra una que nunca se usó, e intentar borrar la moneda PEN (con tipos de cambio) muestra «está en uso:
  desactívela».
- Sin `catalogos.editar-sensibles` no se puede cambiar el signo de un tipo de documento.
- Dos personas editan el mismo elemento: la segunda recibe el aviso de versión desactualizada.
- Tras `db:seed` en un despliegue, una moneda y la descripción de un parámetro editadas por pantalla siguen igual.
- «Aplicar actualización» de ubigeos no desactiva un distrito manual.

## Bitácora

- 2026-10-10 — Pedido del desarrollador al cerrar la 1.2: poder agregar, editar y borrar en la pantalla.
  Decisiones E1–E4 tomadas; el resto queda para planificar.
- 2026-10-10 — Sesión nueva. El PR #4 de la 1.2 se fusionó sin el commit de este plan; la rama
  `etapa-1-edicion-catalogos` sale de `main` y lo trae con cherry-pick. Planificación: el desarrollador eligió
  E5–E8 (base genérica, permiso aparte para campos de dinero, `origen` en ubigeos, código inmutable); E9–E11
  se adoptan del plan inicial. Siguiente: paso 1.
- 2026-10-10 — El desarrollador confirma E9–E11.
- 2026-10-10 — Paso 1 hecho. `version` + CHECK en los nueve catálogos y `ubigeos.origen` (string 10, sin default,
  CHECK `inei|manual`) editando las migraciones 000005–000007. Sin macro: una línea por tabla y los CHECK en un
  `foreach`, como `parametros`. Para que la suite siga en verde se adelantó lo mínimo de los pasos 2 y 6: enum
  `OrigenUbigeo`, `origen` en `fillable` y casts de `Ubigeo`, la factory, el seeder y los nuevos de
  `UbigeosService::aplicar()` nacen `inei`. La lógica de `aplicar()` con `origen` sigue pendiente (paso 6).
  Siguiente: paso 2.
- 2026-10-10 — Paso 2 hecho. `EsCatalogo` usa `RegistraCambios` (sin `version` en la bitácora): los nueve
  catálogos la heredan. La definición vive en el enum `Catalogo`: `campoCodigo()` (con formato en monedas
  `^[A-Z]{3}$` y ubigeos `^[0-9]{6}$`), `campos()` (nombres = columnas, para que el servicio guarde sin
  traducir), `camposSensibles()` y `tieneSensibles()`. Nuevo `TipoCampo::ElementoCatalogo` y
  `Campo::elementoDe()` (id; la regla `exists` confirma que existe, el servicio decide si debe estar activo),
  dibujado como Select buscable en `AccionFilament`. Mensajes `exists`, `in` y `regex` en `lang/es`.
  Bitácora de la carga inicial: sí se anotan los ~1 900 ubigeos (+~3 s, una sola vez en la primera
  instalación); se acepta, no se exime al seeder. `CatalogoDefinicionTest` vigila que los campos coincidan
  con las columnas de cada tabla. Siguiente: paso 3.
- 2026-10-10 — Paso 3 hecho. `EdicionCatalogosService` identifica el elemento por **código** (inmutable, sirve a
  la API futura; igual que `CambiarParametro` usa `grupo.clave`). Errores en una sola clase
  `ElementoCatalogoRechazado` (noExiste, codigoDuplicado, campoNoEditable, referenciaInactiva,
  yaEstaEnEseEstado, enUso), al estilo de `ParametroRechazado`, en vez de las dos clases que preveía el plan.
  El duplicado se captura con `UniqueConstraintViolationException`; la FK (23503) con `QueryException` alrededor
  de un `DB::transaction` anidado (punto de guardado; además PHPStan no ve que `delete()` lance). Editar sin
  cambios no sube la versión. Desactivar lo ya desactivado se rechaza. Una referencia a otro catálogo debe
  estar activa al elegirla, pero si no cambió se conserva aunque se haya desactivado después.
  `EdicionCatalogosServiceTest`: 29 pruebas, con el ciclo completo en los nueve catálogos. Siguiente: paso 4.
- 2026-10-10 — Paso 4 hecho. `RegistroAcciones::registrar()` acepta clase o instancia; `obtener()` devuelve la
  instancia registrada; `todas()` sigue clave → clase. Acciones en `Acciones/Catalogos/`: base abstracta
  `AccionCatalogo` (constructor `Catalogo` + `EdicionCatalogosService`) y `Crear…`, `Editar…`,
  `EditarSensibles…`, `Desactivar…`, `Activar…` y `BorrarElementoCatalogo`. Claves con guiones, como el resto:
  `nucleo.formas-pago.borrar` (el valor del enum lleva `_`). 47 acciones: 5 × 9 + `editar-sensibles` en monedas y
  tipos de documento. Los campos de las de un elemento existente son `codigo` (la pantalla lo fija desde la
  fila) y `version` (`deControl`). Quien tiene solo `catalogos.editar-sensibles` puede crear monedas y tipos de
  documento: ese permiso se da junto con `catalogos.editar` (anotar en el ADR del paso 8).
  `AccionesCatalogoTest` (24) y un caso de instancia en `RegistroAccionesTest`. Siguiente: paso 5.
- 2026-10-10 — Paso 5 hecho. `ParametrosService::cambiar()` y `cambiarDesdeTexto()` aceptan `?string $descripcion`
  (null conserva; se recorta; en blanco → `ParametroRechazado::descripcionVacia`). Un parámetro no editable no
  cambia ni su descripción. `CambiarParametro` suma `descripcion` opcional (máx. 255). `ParametrosSeeder` ya
  no actualiza la descripción de uno existente; `es_editable` sigue mandado por el código. Se adelantó del
  paso 7 el `fillForm` de `ParametroResource` con la descripción (si no, el formulario la mostraba vacía).
  La prueba «volver a sembrar… sí actualiza la descripción» se invirtió: ahora exige que no la pise.
  Siguiente: paso 6.
- 2026-10-10 — Paso 6 hecho. `CatalogosSeeder`: `oficial()` y `delNegocio()` se unen en `crearFaltantes()`
  (`firstOrCreate`). `UbigeosService` compara contra **todas** las filas en `diferencias()` y `aplicar()` (un
  solo `comparar()` privado; `PadronUbigeos::comparar()` sigue genérico porque también lo usa el comando del
  CSV). Retirados = solo `inei` y activos. Renombrados conservan `es_activo`; si eran manuales pasan a `inei`.
  Nuevo `DiferenciaUbigeos::$adoptados` (manuales que el INEI publica igual → `inei`), en el resumen de la
  pantalla. Límite aceptado: si el INEI retira un código y años después lo vuelve a publicar, ya no se
  reactiva solo (no se distingue de uno desactivado a mano); lo activa una persona. La prueba de seeder
  «una lista oficial vuelve a su valor oficial» se invirtió. `docs/operaciones/despliegue.md` al día.
  Siguiente: paso 7.
- 2026-10-10 — Paso 7 hecho. En `Filament/Pages/Catalogos.php`: «Agregar» como acción de cabecera de la
  **tabla** (las de la página son las pestañas) y acciones de fila Editar, Campos de dinero (solo donde hay
  sensibles), Desactivar/Activar (según estado, con `hidden()` para no pisar la visibilidad por permiso que
  pone `AccionFilament`) y Borrar, todas con confirmación salvo los formularios. Helpers `accionCatalogo()` y
  `accionDeFila()`: el código se fija desde la fila y `fillForm` captura versión y campos al abrir. Nuevo
  `Catalogo::camposNoSensibles()`. Aviso único «Guardado.». `ConfiguracionPanelTest` +9 pruebas (agregar,
  CUBSO con unidad, editar con valores precargados, conflicto de versión, desactivar/activar, borrar usado y
  sin usar, permisos general y de dinero, descripción del parámetro). Siguiente: paso 8.
- 2026-10-10 — Paso 8 hecho. ADR 0013 «Catálogos y parámetros editables desde la pantalla» (E1–E11 y sus
  consecuencias: `editar-sensibles` va junto con `editar`, reactivación del INEI, bitácora de la carga
  inicial). `codigo.md`: catálogos los escribe solo el núcleo, traducción de 23505/23503 a `ErrorDeNegocio`,
  `Campo::elementoDe()`, una clase con varias instancias registradas. `base-de-datos.md`: `version` en toda
  tabla editable por pantalla y la sección de catálogos reescrita (borrar lo nunca usado, seeder solo crea).
  Skill `/nueva-accion` al día. Nota en D4 y D7 del plan 1.2 apuntando al ADR. Siguiente: paso 9.
- 2026-10-10 — Paso 9 hecho. `revisor-arquitectura`: sin bloqueantes, seis observaciones.
  - Obs. 1 (borrar/desactivar elementos que el sistema usa, p. ej. PEN, con el permiso general): el
    desarrollador eligió **permiso de dinero + moneda local fija**. Desactivar y borrar en monedas y tipos de
    documento exigen `catalogos.editar-sensibles` (`AccionCatalogo::permisoSiMueveDinero()`, también en
    crear); `EdicionCatalogosService` rechaza desactivar o borrar la moneda local
    (`ElementoCatalogoRechazado::monedaLocal`). El servicio ahora depende de `ParametrosService`: las pruebas
    de servicio y acciones siembran `ParametrosSeeder`.
  - Obs. 2 (confirmada con prueba): el Select de un `ElementoCatalogo` rechazaba la referencia ya desactivada
    y no dejaba guardar. `CatalogosService::opciones($catalogo, $actual)` la incluye marcada «(desactivado)».
  - Obs. 4: docblock de `RegistroAcciones::todas()` advierte usar `obtener()`.
  - Obs. 3 (INEI que vuelve a publicar un retirado no sale en el resumen), 5 (`Campo` atado a `Catalogo`) y 6
    (listas oficiales ya no se actualizan por código, E9): aceptadas tal cual; la 3 y la 6 están en el ADR.
  ADR 0013 al día. Siguiente: paso 10.
- 2026-10-10 — Paso 10. Cierre: `composer check` en verde (pint, phpstan sin errores, 12 migraciones conformes,
  396 pruebas / 1150 aserciones). Sin escenario de aceptación del v4 que aplique (catálogos no son un flujo).
  Sin datos reales. `pendientes.md` sin cambios (quién recibe `catalogos.editar` y `catalogos.editar-sensibles`
  es la 1.4). Plan a `completados/`, fila 1.2b del mapa con ✅. Pendiente del desarrollador: push y PR.
