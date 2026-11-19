# Plan: plantillas personalizables, el mecanismo del núcleo y su primer tipo (tarea 2.11)

- **Estado:** completado
- **Responsable:** Sebastián (área 1 y núcleo; ADR 0020; modo actual: trabaja solo)
- **Entrega:** `entrega/plantillas` (aparte; después de la 2.9b). Este plan y el ADR viajan en `plan/adr-plantillas`.
- **Módulo(s):** `Nucleo` (mecanismo), `Erp/Presupuestos` (el tipo «árbol de partidas de control»)
- **Fase:** F2
- **Creado:** 2026-11-12 · **Actualizado:** 2026-11-15

## Objetivo

Que quien arma un árbol de partidas de control pueda guardarlo como plantilla (personal o, con permiso, de la empresa)
y usarla en otro proyecto: la plantilla arma la propuesta editable del árbol, se confirma por la acción de siempre, y
el árbol creado dice de qué plantilla y versión salió. El núcleo no sabe nada del árbol: el segundo tipo (presupuesto
con APU) entra después solo con su definición, su columna y sus botones.

## Contexto que leí

- `docs/decisiones/0022-plantillas-personalizables.md` (aceptado; este plan es su primera tarea).
- ADR 0016 (campos sensibles), ADR 0020 (áreas y costuras), ADR 0021 (permisos con su área).
- `docs/convenciones/base-de-datos.md` (FK con índice, `NOT NULL`, `CHECK`, `version`), `codigo.md` (acciones,
  sensibles), `interfaz.md` (panel deslizante, íconos únicos).
- `docs/planes/completados/2026-11-11-pantallas-area-1.md` (la pestaña «Partidas de control», D5 del costo sugerido).
- Plan de la 2.9b (por escribir): la **propuesta editable del árbol** que esta tarea reutiliza como destino.
- `docs/diseno/v4` parte 1 y parte 6: «plantilla contable» (etapa 3) es otra cosa; el glosario las distingue.
- Pendientes que tocan: **Tipologías de proyecto** (las «plantillas de fase» del área 4): esta tarea no las define ni
  depende de ellas. **Organigrama en espera**: por eso el responsable no se guarda en la plantilla (ver D6).

## Decisiones

- **D1 (2026-11-12, desarrollador).** Solo plantillas **personales** (las ve y usa su dueño) y **de la empresa** (las ve
  todo el que puede crear ese documento). No se comparten con una persona o un equipo en particular.
- **D2 (2026-11-12, desarrollador).** Publicar en la empresa pide **el permiso de su tipo**, con su área (ADR 0021). El
  árbol usa `erp.presupuestos.plantillas.publicar`, que compartirán los demás tipos del área 1.
- **D3 (2026-11-12, desarrollador).** **Siempre se aplica la última versión**: el selector ofrece solo la vigente; las
  anteriores quedan como historia. Aplicar **copia** el contenido al documento, no lo enlaza: publicar una versión nueva
  no cambia lo ya hecho, y lo aprobado no se toca (regla 6). El documento guarda la versión de la que salió en
  `plantilla_version_id` (FK con índice a `plantilla_versiones`; nula solo si el documento no vino de una plantilla).
- **D4 (2026-11-12, desarrollador).** **El árbol es el primer tipo.** El contrato entre el núcleo y el tipo (registro,
  permiso de publicar, cómo se guarda el contenido, cómo se aplica) no asume nada del árbol: el contenido es del tipo
  y el núcleo no lo interpreta. El segundo será el presupuesto con APU, que sí lleva montos, rendimientos y análisis;
  si hay montos, nunca `float` (regla 4).
- **D5 (2026-11-12, desarrollador).** Un tipo que guarda montos declara su **grupo sensible** (ADR 0016). Sin ese
  permiso, la plantilla aparece en la lista pero su contenido no se muestra ni se envía al navegador, y no se puede
  aplicar. El árbol no guarda montos: no declara grupo.
- **D6 (2026-11-12, desarrollador).** La plantilla de árbol guarda **códigos, nombres y jerarquía** de las partidas, y
  si una es la de gastos generales. **No guarda montos** (el costo sale del presupuesto aprobado, D5 de la 2.10) **ni
  responsables**: con el organigrama en espera no hay puestos reales a los que atarlos, así que el responsable se
  elige en la propuesta (por omisión, el jefe del proyecto). Tampoco guarda la PC-99, que la crea el sistema al
  aprobar.
- **D7 (2026-11-12, desarrollador; ajustada el mismo día).** Una plantilla se guarda desde un árbol **en cualquier
  estado** (un borrador bien armado ya sirve). «Aplicar plantilla» se ofrece si el proyecto **no tiene árbol** o si
  tiene un **árbol en borrador sin ninguna partida**: la misma regla que «Proponer desde el presupuesto» de la 2.9b (su
  D3), con **la misma comprobación única** de «el árbol se puede llenar» que construye la 2.9b; no se duplica. Si el
  borrador tiene partidas, se rechaza con un mensaje que dice por qué y qué hacer (por ejemplo: «El árbol de
  PRY-2026-0012 (v1, en borrador) ya tiene partidas: edítalo en su pestaña, o anúlalo antes de usar una plantilla»),
  nunca con un error genérico; la pantalla lo muestra tal cual. En la pantalla, **«Desde una plantilla» es una opción
  del panel «Armar el árbol» de la 2.9b** (su D7), no un botón propio.
- **D8 (2026-11-14, desarrollador).** Desde una plantilla, el **costo presupuestado** del árbol es un campo del panel,
  sugerido y editable como en «Crear el árbol vacío»: el del borrador vacío si ya existe; si no, costo directo + gastos
  generales del presupuesto aprobado (flujo 1: 19,525.00); si no hay ninguno, lo escribe la persona. Las hojas nacen sin
  monto: todo el costo queda «por repartir».
- **D9 (2026-11-14, desarrollador).** En la pestaña «Partidas de control», el panel «Plantillas» **solo administra**
  (guardar el árbol como plantilla, renombrar, actualizar, publicar, desactivar), sin «Usar» (`ofrecerUsar: false`).
  Usar una plantilla tiene un solo camino: «Armar el árbol» → «Desde una plantilla».
- **D10 (2026-11-14, desarrollador).** Si la plantilla tiene una versión nueva entre abrir la propuesta y confirmarla, se
  **rechaza** («La plantilla «X» cambió (ahora v2) mientras revisabas la propuesta: vuelve a abrirla.») y no crea nada,
  como el cambio de presupuesto en la 2.9b: lo que se confirma es lo que se vio, y el árbol guarda la versión que usó.
- **D11 (2026-11-14, desarrollador; de la revisión de arquitectura).** Crear o armar el árbol de partidas (vacío,
  desde el presupuesto o desde una plantilla) **pide el permiso de ver costos**: quien lo arma tiene que conocer el
  costo presupuestado. Sin él no se ofrece «Armar el árbol» ni «Crear el árbol vacío», y las tres acciones lo rechazan
  también sin la pantalla (`ArbolPartidasService::exigirVerCostos`). Quien lo tiene ve el costo como en D8. Cambia lo
  de la 2.9b, que dejaba armar desde el presupuesto sin ver montos.

## Modelo de datos (núcleo)

- `plantillas`: `id`, `tipo` (clave del tipo registrado, `varchar`), `nombre`, `descripcion` (nula), `alcance`
  (`CHECK in ('personal','empresa')`), `propietario_user_id` (FK con índice; `CHECK`: no nula si es personal, nula si
  es de la empresa), `es_activa`, `version` (bloqueo optimista, `CHECK >= 1`), `timestamps`. Único entre las activas:
  `(tipo, alcance, coalesce(propietario_user_id, 0), lower(nombre))`.
- `plantilla_versiones`: `id`, `plantilla_id` (FK con índice), `numero` (`CHECK >= 1`; único con `plantilla_id`),
  `formato` (entero del tipo, `CHECK >= 1`), `contenido` (`jsonb not null`), `creada_por_user_id` (FK con índice),
  `origen_documento` y `origen_id` (de qué documento se guardó, nulos si se creó a mano; sin FK porque el documento es
  de otro módulo), `created_at`. Inmutable: una versión no se edita.
- En el documento del tipo (esta tarea, `partida_arboles`): `plantilla_version_id` (FK con índice, nula).

## Pasos

- [x] 1. Plan y ADR 0022 aceptado (D5, D6 y D7 confirmadas).
- [x] 2. Migraciones del núcleo (`plantillas`, `plantilla_versiones`) con sus `CHECK` y únicos; modelos con bitácora.
- [x] 3. El contrato: `DefinicionPlantilla` (clave, nombre, permiso de publicar, formato actual, `validar()`,
  `grupoSensible()`, `capturar(documentoId)`, `proponer(contenido, formato, contexto)`) y `RegistroPlantillas`.
  Pruebas con un tipo falso, para que el núcleo no dependa del árbol.
- [x] 4. `PlantillasService` y sus acciones registradas: guardar como plantilla, editar (versión nueva), publicar en la
  empresa, desactivar; `disponibles(tipo, usuario)` (las suyas y las de la empresa, solo la vigente). Permisos en
  `CatalogosSeederTest`. Concurrencia: dos ediciones a la vez no crean la misma versión (único + versión leída).
- [x] 5. Piezas de pantalla comunes: el selector «Usar plantilla» y el panel deslizante «Plantillas» (Mías / De la
  empresa) con los patrones de `interfaz.md`; contenido oculto si falta el grupo sensible del tipo.
- [x] 6. El tipo árbol en `Erp/Presupuestos`: su definición
  (D6), `partida_arboles.plantilla_version_id`, «Guardar como plantilla» en la pestaña «Partidas de control», «Desde
  una plantilla» como opción del panel «Armar el árbol» de la 2.9b (arma su propuesta y usa su comprobación de «el
  árbol se puede llenar», D7), y «Creado desde … vN» en la pestaña. Con la columna, el `CHECK` en `partida_arboles`:
  presupuesto o plantilla, no los dos (D6 de la 2.9b). El responsable por omisión sale del mismo lugar que en la 2.9b
  (su D4: el jefe del proyecto; con jefe inactivo, elegir antes de confirmar).
- [x] 7. Documentos: glosario («plantilla» frente a «plantilla contable»), `ARCHITECTURE.md` (el núcleo expone
  `PlantillasService`), `interfaz.md` si nace un patrón, mapa.
- [x] 8. Revisor de arquitectura y `composer check:tarea` en verde.

## Preparado para lo diferido

- **Presupuesto con APU, segundo tipo:** solo le falta su `DefinicionPlantilla` (capturar títulos, partidas con unidad,
  rendimiento y sus líneas de APU; montos como texto decimal; grupo sensible «costos»), la columna
  `presupuestos.plantilla_version_id` y los botones en su ficha. El núcleo no cambia.
- **Ver qué cambió entre versiones:** cada documento guarda `plantilla_version_id`; comparar dos contenidos del mismo
  tipo es una función más de su definición (`diferencias(a, b)`), sin tocar las tablas.
- **Responsable por puesto:** cuando se entregue el organigrama, la plantilla de árbol podrá guardar el puesto
  responsable (formato 2 del tipo) y la propuesta lo resolverá a la persona.
- **Compartir con personas o equipos:** descartado por ahora (D1); si se pide, es un alcance más en `plantillas`.
- **Tipologías de proyecto:** si se definen, una plantilla podrá llevar su tipología como dato del tipo.
- **Otras áreas** (cotización, contrato, pedido recurrente, requisitos por etapa): registran su tipo cuando construyan
  su módulo.

## Criterio de aceptación

- Núcleo, con un tipo falso: guardar, editar (versión 2), publicar con y sin permiso, desactivar; solo la vigente se
  ofrece; personal ajena no se ve; contenido de un tipo sensible oculto sin su permiso.
- Árbol: guardar como plantilla el árbol del flujo 1, aplicarla en otro proyecto sin árbol y en uno con un borrador
  vacío, editar la propuesta,
  confirmar, y que el árbol nuevo tenga las partidas, sin montos ni responsables copiados, con
  `plantilla_version_id` y «Creado desde … v1» a la vista; editar la plantilla no cambia el árbol ya creado.
- Árbol, D7: aplicar una plantilla a un proyecto cuyo árbol en borrador ya tiene partidas se rechaza con el mensaje que
  explica por qué y qué hacer (en el servicio y en la pantalla), y no crea nada.
- `CatalogosSeederTest`, `IconosUnicosTest`, `EtiquetasEnEspanolTest` y `ArquitecturaTest` en verde.

## Bitácora

- 2026-11-12 — Plan creado con las cuatro respuestas del desarrollador al ADR 0022 (D1 a D4). D5 a D7 quedan
  propuestas, por confirmar. Sin código hasta aceptar el ADR; va después de la 2.9b, que construye la propuesta
  editable del árbol.
- 2026-11-12 — Paso 1 hecho. El desarrollador confirmó D5, D6 y D7, con un ajuste a D7 (el rechazo a un proyecto que ya
  tiene árbol explica por qué y qué hacer, y es escenario de prueba). ADR 0022 aceptado.
- 2026-11-12 — Rama `entrega/plantillas` creada en esta copia, apilada sobre `plan/adr-plantillas` (decisión del
  desarrollador). **El paso 6 está bloqueado por la 2.9b** (usa su propuesta editable del árbol): los pasos 2 a 5 no
  dependen de ella. Cuando `plan/adr-plantillas` se fusione en `main`, traer `main` a esta rama antes de seguir, para
  que su PR muestre solo el código.
- 2026-11-12 — Paso 2 hecho. Migración `2026_11_12_000001_crear_plantillas` (no están en el DBML: nacen del ADR 0022).
  `plantillas`: alcance con CHECK de lista, dueño exigido si es personal y prohibido si es de la empresa (D1), nombre
  comparable como en motivos y único entre las activas del mismo tipo y dueño (`COALESCE(propietario_user_id, 0)`),
  bloqueo optimista. `plantilla_versiones`: número único por plantilla, formato desde 1, contenido `jsonb` que debe
  ser objeto (el núcleo no lo interpreta, D4), origen (clave del documento + id, sin llave: es de otro módulo) completo
  o vacío. Modelos `Plantilla` (tipo, alcance y dueño inmutables; no se borra; `vigente()` = número mayor, D3; alias
  `plantilla` en el morph map) y `PlantillaVersion` (inmutable; `contenido` oculto al serializar, D5). Pruebas:
  `PlantillasTablasTest` (6).
- 2026-11-12 — Paso 3 hecho. `Nucleo/Plantillas/DefinicionPlantilla` (clase abstracta, como `DefinicionDocumento`):
  `clave()`, `nombre()`, `permisoUsar()` (el de crear el documento, D1), `permisoPublicar()` (D2), `formatoActual()`,
  `validar(contenido, formato)` (lista de problemas), `capturar(documentoId, usuario)`,
  `proponer(contenido, formato, contexto, usuario)` (devuelve la propuesta del tipo, no crea nada), y con valor por
  omisión `documentoOrigen()` y `grupoSensible()` (D5). Se agregó `permisoUsar()`, que el plan no nombraba: el núcleo
  necesita saber quién ve las de la empresa. `RegistroPlantillas` (singleton) valida clave `<area>.<modulo>.<cosa>`
  de hasta 80, nombre, forma de los dos permisos y formato desde 1; los permisos los declara cada módulo.
  Excepciones: `DefinicionPlantillaInvalida` (programación) y `PlantillaRechazada` (de negocio, con `conMotivo` para
  los rechazos de cada tipo, como el D7). Probado con `tests/Modules/Nucleo/Fixtures/TipoPlantillaDePrueba` (lista de
  cosas, formato 2 y lectura del 1, sensible opcional): `PlantillasRegistroTest` (12).
- 2026-11-12 — Paso 4 hecho. `Nucleo/Services/PlantillasService`: `disponibles(tipo, usuario)` (las propias primero y
  las de la empresa, activas, sin contenido: `ResumenPlantilla`), `guardar`, `editar` (nombre y descripción),
  `actualizarDesde` (versión siguiente desde un documento), `publicar` (crea otra, de la empresa, con la vigente como
  v1), `desactivar`, `aplicar` (la propuesta del tipo con la vigente: `PropuestaDesdePlantilla`, con
  `plantillaVersionId`) y `origen(versionId)` («Videovigilancia v3»). Quién puede: usar, el `permisoUsar` del tipo más
  su grupo sensible (D5); una personal, solo su dueño (para los demás «no existe»); una de la empresa la cambia quien
  publica. Bloqueo optimista con `version` y `lockForUpdate`; el número de versión, único en la base: dos cambios a la
  vez con la misma versión leída, el segundo recibe `VersionDesactualizada` (probado; no hizo falta una prueba de
  procesos reales: es el mismo mecanismo que motivos y parámetros). Nombre repetido: mensaje propio (mía o de la
  empresa). Acciones `nucleo.plantillas.{guardar,editar,actualizar,publicar,desactivar}` **sin permiso propio**: el
  permiso depende del tipo y lo revisa el servicio (como las aplicaciones propias del panel); por eso
  `CatalogosSeederTest` no cambia. Los permisos de cada tipo los declara su módulo (paso 6). Pruebas:
  `PlantillasServiceTest` (12).
- 2026-11-12 — Paso 5 hecho. `Nucleo/Filament/Plantillas/PlantillasDeTipo` (Livewire `nucleo.plantillas`): tabla de
  las mías y las de la empresa (nombre, descripción, «Mía» / «De la empresa», «v2 · 12/11/2026»), filtro «Ver»
  (Mías / De la empresa), «Guardar como plantilla» (solo con `documentoId`), y por fila «Usar» (despacha
  `plantilla-elegida` con `plantillaId` y `tipo`; la pantalla del módulo arma la propuesta con su contexto; se apaga
  con `ofrecerUsar: false`), «Actualizar desde este documento», «Cambiar nombre», «Publicar en la empresa» (solo la
  propia y con el permiso del tipo) y «Desactivar»; todo por las acciones `nucleo.plantillas.*`. El contenido nunca
  llega a la pantalla, y sin el grupo sensible del tipo ni la lista (D5). El panel deslizante es un partial,
  `nucleo::filament.plantillas.panel` (`$tipo`, `$documentoId`, `$ofrecerUsar`, `$alCerrar`), con las piezas de
  Catálogos (`.tq-catalogo-panel`), la X y Esc: el módulo lo pone dentro de su `.tq-catalogos`, como el APU. Descubierto:
  tras una acción, la respuesta de Livewire escapa las tildes y `assertSeeInOrder` con «Mía» falla; se revisa en una
  pantalla recién dibujada. Pruebas: `PlantillasPantallaTest` (9).
- 2026-11-12 — Paso 7 adelantado (no depende de la 2.9b; decisión del desarrollador). Glosario: «Plantilla» y
  «Plantilla contable» (etapa 3), que no son lo mismo. `ARCHITECTURE.md`: el núcleo suma las plantillas en su fila y un
  párrafo después de los tres mecanismos (las usa el módulo que lo necesita). `codigo.md`: sección «Plantillas» con lo
  que hace un módulo para sumar un tipo (definición, permisos, contenido, rechazos, `plantilla_version_id`, pantalla,
  pruebas). `interfaz.md`: el patrón del panel de plantillas. El mapa ya tenía la fila de la 2.11. Si el paso 6 cambia
  algo de esto, se ajusta ahí.
- 2026-11-12 — Ajuste del desarrollador a D7: aplicar plantilla vale sin árbol o con un borrador vacío (la regla de D3
  de la 2.9b), con la misma comprobación única que construye la 2.9b, y en pantalla es una opción del panel «Armar el
  árbol» de la 2.9b, no un botón propio. ADR 0022 actualizado. **El paso 6 sigue esperando a la 2.9b** (rama
  `entrega/presupuesto-arbol`, plan `2026-11-12-proponer-arbol.md`).
- 2026-11-12 — De la 2.9b (sus D4 y D6): el responsable por omisión se toma del mismo lugar que allí, y el paso 6 agrega
  el `CHECK` «presupuesto o plantilla, no los dos» junto con `plantilla_version_id`.
- 2026-11-14 — La 2.9b está en `main` (PR #94); `main` traído a esta rama (conflicto en el mapa: queda la fila 2.9b ✅
  de `main` y la 2.11 con su entrega y el ADR aceptado). El paso 6 se desbloquea. Dudas del paso respondidas por el
  desarrollador: D8 (costo sugerido y editable), D9 (el panel solo administra) y D10 (versión nueva al confirmar: se
  rechaza).
- 2026-11-14 — Paso 6, primera parte (servicio y acción; la pantalla va en la segunda). Migración
  `2026_11_14_000101_plantilla_del_arbol`: `partida_arboles.plantilla_version_id` (FK con índice a
  `plantilla_versiones`, nula) y `CHECK partida_arboles_un_origen_check` (presupuesto o plantilla, no los dos).
  `Erp/Presupuestos/Plantillas/PlantillaArbol` (clave `erp.presupuestos.arbol`; usar con `erp.presupuestos.arbol.editar`,
  publicar con `erp.presupuestos.plantillas.publicar`, declarado por el módulo y sumado a `CatalogosSeederTest`;
  formato 1 con código, nombre, padre y gastos generales; `validar()` rechaza la PC-99, repetidas, padres que no
  cuadran y más de una de gastos generales o una con hijas). `ConsultaArbol`: `propuestaConPartidas` (la comprobación
  única de D3 de la 2.9b y su responsable por omisión), `costoParaLlenar` (D8), `propuestaDesdePlantilla` (D10) y
  `plantillaDe` («Videovigilancia v1»). `PropuestaArbol` lleva `plantillaVersionId` y `con()`; `armar()` la guarda.
  Acción `erp.presupuestos.armar-arbol-desde-plantilla` (permiso de editar el árbol; costo obligatorio). Pruebas:
  `PlantillaArbolTest` (10).
- 2026-11-14 — Paso 6 hecho (segunda parte, la pantalla). En «Partidas de control»: «Desde una plantilla» es un origen
  más de `origenes()` (aparece si la persona tiene alguna plantilla de árbol que puede usar); el panel suma el selector
  de la plantilla (cambiarla vuelve a armar la propuesta), la versión revisada oculta (D10) y el costo presupuestado
  sugerido y editable (D8; sin ver costos llega vacío, como en «Crear el árbol vacío»). La descripción dice «Creado
  desde la plantilla Cámaras v1». El botón «Plantillas» (con el permiso de editar el árbol) abre el panel común con
  `ofrecerUsar: false` (D9) en la vista nueva `presupuestos::partidas-de-control`. `codigo.md` («Plantillas»): los dos
  caminos para usar una plantilla, D10 y el árbol como ejemplo. Pruebas: `PlantillaArbolPantallaTest` (4).
  Observado (también en la 2.9b): si mientras el panel está abierto el borrador recibe partidas, el botón «Armar el
  árbol» deja de estar visible y Filament ignora el envío sin aviso; el mensaje de D7 sale al cambiar de origen o de
  plantilla, y la pestaña ya no ofrece el botón. Se deja para el revisor.
- 2026-11-14 — Paso 8: revisión de arquitectura. **Bloqueante, arreglado:** la acción aceptaba confirmar sin la versión
  revisada (campo opcional), y desde la pantalla, si la propuesta fallaba, se podía confirmar una vacía y armar con la
  vigente sin haberla visto (D10). Ahora `plantilla_version_id` es obligatorio en la acción, y el panel no confirma sin
  propuesta. **Observaciones:** (2) el envío que Filament ignoraba sin aviso, en los dos orígenes: «Armar el árbol»
  sigue visible mientras su panel está abierto y la clave de la acción sale de una tabla fija (`ACCIONES`), así que el
  servicio rechaza con el mensaje de D7 (probado al confirmar); (3) y (4) resueltas con D11 (decisión del
  desarrollador: sin ver costos no se arma el árbol); (5) pruebas de los rechazos: versión obligatoria, costo negativo,
  sin ver costos, plantilla personal ajena y proyecto anulado (la de una plantilla de otro tipo queda sin prueba: pide
  registrar un tipo falso con sus permisos, y el código es una línea); (6) `publicar()` bloquea la fila de origen y relee
  su vigente dentro de la transacción. Pruebas ajustadas a D11: `ArbolPartidasTest`, `ArmarArbolDesdePresupuestoTest`,
  `ArmarArbolPantallaTest` y `PartidasDeControlPantallaTest`.
- 2026-11-14 — `check:tarea`: 2745 de 2746 en verde. Falló `MorphMapTest`: `PlantillaVersion` no tenía alias en el
  morph map (lo pide para la bitácora de cambios). Agregado `plantilla_version`. Se vuelve a correr.
- 2026-11-15 — Tarea cerrada. La segunda corrida de `check:tarea` (175 de 227 archivos en un solo proceso, cerca de una
  hora) se detuvo: se cruzaba con el `composer check` de la copia comercial y tardaba demasiado. En su lugar, todas las
  pruebas en paralelo (`php artisan test --parallel --processes=6`): **3153 en verde** en 27,5 minutos (pint, phpstan y
  migraciones ya estaban en verde en la corrida anterior; después solo cambió el alias del morph map, con pint y
  phpstan). Como no pasó por el script, no quedó marcada la «última corrida verde» en esta copia. Decisión del
  desarrollador: `check:tarea` correrá todas en paralelo cuando le toquen muchos archivos (rama `arreglo/` aparte).
  Plan a `completados/`, mapa con ✅.
