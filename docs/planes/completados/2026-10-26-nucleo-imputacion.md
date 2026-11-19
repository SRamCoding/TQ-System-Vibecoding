# Plan: Imputación y centros de costo (M11)

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-26 · **Actualizado:** 2026-10-26

## Objetivo

Toda línea de costo tiene exactamente un destino (regla 3): el núcleo guarda, por línea, a dónde va y sus tres montos
(comprometido, ejecutado, pagado), con las reglas en la base y en un único servicio. Hoy el destino es un centro de
costo; la etapa 2 enchufa las partidas de control sin rehacer nada.

## Contexto que leí

- `docs/planes/mapa-etapas.md` fila 1.9: `imputaciones` apunta a `partidas_control` (etapa 2): resolverlo sin inventar.
- v4 parte 1, idea 2: cada línea va a una partida de un proyecto o al gasto directo con su centro de costo; sin tercera
  opción. El gasto directo con proyecto cae en la PC-99 de ese proyecto (con centro de referencia). El selector de
  imputación se construye una vez y aparece en todos los documentos con costo.
- `docs/diseno/techquk_modelo.dbml`: `centros_costo`, `imputaciones` (una fila por línea de origen, tres montos,
  comprometido ≥ ejecutado), `posibles_adicionales` (contratos, etapa 2), `tipos_compra` (etapa 2).
- Plan 1.5 D9: los motivos de la imputación (`gasto_no_presupuestado`, `exceso_partida`) ya están en el catálogo único.
- Pendientes: «Centros de costo · cuáles son los reales» (Contabilidad), «Árbol de partidas» (etapa 2). No bloquean.

## Decisiones

Aprobadas por el desarrollador el 2026-10-26 (elegidas entre opciones).

- **D1 · El mecanismo, listo para enchufar.** Centros de costo, la tabla `imputaciones` y `ImputacionService` con sus
  reglas. Hoy el único destino es un centro de costo. Las columnas y llaves de `proyecto_id` y `partida_control_id`
  (y `tipo_compra_id`, y `posibles_adicionales`) las agrega la etapa 2 con sus tablas; el destino es una pieza
  (`DestinoImputacion`) para que las partidas se sumen sin tocar a quien imputa.
- **D2 · Centros de costo iniciales.** Los cinco del v4 (Administración, Comercial, Operaciones, Suministro, RRHH),
  editables en Catálogos (ícono `heroicon-o-chart-pie`); pendiente que Contabilidad los confirme.
- **D3 · El selector de imputación** se dibuja con el primer documento que lleva costo (etapa 2), cuando existan las
  partidas y su saldo. Ahora, el servicio.

- **D4 · Tipo de cambio.** De venta, el de la fecha de la línea (convención existente: SUNAT, venta para compras,
  `TipoCambioDelDia`), y fijo para la línea: sus montos se valorizan siempre con él; la diferencia de cambio al pagar
  es de la contabilidad. Si el contador indica otra cosa, se cambia en un solo lugar (`ImputacionService::destinar`).

Detalles de diseño (de la regla 3 y el DBML, sin decisión nueva):

- Una imputación por línea de origen (única en la base), en su moneda y con su tipo de cambio.
- Los montos se mueven por diferencias, desde los efectos de los documentos (comprometer al aprobar, ejecutar al
  despachar o dar conformidad, pagar al aplicar el pago; restar al anular). Nunca negativos y ejecutado ≤ comprometido,
  en la base y en el servicio, con la fila bloqueada.
- Mover un monto de una línea sin destino falla: no hay línea de costo sin imputación.

## Pasos

- [x] 1. Plan aprobado (D1 a D3).
- [x] 2. Centros de costo (catálogo y siembra) e `imputaciones`; `ImputacionService` con destino, montos y totales.
- [x] 3. Documentos, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Una línea sin destino no mueve montos; una con destino lo tiene una sola vez.
- Comprometer 1 000, ejecutar 600, ejecutar 500 más → rechazado (pasa lo comprometido); anular resta y deja 0.
- Los totales por centro de costo suman lo de sus líneas, en soles.

## Bitácora

- 2026-10-26 — Paso 1. Plan aprobado: mecanismo listo para enchufar las partidas en la etapa 2, los cinco centros del
  v4 por confirmar, el selector con el primer documento con costo.
- 2026-10-26 — Paso 2. Tablas `centros_costo` (catálogo, grupo «Comercio y pagos», ícono `heroicon-o-chart-pie`) e
  `imputaciones` (desviaciones del DBML en la migración: sin las columnas de la etapa 2; `tipo_cambio` obligatorio;
  CHECK de destino, de montos y de tipo de cambio). Siembra de los cinco del v4 (`CatalogosSeeder`); fila de pendientes
  al día. `ImputacionService`: `destinar` (moneda, tipo de cambio de venta de la fecha; cambiar el destino solo sin
  montos: reclasificar llega con la etapa 2), `mover` por diferencias con la fila bloqueada, `de` y
  `totalesPorCentroDeCosto` (en soles, redondeo de la base, nunca float). `DestinoImputacion` con `CentroDeCosto`;
  `MomentoImputacion`. Sin acciones de usuario: lo llaman los efectos de los documentos. `ImputacionTest` (13).
  Documentos: `codigo.md` («Imputación»), `comun.md` M11, Común 0.3.14, mapa (qué agrega la etapa 2).
- 2026-10-26 — Revisión de `revisor-arquitectura`. Bloqueante atendido: dos `destinar` a la vez sobre una línea nueva ya
  no chocan: inserta con `insertOrIgnore` (decide la base) y, si ya existía, sigue como cambio con la fila bloqueada.
  Observaciones atendidas: al destinar se ponen en nulo las columnas de destino que no usa (`COLUMNAS_DESTINO`, donde la
  etapa 2 suma la de partida); D4 escrito (tipo de cambio de venta, fijo para la línea); código del centro en
  mayúsculas sin espacios, en la base y en la pantalla; tope de `decimal(14,2)` con mensaje claro; `Imputacion` registra
  sus cambios en la bitácora; `motivo_id` y `comentario` anotados para la etapa 2. Anotado, sin cambio: que otro módulo
  no escriba `imputaciones` es convención (`codigo.md`); una prueba de arquitectura llega cuando exista el primero.
- 2026-10-26 — Paso 3. `composer check`: 1652 de 1653; la que faltaba pedía declarar `centro_costo_id` (suena
  sensible): `Imputacion` declara sus campos (ninguno sensible propio; los montos los protege el documento de origen) y
  pasa. Plan terminado. La rama va encima de `interfaz-letra-marca` (para no cambiarle la app al desarrollador). Sigue
  la 1.10 (alertas).
