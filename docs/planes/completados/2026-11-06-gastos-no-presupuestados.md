# Plan: gastos no presupuestados (flujo 7, tarea 2.6)

- **Estado:** completado
- **Responsable:** Sebastián (área 1, Presupuesto y costo; ADR 0020)
- **Módulo(s):** `Erp/Presupuestos` (y lo que haga falta del núcleo en imputaciones y aprobaciones)
- **Fase:** F2
- **Creado:** 2026-11-06 · **Actualizado:** 2026-11-06

## Objetivo

Un gasto de un proyecto que no estaba en su presupuesto se carga a la PC-99 con motivo de lista cerrada y comentario
obligatorio, siempre pasa por aprobación, y al aprobarse queda comprometido en la PC-99; si el motivo es «cambio pedido
por el cliente», se marca posible adicional y avisa a Administración de Contratos (C11). La opción «gasto directo con
proyecto» del selector de imputación queda encendida. Los escenarios F7 del taxi (45.00) y del posible adicional
(1,286.00) pasan como pruebas.

## Contexto que leí

- `docs/diseno/v4/parte-4-flujo-07.md` (paso 1 y «cuando las cosas no salen»: reclasificar).
- `docs/diseno/v4/parte-6-fichas-documentos.md`, ficha «Gasto no presupuestado»: «una línea de orden, rendición o
  despacho imputada a la PC-99»; numeración del documento que lo origina; estados Borrador → Por aprobar → Aprobado →
  Reclasificado.
- `docs/diseno/v4/parte-8-criterios-aceptacion.md`, F7: taxi sin comentario no se envía; taxi aprobado deja
  comprometido 45.00 en la PC-99 y el margen de 1,372.00 a 1,327.00; 1,286.00 por cambio del cliente marca posible
  adicional y alerta a Administración de Contratos.
- `docs/diseno/v4/parte-7-transversal.md`: aprobación «siempre, cualquier monto: Jefatura de Proyectos; sobre U1
  también Gerencia de Operaciones»; alertas «Gasto no presupuestado registrado» (al enviarse, Jefatura de Proyectos) y
  «Posible adicional detectado» (al aprobarse, Administración de Contratos).
- ADR 0020: C1, C11 (`PosibleAdicionalRegistrado`), C12; «los gastos no presupuestados no tienen tabla propia:
  Sebastián decide su documento».
- Planes 2.1 (D6, «costos tempranos», orden del área 1) y 2.2 (D5 exceso aprobado por el documento que lo contiene,
  D7 PC-99 apagada, «preparado para lo diferido»).
- `pendientes.md`: «sobre U1» en el gasto no presupuestado se mide con el monto del documento (supuesto, configurable):
  no bloquea. «Costos tempranos» (plan 2.1) sigue abierto y no se resuelve aquí.

## Decisiones

Decididas por el desarrollador una por una, 2026-11-06.

- **D1 · Qué es un gasto no presupuestado.** Una línea de otro documento con costo (orden, rendición, despacho)
  imputada a la PC-99, como dice la ficha del v4; no tiene tabla ni formulario propios. Su numeración y sus estados
  son los del documento que la contiene. Descartado: un documento «Gasto no presupuestado» aparte (el mismo gasto se
  contaría dos veces al pagarse con su orden o rendición). Hasta que existan Compras o rendiciones, se prueba con la
  orden de prueba de la 2.2 (`tests/Modules/Erp/Presupuestos/Fixtures/`).
- **D2 · Aprobación, igual que el exceso (C12).** `CondicionRegla` suma «Solo si alguna línea va a la PC-99»; la regla
  entra solo cuando el documento tiene una línea así, nunca se autoaprueba, y sin regla activa el documento queda
  frenado al enviar. «Sobre U1 también Gerencia» es una segunda regla con monto, configurable. Los avisos se cuelgan de
  las mismas llamadas que los documentos ya hacen al enviar y aprobar (`ExcesoDePartidaService::alEnviar` y
  `alAprobar`): Compras, Tareo y Almacén no agregan nada.
- **D3 · Motivos cargados.** La lista `erp.presupuestos.gasto_no_presupuestado` trae los seis del v4 (imprevisto en
  obra, error de metrado, cambio pedido por el cliente, precio mayor al presupuestado, reposición por pérdida o daño,
  otro). «Cambio pedido por el cliente» lleva `motivos.es_cambio_cliente` (la columna ya existe): el posible adicional
  se reconoce por esa marca, no por el nombre. En Configuración se pueden renombrar o agregar.
- **D4 · El margen, en su tarea.** La prueba del taxi comprueba ahora que la PC-99 queda con 45.00 comprometido. La
  comprobación 1,372.00 → 1,327.00 y «mostrar el nuevo margen al enviar» quedan para la tarea del margen (ver
  «Preparado para lo diferido»); el margen se calculará con lo comprometido, así que el taxi lo bajará sin más.
- **D5 · La reclasificación, en su propia tarea del núcleo (2.8).** Mover un costo ya aprobado de una partida a otra,
  con motivo e historial, la usan tres casos: el gasto no presupuestado que cabía en otra partida o que se volvió
  adicional por adenda, los costos tempranos (plan 2.1, nota «Costos tempranos») y la corrección de cualquier
  imputación. Por la D1, el gasto no tiene estados propios: la ficha del v4 dice «Aprobado → Reclasificar →
  Reclasificado», pero aquí no es un estado de ningún documento. La marca de «reclasificada» va donde vive el destino
  de la línea (`imputaciones`, como la marca del exceso de la 2.2, D2), y la reclasificación se guarda como historial de
  movimientos de la imputación. (Se corrige lo propuesto antes en la conversación, que hablaba de declarar un estado
  «Reclasificado» y una transición «Reclasificar» en el gasto: no aplica por la D1.)
  - Esta tarea deja registrada la acción `erp.presupuestos.reclasificar-gasto`, con su permiso del mismo nombre y sus
    campos (la línea, la partida destino, el motivo y el comentario), que por ahora rechaza con «La reclasificación
    llega con su tarea». La 2.8 solo agrega la pieza del núcleo y llena la acción.
- **D6 · Los avisos y el evento C11.**
  - «Gasto no presupuestado registrado» (al enviar): al jefe del proyecto, como destinatario propio (es el responsable
    de su PC-99), sin rol por defecto, igual que la alerta de partida de la 2.2 (D6). La Jefatura de Proyectos se
    entera además por el pedido de aprobación.
  - «Posible adicional detectado» (al aprobar): al jefe del proyecto y, mientras no exista el rol de Administración de
    Contratos, a Gerencia General (`SeguridadService::ROL_GERENCIA_GENERAL`). **Por qué aquí sí va Gerencia General y
    en la D6 de la 2.2 no:** el aviso de partida es informativo, pero un posible adicional que nadie ve es plata que
    Techquk deja de cobrar.
  - El cambio pendiente (quitar Gerencia General y poner Administración de Contratos cuando exista la grilla de roles
    real) queda en `docs/operaciones/antes-de-datos-reales.md`, la lista nueva de lo que se configura antes de los datos
    reales. La 2.2 dejó escrito que esa lista no existía («si se arma, esto va ahí»): se arma aquí, con la regla del
    exceso de la 2.2, la regla de la PC-99 de la D2 y los roles de las alertas.
  - El evento `PosibleAdicionalRegistrado` (C11) se dispara después del commit, para que Contratos reaccione cuando
    se construya.
- **D7 · La consulta sí, la pantalla después.** El servicio de Presupuestos da, con sus pruebas: los gastos de la PC-99
  por proyecto y por motivo (cantidad y total en soles, con el mismo redondeo que el saldo) y los posibles adicionales.
  Vive en el servicio, no en una pantalla, para que la usen la pantalla futura y el margen. El posible adicional se
  reconoce por `motivos.es_cambio_cliente` del motivo de la línea (D3): no se guarda otra marca.
- **Numeración** (desarrollador, 2026-11-06): esta tarea es la 2.6 y la reclasificación la 2.8. El mapa no tenía nada
  desde la 2.3, pero la guía del equipo (`docs/equipo/10-modulos-etapa-2.html`) numera el área 1 con un orden
  anterior (2.2 APU, 2.3 árbol, 2.4 selector, 2.5 C8, 2.6 gastos no presupuestados, 2.7 margen): se respeta el 2.6 que ya
  le da a esta tarea y la reclasificación toma el primer número libre.
- **Pendiente con la empresa** (anotado en `docs/decisiones/pendientes.md`, fila Aprobaciones): si una rendición u
  orden con varias líneas se devuelve entera o se aprueba por partes cuando una línea no convence. Si es «por partes»,
  las líneas necesitarán estados propios, que hoy no tienen (D1). No bloquea esta tarea: hoy se devuelve el documento.

## Pasos

- [x] 1. Decisiones D1–D7 confirmadas en este plan; filas 2.6 y 2.8 en el mapa.
- [x] 2. Núcleo: `CondicionRegla::GastoNoPresupuestado` (en la base, el `CHECK` de `reglas_aprobacion.condicion`) y que
      las reglas con esa condición entren solo si alguna línea va a la PC-99, sin autoaprobación (D2).
- [x] 3. Núcleo: `ExcesoDePartidaService::alEnviar` y `alAprobar` avisan también de las líneas a la PC-99, por
      `FuenteDeDestinos` (D2, D6).
- [x] 4. Presupuestos: los seis motivos cargados con «cambio pedido por el cliente» marcado (D3); la PC-99 encendida
      en el selector (`admiteGastoConProyecto()`, `destino()`); alertas «Gasto no presupuestado registrado» y «Posible
      adicional detectado» y el evento `PosibleAdicionalRegistrado` (D6).
- [x] 5. Presupuestos: la consulta de gastos de la PC-99 y de posibles adicionales (D7); la acción
      `erp.presupuestos.reclasificar-gasto` que rechaza (D5).
- [x] 6. Escenarios F7 como prueba con la orden de prueba: taxi sin comentario; taxi aprobado (PC-99 comprometido
      45.00); 1,286.00 por cambio del cliente (posible adicional, alerta y evento).
- [x] 7. Documentos: ADR 0020 (C11 y C12), `codigo.md` («Imputación»), mapa.
- [x] 8. `revisor-arquitectura`, `composer check` en verde, rama subida.

## Preparado para lo diferido

- **El margen del escenario F7 del taxi** (D4): la comprobación del margen estimado 1,372.00 → 1,327.00 al aprobarse
  y «mostrar el nuevo margen al enviar» quedan para la tarea del margen (`MargenService`, punto 6 de la guía de la D8
  del plan 2.1, donde quedó anotado en su criterio de aceptación). Punto de extensión: el comprometido de la PC-99,
  que el margen leerá como el de cualquier partida.
- **La reclasificación** (D5, tarea 2.8 del núcleo): la acción `erp.presupuestos.reclasificar-gasto` ya está
  registrada con permiso y campos (línea, partida destino, motivo, comentario) y rechaza con «La reclasificación llega
  con su tarea». La 2.8 agrega en `imputaciones` la marca de reclasificada y el historial de movimientos (la pieza que
  cambia el destino de una línea con montos; hoy `ImputacionService::mover()` solo mueve montos dentro del mismo
  destino) y hace que la acción la llame. Le sirve también a los costos tempranos y a la corrección de cualquier
  imputación.
- **La pantalla de gastos no presupuestados** (D7): se arma cuando Compras o las rendiciones generen gastos reales,
  siguiendo los patrones de `docs/convenciones/interfaz.md`, sobre la consulta del servicio de Presupuestos que deja
  esta tarea (la misma que usará el margen).
- **Aprobar por partes** (pendiente con la empresa, D5): si se decide, las líneas necesitarán estados propios.
- **El posible adicional que se anula** (revisor de arquitectura): `PosibleAdicionalRegistrado` no tiene inverso.
  Punto de extensión: el evento lleva `imputacionId`, y la consulta `posiblesAdicionales()` ya excluye lo que dejó de
  estar comprometido; Contratos, al construirse, revisa eso o la costura C11 suma un evento de retiro (ADR 0020).

## Criterio de aceptación

- Escenarios F7 del taxi y del posible adicional, con sus números.

## Bitácora

- 2026-11-06 — Rama `etapa-2-gastos-no-presupuestados` desde `main` (d7fad95). Lectura hecha.
- 2026-11-06 — Decisiones D1–D7 cerradas; el código empieza en una sesión nueva (paso 2). Para no repetir la búsqueda:
  - La condición del exceso vive en `Nucleo/Enums/CondicionRegla.php`, el `CHECK` en la migración
    `Nucleo/database/migrations/2026_11_06_000002_condicion_en_reglas_aprobacion.php` (editable: no hay despliegue,
    ADR 0010; agregar `gasto_no_presupuestado` al `CHECK` y a la regla «sin autoaprobación»), la validación de la regla
    en `AprobacionesService` (~línea 344) y el filtro en `Nucleo/Aprobaciones/ReglasDeAprobacion.php`
    (`reglasQueCalzan($conExceso)` y `abrir()`: «el exceso nunca pasa sin su regla», con `excesoSinRegla()`). Para la
    PC-99 hace falta algo como `ImputacionService::algunaExcedida()` que pregunte si alguna línea va a la PC-99
    (`FuenteDeDestinos::esNoPresupuestada()`), y su par «sin regla».
  - Los avisos se cuelgan de `Nucleo/Services/ExcesoDePartidaService.php` (`alEnviar` y `alAprobar`; hoy salta las
    líneas de la PC-99 en `revisar()`), por `FuenteDeDestinos` (Presupuestos la implementa en
    `Erp/Presupuestos/Imputacion/DestinosDePresupuestos.php`), después del commit como `avisarSaldos`.
  - La PC-99 la crea `Efectos/CrearPartidaNoPresupuestada.php` con responsable = jefe del proyecto
    (`ProyectosService::resumen()->jefeProyectoUserId`): ese es el destinatario propio de las dos alertas (D6).
  - La alerta de la 2.2 está en `PresupuestosServiceProvider::declararAlertas()`; el rol de Gerencia General es
    `SeguridadService::ROL_GERENCIA_GENERAL`. `motivos.es_cambio_cliente` existe y nadie lo usa todavía.
  - La orden de prueba y su escenario con aprobaciones: `tests/Modules/Erp/Presupuestos/Fixtures/` y
    `tests/Modules/Erp/Presupuestos/ExcesoDePartidaTest.php` (modelo para las pruebas F7).
- 2026-11-06 — Paso 2 hecho. `CondicionRegla::GastoNoPresupuestado` con `admiteAutoaprobacion()` (solo «Ninguna»); en la
  migración, el `CHECK` suma el valor y el de autoaprobación pasa a `reglas_aprobacion_condicion_sin_autoaprobacion_check`
  (`condicion = 'ninguna' OR tope IS NULL`). `ImputacionService::algunaNoPresupuestada($origenes, FuenteDeDestinos)` y
  `AprobacionRechazada::noPresupuestadoSinRegla()`. **Hueco corregido** (también afectaba al exceso de la 2.2): cuando la
  misma jefatura firma por la regla del monto (con tope) y por la del exceso o la PC-99, `ReglasDeAprobacion::abrir()`
  dejaba una sola firma con la regla del monto, y su tope permitía autoaprobar; ahora la firma queda con la regla sin
  autoaprobación. Pruebas en `GastoNoPresupuestadoTest` (nuevo; las líneas a la PC-99 se destinan todavía con
  `PartidasService::destino()`, hasta encender el selector en el paso 4) y una en `ExcesoDePartidaTest`.
- 2026-11-06 — Paso 3 hecho. `ExcesoDePartidaService::revisar()` junta las líneas a la PC-99 como
  `LineaNoPresupuestada` (núcleo: ids, motivo y monto en soles); `alEnviar` y `alAprobar` llaman, después del commit y
  solo si hay alguna, a `FuenteDeDestinos::noPresupuestadosEnviados()` y `noPresupuestadosAprobados()` (con `rescue`,
  como `avisarSaldos`). En `DestinosDePresupuestos` quedan vacías hasta el paso 4, donde van sus pruebas.
- 2026-11-06 — Paso 4 hecho.
  - **Motivos (D3):** no había cómo cargar los motivos de una lista (solo nacía «Otro»). El núcleo suma
    `MotivoInicial` y `RegistroDocumentos::familiaDeMotivos($codigo, $nombre, $iniciales)`; el sincronizador crea cada
    uno solo si ni su código ni su nombre están en la lista, así no pisa lo renombrado o desactivado en Configuración.
    Presupuestos declara los cinco del v4 (más «Otro», que ya existía), con «Cambio pedido por el cliente» marcado.
  - **Selector:** `admiteGastoConProyecto()` en verdadero; `destino()` con «gasto directo con proyecto» busca la PC-99
    del proyecto (`PartidasService::noPresupuestada()`) y la PC-99 elegida como partida se rechaza con «se elige como
    gasto directo con proyecto». `FuenteDeDestinos::motivosNoPresupuestados()`. En `SelectorImputacion`, con la PC-99:
    motivo de su lista y comentario obligatorios, centro de costo de referencia opcional; al cambiar de opción se limpia
    el motivo.
  - **Avisos (D6):** `AvisoDeGastosNoPresupuestados` emite las dos alertas una vez por línea (hecho
    `imputacion:<id>`), sin montos, al responsable de la PC-99 (destinatario propio). «Posible adicional» trae Gerencia
    General por defecto. El evento `PosibleAdicionalRegistrado` (proyecto, imputación, monto en soles) sale en cada
    aprobación de una línea con `es_cambio_cliente`, aunque la alerta esté apagada: Contratos no depende de ella.
- 2026-11-06 — Paso 5 hecho. `GastosNoPresupuestadosService` (Presupuestos): `porMotivo(?proyectoId)` da
  `GastoPorMotivo` (proyecto, motivo, si es cambio del cliente, cantidad y total en soles) y `posiblesAdicionales()` da
  `PosibleAdicional` (imputación, origen, motivo, comentario, fecha, soles). Cuenta solo lo aprobado (comprometido mayor
  que cero) con el redondeo del saldo (`ROUND` de la suma en la base). Lee `imputaciones` y `motivos` del núcleo, como ya
  hacen `PartidasEnBase` y `DestinosDePresupuestos`. La acción `erp.presupuestos.reclasificar-gasto` (`ReclasificarGasto`:
  imputación, partida destino, motivo y comentario; permiso del mismo nombre) rechaza con
  `ReclasificacionRechazada::todaviaNo()`.
- 2026-11-06 — Paso 6 hecho. Los tres escenarios F7 en `GastoNoPresupuestadoTest` (15 pruebas en el archivo). Taxi
  sin comentario: la línea no se puede ni registrar (el destino lo rechaza, por la acción y por el servicio), así que no
  hay qué enviar. Taxi aprobado: PC-99 con presupuestado 0.00 y comprometido 45.00 (el margen, D4, en su tarea).
  1,286.00 por cambio del cliente: aparece en `posiblesAdicionales()`, alerta a Gerencia General (en lugar de
  Administración de Contratos, D6) y evento C11 con el monto.
- 2026-11-06 — Paso 7 hecho. ADR 0020: C11 con el evento real (campos y cuándo sale) y nota en C12 (el gasto no
  presupuestado se cuelga de las mismas tres piezas). `codigo.md`: «Imputación» (la PC-99 no le pide nada más al
  documento; sin autoaprobación; reclasificar no es un `destinar()` nuevo) y «Motivos de catálogo» (`MotivoInicial`).
  Mapa: fila 2.6 al día. `antes-de-datos-reales.md` ya tenía sus filas (commit de la D6).
- 2026-11-06 — Revisión de `revisor-arquitectura`: sin bloqueantes. Atendido: el evento C11 sale antes de la alerta y
  cada alerta va en su propio `rescue` (si una falla, el evento y las demás líneas siguen); `alEnviar` va dentro de la
  transacción de la transición (dicho en su docblock, en C12 y en `codigo.md`); el evento C11 no tiene inverso (anotado
  en el ADR y en «Preparado para lo diferido»); comentario corregido en `ReglasDeAprobacion::abrir()`; pruebas de dos
  líneas a la PC-99 (dos avisos) y del orden inverso en la firma compartida. No atendido (menor): `Motivo` se consulta
  dos veces por línea en los avisos; el plan completado de la 2.2 conserva el nombre viejo de la restricción (historia).
- 2026-11-06 — Cierre. `composer check` (una corrida): pint, phpstan y las 62 migraciones en verde; pruebas 2538 de
  2539. La que falló, `CatalogosSeederTest` («los permisos salen de las acciones registradas…»), lista todos los
  permisos y no tenía el nuevo `erp.presupuestos.reclasificar-gasto`; se agregó y el archivo pasa solo (5 de 5). No se
  repitió la corrida completa. Desvíos del diseño: ninguno de las D1–D7; se sumaron `MotivoInicial` en el núcleo (no
  había cómo cargar los motivos de una lista) y la corrección de la firma compartida (paso 2). Qué sigue: la 2.7
  (margen, con el escenario 1,372.00 → 1,327.00) y la 2.8 (reclasificación). `pendientes.md` sin cambios.
- 2026-11-06 — Regla de la firma escrita como está (desarrollador): la firma del exceso o de la PC-99 nunca la da quien
  pidió, aunque coincida con la jefatura que firma por monto; la autoaprobación de la regla del monto en otra unidad
  sigue valiendo. En la C12 del ADR 0020 y en `codigo.md`, con el ejemplo de las dos unidades. Sin cambio de código.
  **Pregunta revisada (si quien pidió puede firmar a mano como firmante normal):** no puede. `ResolutorDeFirmante`
  (1.7) da como firmante al jefe de la unidad, saltando a quien pidió (si es el jefe, sube a la unidad de arriba), y
  nadie más de la unidad firma. No hay nada que cambiar. Lección agregada a `AGENTS.md` («Cómo trabajar»).
- 2026-11-06 — `composer check` completo en verde (exit 0): pint passed, phpstan passed, 62 migraciones cumplen las
  convenciones, pest passed con 2539 de 2539. Lista para el PR.
