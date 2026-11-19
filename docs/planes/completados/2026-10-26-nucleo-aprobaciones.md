# Plan: Aprobaciones configurables (M2) con prueba de concurrencia

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-10-26 · **Actualizado:** 2026-10-26

## Objetivo

Las transiciones que requieren aprobación se ejecutan solas cuando se completan las firmas que piden las reglas, y esas
reglas son datos que un administrador cambia desde Configuración. Firmar, observar y autoaprobar son acciones
registradas, con su prueba de concurrencia real. `SinAprobaciones` deja el contenedor.

## Contexto que leí

- `docs/planes/mapa-etapas.md` fila 1.7.
- `docs/diseno/v4/parte-7-transversal.md` «Matriz de aprobaciones»: reglas como datos editables por pantalla; montos U1,
  U2, U3 los fija Gerencia; quien aprueba es una jefatura o gerencia («sobre U1 también Gerencia de Operaciones»).
- `docs/diseno/techquk_modelo.dbml` M2: `reglas_aprobacion`, `regla_aprobadores`, `aprobaciones`.
- Plan 1.5 (`completados/2026-10-20-nucleo-estados-transiciones.md`) D5, D6, D7 (sin permiso por transición; firmas
  atadas a la versión; se ejecuta con `transicionarComoSistema()` y la última firma como `originadoPor`) y D9 (motivos
  de catálogo con «Otro»).
- Plan 1.4: el jefe es un puesto; `OrganigramaService::jefeDe()` (nulo si está vacante: la aprobación sube).
- Pendientes: «Aprobaciones · valores de U1, U2, U3» (Gerencia) y «Organigrama real» (Gerencia). **No bloquean:** las
  reglas son datos y no se siembran montos inventados.

## Decisiones

Aprobadas por el desarrollador el 2026-10-26: D1, D2 y D6 elegidas entre opciones; D3 a D5 y D7 a D9 aprobadas juntas.

- **D1 · Quién firma.** Cada paso de una regla nombra una jefatura, no una persona ni un rol: la de una unidad fija
  («Gerencia General») o la de la unidad del documento («su jefatura»). Firma quien ocupe ese puesto al momento de
  firmar. Si está vacante, sube a la jefatura de la unidad de arriba. Desviación del DBML (`user_id` o `rol`): una persona
  fija deja de servir cuando rota, y un rol no tiene jerarquía para subir.
- **D2 · Varias reglas y su orden.** Se aplican todas las reglas activas que calzan con el documento, la transición y
  el monto, acumuladas («sobre U1 también Gerencia de Operaciones»). Las firmas van en orden: la jefatura antes que la
  gerencia; cada una espera a la anterior.
- **D3 · Monto y moneda.** Las reglas van en soles, con rango «más de» y «hasta» (incluye el tope). El documento declara
  su monto y moneda; en otra moneda se convierte con el tipo de cambio del día en que se pidió la aprobación.
- **D4 · Observar.** El firmante observa con un motivo de catálogo (familia propia, con «Otro») y un comentario
  obligatorio que dice qué corregir. El documento vuelve a su estado editable por la transición que su definición
  declara para eso, y todas las firmas de esa ronda quedan anuladas.
- **D5 · Firmas atadas a la versión.** Pedir la aprobación abre una ronda con los pasos que tocan, calculados en ese
  momento. Si el documento vuelve a un estado editable o cambia su versión, la ronda se cierra sin efecto y se empieza
  de cero.
- **D6 · Autoaprobación.** Si quien debe firmar es quien pidió la aprobación, no firma: el paso sube a la jefatura de
  arriba. Excepción: con el permiso `aprobaciones.autoaprobar`, si la regla lo permite hasta su tope de monto y con un
  motivo, firma y la firma queda marcada para revisión posterior (la lista se ve en Configuración; el aviso, en la 1.10).
- **D7 · Delegación y suplentes: después.** El DBML los trae (`puede_delegar`, `suplente_user_id`); la vacante ya se
  cubre subiendo. Queda listo el punto de extensión: quien resuelve el firmante es una pieza (`ResolutorDeFirmante`).
- **D8 · Pantallas.** Configuración → Aprobaciones: las reglas con sus pasos, editables por acciones registradas (con
  versión). Firmar y observar son acciones registradas `nucleo.aprobaciones.*`, sin pantalla de documento todavía (no
  hay documentos reales); el servicio ya responde «qué tengo por firmar» para la bandeja que llegue con el primer
  documento.
- **D9 · Concurrencia.** Firmar bloquea la fila del documento y revisa dentro del bloqueo; la base impide dos firmas del
  mismo paso. Prueba en `tests/Concurrencia`: dos personas firman a la vez el último paso → la transición corre una vez.

## Pasos

- [x] 1. Plan aprobado (D1 a D9).
- [x] 2. Tablas (reglas, pasos, rondas y firmas), modelos y motivos de observación.
- [x] 3. `AprobacionesService`: pedir (abre la ronda), firmar, observar, autoaprobar; quién firma con la vacante que
  sube; reemplaza `SinAprobaciones`.
- [x] 4. Acciones registradas y pruebas de reglas (montos, moneda, acumuladas, orden, vacante, versión, observar,
  autoaprobación).
- [x] 5. Prueba de concurrencia real.
- [x] 6. Configuración → Aprobaciones.
- [x] 7. Documentos, `revisor-arquitectura`, `composer check`, `/cerrar-tarea` y PR.

## Criterio de aceptación

- Con las reglas de la matriz del v4 cargadas con montos de prueba (U1 = 1 000, U2 = 10 000): un requerimiento de
  800 lo firma solo su jefatura; uno de 5 000, su jefatura y luego Gerencia de Operaciones; con la última firma, el
  documento pasa a aprobado con esa persona como originadora.
- Jefatura vacante → firma la de arriba. Documento reabierto → firmas anuladas, ronda nueva.
- Dos firmas a la vez del último paso → una sola transición.

## Bitácora

- 2026-10-26 — Paso 1. Plan escrito y aprobado: firma una jefatura (fija o la del documento, sube si está vacante), en
  orden, reglas acumuladas en soles; autoaprobación solo con permiso, tope de la regla y motivo, marcada para revisión.
- 2026-10-26 — Pasos 2 a 4. Tablas `reglas_aprobacion`, `pasos_regla_aprobacion`, `rondas_aprobacion` y
  `firmas_aprobacion` (desviaciones del DBML explicadas en la migración). Diseño: `AprobacionDeTransicion` gana
  `alCambiarEstado()`, que `TransicionesService` llama tras crear y tras cada transición, dentro de la transacción;
  `ReglasDeAprobacion` (en el contenedor) abre la ronda al llegar a un estado con salida que requiere aprobación y la
  cierra al salir (completa si salió por esa transición; si no, anulada). Las firmas que firma `AprobacionesService`
  (firmar, autoaprobar, observar, porFirmar, revisar autoaprobación, reglas). Dos piezas para no tener una dependencia
  circular con `TransicionesService`. Quién firma: `ResolutorDeFirmante` → `JefaturaQueSube` (punto de extensión de
  D7). `DefinicionDocumento` gana `montoParaAprobacion()`, `unidadDeAprobacion()` y `transicionAlObservar()`.
  Decisiones de detalle: sin reglas que calcen, la ronda nace sin firmas y bloquea (nunca aprueba sola); la misma
  jefatura en dos reglas firma una vez; las reglas se ordenan por su monto mínimo (la jefatura antes que la gerencia);
  un paso se escribe `documento` o `unidad:<id>` en la acción. Familia de motivos `nucleo.aprobaciones.observar`.
  Permisos: `aprobaciones.configurar`, `aprobaciones.autoaprobar`, `aprobaciones.revisar`. `AprobacionesTest` (23).
- 2026-10-26 — Paso 5. `tests/Concurrencia`: la última firma dada dos veces a la vez → una firma, una transición; la otra
  falla con `AprobacionRechazada`. Comprobado que detecta el error: sin los bloqueos del servicio, la segunda llega hasta
  la transición y falla con otro error (la transición corre una vez gracias a su propio bloqueo, pero ya no limpio).
- 2026-10-26 — Paso 6. Configuración → Aprobaciones (`ReglaAprobacionResource`, ícono `heroicon-o-document-check`,
  elegido por el desarrollador): reglas con documento y paso, rango, firmas en orden, tope de autoaprobación y
  activa; agregar, editar (versión al abrir), desactivar y activar por las acciones registradas. Los pasos se eligen en
  un selector («La jefatura del documento» o «Jefatura de <unidad>», en el orden en que se eligen), nunca escritos a
  mano. `PantallaAprobacionesTest`. Documentos: `codigo.md`, `comun.md` M2, Común 0.3.12, mapa.
- 2026-10-26 — Revisión de `revisor-arquitectura`. Bloqueante atendido: todas las acciones (`autoaprobar`,
  `revisar-autoaprobacion`, `editar-regla`, `desactivar-regla`, `activar-regla` y la nueva `recalcular`) se prueban sin
  interfaz, con su permiso. Observaciones atendidas: un documento con aprobación que no declara su monto falla (antes
  valía cero y se aprobaba en el nivel más bajo); D5 cumplido: firmar sobre otra versión reinicia la ronda (se confirma
  el reinicio y se avisa «las firmas empezaron de cero»), y `recalcular` (permiso `aprobaciones.configurar`) rehace una
  ronda que nació sin reglas; en una cadena de aprobaciones la ronda siguiente conserva a quien pidió la primera;
  CHECK nuevos (firmante de la firma igual que el del paso, revisa otra persona, versión ≥ 1, transición no vacía); tope
  cero con mensaje claro; monto en soles redondeado al céntimo (medio hacia arriba), no truncado; nota en `codigo.md`
  sobre `originadoPor` cuando el sistema lleva el documento a aprobación. D6: la lista de autoaprobaciones por revisar
  es una pestaña de Configuración → Aprobaciones (elegido por el desarrollador), con «Marcar revisada»
  (`heroicon-o-clipboard-document-check`; `heroicon-o-check` ya lo usa la barra de desarrollo). Las tarjetas de Mien
  («Por firmar», «Autoaprobaciones por revisar») van con el primer documento real (decidido por el desarrollador;
  anotado en el mapa). Aceptado como está: la misma persona puede firmar dos pasos si la jefatura sube (`codigo.md`).
- 2026-10-26 — Paso 7. `composer check` en verde (1597 pruebas, 11,5 min). Plan terminado. Sigue la 1.8 (adjuntos y
  notificaciones).
