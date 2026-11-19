# Plan: proponer el árbol de partidas desde el presupuesto aprobado (tarea 2.9b)

- **Estado:** terminado
- **Responsable:** Sebastián (área 1, Presupuesto y costo; ADR 0020)
- **Entrega:** `entrega/presupuesto-arbol` (sola; decidido el 2026-11-11)
- **Módulo(s):** `Erp/Presupuestos`
- **Fase:** F2
- **Creado:** 2026-11-12 · **Actualizado:** 2026-11-14

## Objetivo

Que en la pestaña «Partidas de control» de un proyecto con presupuesto aprobado se pueda pedir **«Proponer desde el
presupuesto»**: el sistema muestra una propuesta del árbol (un nodo por título y por partida, más los gastos
generales), la persona la revisa y, al confirmar, nace el árbol en borrador con esas partidas, sus montos y su origen
en el presupuesto, que se ve en la columna «Origen». Con el flujo 1, el árbol propuesto suma 19,525.00 (costo directo
17,750.00 + gastos generales 1,775.00) y queda listo para aprobar.

La propuesta es la pieza que la 2.11 reutiliza para las plantillas (ADR 0022): «desde el presupuesto», «desde una
plantilla» y «desde cero» son tres orígenes de una misma propuesta, que confirma una sola acción.

## Contexto que leí

- `docs/diseno/v4/parte-4-flujo-01.md`, paso 4 (líneas 92–112): «Pueden partir de cero o pedirle al sistema una
  propuesta desde el presupuesto y ajustarla. Cada partida recibe un responsable.» El ejemplo separa, dentro de la
  partida 01.01, materiales (5,830.00) y mano de obra (600.00), y muestra la columna «Origen en el presupuesto».
- `docs/diseno/v4/parte-6-fichas-documentos.md`, árbol de partidas: «Proponer desde presupuesto (opcional): Borrador →
  Borrador, propone un nodo por título y partida»; «Origen: referencia opcional a la partida o insumo del presupuesto»;
  presupuestado solo en hojas; una persona responsable por partida.
- DBML v2: `partida_arboles.presupuesto_id`, `partidas_control.origen_titulo_id` y `origen_partida_id` (la migración
  de la 2.1 los dejó para esta tarea).
- `ArbolPartidasService` (crear, agregar, fijar, desactivar; códigos `PC-NN.NN`, la PC-99 reservada),
  `ConsultaArbol::costoSugerido` (D5 de la 2.10: costo directo + gastos generales del aprobado vigente) y la pestaña
  `PartidasDeControlDeProyecto` (dejó el lugar del botón y de la columna «Origen»).
- ADR 0022 y plan 2.11 (rama `entrega/plantillas`, sin fusionar): la propuesta editable y `plantilla_version_id`.
- Pendientes: **«Árbol de partidas»** (quién responde por cada partida; cómo es un árbol real): «Nada: el árbol es
  libre»; no bloquea, y por eso el responsable se propone por omisión y se cambia (D4).

## Decisiones

- **D1 (2026-11-12, desarrollador). Qué nodos propone.** Uno por título (padre, suma de sus hijos), uno por subtítulo
  y uno por partida (hoja, con su parcial), con el código del presupuesto: título 01 → PC-01, partida 01.01 → PC-01.01.
  Separar por tipo de insumo, como el ejemplo del flujo 1, se hace después a mano en el borrador.
- **D2 (2026-11-12, desarrollador). Los gastos generales.** Una hoja más en la raíz, «Gastos generales», marcada
  `es_gastos_generales`, con el monto de gastos generales del presupuesto, para que el árbol cuadre al proponerse. Su
  código es el **siguiente código raíz libre**, no fijo: en el flujo 1 da exactamente **PC-04 con 1,775.00**, y el árbol
  19,525.00 = 17,750.00 + 1,775.00 (escenario de prueba con esos números). Si el presupuesto no tiene gastos generales
  (cero), no se crea la hoja. En la propuesta se puede renombrar o cambiar su responsable, pero la marca se mantiene.
- **D3 (2026-11-12, desarrollador). Cuándo se puede llenar el árbol.** Si el proyecto **no tiene árbol** (la acción lo
  crea en borrador y lo llena) o si tiene un **árbol en borrador sin ninguna partida** (la acción lo llena). Motivo: la
  ficha del árbol (parte 6) pone «Proponer desde presupuesto» como acción del borrador (Borrador → Borrador), y «Crear
  el árbol» ya deja borradores vacíos: quien creó el árbol primero no puede quedar atrapado. Si el borrador ya tiene
  partidas (incluido el que nace de «Nueva versión», que conserva las que tienen costos), se rechaza con un mensaje que
  dice por qué y qué hacer; nunca se pisa nada. «Desde el presupuesto» exige además un presupuesto aprobado y vigente.
  Es **una sola comprobación** de «el árbol se puede llenar», en un solo lugar, que usan todos los orígenes
  (presupuesto, plantilla y, a futuro, archivo). Se prueban los tres casos: sin árbol, borrador vacío y borrador con
  partidas (rechazo).
- **D4 (2026-11-12, desarrollador). Responsable.** Por omisión, todas las partidas propuestas llevan al **jefe del
  proyecto**; se cambia por partida en la propuesta y después con «Editar partida» en el borrador. El valor por omisión
  sale de **un solo lugar** que usan todos los orígenes (también la plantilla, D6 de la 2.11), no cada uno por su
  cuenta. No se infiere desde puestos ni especialidades: el pendiente «Árbol de partidas» y el organigrama siguen
  abiertos (regla 12). Si el proyecto no tiene un jefe que pueda responder (inactivo), la propuesta no inventa a nadie:
  pide elegir el responsable antes de confirmar, con un mensaje claro. En pruebas, usuarios inventados.
- **D5 (2026-11-12, desarrollador). Qué se cambia en la propuesta.** Antes de confirmar: renombrar y cambiar el
  responsable por partida, y quitar partidas. Reagrupar, mover, agregar y fijar montos se hacen después en el borrador
  con sus acciones de siempre; no se duplican en la ventana. Se suma **«Editar partida»** (nombre y responsable) en el
  borrador, que hoy no existe.
  - Quitar una partida no hace desaparecer su monto: queda «por repartir», visible en el borrador, y Aprobar sigue
    exigiendo que las hojas sumen el costo presupuestado.
  - Quitar un título quita también sus hijas, y todo su monto queda por repartir; la ventana lo avisa antes.
  - La hoja de gastos generales (D2) no se puede quitar en la propuesta.
  - Lo que se cambie en la propuesta no altera el origen guardado de cada partida.
- **D6 (2026-11-12, desarrollador). Origen.**
  - `partida_arboles.presupuesto_id`: el presupuesto aprobado del que salió la propuesta; nulo en un árbol armado a
    mano o desde plantilla.
  - Cada partida guarda `origen_titulo_id` u `origen_partida_id`, como máximo uno (`CHECK`), los dos con FK e índice.
    La hoja de gastos generales no lleva ninguno: su origen se deduce de su marca y del presupuesto del árbol.
  - La columna «Origen» muestra «título 01», «01.01 · Tendido de cable UTP» o «gastos generales del presupuesto»;
    vacía si la partida no salió del presupuesto.
  - El origen es histórico: renombrar o cambiar el responsable (en la propuesta o en el borrador) no lo altera; una
    partida agregada después nace sin origen.
  - La base garantiza que el origen sea del presupuesto del árbol (revisión de arquitectura): `origen_presupuesto_id`
    con llaves compuestas al título o la partida y al árbol; el presupuesto del árbol no cambia mientras alguna
    partida salga de él.
  - Un árbol desde plantilla guarda su `plantilla_version_id` (2.11), no estos orígenes. `CHECK` en `partida_arboles`:
    presupuesto o plantilla, no los dos; como `plantilla_version_id` todavía no existe, ese `CHECK` lo agrega la 2.11.
- **D7 (2026-11-12, desarrollador). Un solo botón «Armar el árbol».** Cambio de pantalla respecto del v4, que dibuja
  botones separados. En la pestaña hay un solo botón, «Armar el árbol», que abre un panel deslizante (el que ya tiene el
  sistema, `docs/convenciones/interfaz.md`; nada de un patrón nuevo) con los orígenes:
  - **Desde el presupuesto:** esta tarea.
  - **Desde una plantilla:** la 2.11 lo suma como opción cuando llegue a su paso.
  - **Desde un archivo** (Excel del cliente o MS Project, flujo 1 del v4, fase posterior, con autorización de
    Gerencia): **no se muestra** hasta que exista. No se agrega tarea al mapa por esto.

  El botón se muestra solo si el árbol se puede llenar (D3). Cada origen sigue siendo su propia acción con su permiso
  (regla 7); el panel muestra solo las que la persona puede usar, y si no puede ninguna, el botón no aparece.

## Pasos

- [x] 1. Plan aprobado (D1 a D7).
- [x] 2. Migración: `partida_arboles.presupuesto_id`, `partidas_control.origen_titulo_id` y `origen_partida_id` (FK
  con índice; un origen a la vez, `CHECK`), y que el presupuesto sea del mismo proyecto.
- [x] 3. La propuesta: `PropuestaArbol` (nodos con código, nombre, padre, monto, responsable y origen) armada por
  `ConsultaArbol::propuestaDesdePresupuesto(proyectoId)` con el rechazo de D3. Escenario F1: suma 19,525.00.
- [x] 4. Confirmar: `ArbolPartidasService::crearDesdePropuesta()` y su acción registrada (con su permiso, en
  `CatalogosSeederTest`): crea el árbol en borrador con todas las partidas en una transacción; si algo falla, no queda
  nada. Recibe un origen opcional de plantilla (punto de extensión para la 2.11).
- [x] 5. «Editar partida» (nombre y responsable) en el borrador: servicio, acción y botón.
- [x] 6. Pantalla: «Proponer desde el presupuesto» en la pestaña, la ventana con la propuesta editable, y la columna
  «Origen».
- [x] 7. Revisor de arquitectura y `composer check:tarea` en verde.

## Preparado para lo diferido

- **Orígenes del árbol (D7):** el panel «Armar el árbol» admite orígenes nuevos sin rehacerse: agregar plantilla o
  archivo es registrar una opción más. La propuesta editable recibe las partidas propuestas sin importar de dónde
  salieron, y cada partida guarda su origen (hoy: de qué título o partida del presupuesto; a futuro: plantilla y
  versión, o archivo y fila).
- **Plantillas (2.11):** `crearDesdePropuesta()` recibe la propuesta venga de donde venga; la plantilla de árbol arma
  una `PropuestaArbol` (la 2.11 le suma `plantillaVersionId` y lo guarda en el árbol), y en la pantalla basta una entrada
  más en `origenes()` con su etiqueta, su acción y cómo arma su propuesta. **Pendiente para la 2.11 (rama `entrega/plantillas`, no
  se toca desde aquí):** su D7 dice «se aplica solo a un proyecto sin árbol»; debe pasar a la regla de D3 de esta tarea
  (sin árbol o borrador vacío) y usar la misma comprobación única. *Hecho el 2026-11-12 en `entrega/plantillas`
  (e236b12), por pedido del desarrollador.*
- **Separar por tipo de insumo** (si D1 queda sin él): la propuesta se arma en un solo lugar; separar una partida en
  hojas por tipo sería una opción más de esa función, con origen en la partida.
- **Responsable por omisión (D4):** cuando se decida el pendiente «Árbol de partidas» (o llegue el organigrama), solo
  cambia ese valor por omisión, en su único lugar.
- **Origen desde plantilla (D6):** la 2.11 agrega `partida_arboles.plantilla_version_id` y el `CHECK` «presupuesto o
  plantilla, no los dos».
- **Nueva versión del árbol:** fuera de esta tarea; la propuesta no toca árboles aprobados.

## Criterio de aceptación

- F1: la propuesta del flujo 1 tiene PC-01 CÁMARAS (6,430.00), sus partidas, PC-02 CABLEADO Y POSTES…, la hoja de
  gastos generales (1,775.00) y suma 19,525.00; confirmada, el árbol en borrador cuadra y se puede aprobar.
- Sin presupuesto aprobado, o con árbol ya creado, el rechazo dice por qué y qué hacer.
- Quitar una partida en la propuesta deja ese monto por repartir; cambiar nombre y responsable se guarda.
- La columna «Origen» muestra de qué título o partida salió cada una (escenario del flujo 1, una por una).
- Flujo 1 quitando una partida en la propuesta: el borrador muestra su monto por repartir y no deja aprobar hasta
  repartirlo. Quitar un título se avisa y quita sus hijas; la hoja de gastos generales no se puede quitar.
- Proyecto con jefe inactivo: la propuesta pide elegir responsable antes de confirmar.

## Bitácora

- 2026-11-12 — Rama `entrega/presupuesto-arbol` creada desde `origin/main` (9d98482). Plan escrito; D1 a D6 por
  confirmar.
- 2026-11-12 — D1 (título y partida), D2 (hoja de gastos generales con el siguiente código raíz libre: PC-04 en el
  flujo 1; sin hoja si los gastos generales son cero) y D3 (sin árbol o borrador vacío, una sola comprobación) decididas
  por el desarrollador; nueva D7: un solo botón «Armar el árbol» con un panel de orígenes. Anotado para la 2.11: su D7
  pasa a la regla de D3 de aquí (sin tocar su rama).
- 2026-11-12 — D4 (jefe del proyecto por omisión, desde un solo lugar; con jefe inactivo, elegir antes de confirmar),
  D5 (nombre, responsable y quitar en la propuesta; lo demás en el borrador, con «Editar partida» nueva) y D6 (origen
  histórico: presupuesto en el árbol, título o partida en cada una; el `CHECK` con la plantilla lo agrega la 2.11)
  decididas. Paso 1 hecho. El ajuste a la D7 de la 2.11 se hizo en su rama (e236b12).
- 2026-11-12 — Paso 2 hecho. Migración `2026_11_12_000002_origen_del_arbol` (000002 para no chocar con
  `2026_11_12_000001_crear_plantillas` de la 2.11: la tabla `migrations` guarda solo el nombre). `presupuestos` gana
  el único `(id, proyecto_id)`; `partida_arboles.presupuesto_id` (nulo, con índice) con llave compuesta a ese único:
  el presupuesto es del mismo proyecto. `partidas_control.origen_titulo_id` y `origen_partida_id` (FK con índice),
  `CHECK` de a lo sumo uno, y otro `CHECK`: gastos generales y PC-99 sin origen. Desviación anotada en la migración:
  que el origen sea del presupuesto del árbol lo garantiza el servicio (amarrarlo en la base exigiría copiar el
  presupuesto en cada partida). `PartidaControl` declara el origen inmutable (`CodigoInmutable`), y los dos modelos
  lo suman a `$fillable`. Pruebas: `OrigenDelArbolTest` (4). Para el paso 4: al llenar un borrador vacío (D3), su
  `costo_presupuestado` pasa a ser el del presupuesto (costo directo + gastos generales), o el árbol no cuadraría.
- 2026-11-12 — Paso 3 hecho. `PropuestaArbol` (proyecto, borrador a llenar o nulo, costo presupuestado, responsable
  por omisión, nodos, presupuesto; `totalHojas()`) y `NodoPropuesto` (código, nombre, padre, hoja, monto, gastos
  generales, origen de título o partida y su texto). En `ConsultaArbol`: **`arbolParaLlenar(proyectoId)`, la
  comprobación única de D3** (nulo sin árbol; el borrador si está vacío; rechazo explicado si está aprobado o tiene
  partidas) con `sePuedeLlenar()` para la pantalla; **`responsablePorOmision(proyectoId)`, el lugar único de D4** (el
  jefe si es un usuario activo; si no, nulo); `propuestaDesdePresupuesto(proyectoId)` (D1, D2, D6; exige presupuesto
  aprobado y vigente y proyecto que acepta costos; no crea nada) y `presupuestoAprobado()`, que ahora usa también
  `costoSugerido()`. Casos borde: un título 99 del presupuesto chocaría con la PC-99 y se rechaza explicando; sin raíz
  libre para gastos generales (hasta la 98), igual; un nombre de más de 200 caracteres (la partida del presupuesto
  admite 300) se acorta con «…» y se puede cambiar en la propuesta. Escenario F1 con los números del v4: PC-01.01
  6,430.00, PC-02.01 2,412.00, PC-02.02 4,360.00, PC-03.01 4,308.00, PC-03.02 240.00, PC-04 gastos generales 1,775.00;
  total 19,525.00. Pruebas: `PropuestaArbolTest` (8).
- 2026-11-12 — Paso 4 hecho. `ArbolPartidasService::crearDesdePropuesta(propuesta, ajustes, usuario)`: vuelve a
  pasar por `arbolParaLlenar()` (si el árbol cambió desde que se armó la propuesta, rechaza), crea el borrador o
  bloquea el vacío y revisa de nuevo que no tenga partidas (dos confirmaciones a la vez: la segunda se rechaza), le pone
  el costo del origen y el `presupuesto_id`, y agrega las partidas con `agregarPartida()` (que ahora recibe el origen)
  y sus montos con `fijarPresupuestado()`, todo en una transacción: si una falla, no queda nada. `AjustesPropuesta`
  (D5): nombres y responsables por código, partidas quitadas (un título se lleva sus hijas; la de gastos generales no
  se quita; un código que no está en la propuesta se rechaza; quitarlas todas también) y el responsable para todas
  (D4: si el jefe no es un usuario activo y nadie lo elige, se rechaza pidiendo elegirlo). Acción
  `erp.presupuestos.armar-arbol-desde-presupuesto` con el permiso existente `erp.presupuestos.arbol.editar` (no hay
  permiso nuevo: `CatalogosSeederTest` no cambia); arma la propuesta en el servidor, nunca la de la pantalla. Sus
  ajustes por partida van como listas de textos `PC-01.01=…`, porque las acciones no tienen un tipo de campo «filas»;
  si más acciones lo piden, se agrega ese tipo al núcleo. Pruebas: `ArmarArbolDesdePresupuestoTest` (10): F1 completo
  y aprobado, origen exacto, borrador vacío llenado, borrador con partidas rechazado, renombrar y responsable,
  quitar una partida (4,360.00 por repartir y no se aprueba hasta repartirlo), quitar un título (4,548.00), gastos
  generales no se quita, jefe inactivo, falla a mitad sin rastro, y la acción.
- 2026-11-12 — Paso 5 hecho. `ArbolPartidasService::editarPartida(partidaId, nombre, responsable, versión)`: solo en
  borrador y en una partida activa; nombre con texto de hasta 200 y responsable activo; el código, el monto y el origen
  no se tocan. Acción `erp.presupuestos.editar-partida` con el permiso `erp.presupuestos.arbol.editar` (sin permiso
  nuevo). En la pestaña, botón «Editar» por fila (no en la PC-99), con nombre, responsable y versión leída al abrir. El
  selector de responsable ahora incluye también al que la partida ya tiene aunque esté inactivo (regla de AGENTS.md:
  si no, Filament rechaza el valor); el servicio pide entonces uno activo. Las filas de la tabla llevan
  `responsable_user_id`. Pruebas: `EditarPartidaControlTest` (6).
- 2026-11-12 — Paso 6 hecho. En la pestaña «Partidas de control»: botón **«Armar el árbol»** (D7) en el encabezado,
  visible solo si `sePuedeLlenar()` (D3) y hay algún origen para la persona (`origenes()`: cada uno con su acción
  registrada, su permiso y si «hay de dónde»; hoy solo «Desde el presupuesto aprobado»; la 2.11 suma la plantilla con
  una entrada más). Abre el panel deslizante de Filament (`slideOver`, como «Nuevo presupuesto»; ningún patrón nuevo)
  con el origen, «Responsable de todas» (el jefe por omisión, D4) y la propuesta en un `Repeater` fijo: por partida, su
  etiqueta (código, origen y, solo con `campos.ver-costos`, su monto), nombre, responsable («El de todas») y «No va»
  (deshabilitado en gastos generales; en un título avisa que quita sus partidas). Al confirmar se ejecuta la acción
  registrada del origen por `EjecutorAcciones`, con los ajustes como «PC-…=…»; el rechazo del servicio sale como
  mensaje y el panel no se cierra. Columna **«Origen»** (`FilaArbol::$origen`, armada en `ConsultaArbol::filas`).
  «Crear el árbol» del estado vacío pasa a llamarse «Crear el árbol vacío» (el camino a mano sigue). Pruebas:
  `ArmarArbolPantallaTest` (6).
- 2026-11-14 — Paso 7. Primer `check:tarea` (desde la última verde, 19 archivos): 314 pruebas en verde, pint y
  phpstan; no cuenta para cerrar porque los arreglos de abajo se escribieron mientras corría. Revisión de arquitectura,
  atendida:
  - **Bloqueante, rechazos sin prueba:** ahora con prueba el árbol que cambió desde la propuesta, el presupuesto
    vigente que cambió, quitar todas, el título que queda como hoja sin monto, editar una desactivada y la PC-99, el
    proyecto que no acepta costos, el título 99 y sin raíz libre, los ajustes mal escritos y la PC-99 con origen. La
    rama «otra persona acaba de armar este árbol» (dos confirmaciones a la vez sobre el mismo borrador vacío) solo se
    alcanza con procesos de verdad: la protegen el bloqueo de la fila y la revisión de partidas; no se agregó una
    prueba en `tests/Concurrencia`.
  - **Origen en la base:** `origen_presupuesto_id` con llaves compuestas (nombres cortos: PostgreSQL corta a 63); ya
    no es una desviación.
  - **Orígenes en la pantalla:** cada entrada de `origenes()` dice su acción y cómo arma su propuesta; el selector es
    `live` y vuelve a armarla; al confirmar se usa la acción del origen elegido.
  - **Confirmar:** vuelve a revisar que el presupuesto siga siendo el vigente; una llave rota sale como mensaje; un
    error de validación de la acción sale como aviso en el panel.
  - **`editarPartida`** rechaza la PC-99 también sin pantalla (la de gastos generales sí se renombra, D2).
  - Decisiones duplicadas del plan: quedó una sola versión de D4 a D7.
- 2026-11-14 — Cierre: `composer check:tarea` limpio en verde (desde la última verde, 9 archivos: 20 archivos de prueba
  más `tests/Arch` y `tests/Feature`, 322 pruebas, pint y phpstan). Sin `composer check` completo: va al cerrar la
  etapa 2. Mapa al día (2.9b ✅) y plan a `completados/`. Sigue la 2.11: su paso 6 espera a que esta entrega esté en
  `main`.
