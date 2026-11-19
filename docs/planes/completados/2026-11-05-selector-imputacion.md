# Plan: 2.2 · El selector de imputación

- **Estado:** terminado (rama subida; el PR lo abre y lo fusiona Sebastián)
- **Responsable:** Sebastián (área 1 · Presupuesto y costo, ADR 0020)
- **Módulo(s):** `Erp/Presupuestos`, Nucleo (imputación)
- **Fase:** etapa 2
- **Creado:** 2026-11-05 · **Actualizado:** 2026-11-06

## Objetivo

Que cualquier documento con costo ponga en sus líneas un solo selector de imputación: proyecto › partida, o gasto
directo con su centro de costo. Que muestre el saldo de la partida con la línea incluida antes de pedir. Que una
línea que excede quede marcada, con su motivo, y que el documento que la contiene exija su aprobación con las
aprobaciones configurables (1.7). Además, la alerta «Partida excedida o al 90 %». El «gasto directo con proyecto»
(PC-99) queda desactivado, como punto de extensión.

## Contexto que leí

- Mapa: fila 2.1 y la línea del área 1. Plan 2.1 (`completados/2026-11-04-costura-c1.md`): D6, D7, D8 y «Preparado
  para lo diferido».
- v4 parte 1 (el selector: proyecto › partida, gasto directo con o sin proyecto, el saldo con la línea, «se puede
  continuar, pero la línea se marca y exige motivo y aprobación»). v4 parte 7: alerta «Partida excedida o al 90 %»,
  al aprobar la línea que la excede, al responsable de la partida y a Jefatura de Proyectos.
- Núcleo: `ImputacionService`, `DestinoImputacion`, `CentroDeCosto`; aprobaciones de la 1.7 (`ReglasDeAprobacion`:
  las reglas van por tipo de documento, transición y monto; un paso con aprobación sale de un estado no editable);
  alertas de la 1.10 (`RegistroAlertas`, `DefinicionAlerta`); `tests/Arch` (un módulo no usa el `Filament/` de otro).
- `pendientes.md`: nada bloquea. «Aprobaciones» (U1, U2, U3) es configurable.

## Decisiones

Propuestas del agente, presentadas una por una y **cerradas** por el desarrollador (2026-11-05) antes de escribir
código.

- **D1 · Dónde vive el componente** (aprobada por el desarrollador, opción A, con una precisión; 2026-11-05).
  - El componente Filament vive en el núcleo (`Nucleo/Imputacion/`): la regla de arquitectura no deja que otro módulo
    use el `Filament/` de Presupuestos, y la imputación ya es del núcleo (M11; plan 1.9, D3).
  - El núcleo declara una interfaz, `FuenteDeDestinos`, con lo que el selector necesita: buscar proyectos, buscar
    partidas, el saldo con la línea, armar el destino y si la PC-99 está disponible. Presupuestos la implementa y la
    registra en el contenedor. Los centros de costo los da el núcleo. El núcleo sigue sin depender de `Erp`.
  - **Precisión del desarrollador:** la implementación de Presupuestos depende **solo** de las interfaces
    `PartidasService` (C1) y `ProyectosService` (C2), nunca de modelos ni tablas. Así, quien prueba un formulario
    con el selector registra `PartidasEnMemoria` y `ProyectosEnMemoria`, sin una tercera versión falsa. Una prueba
    lo demuestra: el selector funcionando con las dos versiones falsas.
  - Descartadas: el componente en Presupuestos con una excepción a `tests/Arch` (primera excepción al límite entre
    módulos, y cambio de una regla de los agentes), y un selector por documento (contradice el v4 y el ADR).
- **D2 · Qué elige, qué devuelve y dónde queda guardado** (aprobada por el desarrollador con un ajuste, 2026-11-05).
  - Tres opciones (v4, parte 1): «Proyecto › partida» (proyecto de C2 `activos`, partida de C1 `buscar`, con la
    ruta); «Gasto directo sin proyecto» (centro de costo activo, del núcleo); «Gasto directo con proyecto» (PC-99),
    visible y desactivada (D7).
  - Devuelve `EleccionImputacion` (núcleo): tipo (partida, centro de costo, no presupuestado), proyecto, partida,
    centro de costo, y motivo y comentario cuando hacen falta.
  - Se guarda en `imputaciones` (una fila por línea); la línea no lleva columnas de destino propias. Al reabrir, la
    elección se lee de su imputación. Lo ya guardado se muestra aunque ya no esté activo (AGENTS.md): para su nombre,
    **C1 suma `PartidasService::resumen(partidaId)`** (real y falsa; anotado en el ADR 0020).
  - Campo obligatorio en toda línea con costo (regla 3). El destino cambia mientras los montos estén en cero; con
    montos es la reclasificación (flujo 7).
  - **Cómo lo usa cada documento (ajuste del desarrollador; reglas 3 y 7):** `EleccionImputacion` es un campo de los
    datos de la acción del documento (sus `Datos…`), no algo que Filament procese aparte. El servicio del documento,
    **en una sola transacción**, guarda la línea, pide el destino a `FuenteDeDestinos` y llama a
    `ImputacionService::destinar()`. Si `destinar()` falla (por ejemplo, la partida se desactivó en ese momento), la
    línea no queda guardada. Así una línea sin destino es imposible también desde la API futura. Prueba, con un
    documento de prueba en `tests/` (hoy no hay un documento con costo): `destinar()` falla y la línea no queda.
- **D3 · El saldo con la línea y la moneda** (aprobada por el desarrollador con un ajuste, 2026-11-05).
  - El selector muestra presupuestado, comprometido con esta línea y disponible, o cuánto excede; un aviso ámbar al
    90 % o más, y el de exceso con su motivo (D4). Se actualiza al cambiar la partida o el monto.
  - Lo calcula la implementación de `FuenteDeDestinos` de Presupuestos, solo con `PartidasService::saldo()` (D1), y
    devuelve `SaldoConLinea` (núcleo). Funciona igual con `PartidasEnMemoria`.
  - La línea se convierte a soles con el tipo de cambio de venta de su fecha, el mismo de `destinar()` (plan 1.9,
    D4). Si falta el de ese día, muestra el saldo sin la línea y lo avisa: no adivina un valor.
  - En vivo es un aviso, sin bloqueo. Varias líneas del mismo documento a la misma partida: el selector avisa por
    línea; manda la revisión del documento.
  - **Dónde manda la revisión (ajuste del desarrollador):**
    - **Al enviar:** suma las líneas del documento por partida.
    - **Al aprobar, otra vez**, porque es cuando sube el comprometido (v4 parte 7: «al aprobar la línea que la
      excede») y dos documentos distintos no se ven entre sí. Ejemplo: partida con S/ 1,000 disponibles; las órdenes
      A y B, de S/ 600 cada una, se envían sin exceso; se aprueba la A y, al aprobar la B, la partida se excedería en
      S/ 200.
    - Al aprobar, si una línea ahora excede y no tiene motivo, la aprobación se rechaza con un mensaje claro («la
      partida PC-… se excedería en S/ …: indica el motivo»). Precisión técnica: la aprobación la ejecuta la última
      firma; si se rechaza, el documento queda en «Por aprobar» y vuelve a editable cuando quien firma lo **observa**
      (la transición de la 1.7, `transicionAlObservar`, con su motivo).
    - **Corregido (desarrollador, 2026-11-06; ver la bitácora del paso 11):** una línea que al aprobar **pasa** de no
      marcada a excedida se rechaza **aunque traiga motivo**, porque su ronda se abrió sin la regla del exceso (D5: el
      exceso nunca pasa sin su regla); quien firma observa y, al reenviarla, la ronda la trae. Solo sigue la línea que
      ya venía marcada del envío, cuya ronda ya incluye la regla del exceso. La frase anterior («si ya tiene motivo,
      sigue») estaba mal.
    - La revisión al aprobar bloquea las partidas (`lockForUpdate`, en orden de id para no cruzarse) en la misma
      transacción que sube el comprometido: dos aprobaciones a la vez no ven las dos el saldo completo.
    - Es una pieza que cada documento llama al aprobar (`revisarExcesoAlAprobar(lineas)`), con prueba en
      `tests/Concurrencia` sobre el documento de prueba de la D2. **Cambia la costura C1**: se anota en el ADR 0020
      con `resumen(partidaId)`.
- **D4 · La marca de exceso** (aprobada por el desarrollador con dos ajustes, 2026-11-05).
  - En `imputaciones` (migración del núcleo): `excede_partida` (booleano, falso por defecto). La base exige que con
    `excede_partida` haya partida (y nunca la PC-99, nota de la D2), motivo y comentario. Motivo y comentario usan
    las columnas que ya existen.
  - Lista de motivos propia, «Exceso de partida» (`presupuestos.exceso_partida`), registrada por Presupuestos, con su
    «Otro». Comentario obligatorio.
  - El motivo se escribe en el selector cuando el saldo con la línea excede, y viaja en `EleccionImputacion`.
  - La marca la ponen las dos revisiones de la D3 por una sola entrada del núcleo: `ExcesoDePartidaService::alEnviar
    ($lineas)` (sin bloqueo) y `alAprobar($lineas)` (con las partidas bloqueadas, en la transacción de la
    aprobación). Suman las líneas del documento por partida sobre el comprometido actual (por `FuenteDeDestinos`,
    sobre C1), marcan todas las líneas del documento a una partida que se excede y desmarcan las que ya no; si una
    marcada no tiene motivo o comentario, error de negocio con la partida y cuánto se pasa.
  - Cada línea llega como `LineaDeCosto` (origen y monto en su moneda); la partida y el tipo de cambio salen de su
    imputación.
  - Funciona con las versiones falsas (va por `FuenteDeDestinos`). **C1 suma la lectura del saldo con bloqueo**
    (`saldo($partidaId, bloquear: true)`), anotada en el ADR 0020 con `resumen()`.
  - **Ajuste 1 · orden fijo de bloqueo:** `alAprobar` bloquea las partidas siempre en el mismo orden (id ascendente).
    Si no, dos aprobaciones con las mismas partidas en distinto orden se traban (deadlock) y PostgreSQL cancela una con
    un error que nadie entiende. Prueba en `tests/Concurrencia`: dos documentos con las mismas dos partidas en orden
    inverso, aprobados a la vez, y los dos terminan bien (uno espera al otro).
  - **Ajuste 2 · desmarcar limpia:** al desmarcar una línea que ya no excede, se limpian también su motivo y su
    comentario de exceso (como en la D6 de la 2.1, sin restos). `Imputacion` guarda bitácora campo por campo
    (`RegistraCambios`), así que el motivo anterior queda ahí. Prueba para el caso.
- **D5 · Cómo el documento exige la aprobación del exceso** (aprobada por el desarrollador, opción B con ajustes,
  2026-11-05).
  - Cambia la 1.7 (núcleo): `reglas_aprobacion` suma una columna **«condición»** (NOT NULL, por defecto «ninguna»),
    con los valores «ninguna» y «exceso de partida». Una regla con «exceso de partida» entra en la ronda solo si el
    documento tiene alguna línea con `excede_partida` (D4). El documento lo dice con `lineasDeCosto($documento)` en su
    definición. Se configura en Configuración → Aprobaciones.
  - La fila del v4 («Cualquier línea que exceda su partida: siempre Jefatura de Proyectos; sobre U1 también Gerencia
    de Operaciones») queda como dos reglas sobre la transición «aprobar» de cada documento, que se suman a las de
    monto.
  - Si el exceso aparece recién al aprobar, la ronda ya abierta no lo incluía: `alAprobar` rechaza, el firmante
    observa y la ronda nueva lo incluye.
  - **Sin autoaprobación del exceso** (desarrollador, 2026-11-06): una regla con la condición «exceso de partida» no
    acepta tope de autoaprobación (v4 parte 7: lo firma siempre Jefatura de Proyectos; misma idea que «nadie aprueba su
    propio incremento» del fondo del residente, mapa, etapa 3). Lo exige la base
    (`reglas_aprobacion_exceso_sin_autoaprobacion_check`), lo rechaza `AprobacionesService` con un mensaje claro y en
    Configuración → Aprobaciones el campo del tope no aparece con esa condición. La 1.7 ya no deja que quien pide firme
    su propio paso con `firmar()` (no es su firmante): la única vía es `autoaprobar()`, que exige el tope. Así que no
    queda una pregunta abierta sobre la 1.7.
  - **Supuesto (pendiente «Aprobaciones»):** «sobre U1» se mide con el monto del documento, como las demás reglas.
    Si se mide con el monto del exceso, cambia el cálculo de la ronda. Preguntado en `pendientes.md`.
  - Descartada la opción A (un camino de estados aparte por documento: dos estados y tres transiciones más en cada
    documento con costo, y la misma regla configurada una vez por tipo).
  - **Sin firmante por defecto, y el exceso nunca pasa sin su regla** (desarrollador, 2026-11-05; corrige una nota
    anterior que decía «sin regla configurada se bloquea», lo que solo vale si el documento no tiene ninguna regla):
    - El rol «Jefatura de Proyectos» no existe (pendiente «Grilla de roles real»), así que la regla «exceso de
      partida» no trae firmante. El administrador la configura en Configuración → Aprobaciones con quien
      corresponda. No se crea el rol ni se atribuye a una persona; en pruebas, un rol de nombre inventado.
    - Sin esa regla, la 1.7 abriría la ronda solo con las reglas de monto y el exceso pasaría sin aprobación aparte,
      contra el «Siempre» del v4. Por eso: **si el documento tiene alguna línea que excede y ninguna regla activa con
      la condición «exceso de partida» le aplica, el envío se rechaza y no se abre la ronda**, con el mensaje «Hay
      líneas que exceden su partida y no hay una regla de aprobación para el exceso: un administrador debe
      configurarla en Configuración → Aprobaciones». Prueba: documento con reglas de monto, sin regla de exceso y
      una línea que excede; el envío se rechaza y no se abre la ronda.
  - **Costura C12 · El exceso de partida** (ADR 0020; dueño Sebastián; la usan Compras, Tareo y Almacén), con sus tres
    pasos: (a) el selector y la línea con su destino en una sola transacción, (b) `alEnviar` y `alAprobar`, (c)
    `lineasDeCosto()` en la definición. En el ADR también: `PartidasService::resumen()` y `saldo(…, bloquear: true)`.
- **D6 · La alerta «Partida excedida o al 90 %»** (aprobada por el desarrollador con precisiones, 2026-11-05).
  - Alerta del catálogo único (1.10), registrada por Presupuestos: `presupuestos.partida-excedida`, con partida,
    proyecto, porcentaje y cuánto excede.
  - Se dispara en `ExcesoDePartidaService::alAprobar` (al aprobar la línea, v4 parte 7), **después del commit**, con
    dos umbrales: al 90 % (o más, sin pasarse) y excedida. No en la PC-99 (tiene su propia alerta, en su tarea).
  - Una vez por cruce: hecho = partida + umbral + versión del árbol (`partida:15|90|v1`). Una versión nueva del árbol
    que sube el presupuestado puede volver a dispararla.
  - **Salto de dos umbrales (precisión del desarrollador):** si una sola aprobación lleva la partida de menos del
    90 % a excedida (del 80 % al 120 %), sale solo la de excedida; el hecho del 90 % de esa versión queda registrado
    igual, para que no salga después. Prueba para el caso.
  - El 90 % es el parámetro `presupuestos.alerta_partida_porcentaje` (inicial 90), editable en Configuración.
  - Destinatarios: el responsable de la partida, siempre (persona puntual). **Sin rol por defecto:** «Jefatura de
    Proyectos» no existe (pendiente «Grilla de roles real»); se asigna en Configuración → Alertas cuando exista. No
    se inventa el rol.
  - **Cambio del núcleo de la 1.10 (decisión del paso; desarrollador, 2026-11-05).** El núcleo exigía al menos un rol
    por defecto en toda alerta, y al guardarla en Configuración → Alertas; chocaba con «sin rol por defecto» (esta
    D6, porque «Jefatura de Proyectos» no existe todavía). Ahora `DefinicionAlerta` admite `destinatarioPropio` (a
    quién llega siempre, como persona puntual: «al responsable de la partida»); solo con él, la alerta puede no
    tener roles. Las demás siguen exigiendo al menos uno.
    - Al emitir, una alerta con destinatario propio llega por sus roles sin el respaldo del administrador, más la
      persona puntual si está activa. Si no llega a nadie (sin roles con alguien activo y el responsable inactivo),
      entra el respaldo de siempre: el administrador, para que se entere y reasigne la partida.
    - En Configuración → Alertas se guarda sin roles, no figura como «sin nadie», y la pantalla dice «Siempre llega
      al responsable de la partida».
    - Pruebas: `tests/Modules/Nucleo/AlertasDestinatarioPropioTest.php`.
- **D7 · La PC-99 desactivada** (aprobada por el desarrollador, 2026-11-05).
  - El selector muestra «Gasto directo con proyecto» deshabilitada, con la nota «Llega con el gasto no
    presupuestado».
  - `FuenteDeDestinos::admiteGastoConProyecto()` responde `false`; la tarea del flujo 7 la cambia a `true`, sin tocar
    el componente. `EleccionImputacion` ya tiene el tipo «no presupuestado», y C1 ya da `noPresupuestada()` y su
    destino con motivo (2.1, D6). Mientras esté apagada, `FuenteDeDestinos::destino()` rechaza ese tipo («el gasto no
    presupuestado todavía no está disponible»): tampoco entra por la API.
  - Al encenderse, pide el motivo del gasto no presupuestado, no el del exceso (nota de la D2).
  - Prueba: la opción se ve deshabilitada, y el tipo «no presupuestado» por la acción se rechaza.
- **D8 · Dónde se ve y cómo se prueba** (aprobada por el desarrollador, con la página de modo desarrollo, 2026-11-05).
  - Un documento de prueba con líneas de costo en `tests/Modules/Erp/Presupuestos/Fixtures/` (tablas creadas en la
    transacción de la prueba): borrador → por aprobar → aprobado, con «aprobar» que requiere aprobación (1.7),
    `lineasDeCosto()` en su definición, su servicio (la línea con su destino en una transacción; `alEnviar`;
    `alAprobar` que sube el comprometido) y la acción «agregar línea» con la elección entre sus campos.
  - La elección como campos de una acción: `EleccionImputacion::campos()` (`imputacion_tipo`,
    `imputacion_proyecto_id`, `imputacion_partida_id`, `imputacion_centro_costo_id`, `imputacion_motivo_id`,
    `imputacion_comentario`) y `EleccionImputacion::desdeDatos()`. La validación de fondo es de `FuenteDeDestinos` y C1.
  - El componente se prueba con `Livewire::test` sobre él directamente (AGENTS.md), con `PartidasEnMemoria` y
    `ProyectosEnMemoria`: las tres opciones, el saldo con la línea, los avisos del 90 % y del exceso, el motivo al
    exceder, la PC-99 deshabilitada y la partida inactiva ya guardada.
  - **Página «Probar el selector de imputación», solo en modo desarrollo** (nunca en producción), con datos de prueba
    de nombres inventados (regla 10), el componente real y la misma `FuenteDeDestinos` de los documentos. Muestra las
    tres opciones, el saldo con la línea, los avisos del 90 % y del exceso con su motivo, la PC-99 deshabilitada y una
    partida inactiva ya guardada. Sigue `docs/convenciones/interfaz.md`, sin diseño propio. Prueba: responde en modo
    desarrollo y no existe (404 o sin ruta) fuera de él.
  - **Los datos de la página (decisión del paso 10; desarrollador, 2026-11-05, opción 1 con tres condiciones):**
    1. `PartidasEnMemoria` (con su `DestinoEnMemoria`) y `ProyectosEnMemoria` pasan a la app:
       `Erp/Presupuestos/Services/EnMemoria/` y `Erp/Proyectos/Services/EnMemoria/`. Siguen pasando las mismas
       pruebas del contrato que las reales. La intención «nunca en producción» (2.1, D1) se mantiene: solo se usan en
       pruebas y en esta página. `ArbolDePrueba` sigue en `tests/Costuras/` (arma datos reales).
    2. **Alcance solo de la página:** no registra las versiones en memoria en el contenedor de la app. Arma su propia
       `FuenteDeDestinos` real con las versiones en memoria inyectadas solo ahí; el resto de la app, en modo
       desarrollo, sigue con C1 y C2 reales. Prueba: con la página activa, `ProyectosService` y `PartidasService`
       resueltos desde el contenedor siguen siendo los reales.
    3. Prueba en `tests/Arch`: ninguna clase de `app/` usa las versiones `EnMemoria`, salvo la página de modo
       desarrollo (y los providers de pruebas, si los hubiera).
  - Fuera de esa página, no se ve en la app: el primer documento real que lo use es la orden de Compras.

## Pasos

- [x] 1. Fila en el mapa, plan y rama `etapa-2-selector-imputacion`.
- [x] 2. Decisiones D1–D8 aprobadas y anotadas; ADR 0020 con `resumen()`, `saldo(…, bloquear: true)` y la C12.
- [x] 3. Núcleo: `EleccionImputacion`, `SaldoConLinea`, `LineaDeCosto`, `FuenteDeDestinos`; migración de
      `imputaciones` (`excede_partida` y sus checks); `ImputacionService` (marcar y desmarcar el exceso).
- [x] 4. C1: `resumen()` y `saldo(…, bloquear)` en real y falsa, con sus pruebas del contrato.
- [x] 5. Presupuestos: la `FuenteDeDestinos` sobre C1 y C2; la lista de motivos «Exceso de partida».
- [x] 6. Núcleo: `ExcesoDePartidaService` (`alEnviar`, `alAprobar` con orden fijo de bloqueo) y la alerta (Presupuestos).
- [x] 7. 1.7: la columna «condición» de `reglas_aprobacion`, su pantalla, la ronda con la condición y el rechazo del
      exceso sin regla.
- [x] 8. El documento de prueba y sus pruebas (transacción, enviar, aprobar, desmarcar, alerta, concurrencia).
- [x] 9. El componente del selector en el núcleo y sus pruebas con las versiones falsas.
- [x] 10. La página de modo desarrollo y su prueba.
- [x] 11. Documentos (codigo.md: cómo usa un documento el selector), `revisor-arquitectura`, `composer check`, subir
      la rama y avisar (el PR lo abre Sebastián).

## Preparado para lo diferido

- **El exceso en la PC-99** (desarrollador, 2026-11-05): la PC-99 tiene presupuestado 0, así que toda línea ahí sería
  un exceso. Cuando se active, el selector no pide el motivo del exceso en la PC-99: su motivo es el del gasto no
  presupuestado (plan 2.1, D6), y los dos usan las mismas columnas `motivo_id` y `comentario`.
- **Antes de usar el sistema con datos reales: la regla de aprobación del exceso** (desarrollador, 2026-11-05). Hasta
  que se configure en Configuración → Aprobaciones una regla activa con la condición «exceso de partida», **toda
  orden (o documento con costo) con una línea que excede queda frenada al enviar**. No hay todavía una lista de lo
  que se configura antes del arranque (`docs/operaciones/despliegue.md` cubre la instalación técnica); si se arma,
  esto va ahí, y también podría ser un paso del asistente de configuración inicial (1.13).
- **La condición «gasto no presupuestado» en las reglas de aprobación** (desarrollador, 2026-11-05): la columna
  «condición» de `reglas_aprobacion` es una lista de valores; el v4 tiene la misma forma para la PC-99 («siempre
  Jefatura; sobre U1 también Gerencia»), así que entra con su tarea como un valor más, sin otra columna.
- **Varias líneas del mismo documento en vivo** (D3): el selector avisa por línea. Si un documento necesita el aviso
  con la suma de sus demás líneas, el punto de extensión es un parámetro del componente («ya comprometido en este
  documento») que el formulario llena; hoy manda la revisión al enviar.
- **La reclasificación** (cambiar el destino de una línea con montos) sigue para el flujo 7: el selector queda de
  solo lectura en una línea con montos.

## Criterio de aceptación

- Documento de prueba: si `destinar()` falla, la línea no queda (D2); al enviar, las líneas que juntas exceden su
  partida se marcan y sin motivo se rechaza; al aprobar, el exceso que aparece por otro documento ya aprobado se
  rechaza sin motivo (D3); desmarcar limpia motivo y comentario y deja la bitácora (D4).
- Aprobaciones: una regla con la condición «exceso de partida» entra solo si hay exceso; con reglas de monto y sin
  regla de exceso, el envío con exceso se rechaza y no se abre la ronda (D5).
- Alerta: al 90 % y excedida, una vez por cruce; de menos del 90 % a excedida de una vez, solo la de excedida, y la
  del 90 % ya no sale después (D6).
- PC-99: deshabilitada en el selector y rechazada por la acción (D7).
- C1: `resumen()` y `saldo(…, bloquear)` pasan el contrato contra la real y la falsa.
- Selector: funciona con `PartidasEnMemoria` y `ProyectosEnMemoria` (D1), con las tres opciones, los avisos y la
  partida inactiva ya guardada (D8).
- Concurrencia: dos aprobaciones a la vez sobre una partida no la exceden sin motivo; dos documentos con las mismas
  dos partidas en orden inverso terminan los dos (D3, D4).
- Página de modo desarrollo: responde en modo desarrollo y no existe fuera de él (D8).

## Bitácora

- 2026-11-05 — Paso 1. Fila 2.2 en el mapa, plan y rama desde `main` (223c65b).
- 2026-11-05 — Paso 2. D1–D8 cerradas una por una, con ajustes del desarrollador. ADR 0020: C1 suma `resumen()` y
  `saldo(…, bloquear: true)`; nueva C12 «El exceso de partida». `pendientes.md`: «sobre U1» del exceso y del gasto
  no presupuestado. Los datos de la página de modo desarrollo: resueltos con el desarrollador (D8, opción 1).
- 2026-11-05 — Pasos 3 y 4, y el cambio de alertas de la 1.10 (D6). Hecho: `TipoImputacion`, `EleccionImputacion`
  (con `campos()`, `desdeDatos()`, `desdeImputacion()`), `SaldoConLinea`, `LineaDeCosto`, `OpcionDestino` y la
  interfaz `FuenteDeDestinos`; `ImputacionService::marcarExceso()` (desmarcar limpia motivo y comentario);
  `AlertasService::darPorEmitida()`; `DefinicionAlerta::destinatarioPropio`; la migración de `excede_partida` (en
  Presupuestos: su regla nombra `partida_control_id`, como la de la 2.1); C1 con `resumen()` y `saldo(…, bloquear)`.
  Adelantado del paso 5 (sin registrar todavía en el provider): `DestinosDePresupuestos`, `AvisoDeSaldos` y el
  motivo de exceso en `DestinoPartida`. Las versiones en memoria se movieron a la app (D8).
  - Desvío menor de la D6: el hecho de la alerta usa el presupuestado de la partida en lugar de la versión del árbol
    (`partida:15|umbral|1500.00`). Cumple lo mismo (un cambio de presupuesto en una versión nueva la deja volver a
    salir) sin sumar a C1 una lectura de la versión.
  - No hay prueba de «saldo con bloqueo fuera de una transacción»: las pruebas corren dentro de la de
    `RefreshDatabase`, así que nunca hay nivel cero. La guarda queda en el código.
- 2026-11-05 — Pasos 5 a 8. Provider de Presupuestos: `FuenteDeDestinos` → `DestinosDePresupuestos`, la lista «Exceso
  de partida», el grupo de avisos «Presupuesto y costo», el parámetro `presupuestos.alerta_partida_porcentaje` (90) y
  la alerta `presupuestos.partida-excedida` (con guion: así van las claves de aviso). `ExcesoDePartidaService`
  (`alEnviar`; `alAprobar` con orden fijo y aviso después del commit). 1.7: `reglas_aprobacion.condicion`
  (`CondicionRegla`), su campo en las acciones y la tabla, la ronda con la condición y el rechazo del exceso sin regla.
  Documento de prueba en `tests/Modules/Erp/Presupuestos/Fixtures/` y `ExcesoDePartidaTest` (9 pruebas); dos de
  concurrencia (misma partida a la vez; mismas dos partidas en orden inverso).
  - **Ajuste a la D6 por la regla 10:** la alerta no lleva el monto del exceso. Los costos son datos sensibles y la
    alerta viaja por correo, así que va con partida, proyecto y porcentaje; el monto se ve en el sistema.
  - En `tests/Concurrencia` el correlativo de proyectos se deja al día después de borrar `series_documento` (los
    proyectos de pruebas anteriores siguen en esa base).
  - Vista al pasar: otra sesión hizo un commit al núcleo en `familiaDeMotivos` (tarea 2.20). Revisar al traer `main`.
- 2026-11-05 — Paso 9. `Nucleo/Filament/Imputacion/SelectorImputacion::controles()`: el control de cada campo de
  `EleccionImputacion`, para `$controles` de `AccionFilament::desde()`. Lee monto, moneda y fecha de la línea de sus
  campos. `FuenteDeDestinos` suma `motivosDeExceso()` (la lista es de Presupuestos y el núcleo no la nombra). Sin tipo
  de cambio del día, avisa y muestra el saldo sin la línea (D3). Pruebas: `SelectorImputacionTest`, solo con las
  versiones en memoria, con el formulario de prueba `Fixtures/FormularioConSelector`.
- 2026-11-05 — Paso 10. `Erp/Presupuestos/Filament/Desarrollo/ProbarSelectorImputacion`
  (`/probar-selector-imputacion`): responde 404 sin el modo desarrollo activo (como `VistaPreviaFechas`), no va en
  menús, arma su `DestinosDePresupuestos` con las versiones en memoria y datos inventados, y un botón «Ver una línea ya
  guardada» la llena con la partida desactivada. Se registra en el `register()` del provider (el panel se arma antes
  del `boot` de los módulos). `tests/Arch`: las versiones `EnMemoria` solo las usa esa página.
- 2026-11-05 — Paso 11. Documentos: `codigo.md` («Cómo usa el selector de imputación un documento con costo») y la C12.
  Se trajo `main` (PR #79, 2.20a: íconos únicos y ADR 0021). Primera corrida de `composer check`: 2515 de 2516; falló
  `sinDestinatariosPropios()` alerta por alerta, que no sabía de las alertas con destinatario propio (corregido).
  Revisión de `revisor-arquitectura`, 2 bloqueantes y 12 observaciones:
  - **Corregido · exceso nuevo al aprobar con motivo.** Se aprobaba sin la firma del exceso, porque la ronda se abrió
    sin esa regla. Ahora `alAprobar` rechaza toda línea que pasa de no marcada a excedida, aunque traiga motivo: quien
    firma observa y la ronda nueva trae la regla. Esto aplica la D5 («nunca pasa sin su regla») por encima de la frase
    de la D3 («si ya tiene motivo, sigue»), que solo vale para la línea que ya venía marcada del envío. **Se avisa al
    desarrollador.** Además, `marcarExceso(false)` limpia el motivo aunque la línea nunca haya estado marcada (sin
    restos, D4): un motivo escrito de antemano no sobrevive a un envío sin exceso.
  - **Corregido · claves del documento de prueba con `erp.`** (ADR 0021): es el ejemplo que copiarán los documentos
    reales.
  - Corregido: el motivo y el comentario se ven con cualquier partida (dos líneas que solo juntas exceden también los
    necesitan), obligatorios solo si la línea sola excede; «enviar» de acción propia, dicho en `codigo.md` y en la C12;
    las marcas no se revierten al anular, dicho; el aviso de saldos después del commit no puede tirar la aprobación
    (`rescue`); el hecho de la alerta guarda una huella del presupuestado, no el monto (regla 10); pruebas del 90 % que
    no se repite y de `ademas` sin inactivos en una alerta normal.
  - **Resuelto el 2026-11-06:** la regla con condición «exceso de partida» no acepta tope de autoaprobación (D5).
  - Anotado: la migración de `excede_partida` vive en Presupuestos (su regla nombra `partida_control_id`), aunque la
    D4 decía «del núcleo», y el núcleo usa esa columna por su nombre, como ya hacía con `partida_control_id`. El
    rendimiento (`PartidasEnBase::resumen()` carga el árbol en cada llamada; el selector calcula el saldo varias
    veces por dibujo) queda para cuando haya un documento real que medir.
- 2026-11-06 — Cierre. Segunda corrida de `composer check` en verde: pint, phpstan, 62 migraciones y 2519 pruebas.
  Plan a `completados/`. Para el desarrollador: el exceso nuevo al aprobar se rechaza aunque traiga motivo (D5 sobre
  D3) y la pregunta del tope de autoaprobación en la regla del exceso.
- 2026-11-06 — Después del cierre (desarrollador): D3 corregida a favor de la D5; sin autoaprobación del exceso (base,
  servicio y pantalla, con pruebas); la 2.1b también renombra las claves `presupuestos.` de esta tarea.

