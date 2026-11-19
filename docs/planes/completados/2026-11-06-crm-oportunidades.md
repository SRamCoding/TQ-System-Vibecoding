# Plan: Oportunidades (CRM-02)

- **Estado:** terminada (2026-11-08): `composer check` en verde; rama subida, PR a cargo de Sebastián
- **Responsable:** Sebastián (área 2, Comercial, del ADR 0020; modo actual de AGENTS.md)
- **Módulo(s):** `Crm/Oportunidades` (nuevo). Toca `Crm/Cuentas` (FK de `cuenta_marcas_cliente.oportunidad_id`,
  `exigirUsable`, origen en `ResumenCuenta`, aportes invertidos, `OportunidadEnlazable`, selector de motivo común;
  D6, D7, D13) y el núcleo (`Campo::motivoDe`, tipo de party «Competidor»; D4, D7), cada uno en su commit
- **Fase:** cerrada. **Se fusiona después de la 2.20 y antes de la 2.0b.**
- **Creado:** 2026-11-06 · **Actualizado:** 2026-11-06

## Objetivo

Existe el módulo `Crm/Oportunidades`: una oportunidad nace en una cuenta usable con su ejecutivo, origen, línea de
solución, monto y moneda; recorre el embudo (Prospecto → Calificada → Propuesta enviada → Negociación) sin saltar
etapas y cumpliendo lo que cada etapa exige (acumulado); se cierra ganada (la cuenta pasa a cliente y sale
`OportunidadGanada`) o perdida (con motivo, «¿Lo ganó otra empresa?», ganador y precio), y anular revierte sus
efectos (anular una ganada, solo con el contrato en borrador). La probabilidad la da la etapa. La lista con portada, el
tablero, la ficha y la pestaña en la cuenta siguen los patrones del sistema, y los puntos de extensión para
Cotizaciones (CRM-03), Actividades (CRM-05) y Licitaciones (CRM-04) quedan listos.

## Contexto que leí

- `AGENTS.md` (reglas, «Cómo trabajar», «Trabajo en equipo», modo actual), `docs/planes/plantilla.md`.
- `docs/planes/mapa-etapas.md`: «Etapa 2 · tareas» (filas 2.1 a 2.20) y «Etapa 2 · anotado antes de abrirla»
  (orígenes comerciales; oportunidades y el ejecutivo de la cuenta; ganar → `marcarCliente`).
- ADR 0020: tabla de puntos (Oportunidades: 3 de complejidad, 5 puntos, «trece pantallas, tablero con requisitos
  por etapa»), área 2, **C3 · La oportunidad ganada** (dueño: área 2; la usa Contratos), C2, C7, C8, reglas para
  todas.
- `docs/decisiones/pendientes.md`: «Tipologías de proyecto» (bloquea el campo Tipología de la Nueva oportunidad),
  «Líneas de solución» (son datos, no bloquea), «Grilla de roles real» (nota del CRM sobre `parties.ver`). Ninguna
  fila trata el embudo: sus valores por validar van como preguntas (abajo).
- Plan 2.20 (`docs/planes/completados/2026-11-04-crm-cuentas.md`): D7 (ejecutivo, reasignar, atribución, «lo
  propio»), D8 (`cliente_desde`, `cuenta_marcas_cliente`, `marcarCliente` y `revertirCliente`), D9 (impedimentos
  para desactivar), D11 (pantallas, selectores de catálogo con el campo común, «nada de avisos sobre datos que aún no
  existen»), D12 (lo que Cuentas ofrece), «Preparado para lo diferido» y la bitácora final (revisor y check).
- Código de Cuentas: `CuentasService` (`buscar` con `incluirId`, `resumen`, `deParty`, `marcarCliente`,
  `revertirCliente`), `ResumenCuenta`, `Events/EjecutivoDeCuentaCambiado`, `Pestanas/` (`RegistroPestanasCuenta`,
  `PestanaCuenta`, `BotonCuenta`), `Actividad/` (`ActividadDeCuentas`, `AporteActividad`,
  `AporteOportunidadesAbiertas`, `DatoActividad`, `DatosActividadCuenta`), la migración de `cuenta_marcas_cliente`
  (su `oportunidad_id` va sin llave foránea: «la agrega CRM-02»).
- Núcleo: `Estados/` (`CondicionTransicion` recibe documento, usuario y transición; `RegistroDocumentos` acepta
  efectos, revisiones antes de anular y familias de motivos de otros módulos, pero no condiciones),
  `Catalogo` (existen `LineasSolucion`, `Monedas`, `OrigenesComerciales`), `TipoCambio/`, `Campo::elementoDe`,
  `tests/Arch/ArquitecturaTest.php` (capas que no pueden usar modelos ajenos).
- v4: parte 2 (paso 2 «Nace la oportunidad y se califica», tablero, ficha, avance y cierre; reglas del CRM: «no se
  salta etapas hacia adelante; toda oportunidad abierta tiene próxima acción con fecha; no se cierra perdida sin
  motivo»; tablero de Gerencia Comercial), parte 3 (la frontera), parte 1, parte 4 flujo 1 (el presupuesto enlazado
  pasa al proyecto), parte 7 (numeración, nada se borra), parte 8 (escenarios del CRM y de la frontera), parte 9
  (pantallas en boceto). Parte 6 no tiene ficha de oportunidad.
- DBML v2, `CRM_02_Oportunidades`: `oportunidades`, `oportunidad_lineas`, `oportunidad_competidores`,
  `motivos_perdida` (reemplazada por la familia de motivos) y las referencias a `oportunidades` desde `cotizaciones`,
  `leads`, `tickets` y `presupuestos`.
- `docs/analisis/bloques/crm-pantallas.md` §CRM-02 (13 pantallas) y su diseño en
  `docs/diseno/fuentes/crm-pantallas-2026-10-13/` (`Oportunidades`, `OportunidadesTabla`, `Oportunidad`,
  `OportunidadNueva`, `OpAvanzar`, `OpCerrar`, `Avanzar`, `OpCompetidores`, `OpHistorial`, `CuentaOportunidades`,
  `CuentaOportunidadNueva`). `docs/analisis/bloques/crm.md`: el CRM está en 0.4.2, en validación (Cristhian, luego
  Miguel).
- `docs/glosario.md`: no define oportunidad, embudo, ponderado ni etapa (se suman al cerrar, paso 12).
- Convenciones: `codigo.md` (Estados y efectos, motivos de catálogo, numeración, Filament, «Lo propio y lo de
  todos», nombres de permisos con área) y `base-de-datos.md` (dinero y tiempo, integridad y concurrencia, «lo que se
  analiza se elige»).

## Decisiones

Propuestas el 2026-11-06. Ninguna está aprobada. Cada una dice el problema, las opciones, la recomendación y lo
descartado. Las que dependen de la empresa llevan su pregunta al final («Preguntas para la empresa»): se construyen
configurables con el valor del diseño, como manda `pendientes.md` para lo POR VALIDAR, y se anotan allí al aprobar.

- **D1 · La oportunidad es un documento con estados del núcleo; las etapas son sus estados.** *Aprobada
  (desarrollador, 2026-11-06), con ajustes.*
  - **Problema:** el v4 describe un embudo con cinco columnas (Prospecto, Calificada, Propuesta enviada,
    Negociación, Cierre) y dos salidas (ganada, perdida). El DBML tiene `estado_id` y no una columna de etapa.
  - **Decisión:** la etapa es el estado de `TransicionesService` (`DefinicionDocumento`): una sola máquina, con
    historial, permisos, motivos, condiciones y efectos que ya existen. `OportunidadDefinicion`, clave
    `crm.oportunidades.oportunidad` (prefijo de área, ADR 0021).
  - **Estados:** `prospecto` (inicial), `calificada`, `propuesta_enviada`, `negociacion` (los cuatro abiertos,
    editables), `ganada`, `perdida` (finales, no editables) y `anulada` (`es_cancelacion`).
  - **Transiciones (son datos, no código):**
    1. `calificar` (prospecto → calificada), `enviar_propuesta` (calificada → propuesta_enviada), `negociar`
       (propuesta_enviada → negociacion): «Avanzar etapa», una a la vez; no se saltan etapas hacia adelante (v4).
    2. `retroceder` desde cada etapa abierta a la anterior, con motivo de su familia (con «Otro» y detalle).
    3. `ganar` desde `negociacion` y `perder` desde cualquier etapa abierta: acciones propias, con
       `fecha_cierre_real`.
    4. `anular` desde las abiertas y `perdida`, con motivo (duplicada, cuenta equivocada); `anular_ganada` solo desde
       `ganada`, con motivo, revierte los efectos vivos y es solo para Gerencia Comercial (D10).
    - **Provisional:** ganar desde `propuesta_enviada` (licitación pública sin negociación), si se puede retroceder
      y con qué motivos. Queda en `pendientes.md` («Embudo comercial») con la pregunta abierta; la respuesta se
      aplica cambiando datos (transiciones y motivos), no código.
  - **Exigencias de cada etapa** (corregidas con la D9, 2026-11-06): son condiciones de la transición, revisadas en el
    servicio (no texto en pantalla), por una sola `CondicionTransicion` que lee el registro `RequisitosDeEtapa` (D9).
    Fuentes: el diseño del 13 oct exige para calificar necesidad, presupuesto y decisor; el panel «Avanzar» del v4
    (Calificada → Propuesta enviada) exige decisor identificado, presupuesto confirmado y al menos una cotización
    emitida; el v4 exige además programar la próxima acción en todo avance y una cotización aceptada para ganar.
    1. Calificar exige necesidad confirmada, decisor identificado (un contacto de la cuenta) y presupuesto
       confirmado.
    2. Enviar la propuesta exige además al menos una cotización emitida.
    3. Todo avance exige la próxima acción programada.
    4. Ganar exige una cotización aceptada (la que lleva `OportunidadGanada`).
    - **Se acumulan:** al avanzar se revisan todos los exigidos hasta la etapa destino, no solo los de esa
      transición (como el panel del v4). Nunca se revisan hacia atrás: si una oportunidad deja de cumplir uno, no
      retrocede sola; solo no puede avanzar.
    - **En esta tarea se exige el punto 1.** La cotización emitida y la aceptada las registra CRM-03, y la próxima
      acción CRM-05, en `RequisitosDeEtapa` cuando lleguen; esta tarea deja el registro listo y lo prueba con un
      requisito falso. Dos consecuencias, a propósito:
      - Hasta CRM-03 se puede ganar sin cotización, y `OportunidadGanada` sale sin ella (ver la C3 del ADR 0020).
      - Al activarse un requisito, las oportunidades abiertas que no lo cumplan quedan detenidas en su etapa hasta
        cumplirlo (no se mueven).
  - **Probabilidad:** un valor por estado abierto, configurable en Configuración (parámetros del módulo), que nadie
    edita en la oportunidad; el embudo ponderado se calcula con él (detalle en la D2).
  - **Perder:** motivo elegido de la familia `crm.oportunidades.oportunidad.perder` (se elige, no se escribe; la
    familia mantiene «Otro» con detalle obligatorio, como manda la convención, que prevalece sobre el v4). Motivos de
    partida: Precio, Plazo, Especificaciones, Presupuesto cancelado, No respondió, Decisión interna (diseño del
    13 oct) y Proceso desierto. Al perder se pregunta «¿Lo ganó otra empresa?»: si sí, el competidor (ficha de
    Personas y empresas) y su precio con moneda son obligatorios; si no, no se piden. No depende del motivo, así que
    vale también para los que se agreguen. Alimenta el gráfico de motivos y el histórico de precios por entidad
    (detalle en la D4).
  - **Anular una ganada** (`anular_ganada`, D10; cruza áreas: amplía la C3; **espera el cambio al ADR 0020 antes de construir la parte de
    Contratos**):
    1. Solo se anula si su contrato sigue en borrador. Dentro de la transacción que anula, Oportunidades llama a
       `ContratosDeOportunidad::anularBorrador(oportunidadId, motivo)`: Contratos anula su borrador y pide a C2
       anular el proyecto en preparación (`ProyectosService::anularEnPreparacion`). Si el contrato ya avanzó, falla
       con «Primero anula el contrato CT-…» y se deshace todo.
    2. Orden de bloqueo fijo: primero la oportunidad, después el contrato, después el proyecto.
    3. En la misma transacción, el efecto de ganar se revierte: `CuentasService::revertirCliente` (D8 de Cuentas,
       D3).
    4. Después de confirmar se emite `OportunidadGanadaAnulada(oportunidadId, cuentaId, motivo)`, como aviso.
    5. **Mientras tanto** esta tarea deja el punto de extensión: la interfaz `ContratosDeOportunidad` con una versión
       falsa registrada en el contenedor que responde «no hay contrato» (ADR 0020, regla 3), y la llamada ya hecha
       desde la anulación. Contratos (área 3) registra la real; `anularEnPreparacion` lo construye el área 4.
  - **Numeración:** `OP-AAAA-NNNN` con el servicio del núcleo (`numero` `NOT NULL` y único); los mensajes nombran la
    oportunidad por su número y nombre.
  - **Bloqueo optimista:** `version`, capturada al abrir el formulario (`Campo::deControl()`).
  - **No se cambia la cuenta** de una oportunidad: si se eligió mal, se anula y se crea otra (la marca de cliente y
    la atribución dependen de ella).
  - **Descartado:** una columna `etapa` aparte del estado (duplica historial y reglas); «Reabrir» una perdida (si el
    negocio revive, es una oportunidad nueva); arrastrar entre columnas como única forma de avanzar (D11); revisar
    «sigue en borrador» y luego avisar con un evento (deja un hueco entre la revisión y el aviso en que el contrato
    puede aprobarse).

- **D2 · La probabilidad la da la etapa; nadie la edita.** *Aprobada (desarrollador, 2026-11-06), con ajustes.*
  - **Problema:** el DBML tiene `probabilidad smallint` editable; el v4 dice «la probabilidad la define la etapa;
    nadie la edita».
  - **Decisión:**
    1. **Sin columna de probabilidad.** Un parámetro del módulo por etapa abierta,
       `crm.oportunidades.probabilidad.<etapa>`, con los valores del diseño (prospecto 10, calificada 25,
       propuesta_enviada 50, negociacion 75), editable por Gerencia en Configuración. Ganada 100 y perdida 0 son
       fijos; las anuladas no entran al embudo. Se quita `probabilidad` del DBML en esta tarea.
    2. **Validador de los porcentajes** (`ValidadorParametro`, uno compartido por los cuatro): cada uno entero entre
       1 y 99, y sin bajar de una etapa abierta a la siguiente (prospecto ≤ calificada ≤ propuesta_enviada ≤
       negociacion), comparando con los valores vigentes de las vecinas. Consecuencia: para subir dos a la vez se
       cambia primero el de la etapa más avanzada; el mensaje lo dice.
    3. **Ponderado = monto × probabilidad, calculado al leer**, en `bcmath`, nunca `float`: si Gerencia cambia un
       porcentaje, todo el embudo abierto se recalcula y el tablero cuadra con la configuración.
    4. **Fecha de entrada a la etapa:** columna `en_etapa_desde`, copia del historial de estados (la fecha de la
       última entrada al estado actual). La pone un efecto interno en cada transición (campo derivado, solo cambia
       dentro de un efecto). **Al retroceder, el contador vuelve a empezar**: cuenta desde que volvió a esa etapa,
       no desde la primera vez que estuvo en ella. Una prueba de coherencia revisa que sea siempre igual a la última
       entrada al estado actual en el historial, también después de retroceder. Con columna se filtra y ordena en
       la base («estancadas»).
    5. **Máximo de días por etapa:** parámetro `crm.oportunidades.dias_maximos.<etapa>`, entero o vacío. **14 solo
       en Calificada** (lo que da el diseño); las demás, vacío = sin máximo, hasta que la empresa responda la
       pregunta 2 (no se inventa: en licitaciones públicas Propuesta y Negociación pueden durar semanas sin estar
       estancadas). Una oportunidad está estancada si su etapa tiene máximo y lo superó.
    6. Los porcentajes y los días quedan como provisionales en `pendientes.md` («Embudo comercial»).
  - **Descartado:** (B) una foto de la probabilidad en cada transición, porque cambiar un porcentaje no reclasificaría
    el embudo abierto; (C) la columna editable del DBML, que contradice el v4; 14 días en todas las etapas (inventado).

- **D3 · Ganar: la cuenta pasa a cliente y sale `OportunidadGanada`; anular lo revierte.** *Aprobada (desarrollador,
  2026-11-06), con ajustes.*
  - **Problema:** el plan 2.20 (D8.3) dejó `marcarCliente(cuentaId, fecha, oportunidadId)` y
    `revertirCliente(cuentaId, oportunidadId)` para el efecto de «ganar». La C3 pide el evento `OportunidadGanada`
    con la cotización aceptada, las condiciones y el presupuesto enlazado, que no existen hasta CRM-03; la D1 decide
    que el evento sale ya, con lo que falte vacío.
  - **Decisión:**
    1. **Efecto `GanadaMarcaCuentaCliente`** en `ganar`: `aplicar()` llama a `marcarCliente` con
       `fecha_cierre_real` y guarda cuenta y oportunidad en `efectos_aplicados.datos`; `revertir()` llama a
       `revertirCliente` con esos datos. Perder no toca la marca.
    2. **Efecto `GanadaAvisaAlErp`** en `ganar`: emite `OportunidadGanada` después de confirmar, con la oportunidad,
       la cuenta (y su party), el monto final, la moneda, la fecha de cierre real y el ejecutivo. Van vacíos, hasta
       que existan sus módulos: la cotización aceptada y sus condiciones (plazo, forma de pago, garantía) y el
       presupuesto enlazado (CRM-03), y la convocatoria de origen (CRM-04). Su `revertir()` es la llamada a
       `ContratosDeOportunidad::anularBorrador` de la D1.
    3. **Anular una ganada** deshace los efectos en orden inverso: primero Contratos (contrato y proyecto), después
       Cuentas (la marca). Orden de bloqueo: oportunidad → contrato → proyecto → cuenta (C3 del ADR 0020).
    4. **`ganar` es acción propia:** pide la fecha de cierre real (por defecto hoy) y confirma el monto final (puede
       diferir del estimado). La fecha **nunca es futura** (lo valida el servicio); puede ser anterior a la creación
       de la oportunidad (registro tardío). Si tiene más de 90 días, la pantalla pide confirmación, sin bloquear.
       Exige además la cuenta usable (D6): no se entrega al ERP un cliente inactivo.
    5. La llave foránea `cuenta_marcas_cliente.oportunidad_id → oportunidades.id` la agrega la migración de esta
       tarea, como dejó dicho Cuentas.
  - **Pruebas de la marca con varias oportunidades de la misma cuenta** (Cuentas toma la marca vigente más antigua;
    se prueban de punta a punta desde Oportunidades):
    1. Ganada en marzo, perdida en noviembre → sigue cliente desde marzo (perder no toca la marca).
    2. Ganadas en marzo y agosto, se anula la de agosto → sigue cliente desde marzo.
    3. Se anulan las dos y no hay marca manual → vuelve a prospecto.
    4. Ganada en agosto, luego se registra tarde una ganada de marzo → cliente desde marzo.
  - **Descartado:** que Cuentas escuche un evento para marcar (D8 del 2.20: la marca va en la misma transacción);
    esperar a CRM-03 para emitir el evento (D1); «nunca anterior a la creación» para la fecha de cierre (contradice
    el registro tardío).

- **D4 · Perder: motivo de catálogo, «¿Lo ganó otra empresa?» y el precio del ganador.** *Aprobada (desarrollador,
  2026-11-06), con ajustes.*
  - **Problema:** «perder pide más información que ganar, a propósito»: motivo (lista cerrada), quién ganó y a qué
    precio. Pero hay pérdidas sin ganador (presupuesto cancelado, proceso desierto) y pérdidas en que no se sabe quién
    ganó o a qué precio (frecuente en concursos privados).
  - **Decisión:**
    1. **Motivo:** la transición `perder` exige motivo de la familia `crm.oportunidades.oportunidad.perder` (la
       «pérdida de oportunidad» de la D9b del plan 1.5), que declara este módulo. Se elige de la lista; «Otro» con
       detalle obligatorio (convención). Motivos de partida (D1): Precio, Plazo, Especificaciones, Presupuesto
       cancelado, No respondió, Decisión interna, Proceso desierto. Se agregan otros en Configuración.
    2. **«¿Lo ganó otra empresa?»**, con tres respuestas: **Sí**, **No** y **No se sabe**. No depende del motivo.
       La respuesta se guarda en la oportunidad (`ganador_conocido`, enum de base con CHECK; nula mientras no esté
       perdida, obligatoria en `perdida`), para que los reportes no la deduzcan de la tabla de competidores. El
       gráfico de motivos y los reportes distinguen «No» de «No se sabe».
       - **No** y **No se sabe:** no se piden competidor ni precio.
       - **Sí:** el competidor es obligatorio, una ficha de Personas y empresas (selector del registro único, con
         alta rápida si no está; nunca texto libre). El precio, mayor que cero y con su moneda, es obligatorio salvo
         que se marque explícitamente **«precio desconocido»**: entonces queda nulo, y no entra en el histórico de
         precios. CHECK en la base: precio y moneda juntos, o «precio desconocido» sin ninguno de los dos.
       - En licitaciones públicas el ganador y su precio son públicos en el SEACE, así que «No se sabe» debería ser
         raro allí; con la integración del OECE (CRM-04) podrán llegar solos (ver «Preparado para lo diferido»).
    3. **El ganador queda en `oportunidad_competidores`**, la misma lista de la pestaña Competidores: `es_ganador`
       con un índice único parcial (un ganador por oportunidad), `precio decimal(14,2)` y `moneda_id` (el DBML trae
       `precio_estimado` sin moneda; se corrige) y `precio_desconocido`. Si no estaba en la lista, se agrega.
    4. **Tipo «Competidor»** en el catálogo de tipos de persona y empresa (`roles_party`, código `competidor`):
       hoy no existe. Se agrega en `CatalogosSeeder` (y lo que haga falta para las bases ya sembradas) en un commit
       propio marcado «Núcleo:». Al registrar una ficha como competidor de una oportunidad (en la lista o como
       ganador), se le asigna con `PartiesService::asignarRol` desde hoy, solo si `tieneRol` dice que no lo tiene;
       si dos registros lo asignan a la vez, el rechazo por cruce de periodos se trata como «ya lo tiene». Una
       ficha puede ser competidor y proveedor (o cliente) a la vez. Solo se ofrecen fichas activas (más la que ya
       está, marcada).
    5. **Histórico de precios por entidad:** sale de los ganadores con precio: qué competidor ganó, a qué precio,
       para qué cliente y cuándo, en su moneda original y sin convertir, junto a nuestro monto para comparar.
    6. **Anular una perdida:** se puede (D1); no hay efectos que revertir. Sus datos se quedan, pero una anulada no
       cuenta en gráficos ni en el histórico.
  - **Descartado:** ganador obligatorio siempre (no aplica a cancelaciones ni a desiertos); sí/no sin «No se sabe»
    (mezcla «nadie ganó» con «no lo averiguamos»); precio siempre obligatorio (obliga a inventarlo y ensucia el
    histórico); ganador en texto libre (el diseño pide «una organización del registro único»).

- **D5 · El ejecutivo de la oportunidad y la reasignación desde la cuenta.** *Aprobada (desarrollador, 2026-11-06),
  con un ajuste.*
  - **Problema:** el v4 lo llama «Dueño». El plan 2.20 (D7.5) fija la atribución al ejecutivo de la oportunidad y que
    las cerradas nunca cambian de ejecutivo; Cuentas dispara `EjecutivoDeCuentaCambiado(cuentaId,
    ejecutivoAnteriorUserId, ejecutivoNuevoUserId, incluyeOportunidadesAbiertas)`, uno por cuenta, después de
    confirmar.
  - **Decisión:**
    1. `ejecutivo_user_id` `NOT NULL`. Al crear, por defecto el ejecutivo de la cuenta (`ResumenCuenta`); se puede
       elegir otro entre los usuarios activos con `crm.cuentas.atender` (una sola definición de «quién vende»), más
       el que ya tiene si dejó de cumplir, marcado.
    2. Cambiarlo es `crm.oportunidades.reasignar` (una o varias, todo o nada), solo en abiertas: en `ganada`,
       `perdida` y `anulada` no cambia, y así la atribución no se mueve.
    3. **Oyente de `EjecutivoDeCuentaCambiado`:** si `incluyeOportunidadesAbiertas`, pasa al nuevo ejecutivo solo las
       abiertas de esa cuenta **cuyo ejecutivo era el anterior**; las asignadas a otra persona a propósito se quedan
       con ella. En su propia transacción, con cada fila bloqueada, sin comparar versiones (solo toca el ejecutivo),
       idempotente (repetirlo no cambia nada) y con bitácora por oportunidad.
    4. Va **en la cola, con reintentos** (no es atómico con la reasignación de la cuenta: el evento es posterior al
       commit, ADR 0020 regla 2). Si agota los reintentos, además del monitoreo técnico (tareas fallidas), su
       `failed()` emite una **alerta del núcleo** `crm.oportunidades.traspaso_fallido` («No se pudieron pasar las
       oportunidades de la cuenta {cuenta} de {anterior} a {nuevo}», con enlace a la cuenta), a Gerencia General de
       fábrica (`SeguridadService::ROL_GERENCIA_GENERAL`), configurable en Configuración → Alertas, para que alguien
       lo corrija a mano con «Reasignar».
    5. **Sin aviso propio** ni «y M oportunidades» en el de Cuentas: el de Cuentas («Se te asignaron N cuentas») ya
       llega, y el nuevo ejecutivo las ve en «Mías».
  - **Descartado:** mover todas las abiertas de la cuenta sin mirar su ejecutivo; un permiso propio
    `crm.oportunidades.atender` (dos listas de vendedores que se desalinean); llamar a Oportunidades desde Cuentas
    (dependencia en los dos sentidos); sumar el número de oportunidades al aviso de Cuentas (Cuentas no lo sabe al
    avisar).

- **D6 · La cuenta: selector con `CuentasService::buscar` e `incluirId`, y `exigirUsable` en el servicio.**
  *Aprobada (desarrollador, 2026-11-06), con ajustes.*
  - **Decisión:**
    1. El campo de cuenta usa `buscar(texto, 20, incluirId: <la que ya tiene>)`: solo cuentas usables, y la actual
       aunque ya no lo sea, marcada (regla de AGENTS.md sobre selectores). Desde la ficha de la cuenta, la cuenta
       llega elegida y fija. Como la cuenta no se cambia después de crear (D1), `incluirId` importa al ver o editar
       una cuyo cliente se desactivó.
    2. `oportunidades.cuenta_id` es FK a `cuentas` con índice (la base sí puede apuntar a otro módulo; el código
       solo pasa por `CuentasService`).
    3. **El servicio revisa que la cuenta sea usable, no solo la pantalla**, con un método nuevo de Cuentas que se
       llama dentro de la transacción de Oportunidades:
       `CuentasService::exigirUsable(int $cuentaId, bool $exclusivo = false): ResumenCuenta`. Bloquea la fila de la
       cuenta (`FOR SHARE`, o `FOR UPDATE` con `exclusivo`) y falla con `CuentaRechazada` si no es usable.
       - **Crear** lo llama con bloqueo compartido: varias creaciones en la misma cuenta no se esperan entre sí, pero
         una desactivación espera a que terminen (y ellas a la desactivación).
       - **Ganar** lo llama con `exclusivo: true`, desde la condición de la transición, antes de los efectos:
         `marcarCliente` igual necesita el bloqueo exclusivo, y pasar de compartido a exclusivo con dos ganadas a la
         vez en la misma cuenta (ítems de una misma licitación) las trabaría entre sí y PostgreSQL cancelaría una.
         Con exclusivo desde el inicio, la segunda espera a la primera y las dos terminan bien. El bloqueo de
         `marcarCliente` en la misma transacción no espera (ya lo tiene).
       - **Orden de bloqueo** (C3): oportunidad → contrato → proyecto → cuenta. Al crear y al ganar solo intervienen
         la oportunidad y la cuenta, en ese orden.
       - Se prefirió el parámetro a que `marcarCliente` revise si es usable: la revisión es una condición de la
         transición (mensaje claro antes de ganar), no un fallo dentro de un efecto.
    4. **Desactivar la cuenta** (D9 de Cuentas) toma el bloqueo exclusivo primero (`bloquear()`, `FOR UPDATE`) y
       recién después consulta los impedimentos (oportunidades abiertas). Verificado en el código el 2026-11-06
       (`CuentasService::desactivar`); una prueba lo cuida. Con lectura confirmada (READ COMMITTED), la consulta de
       impedimentos que corre después de esperar ve la oportunidad que se acaba de crear.
    5. Lo que no cubre, a propósito: desactivar la **ficha** de Personas y empresas no revisa trabajo en curso (el
       núcleo solo la quita de los selectores; «lo ya registrado la conserva»), así que no se bloquea aquí.
    6. **Pruebas de concurrencia con procesos reales** (`tests/Concurrencia`): crear y desactivar a la vez (nunca
       queda una abierta en una cuenta inactiva) y dos ganadas a la vez de la misma cuenta (las dos terminan bien,
       sin bloqueo mutuo).
  - **Descartado:** leer `cuentas` con un modelo de Cuentas (regla 2); permitir crear en una cuenta inactiva;
    revisar solo en la pantalla; bloqueo compartido también en ganar (se traba con dos ganadas a la vez).

- **D7 · Origen y línea de solución con los catálogos del núcleo.** *Aprobada (desarrollador, 2026-11-06), con
  ajustes.*
  - **Decisión:**
    1. `origen_comercial_id` `NOT NULL` → «Orígenes comerciales» (el mismo nombre de columna que `cuentas`; el DBML
       dice `origen_lead_id` y nulo, y se ajusta). **Por defecto, el origen de la cuenta**, y se puede cambiar (una
       cuenta que llegó por una convocatoria puede traer después una oportunidad por referencia). Para eso
       `ResumenCuenta` suma `origenComercialId` (cambio pequeño en Cuentas, misma área).
    2. `linea_solucion_id` `NOT NULL` → «Líneas de solución» (lo que se analiza se elige; el catálogo ya existe y su
       lista real es un pendiente de datos, que no bloquea).
    3. **Selectores: la pieza común, nunca un `Select` armado a mano ni uno propio de Oportunidades.**
       a. Catálogos (origen, línea de solución, moneda): en acciones, `Campo::elementoDe(..., Catalogo::…)`; en
          páginas, `AccionFilament::campo(acción, campo)` (2.20). Así reciben solos la mejora de la 2.0 (búsqueda en
          vivo, «+» y «Ver la lista»).
       b. **Motivos** (perder, retroceder, anular): pieza común nueva del núcleo, en un commit propio «Núcleo:»
          (desarrollador, 2026-11-06): `Campo::motivoDe(nombre, etiqueta, familia)`, dibujada por `AccionFilament`
          y disponible en páginas por `AccionFilament::campo`. Ofrece los motivos activos de la familia, el valor
          actual aunque esté inactivo (marcado), y con «Otro» exige el detalle (mínimo
          `motivos.minimo_detalle_otro`). Con su prueba. Hoy `Campo` no tenía tipo para esto (los motivos no son un
          `Catalogo`). **Cuentas cambia en esta tarea** su `Select` propio (`SelectoresCuenta::motivoDesactivar`)
          por esta pieza.
    4. **Tipología (T1 a T6):** no se construye; su campo espera el pendiente «Tipologías de proyecto» (pregunta 6).
    5. **Convocatoria de origen:** la pone Licitaciones (CRM-04), no esta tarea (D12).
  - **Descartado:** `origen_lead_id` con su nombre del DBML (dos nombres para el mismo catálogo); campaña y ticket de
    origen ahora (CRM-07 y postventa no existen; cada uno agrega su enlace); un selector propio de Oportunidades.

- **D8 · Montos y moneda; sin líneas de producto.** *Aprobada (desarrollador, 2026-11-06).*
  - **Decisión:**
    1. `monto_estimado decimal(14,2) NOT NULL` con `CHECK (monto_estimado > 0)` y `moneda_id NOT NULL` (catálogo
       Monedas). En PHP, texto decimal con `bcmath`, nunca `float`. Editable en las etapas abiertas; al ganar se
       confirma el monto final (D3).
    2. **Totales del embudo en soles, convertidos al leer** (no se guarda la conversión) con `TipoCambioService::del`,
       al **tipo de compra**: el monto en otra moneda es dinero por cobrar, y lo que se cobra se valora al tipo de
       compra (al de venta se valora lo que se debe). Sin tipo de cambio del día, el núcleo da el último registrado
       dentro de su tolerancia (`tipo_cambio.dias_tolerancia`, 4 días, ADR 0012); si no hay ninguno, esas
       oportunidades se muestran aparte, sin inventar una tasa. La portada dice «incluye US$ X a T.C. Y».
    3. **`oportunidad_lineas` no se construye:** ninguna pantalla del diseño la muestra; los ítems van en la
       cotización (CRM-03). Si se pide una estimación por ítem, es una tabla nueva sin tocar lo demás.
    4. `fecha_cierre_estimada` `NOT NULL` (el DBML la pide; el «—» de la tabla del diseño se lee como boceto).
       «Cierran este mes» y el embudo por mes dependen de ella.
  - **Descartado:** `float`; guardar el monto convertido; tipo de venta (valora deudas, no cobros); construir las
    líneas «por si acaso».

- **D9 · Lo que cada etapa exige: requisitos propios y aportados por otros módulos.** *Aprobada (desarrollador,
  2026-11-06), con corrección de la D1.*
  - **Problema:** el v4 manda «no se avanza sin cumplir lo que la etapa exige ni sin programar la próxima acción».
    Parte de lo exigido es de otros módulos: «al menos una cotización emitida» y «una cotización aceptada» (CRM-03),
    «próxima acción con fecha» (CRM-05). El núcleo no deja que otro módulo agregue condiciones a una definición.
  - **Decisión:**
    1. **Calificación en la propia oportunidad:** `necesidad_confirmada` y `presupuesto_confirmado` (boolean
       `NOT NULL DEFAULT false`) y `decisor_contacto_id` (FK nula a un contacto, con índice; el servicio revisa que
       sea un contacto de la ficha de la cuenta). Editables en la ficha o al avanzar. `presupuesto_confirmado`
       significa «el cliente tiene presupuesto»; la otra lectura («existe el presupuesto con APU del ERP») sigue
       como pregunta 1 y, si gana, la revisaría un requisito que registre Presupuestos, sin tocar Oportunidades.
    2. **Registro `RequisitosDeEtapa`:** cada requisito es una clase con la etapa desde la que se exige, su texto
       («Decisor identificado») y `cumple(oportunidad): bool`. Oportunidades registra los suyos; CRM-03 y CRM-05
       registran los suyos cuando existan. Una sola `CondicionTransicion`, `CumpleRequisitosDeEtapa`, en las
       transiciones que avanzan (`calificar`, `enviar_propuesta`, `negociar`, `ganar`), revisa **todos los
       requisitos exigidos hasta la etapa destino** (se acumulan, como el panel del v4), y el mensaje lista los
       faltantes («el modal no permite avanzar; muestra el requisito faltante»). El panel «Avanzar» muestra antes de
       confirmar cuáles se cumplen y cuáles no.
    3. **En esta tarea:** calificar exige necesidad confirmada, decisor identificado y presupuesto confirmado (y por
       acumulación, todo avance posterior también). Enviar la propuesta suma «una cotización emitida» cuando llegue
       CRM-03. Negociar y ganar, sin requisitos propios (la cuenta en uso al ganar es `exigirUsable`, D6, aparte
       porque además bloquea la cuenta). Retroceder, perder y anular no revisan requisitos.
    4. **Nunca hacia atrás:** si una oportunidad deja de cumplir un requisito (o se activa uno nuevo), no retrocede
       sola; solo no puede avanzar.
    5. Los requisitos son código (cada uno revisa algo); cambiar cuál se exige dónde tras la validación es una tarea
       corta (pregunta 1), a diferencia de las transiciones y motivos, que son datos (pregunta 3).
  - **Descartado:** requisitos como texto configurable (no se pueden revisar); que CRM-03 edite la definición de
    Oportunidades (dos módulos dueños de una regla); esperar a CRM-03 y CRM-05 para construir el avance; revisar solo
    los requisitos de la transición (el v4 los acumula); «Necesidad confirmada» solo informativa o sin construir.

- **D10 · Permisos con `crm.oportunidades.*` y «propias o todas».** *Aprobada (desarrollador, 2026-11-06), con
  ajuste en anular.*
  - **Decisión:**
    1. Módulo: `crm.oportunidades.ver` (todas: el equipo comercial ve todo el embudo, como en Cuentas). «Mías» es un
       filtro, no un permiso.
    2. Acciones: `crm.oportunidades.crear`, `crm.oportunidades.editar` (todas) y `crm.oportunidades.editar-propias`
       (donde es el ejecutivo; tener los dos es tener el de todas), `crm.oportunidades.reasignar`. Competidores y
       datos de calificación se editan con editar o editar-propias.
    3. Transiciones: `crm.oportunidades.oportunidad.<codigo>` (el núcleo arma el permiso con la clave y el código, y
       el código va en minúsculas con guion bajo): `calificar`, `enviar_propuesta`, `negociar`, `retroceder`,
       `ganar`, `perder`, `anular` y `anular_ganada`.
    4. **Anular son dos transiciones**, cada una con su permiso y su familia de motivos (el núcleo da una familia por
       transición):
       - `anular`: desde `prospecto`, `calificada`, `propuesta_enviada`, `negociacion` y `perdida`, con motivo. Con
         la regla de «propias» (el ejecutivo anula las suyas; Gerencia Comercial, todas). Es la corrección común:
         oportunidad duplicada o cuenta equivocada (D1: la cuenta no se cambia; se anula y se crea otra).
       - `anular_ganada`: solo desde `ganada`, con motivo, con los efectos de la D1 y la D3 (contrato en borrador,
         proyecto en preparación, marca de cliente). Permiso `crm.oportunidades.oportunidad.anular_ganada`, **sin
         variante «propias»**: pensado solo para Gerencia Comercial.
    5. **«Propias» en las transiciones:** el permiso de una transición solo se revisa por ámbitos. Una
       `CondicionTransicion` `SobreLoPropio` usa `SeguridadService::puedeSobrePropio` (el mismo método que editar:
       un solo lugar) con `editar` / `editar-propias` y el ejecutivo de la oportunidad. Va en todas las transiciones
       de persona salvo `anular_ganada`. Así un ejecutivo mueve solo las suyas también por API, sin tocar el núcleo.
    6. **Roles de prueba** mientras la «Grilla de roles real» esté pendiente (el organigrama está en espera: no se
       crean roles reales): Ejecutivo comercial (ver, crear, editar-propias y las transiciones sobre las propias,
       incluida `anular`) y Gerencia Comercial (todo, más `reasignar` y `anular_ganada`).
    7. El traspaso automático de la D5 lo hace el sistema y no pasa por permisos de persona; el historial lo registra
       como hecho por el sistema, con referencia a la reasignación de la cuenta.
  - **Descartado:** «ver propias» (escondería oportunidades y alentaría duplicados); un tipo de ámbito «propios» en el
    núcleo (descartado en la D7 del 2.20, opción B); revisar «propias» solo en la pantalla; una sola `anular` para
    todo (anular una ganada desarma el contrato y no debe estar al alcance del ejecutivo).

- **D11 · Las pantallas, con los patrones del sistema.** *Aprobada (desarrollador, 2026-11-06), con detalles.*
  1. **Lista con portada** (patrón de Cuentas y de Personas y empresas): tarjetas Embudo total, Ponderado y Cierran
     este mes (cantidad y monto), en soles con las sin tipo de cambio aparte (D8); cuando aporte CRM-05, Sin próxima
     acción. Pestañas con su cantidad: Abiertas, Mías, Estancadas (D2), Ganadas, Perdidas, Anuladas, Todas. Avisos con
     «Ver cuáles»: estancadas y con ejecutivo inactivo. Tabla: número y nombre, cuenta, monto y moneda, etapa · %,
     cierre estimado, días en la etapa (ámbar si pasa el máximo), ejecutivo; filtros por etapa, ejecutivo, línea,
     origen y cierre estimado; acción en bloque «Reasignar».
  2. **Las anuladas no cuentan** en las tarjetas, el embudo ponderado ni el tablero: solo aparecen en su pestaña y en
     «Todas», marcadas.
  3. **Tablero:** columnas por etapa abierta con su cantidad y porcentaje, y tarjetas con cuenta, monto y días; el
     botón «Avanzar» de cada tarjeta abre el mismo panel deslizante. La columna **«Cierre»** muestra solo las ganadas
     y perdidas del período en curso (parámetro `crm.oportunidades.tablero_cierre_periodo`, de fábrica el mes en
     curso); las demás cerradas, en la lista. Arrastrar entre columnas queda para la mejora visual del final
     (Sebastián): pide un componente que hoy no existe.
  4. **Nueva oportunidad:** panel deslizante (desde la lista y desde la cuenta), no una página. Campos: nombre, cuenta
     (D6; fija si viene de la cuenta), línea de solución, monto y moneda, cierre estimado, origen (por defecto el de
     la cuenta, D7) y ejecutivo (por defecto el de la cuenta, D5); arriba, «Etapa inicial: Prospecto · 10 %».
  5. **Ficha:** cabecera con nombre, cuenta (enlace), monto, etapa · %, ponderado y días en la etapa, y la barra de
     estados con «Avanzar etapa» y «Cerrar»; «Retroceder», «Anular» y «Anular ganada» en «Más», cada una solo donde
     aplica y con su permiso (D10). Pestañas: **Resumen** (calificación: necesidad, presupuesto y decisor; datos;
     ponderado), **Competidores** (fichas de Personas y empresas con precio opcional y su moneda), **Historial de
     etapas** (`historialDe`, con los días en la etapa anterior) e **Historial** de la bitácora; las de otros módulos
     (Cotizaciones, Presupuesto, Actividades, Documentos) se suman con `RegistroPestanasOportunidad` (D12).
  6. **Avanzar y Cerrar en panel deslizante**, no como pestañas (el diseño las pone de pestaña; el patrón del sistema
     es el panel). Avanzar muestra «Esta etapa exige» con lo cumplido y lo faltante, acumulado hasta la etapa destino
     (D9); lo que falta de la propia oportunidad (las dos casillas y el decisor) se completa ahí mismo; y «Qué cambia
     al avanzar» (probabilidad y ponderado). Cerrar muestra las dos salidas lado a lado: ganar (fecha de cierre real
     y monto final, D3) y perder (motivo, «¿Lo ganó otra empresa?», competidor y precio o «precio desconocido», D4).
  7. **Bloqueo optimista en los paneles:** Avanzar y Cerrar (ganar y perder) capturan la versión **al abrir**, no al
     guardar (`Campo::deControl()` + `fillForm`, AGENTS.md). Prueba: dos personas abren el panel de la misma
     oportunidad; la primera guarda y la segunda recibe el aviso de conflicto.
  8. **Nada sobre datos que aún no existen:** la tarjeta «Sin próxima acción», el punto ámbar de la próxima acción y
     las pestañas de otros módulos aparecen solos cuando sus módulos aporten.
  9. **Ícono:** `heroicon-o-presentation-chart-line` (reservado para Oportunidades en la 2.20) para la caja, la
     pantalla y la pestaña en la cuenta. Hoy solo lo usa una pestaña de prueba en `PestanasFichaTest`, que no es
     una pantalla registrada; `IconosUnicosTest` lo confirma al construir.
  - **Descartado:** una página por pestaña del diseño (Avanzar, Cerrar); un tablero con arrastre construido a mano en
    esta tarea; `heroicon-o-funnel`.

- **D12 · Lo que Oportunidades deja para CRM-03, CRM-04, CRM-05 y las demás áreas, y lo que ofrece.** *Aprobada
  (desarrollador, 2026-11-06), con ajustes.*
  1. **`OportunidadesService`** de lectura para los demás: `buscar(texto, cuentaId?, incluirId?)` (solo abiertas, para
     el selector de la cotización) y `resumen(id)` (`ResumenOportunidad`: número, nombre, cuenta, ejecutivo, etapa,
     monto, moneda, si está abierta). Nunca el modelo. Es la puerta de la costura C13 (punto 8).
  2. **`RequisitosDeEtapa`** (D9): lo que se exige **al avanzar**.
  3. **Impedimentos de las salidas** (anular, anular_ganada, perder, retroceder): cada módulo suma los suyos, como los
     impedimentos de desactivar en Cuentas. El mensaje dice qué lo impide («Tiene la cotización COT-… aceptada;
     anúlala primero»). Para no duplicar lo que el núcleo ya tiene:
     - `anular` y `anular_ganada` van a `anulada` (cancelación): usan el mecanismo existente del núcleo,
       `RevisionAntesDeAnular` registrada en `RegistroDocumentos` con la clave `crm.oportunidades.oportunidad`, que
       `TransicionesService` revisa dentro del bloqueo y nombra con `DocumentoQueImpide`. No se escribe nada nuevo.
     - `perder` y `retroceder` no son cancelaciones: registro del módulo `ImpedimentosDeOportunidad` (por
       transición, devuelve `DocumentoQueImpide`) que revisa una `CondicionTransicion` `SinImpedimentos`, con el
       mismo mensaje.
     - Esta tarea deja los dos listos y los prueba con un impedimento falso cada uno. La regla de Cotizaciones (no
       anular con cotización aceptada vigente) se registra cuando llegue CRM-03.
  4. **Secciones de formulario** de Nueva oportunidad y del panel Avanzar: registro `SeccionesDeOportunidad` donde
     otro módulo suma su sección (campos, validación y guardado por su propio servicio, en la misma transacción que
     crea o avanza). Esta tarea lo deja listo y lo prueba con una sección falsa. CRM-05 lo usa para «Próxima acción».
  5. **`RegistroPestanasOportunidad`** (pestañas y botones de la ficha, como `RegistroPestanasCuenta`).
  6. **CRM-03 (Cotizaciones):** `cotizaciones.oportunidad_id` apunta a `oportunidades`; registra «una cotización
     emitida» (desde Propuesta enviada) y «una cotización aceptada» (para ganar) en `RequisitosDeEtapa`; su
     impedimento de anular (punto 3); la pestaña Cotizaciones; y completa en `OportunidadGanada` la cotización
     aceptada y sus condiciones, que hoy salen vacías (D3).
  7. **CRM-05 (Actividades):** «próxima acción con fecha» como requisito, su sección en Nueva y Avanzar (punto 4), la
     tarjeta «Sin próxima acción», el punto ámbar y la pestaña Actividades. Hasta entonces, la oportunidad no guarda
     una próxima acción propia. **CRM-04 (Licitaciones):** el enlace de la convocatoria va en su tabla hacia la
     oportunidad, su pestaña por el registro y el ganador desde el OECE (D4).
  8. **Lo que cruza áreas es costura del ADR 0020; nadie lee las tablas de Oportunidades directamente.** Propuesta de
     **C13 · La oportunidad para otras áreas** (dueño: área 2): Presupuestos (área 1) enlaza la oportunidad
     (`presupuestos.oportunidad_id`, FK con índice) eligiéndola con `buscar` y la lee con `resumen`; los tableros de
     Analítica (etapa 5) leen por métodos de resumen de `OportunidadesService` (embudo, ganadas y perdidas por
     período, motivos, precios de competidores) que se agregan cuando se construyan, nunca por consulta directa.
     Nota junto a la C3, que ya nombra el «presupuesto enlazado». La pestaña Presupuesto la suma el área 1 con C8.
  - **Descartado:** columnas vacías para lo futuro (`campania_id`, `ticket_id`, `cotizacion_aceptada_id`); un
    registro propio de impedimentos para anular (el núcleo ya lo tiene); leer `oportunidades` desde otra área.

- **D13 · Lo que Oportunidades aporta a Cuentas.** *Aprobada (desarrollador, 2026-11-06), con cambio en el punto 4.*
  1. **`AporteOportunidadesAbiertas`** registrado en `ActividadDeCuentas`: `leer()` da las abiertas por cuenta en una
     sola consulta por página; `cuentasConAbiertas()` da la subconsulta para filtrar (punto 4);
     `impedimentosParaDesactivar()` dice «N oportunidades abiertas». Con eso aparecen solos la columna de la lista, la
     cartera por ejecutivo con sus abiertas y el impedimento de la D9 de Cuentas.
  2. **Pestaña «Oportunidades»** en la ficha de la cuenta (`RegistroPestanasCuenta`, permiso `crm.oportunidades.ver`):
     las de esa cuenta, abiertas y cerradas con su motivo, con el detalle en panel deslizante; y el **botón «Nueva
     oportunidad»** en la cabecera (`BotonCuenta`, permiso `crm.oportunidades.crear`) con la cuenta elegida.
  3. **Enlace de la marca de cliente** (lo prometido en la D10 del 2.20): Cuentas declara una interfaz
     `OportunidadEnlazable` (`etiqueta(id)`, `url(id)`), con una versión de fábrica que muestra «Oportunidad #12» sin
     enlace; Oportunidades registra la real («OP-2026-0012 · Nombre», con enlace). Cuentas no importa nada de
     Oportunidades (mismo patrón que `ContratosDeOportunidad`).
  4. **El sentido se invierte: cada módulo nombra solo su tabla.** Los aportes de `ActividadDeCuentas` ya no reciben
     el `Builder<Cuenta>` para modificarlo (obligaba a nombrar el modelo de Cuentas, prohibido por `tests/Arch`, o a
     escribir `cuentas.id` a mano, que acopla sin que la prueba lo vea). En cambio:
     - `AporteOportunidadesAbiertas::cuentasConAbiertas(): Builder` es una subconsulta sobre `oportunidades` que
       selecciona `cuenta_id` de las abiertas; Cuentas la compone con `whereIn('cuentas.id', $subconsulta)`.
     - Para ordenar o filtrar por fecha (`AporteUltimoContacto`, CRM-05), el aporte devuelve una subconsulta con
       `cuenta_id` y su valor (`ultimo_contacto`), y Cuentas hace el cruce (`leftJoinSub`) para filtrar «sin
       contacto desde» y ordenar con las vacías al final.
     - Se ajustan en esta rama la interfaz de Cuentas (`AporteOportunidadesAbiertas`, `AporteUltimoContacto`,
       `ActividadDeCuentas`) y su prueba (`RegistrosCuentaTest`, con aportes falsos). Así el aporte de Oportunidades
       vive en una capa vigilada y `tests/Arch` sigue cuidándolo.
  - **Descartado:** que Cuentas lea `oportunidades`; una consulta por fila; un `Builder` genérico con `cuentas.id`
    escrito a mano; poner el aporte en una carpeta que la prueba no vigila.

## Preguntas para la empresa (regla 12)

Ninguna bloquea empezar: se construyen configurables o en código con el valor del diseño y se anotan en
`pendientes.md` al aprobar este plan (fila nueva «Embudo comercial», con Gerencia Comercial; es parte de la
validación del CRM con Cristhian y Miguel).

1. **Requisitos de cada etapa** (el diseño: «por validar en la Fase 0»; Propuesta → Negociación «por definir»). Y qué
   es «presupuesto confirmado»: ¿el cliente tiene presupuesto (tablero, Prospecto → Calificada) o existe el
   presupuesto con APU del ERP (`OpAvanzar` lo muestra con «PRE-2026-034»)?
   *Anotada en `pendientes.md` («Embudo comercial»), 2026-11-06: se construye con lo aprobado en la D1 y la D9
   (calificar exige necesidad, decisor y presupuesto; «presupuesto confirmado» = el cliente tiene presupuesto).*
2. **Probabilidad y días máximos por etapa** (diseño: 10, 25, 50, 75 %; máximo 14 días solo en Calificada).
   *Anotada en `pendientes.md` («Embudo comercial»), 2026-11-06: provisional con los porcentajes del diseño y 14 días
   solo en Calificada; las demás, sin máximo (D2).*
3. **¿Desde qué etapas se gana?** En una licitación pública no hay negociación: la buena pro llega con la propuesta
   enviada. ¿Se permite ganar desde «Propuesta enviada»? ¿Se puede retroceder de etapa, y con qué motivos?
   *Anotada en `pendientes.md` («Embudo comercial»), 2026-11-06: se construye provisional como en la D1 (ganar
   solo desde Negociación; retroceder con motivo) y la respuesta se aplica cambiando datos.*
4. **Motivos de pérdida iniciales:** el diseño del 13 oct (Plazo, Precio, Especificaciones, Presupuesto cancelado,
   No respondió, Decisión interna) o el DBML (precio, plazo, requisito, competencia, sin presupuesto).
   *Resuelta por el desarrollador (D1, 2026-11-06): los del diseño más «Proceso desierto», con «Otro». Son datos.*
5. Al reasignar una cuenta con «pasar también sus oportunidades abiertas», ¿pasan todas o solo las del ejecutivo
   anterior? (pregunta al desarrollador; D5.3 recomienda las del anterior).
   *Resuelta por el desarrollador (D5, 2026-11-06): solo las del ejecutivo anterior.*
6. **Tipología T1–T6** en la Nueva oportunidad: ya está en `pendientes.md` («Tipologías de proyecto»); el campo no se
   construye hasta que se defina.

## Pasos

Un paso = un commit (o pocos), con la bitácora al día. En cada paso: pint, phpstan y solo las pruebas de lo tocado.

- [x] 1. Fila del mapa, rama `crm-oportunidades` (sobre `crm-cuentas`), plan y decisiones propuestas (2026-11-06)
- [x] 2. Decisiones D1 a D13 aprobadas por el desarrollador; C3, C2 y C13 propuestas en el ADR 0020; «Embudo
      comercial» en `pendientes.md` (2026-11-06)
- [x] 3. **Núcleo:** `Campo::motivoDe(nombre, etiqueta, familia)` dibujado por `AccionFilament` (y por
      `AccionFilament::campo`), con su prueba; tipo de party «Competidor» en `CatalogosSeeder` (D4, D7.3b)
- [x] 4. **Cuentas:** selector de motivo de desactivar con `Campo::motivoDe`; `exigirUsable(cuentaId, exclusivo)`;
      `origenComercialId` en `ResumenCuenta`; aportes invertidos (`cuentasConAbiertas()`, último contacto como
      subconsulta) con `RegistrosCuentaTest`; `OportunidadEnlazable` con su versión de fábrica (D6, D7, D13)
- [x] 5. DBML ajustado (`probabilidad` fuera, `origen_comercial_id`, calificación, `en_etapa_desde`, `version`,
      `ganador_conocido`, competidores con precio, moneda y `precio_desconocido`, `oportunidad_lineas` no construida)
- [x] 6. Esqueleto con `/nuevo-modulo`: provider, caja (`heroicon-o-presentation-chart-line`), permisos (D10),
      parámetros de probabilidad (con su validador), días máximos y período de la columna «Cierre» (D2, D11)
- [x] 7. Migraciones con `/nueva-migracion`: `oportunidades` y `oportunidad_competidores` (D1–D9), FK de
      `cuenta_marcas_cliente.oportunidad_id`
- [x] 8. Documento con estados con `/nuevo-documento`: `OportunidadDefinicion` (D1, D10: `anular` y `anular_ganada`),
      numeración, familias de motivos, condiciones (`CumpleRequisitosDeEtapa`, `SobreLoPropio`, `SinImpedimentos`,
      cuenta usable), efectos (`EntraEnEtapa`, `GanadaMarcaCuentaCliente`, `GanadaAvisaAlErp` con
      `ContratosDeOportunidad` falso), con el recorrido completo y las cuatro pruebas de la marca (D3)
- [x] 9. `RequisitosDeEtapa`, `ImpedimentosDeOportunidad` y `SeccionesDeOportunidad`, cada uno probado con uno falso
      (D9, D12)
- [x] 10. Acciones con `/nueva-accion`: crear, editar y editar-propias, reasignar, avanzar, ganar, perder, retroceder,
      anular, anular_ganada, competidores (con el tipo «Competidor»)
- [x] 11. Oyente de `EjecutivoDeCuentaCambiado` en la cola, idempotente, con la alerta `traspaso_fallido` (D5)
- [x] 12. `OportunidadesService` (`buscar`, `resumen`, embudo en soles al T.C. de compra) y los aportes a Cuentas
      (D8, D12, D13)
- [x] 13. Pruebas de concurrencia con procesos reales: crear y desactivar a la vez; dos ganadas a la vez (D6)
- [x] 14. Pantallas (D11): lista con portada, tablero, Nueva oportunidad, ficha con sus pestañas, paneles Avanzar y
      Cerrar (versión al abrir), pestaña y botón en la cuenta
- [x] 15. Revisor de arquitectura
- [x] 16. Glosario (oportunidad, embudo, etapa, ponderado), `composer check` en verde (una corrida, al final), rama
      subida; el PR lo abre Sebastián

## Preparado para lo diferido

- **Secciones de formulario (D12.4):** `SeccionesDeOportunidad` en Nueva oportunidad y en Avanzar; CRM-05 suma
  «Próxima acción» (campos, validación y guardado por su servicio, en la misma transacción). Probado con una falsa.
- **Impedimentos de las salidas (D12.3):** `RevisionAntesDeAnular` del núcleo para anular y anular_ganada;
  `ImpedimentosDeOportunidad` para perder y retroceder. CRM-03 registra «cotización aceptada vigente».
- **Para la tarea 2.0 (selectores de catálogo):** la pieza común de motivos (`Campo::motivoDe`, D7.3b) ya existe y
  debe recibir el «+», «Ver la lista» y «Lo más escrito en Otro» igual que los catálogos.
- **Orden de fusión:** esta rama está apilada sobre `crm-cuentas` (2.20) y usa `AccionFilament::campo`, que solo
  está allí: **la 2.21 no puede fusionarse antes que la 2.20.**
- **Ganador desde el OECE (D4):** en licitaciones públicas el ganador y su precio son públicos en el SEACE. Cuando
  exista la integración (CRM-04), al perder una oportunidad con convocatoria se proponen solos; la persona confirma.
  `perder` ya guarda `ganador_conocido`, el competidor y el precio en las mismas columnas que llenará la integración.
- **Embudo de meses pasados (D2):** hoy el ponderado se calcula al leer con los porcentajes vigentes. Si Gerencia
  quiere comparar el embudo de un mes con otro, se guarda una foto mensual (oportunidades abiertas con su etapa,
  monto y probabilidad del día) con una tarea programada; `OportunidadesService::resumen` es el punto donde se lee.
- **`OportunidadGanada` (C3):** sale ya en esta tarea con la cotización, sus condiciones, el presupuesto enlazado y la
  convocatoria vacíos (D3); CRM-03 y CRM-04 los completan sin cambiar su forma.
- **Contratos (C3 ampliada, propuesta):** `ContratosDeOportunidad::anularBorrador` con una versión falsa que responde
  «no hay contrato»; Contratos (área 3) registra la real, y `ProyectosService::anularEnPreparacion` la construye el
  área 4. La llamada ya está hecha desde `anular_ganada`.
- **Enlace de la marca de cliente:** `OportunidadEnlazable` en Cuentas, con versión de fábrica sin enlace (D13).
- **Requisitos de otros módulos:** `RequisitosDeEtapa` (D9) recibe «cotización emitida», «cotización aceptada» y
  «próxima acción con fecha» sin tocar la definición.
- **Pestañas y botones de la ficha:** `RegistroPestanasOportunidad` (Cotizaciones, Presupuesto, Actividades,
  Documentos, Licitación de origen).
- **Próxima acción:** CRM-05 suma su sección (D12.4); la portada sabe si alguien aporta «sin próxima acción» y no
  muestra la tarjeta mientras no.
- **`SobreLoPropio`:** vive en Oportunidades; la 2.0b lo moverá al núcleo.
- **Arrastrar en el tablero:** la mejora visual del final; el panel de avanzar ya es la acción que el arrastre abrirá.
- **Tipología:** el campo entra cuando se resuelva «Tipologías de proyecto»; la tabla no cambia más que una columna.
- **Líneas de producto estimadas:** si se piden, `oportunidad_lineas` es una tabla nueva; nada más cambia.
- **Tablero de Gerencia Comercial** (conversión por etapa, motivos de pérdida): sale de `historial_estados` y de la
  familia de motivos, que esta tarea ya deja completos.

## Criterio de aceptación

- **Recorrido:** crear en Prospecto con 10 %; calificar sin necesidad, decisor o presupuesto falla y nombra lo
  faltante; los requisitos se acumulan hasta la etapa destino; saltar etapas no existe; retroceder pide motivo y
  reinicia `en_etapa_desde` (prueba de coherencia con el historial).
- **Ganar:** marca la cuenta como cliente con la fecha de cierre real (nunca futura; puede ser anterior a la
  creación) y emite `OportunidadGanada` con los campos vacíos de la D3; exige la cuenta usable.
- **Marca con varias oportunidades (D3):** ganada en marzo y perdida en noviembre → cliente desde marzo; ganadas en
  marzo y agosto, se anula la de agosto → desde marzo; se anulan las dos sin marca manual → prospecto; ganada en
  agosto y luego una tardía de marzo → desde marzo.
- **Anular:** `anular` desde abiertas y perdida, con «propias»; `anular_ganada` solo para Gerencia Comercial, llama a
  `ContratosDeOportunidad` (falso) en la transacción y revierte la marca; un impedimento falso bloquea con su mensaje.
- **Perder:** sin motivo falla; «Otro» sin detalle falla; «Sí» sin competidor falla; «Sí» sin precio ni «precio
  desconocido» falla (CHECK); «No» y «No se sabe» no piden competidor; la ficha recibe el tipo «Competidor» sin
  fallar si ya lo tiene.
- **Ejecutivo:** el de una cerrada no cambia; el oyente pasa solo las abiertas del ejecutivo anterior, repetirlo no
  cambia nada, y si agota los reintentos sale la alerta `traspaso_fallido`.
- **«Propias»:** un ejecutivo con `editar-propias` no edita ni transiciona una ajena, tampoco por la acción sin
  pantalla.
- **Probabilidad:** el validador rechaza 0, 100 y un porcentaje menor que el de la etapa anterior; el ponderado
  usa bcmath; las anuladas no cuentan.
- **Montos:** el embudo en soles al T.C. de compra; sin T.C. dentro de la tolerancia, esas van aparte.
- **Concurrencia (procesos reales):** crear y desactivar a la vez nunca deja una abierta en una cuenta inactiva; dos
  ganadas a la vez de la misma cuenta terminan las dos bien.
- **Cuentas:** la columna de abiertas, la cartera y el impedimento para desactivar aparecen solos con los aportes
  invertidos; `buscar` con `incluirId` en el selector; la marca enlaza a su oportunidad.
- **Pantallas:** portada, tablero (columna «Cierre» del período), ficha, paneles con la versión capturada al abrir
  (dos personas: la segunda recibe el conflicto) y pestaña en la cuenta; `tests/Arch` incluye el módulo.
- **Escenarios del v4:** «sin cotización emitida no se avanza a propuesta enviada» se prueba aquí con un requisito
  falso y queda completo en CRM-03; el de la frontera (CT-2026-018, 24,715.10) es de CRM-03 y Contratos.

## Bitácora

- 2026-11-06 — Rama `crm-oportunidades` apilada sobre `crm-cuentas` (c5bd64b; Cuentas aún no está en `main`). Plan
  creado con las decisiones D1 a D13 **propuestas**, sin aprobar, y seis preguntas. Contradicciones encontradas entre
  el v4, el DBML y lo que construyó Cuentas: probabilidad editable en el DBML contra «nadie la edita» (D2);
  `origen_lead_id` nulo contra `origen_comercial_id` obligatorio de Cuentas (D7); motivos de pérdida del DBML contra
  los del diseño (pregunta 4); «presupuesto confirmado» con dos lecturas (pregunta 1); `oportunidad_lineas` y el
  competidor en texto libre del DBML sin pantalla en el diseño (D4, D8); precio del competidor sin moneda en el DBML;
  `OportunidadGanada` (C3) pide la cotización aceptada, que no existe hasta CRM-03 (D3); el evento de Cuentas es
  posterior al commit, así que pasar las abiertas no es atómico con la reasignación «todo o nada» (D5.4); la interfaz
  `AporteOportunidadesAbiertas` recibe un `Builder<Cuenta>` que choca con la regla de `tests/Arch` (D13.4); el
  enlace de la marca al historial no tiene mecanismo (D13.3).
- **2026-11-06 · D1 aprobada con ajustes.** Exigencias por etapa como condiciones de transición (decisor y
  presupuesto en esta tarea; cotizaciones y próxima acción las registran CRM-03 y CRM-05); probabilidad por estado,
  configurable; perder con «¿Lo ganó otra empresa?» y siete motivos de partida; anular una ganada llama a Contratos
  dentro de la transacción, con orden de bloqueo oportunidad → contrato → proyecto. Propuesta de ampliación de la C3
  y la C2 escrita en el ADR 0020 (sin construir, espera aprobación). Fila «Embudo comercial» en `pendientes.md`.
  D3 y D4 marcadas para ajustarse a la D1. Sigue: D2.
- **2026-11-06 · D2 aprobada con ajustes.** 14 días solo en Calificada; las demás sin máximo. `en_etapa_desde` es
  copia del historial (se reinicia al retroceder) con prueba de coherencia. Validador de porcentajes (1–99, sin bajar
  entre etapas). Foto mensual del embudo en «Preparado para lo diferido». Sigue: D3.
- **2026-11-06 · D3 aprobada con ajustes.** `OportunidadGanada` sale ya, con cotización, condiciones, presupuesto y
  convocatoria vacíos; fecha de cierre solo «nunca futura» (confirmación si pasa de 90 días); cuatro pruebas de la
  marca con varias oportunidades. ADR 0020: C3 con los campos vacíos y la cuenta al final del orden de bloqueo.
  Sigue: D4.
- **2026-11-06 · D4 aprobada con ajustes.** «¿Lo ganó otra empresa?» con Sí / No / No se sabe, guardado en la
  oportunidad; precio obligatorio salvo «precio desconocido» (CHECK); tipo «Competidor» nuevo en Núcleo (commit
  propio), asignado sin fallar si ya lo tiene. Ganador desde el OECE en «Preparado para lo diferido». Sigue: D5.
- **2026-11-06 · D5 aprobada con un ajuste.** Solo pasan las abiertas del ejecutivo anterior; sin «y M
  oportunidades» en el aviso de Cuentas. Si el traspaso agota sus reintentos, alerta del núcleo
  `crm.oportunidades.traspaso_fallido` a Gerencia General, configurable. Sigue: D6.
- **2026-11-06 · D6 aprobada con ajustes.** `CuentasService::exigirUsable(cuentaId, exclusivo)`: compartido al crear,
  exclusivo al ganar. Verificado que `desactivar` bloquea antes de consultar impedimentos. Dos pruebas de
  concurrencia con procesos reales. Sigue: D7.
- **2026-11-06 · D7 aprobada con ajustes.** Origen por defecto el de la cuenta (`ResumenCuenta` suma su origen);
  selectores con la pieza común. Encontrado: `Campo` no tiene tipo para un motivo de una familia y Cuentas usa un
  `Select` propio para su motivo de desactivar; queda por decidir cómo van los motivos (7.3b). `AccionFilament::campo`
  ya está en esta rama (apilada sobre la 2.20): las pantallas no esperan la fusión. La 2.0 la hará otra sesión.
- **2026-11-06 · D7.3b y D8 aprobadas.** Motivos con una pieza común nueva del núcleo (`Campo::motivoDe`, commit
  «Núcleo:»), que Cuentas también adopta; nota para la 2.0. D8 con T.C. de compra. Pantallas en su orden normal, sin
  esperar la fusión de la 2.20; anotado que la 2.21 no se fusiona antes que la 2.20. Sigue: D9.
- **2026-11-06 · D9 aprobada, con corrección de la D1.** Calificar exige necesidad, decisor y presupuesto (diseño del
  13 oct y panel «Avanzar» del v4); los requisitos se acumulan hasta la etapa destino y nunca se revisan hacia atrás;
  `necesidad_confirmada` se construye. D1 corregida citando las dos fuentes. Sigue: D10.
- **2026-11-06 · D10 aprobada con ajuste.** Anular en dos transiciones: `anular` (con «propias») y `anular_ganada`
  (solo Gerencia Comercial). El permiso va con guion bajo (`…anular_ganada`) porque el núcleo lo arma con el código
  de la transición. «Propias» con `puedeSobrePropio`. Roles de prueba. D1 ajustada. Sigue: D11.
- **2026-11-06 · D11 aprobada con detalles.** Versión al abrir en Avanzar y Cerrar (con prueba de conflicto);
  anuladas fuera de tarjetas, ponderado y tablero; columna «Cierre» del período en curso (parámetro); ícono
  `presentation-chart-line`. Quedan D12 y D13.
- **2026-11-06 · D12 aprobada con ajustes.** Impedimentos de las salidas (anular y anular_ganada con el
  `RevisionAntesDeAnular` que ya tiene el núcleo; perder y retroceder con un registro del módulo); secciones de
  formulario para CRM-05; costura C13 propuesta en el ADR 0020 (Presupuestos y Analítica por `OportunidadesService`).
  Sigue: D13.
- **2026-11-06 · D13 aprobada con cambio; decisiones cerradas.** Los aportes de `ActividadDeCuentas` devuelven una
  subconsulta sobre su propia tabla y Cuentas la compone. `SobreLoPropio` en Oportunidades (la 2.0b lo moverá).
  Orden de fusión: 2.20, 2.21, 2.0b. Plan revisado contra las decisiones: cabecera, objetivo, pasos (ahora 16),
  «Preparado para lo diferido» y criterio de aceptación reescritos. Pregunta 1 anotada en `pendientes.md`.
- **2026-11-06 · Paso 3 (Núcleo).** `TipoCampo::Motivo` y `Campo::motivoDe(nombre, etiqueta, familia, detalle)`;
  `MotivosService::opciones(familia, actual)` (activos, «Otro» al final, el actual desactivado marcado) y `esOtro`;
  `AccionFilament` lo dibuja (selector `live`) y al campo de detalle lo hace visible y obligatorio solo con «Otro»,
  con el mínimo `motivos.minimo_detalle_otro`, también por `AccionFilament::campo`. Las transiciones del núcleo
  (`AccionTransicion`) piden el motivo con esta pieza. Tipo de party «Competidor» en `CatalogosSeeder`. Prueba:
  `CampoMotivoTest`.
- **2026-11-06 · Paso 4 (Cuentas).** Desactivar pide el motivo con `Campo::motivoDe` (sale el `Select` propio de
  `SelectoresCuenta` y el control a mano del detalle). `CuentasService::exigirUsable(cuentaId, exclusivo)` (`FOR
  SHARE` / `FOR UPDATE`, falla fuera de una transacción) con `CuentaRechazada::noUsable`. `ResumenCuenta` suma
  `origenComercialId`. Aportes invertidos: `AporteOportunidadesAbiertas::cuentasConAbiertas()` y
  `AporteUltimoContacto::ultimosContactos()` devuelven una subconsulta sobre su propia tabla y `ActividadDeCuentas`
  hace el cruce (`whereIn`, `whereNotIn`, `leftJoinSub` con las vacías al final). `OportunidadEnlazable` con la
  versión de fábrica «Oportunidad #12», usada en la bitácora de la marca. **Desviación:** el historial del núcleo
  (`TablaHistorial`) no dibuja enlaces, así que la marca muestra el nombre legible de la oportunidad pero no un enlace
  clicable; el enlace pide un cambio del núcleo (anotado para la 2.0b o la mejora visual).
- **2026-11-06 · Paso 5 (DBML).** `oportunidades` sin `probabilidad`, `motivo_perdida_id`, `campania_id` ni
  `ticket_id` (el motivo queda en `historial_estados`; los enlaces los agrega cada módulo); con `origen_comercial_id`,
  `linea_solucion_id` obligatorios, calificación, `decisor_contacto_id`, `en_etapa_desde`, `ganador_conocido` y
  `version`. `oportunidad_competidores` con `party_id` obligatorio, precio con moneda, `precio_desconocido` y
  `es_ganador`. `oportunidad_lineas` marcada como no construida.
- **2026-11-06 · Paso 6 (esqueleto).** `Crm/Oportunidades` con su provider en `bootstrap/providers.php`, la caja
  «Oportunidades» (`heroicon-o-presentation-chart-line`, área CRM) y el permiso `crm.oportunidades.ver`; los demás
  permisos llegan con las acciones (paso 10) y la definición del documento (paso 8). `EtapaAbierta` con su orden y
  sus parámetros. **Ajustes de forma:** los parámetros se llaman `crm.probabilidad_<etapa>`,
  `crm.dias_maximos_<etapa>` y `crm.tablero_cierre_periodo` (el núcleo no admite puntos en la clave); como el núcleo
  no tiene parámetros vacíos, «sin máximo» es **0**. Un validador de probabilidad por etapa (el validador no sabe qué
  parámetro valida), con la regla común en `ValidadorProbabilidad`. Prueba: `OportunidadesModuloTest`.
- **2026-11-06 · Paso 7 (migraciones).** `oportunidades` y `oportunidad_competidores` con sus CHECK (monto > 0,
  `ganador_conocido`, precio y moneda juntos, «precio desconocido» solo del ganador y sin precio, el ganador con
  precio o «desconocido») y el único parcial de un ganador por oportunidad. Modelos `Oportunidad` (documento con
  estados, inmutables `numero` y `cuenta_id`, bitácora legible) y `OportunidadCompetidor`; `GanadorConocido`;
  morph map. **Cambio de orden:** la llave foránea de `cuenta_marcas_cliente.oportunidad_id` pasa al paso 8, en su
  propia migración: con ella, las pruebas de Cuentas que marcan clientes con ids inventados necesitan oportunidades
  reales, y eso pide la definición del documento. Las pruebas de los CHECK van también en el paso 8, por la misma
  razón (una fila necesita su estado).
- **2026-11-06 · Paso 8 (documento).** `DefinicionOportunidad` (`crm.oportunidades.oportunidad`, serie `OP`): siete
  estados; `calificar`, `enviar_propuesta`, `negociar`, `ganar` y `perder` como acciones propias; `retroceder` (una
  fila por origen), `anular` (abiertas y perdida, con «propias») y `anular_ganada` (solo Gerencia). Condiciones
  `SobreLoPropio`, `CumpleRequisitosDeEtapa`, `SinImpedimentos` y `CuentaUsable` (`exigirUsable` exclusivo). Efectos
  `EntraEnEtapa`, `GanadaMarcaCuentaCliente` y `GanadaAvisaAlErp` (emite `OportunidadGanada`; al revertir llama a
  `ContratosDeOportunidad`, con `ContratosSinModulo` de fábrica, y emite `OportunidadGanadaAnulada`). Registros
  `RequisitosDeEtapa` (con los tres requisitos de calificar) e `ImpedimentosDeOportunidad`. Familias de motivos:
  perder (siete de partida), retroceder (solo «Otro», pendiente), anular («Oportunidad duplicada», «Cuenta
  equivocada») y anular_ganada («Ganada por equivocación»). Migración propia con la FK de
  `cuenta_marcas_cliente.oportunidad_id`; las pruebas de Cuentas usan oportunidades reales
  (`OportunidadesDePrueba`). **Ajuste:** `en_etapa_desde` copia la fecha de la fila del historial recién escrita (en
  Lima), no el reloj de la aplicación: el historial la pone con el reloj de la base, y así son siempre iguales. Los
  `SeccionesDeOportunidad` quedan para el paso 9. Prueba: `OportunidadDocumentoTest` (18).
- **2026-11-06 · Paso 9 (secciones).** `SeccionDeOportunidad` y el registro `SeccionesDeOportunidad` (Nueva
  oportunidad y Avanzar): cada sección trae campos del núcleo, que llegan con su clave delante
  (`proxima_accion__fecha`), y los guarda con su servicio dentro de la transacción. **Núcleo:** `Campo::renombrado()`
  (el campo es inmutable). `RequisitosDeEtapa` e `ImpedimentosDeOportunidad` ya estaban (paso 8). Prueba con una
  sección falsa: `SeccionesDeOportunidadTest`. Las acciones de crear y avanzar las suman en el paso 10.
  **Ajuste de una prueba del núcleo:** `PantallasEstadosTest` filtraba la lista de motivos por página; con las
  familias nuevas, los de prueba caían en otra página, y ahora filtra por su lista.
- **2026-11-06 · Paso 10 (acciones).** `OportunidadesService` (crear, editar, reasignar, avanzar, ganar, perder,
  guardar y quitar competidor, resumen) y trece acciones: crear, editar y editar-propias, reasignar, calificar,
  enviar_propuesta y negociar (`AvanzarOportunidad`), ganar, perder, y guardar y quitar competidor con su variante
  de propias. Avanzar, ganar y perder llevan la clave y el permiso de su transición (son su acción propia). Cada
  paso guarda sus datos y hace la transición en una sola transacción. `ResumenOportunidad`, `DatosOportunidad` y
  `OportunidadRechazada`. La confirmación de ganar con más de 90 días es de la pantalla (paso 14). Prueba:
  `AccionesOportunidadTest` (13).
- **2026-11-06 · Paso 11 (traspaso).** `OportunidadesService::traspasarDeEjecutivo` (solo las abiertas del ejecutivo
  anterior, con cada fila bloqueada, repetible) y el oyente en cola `TraspasaOportunidadesAlReasignarCuenta` (5
  intentos), con la alerta `crm.oportunidades.traspaso-fallido` a Gerencia General desde `failed()`. **Lección:** un
  oyente en cola recibe solo el evento en `handle()` (`CallQueuedListener` no inyecta más parámetros), así que la
  regla de AGENTS.md «la dependencia de un solo método va como parámetro de `handle()`» no aplica a oyentes en cola:
  ahí se pide con `app()`. Propuesta para AGENTS.md, al cerrar la tarea. Prueba: `TraspasoOportunidadesTest`.
- **2026-11-06 · Paso 12 (lectura y aportes).** `OportunidadesService::buscar` (abiertas por número o nombre, sin
  tildes, de una cuenta, con `incluirId`), `resumen` y `embudo` (`EmbudoEnSoles`: total y ponderado en moneda local al
  T.C. de compra, lo que no tiene T.C. aparte; suma en la base por moneda y etapa). Scope `abiertas`. Aportes a
  Cuentas: `AporteOportunidadesACuentas` (abiertas por cuenta en lote, subconsulta para filtrar, «N oportunidades
  abiertas» para desactivar) y `EtiquetaDeOportunidad` («OP-2026-0012 · Nombre» en el historial). La pestaña y el
  botón en la cuenta son pantallas: paso 14. Prueba: `LecturaOportunidadesTest`; todo el CRM y `tests/Arch` en verde
  (202).
- **2026-11-06 · Paso 13 (concurrencia).** Con procesos reales (`tests/Concurrencia`): desactivar la cuenta mientras
  tres procesos crean oportunidades en ella (nunca queda una abierta en una cuenta inactiva) y dos ganadas a la vez de
  la misma cuenta (las dos terminan, sin trabarse, con sus dos marcas). Modos nuevos en `proceso.php`:
  `crear-oportunidad`, `desactivar-cuenta` y `ganar`. Como con los proyectos, el correlativo `OP` sigue desde las
  oportunidades que dejaron otras pruebas en esa base. Las 13 de concurrencia en verde.
- **2026-11-06 · Paso 14 (pantallas).** `OportunidadResource` en `/oportunidades` (ícono de la caja), registrado como
  Cuentas. **Lista** con portada (`PortadaOportunidades`): tarjetas Embudo total, Ponderado y Cierran este mes en moneda
  local con `embudo()` (nota «Incluye US$ X a T.C. Y» y lo sin T.C. aparte); pestañas Abiertas, Mías, Estancadas,
  Ganadas, Perdidas, Anuladas y Todas con su cantidad (las anuladas solo en la suya y en Todas, con su etapa marcada);
  avisos estancadas y ejecutivo inactivo con «Ver cuáles»; tabla con días en la etapa en ámbar y «Estancada»; filtros
  por etapa, ejecutivo, línea, origen y cierre; «Reasignar» en bloque. **Tablero** (`/oportunidades/tablero`): una
  columna por etapa abierta con cantidad y %, tarjetas con cuenta, monto y días, «Avanzar» que abre el mismo panel, y
  «Cierre» con las ganadas y perdidas del período del parámetro. **Paneles** deslizantes en `PanelesOportunidad`
  (Nueva, Avanzar, Ganada, Perdida), todos por `AccionFilament`, con la versión tomada al abrir. **Ficha** con
  cabecera, Editar, Avanzar etapa, Cerrar, botones de otros módulos y «Más» (Retroceder, Anular, Anular ganada, cada
  una solo donde aplica); pestañas Resumen, Competidores, Historial de etapas (con los días en la anterior), las de
  `RegistroPestanasOportunidad` (singleton, con botones) e Historial. **En la cuenta:** pestaña «Oportunidades» y botón
  «Nueva oportunidad» con la cuenta fija. Prueba: `OportunidadesPantallasTest` (16).
  **Corrección del dominio:** `Oportunidad::camposDerivados()` declara `en_etapa_desde`. Sin eso, `EntraEnEtapa`
  no podía guardar al entrar en un estado final (ganada, perdida, anulada) si la entrada caía otro día que la anterior:
  la pantalla lo destapó (las pruebas viajan al 6 y la base fecha el historial con su reloj).
  **Desviaciones de la D11:** (1) Cerrar no muestra las dos salidas lado a lado: es un menú «Cerrar» con «Ganada» y
  «Perdida», cada una con su panel (`AccionFilament` dibuja una acción por formulario). (2) La barra de estados no se
  dibuja: el núcleo no tiene componente de pantalla para ella (`BarraDeEstados` es solo datos); la cabecera dice
  «etapa · %». El componente común queda para el núcleo (2.0b o mejora visual). (3) El tablero es una página aparte con
  botón «Tablero» / «Lista»; en Negociación no hay «Avanzar» (sigue Cerrar). (4) «Mías» son las que atiendo, sin las
  anuladas (también ganadas y perdidas). (5) Nueva oportunidad no pide descripción, calificación ni decisor (se
  completan al editar o al avanzar). (6) La confirmación de una fecha de cierre con más de 90 días es una casilla que
  aparece arriba del formulario (los campos de apoyo de `AccionFilament` van primero). (7) «Esta etapa exige» se
  calcula al abrir el panel; no se actualiza al marcar las casillas. (8) El buscador de la lista busca por número y
  nombre, no por cuenta (sería consultar la tabla de Cuentas). (9) Se agregó «Editar» en la ficha (la acción ya
  existía y no tenía pantalla). Íconos nuevos de pestañas: Resumen `information-circle`, Competidores `trophy`,
  Historial de etapas `arrow-trending-up`.
- **2026-11-07 · Paso 15 (revisor de arquitectura).** Corregido:
  - `CuentaUsable` solo lee (`resumen()->usable`). El núcleo evalúa las condiciones también para listar las salidas,
    fuera de una transacción. El bloqueo exclusivo de la cuenta pasó a `OportunidadesService::ganar`, después de la
    oportunidad. Prueba con `salidasDisponibles` en Negociación.
  - El traspaso pasa las abiertas al ejecutivo **vigente** de la cuenta, no al del evento. Así, dos reasignaciones
    procesadas fuera de orden terminan bien. Tiene su prueba.
  - `reasignar` bloquea por id ascendente, como el traspaso, para que dos no se traben.
  - El tipo «Competidor» ya no se reconoce comparando el texto de la excepción: se revisa la ficha activa antes y
    `tieneRol` después del rechazo.
  - `quitarCompetidor` con un id que no existe da un mensaje claro.
  - El tipo del documento sale de la definición, no escrito a mano.
  - Los nombres de las cuentas se leen en lote (`CuentasService::resumenesDe`) y se recuerdan solo durante la
    petición.
  - El dinero en pantalla se formatea con bcmath, sin `float`.
  - La familia de motivos de perder sale de `familiaDeMotivos`.

  Queda como riesgo aceptado, anotado: `ganador_conocido` y `fecha_cierre_real` obligatorios según el estado los
  exige el servicio, no la base (haría falta un disparador).

  La migración de Oportunidades que agrega la llave foránea a `cuenta_marcas_cliente` es la excepción acordada en el
  plan 2.20, no un precedente.

  Escenarios del v4 que completan otras tareas: «sin cotización emitida no se avanza a Propuesta enviada» (CRM-03,
  que registra su requisito real en `RequisitosDeEtapa`) y «Frontera: ganada → CT-2026-018» (Contratos, con
  `ContratosDeOportunidad` real).

  **Por decidir (desarrollador):** aprobar C2, C3 ampliada y C13 en el ADR 0020; el caso del contrato creado
  después de anular la ganada (C3); y si `Pestanas/` y `Actividad/` de Cuentas son API pública (ARCHITECTURE.md),
  junto con la constante del permiso de atender.
- **2026-11-07 · Decisiones del desarrollador sobre la revisión.**
  - **C2, C3 ampliada y C13 aprobadas** en el ADR 0020, tal como estaban escritas.
  - **C3 suma `OportunidadesService::exigirGanada`:** el oyente de Contratos lo llama antes de crear el contrato,
    bloquea la oportunidad (primera en el orden de bloqueo) y dice si sigue ganada. Prueba con los dos órdenes:
    anular antes (no se crea) y crear antes (`anularBorrador` lo anula).
  - **Cambio de regla compartida (ARCHITECTURE.md, regla 2):** un módulo expone a los demás sus `Services/`, sus
    `Events/` y los registros de extensión que declare públicos (Cuentas: `Actividad/` y `Pestanas/`; Oportunidades:
    `Requisitos/`, `Impedimentos/`, `Secciones/` y `Pestanas/`). `tests/Arch` lo vigila con `HARNESS_PUBLICAS`:
    falla si otro módulo usa algo fuera de esa lista; se comprobó con una violación de prueba.
  - `PERMISO_ATENDER` pasa de `CuentasServiceProvider` a `CuentasService`.
  - CRM y `tests/Arch` en verde (230).
- **2026-11-07 · Paso 16 (cierre).** Glosario: sección «Comercial (CRM)» (cuenta, oportunidad, embudo, etapa,
  probabilidad, ponderado, estancada, competidor). AGENTS.md: la excepción del oyente en cola (`app()` dentro de
  `handle()`), lección del paso 11.
- **2026-11-07 · Main traído a la rama** (2.6, 2.8 y 2.60): `MotivoInicial` (plan 2.6) reemplaza los «motivos de
  partida» de la 2.20; Cuentas y Oportunidades declaran sus motivos iniciales con él (mismos códigos) y «Otro» nace
  primero. Diferencia con la 2.20: el sincronizador crea un motivo inicial que falte aunque la familia ya exista (sin
  pisar lo editado en Configuración). Al fusionar la 2.20 aparecerá el mismo choque; esta rama trae la resolución.
- **2026-11-08 · Cierre.** `composer check` en verde (pint, phpstan, migraciones y 2759 pruebas). La primera corrida
  falló en `CatalogosIdentidadTest` (la lista exacta de tipos de persona y empresa no tenía «Competidor»); se corrigió,
  se corrió sola y la corrida completa se repitió en verde. Plan a completados. **Avisos para el PR:** cambio de
  regla compartida en ARCHITECTURE.md (regla 2, lo que expone cada módulo, vigilado en `tests/Arch`); lección nueva en
  AGENTS.md (oyente en cola); costuras C2, C3 y C13 aprobadas en el ADR 0020. Se fusiona después de la 2.20 y antes de
  la 2.0b. Pendiente de mirar a la vista (modo claro y oscuro): tarjetas, tablero y panel Avanzar.

