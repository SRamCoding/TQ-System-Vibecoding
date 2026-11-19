# Plan: portada con fotos, tablero de botonera y ficha con recorrido de Oportunidades (2.21d)

- **Estado:** terminado (falta la revisión en pantalla del desarrollador)
- **Responsable:** Sebastián mientras dure el modo actual (área 2, Comercial, en el ADR 0020)
- **Entrega:** `entrega/crm-oportunidades-vitrina`
- **Módulo(s):** `Crm/Oportunidades`; lee `Crm/Cotizaciones` (C3) y `Erp/Presupuestos` (C8) por sus servicios
- **Fase:** —
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

Oportunidades se ve con el lenguaje de la portada de Proyectos (2.61): tarjetas con la foto o el dibujo de su línea de
solución, un tablero que muestra en cada etapa su gema, una botonera con la cantidad y hasta 5 códigos elegidos, que
se avanzan arrastrando o desde la tabla de la etapa (de una en una) con una compuerta que se rellena ahí mismo y una
animación en la que la gema se convierte en la siguiente; y una ficha con cabecera, recorrido de documentos y la
negociación por versiones. Prototipo aprobado:
`docs/diseno/fuentes/prototipos/oportunidades-portada-ficha.html`.

## Contexto que leí

- Plan 2.21c (`completados/2026-11-11-crm-tablero-gemas.md`): el tablero de gemas que existe hoy (cuatro gemas en 2×2
  por etapa, un círculo con el total que abre la tabla, la «rueda de tallado» que valida al arrastrar, D5: una sola
  lógica para arrastre y botones, D7: requisitos que no se inventan, D8: próxima acción preparada para CRM-05).
- Plan 2.61 (portada y ficha de Proyectos) y 1.4ab (escala de letra): el lenguaje visual que se replica.
- ADR 0023 (propuesta): numeración por dueño y copia al ganar, que alimenta el «Recorrido de los documentos».
- Tabla `oportunidades`: ya tiene `linea_solucion_id`, `necesidad_confirmada`, `presupuesto_confirmado`,
  `decisor_contacto_id` y `en_etapa_desde`.

## Decisiones (desarrollador, 2026-11-16, con el prototipo)

- **D1. Tarjetas de la portada.** Muestran todo lo de hoy (anillo con la probabilidad, código, tipo, nombre, entidad,
  monto y ponderado, días en la etapa contra su plazo, próxima acción, ejecutivo) **menos la barra de etapas**. Arriba,
  la **foto de referencia** de la oportunidad o, si no tiene, el **dibujo de su línea de solución**, con la línea y la
  etapa como etiquetas sobre la imagen. Al abrir, la ficha sí muestra la línea de etapas completa.
- **D2. Foto de referencia.** Una sola, que se sube desde la ficha («📷 Foto»), recortada y achicada en el navegador como
  la de Proyectos (2.61); también va de fondo en la cabecera de la ficha. Quién la cambia: P2.
- **D3. Tablero: la gema solo en el título.** Cada columna (Prospecto, Calificada, Propuesta enviada, Negociación,
  Ganadas, Perdidas) lleva su gema junto al título: piedra en bruto, corte hexagonal, diamante lila, diamante violeta
  brillante, esmeralda y piedra rajada. **Sin montos en el tablero**: ya están en el embudo de arriba. Las
  oportunidades del tablero no llevan gema propia. Reemplaza a la gema por oportunidad y al 2×2 de la 2.21c.
- **D4. Botonera en medio de cada columna** con la cantidad de oportunidades de esa etapa; al tocarla se despliega
  debajo la **tabla de la etapa** (código, oportunidad, entidad, monto, días contra el plazo, próxima acción; en
  Ganadas y Perdidas, cierre y proyecto o motivo). Reemplaza al círculo con el total de la 2.21c.
- **D5. Hasta 5 en el tablero.** En la tabla se marcan oportunidades; las marcadas pasan al tablero **solo con su
  código**, apiladas una debajo de otra, y se quitan con su ×. Máximo **5 por etapa**: con 5, las demás casillas se
  desactivan. Lo elegido se recuerda por persona (como «Mías» en la 2.21c, D15).
- **D6. Avanzar desde la tabla, de una en una.** El botón «Avanzar a …» de la tabla se activa solo con **una**
  oportunidad marcada.
- **D7. Avanzar arrastrando.** En el tablero se arrastra un código a la columna siguiente; no se saltan etapas ni se
  retrocede desde el tablero (como en la 2.21c). Arrastrar a Perdidas pide el motivo de la lista cerrada.
- **D8. La compuerta se rellena ahí mismo.** El diálogo de avance muestra cada requisito con su forma de cumplirlo
  dentro del mismo diálogo: necesidad confirmada (cómo se confirmó), presupuesto confirmado (adjuntar la
  certificación), decisor (elegir entre los contactos de la cuenta o registrar uno), cotización emitida (emitirla desde
  el costeo), cotización aceptada y buena pro (adjuntar el acta u orden). Sin próxima acción hasta CRM-05 (D14). Lo cumplido queda marcado con lo que se
  registró. El botón se habilita cuando todo está. **Los requisitos son los de la 2.21c, D7 (no se inventan)**: el
  prototipo usa los del v4 como ejemplo; Propuesta → Negociación sigue sin requisitos propios mientras Gerencia
  Comercial no los defina. Cada forma de cumplir llama a la acción registrada de su módulo (D5 de la 2.21c: una sola
  lógica).
- **D9. La animación al avanzar.** La gema de la etapa de origen se levanta del código, vuela en arco a la columna de
  destino, a mitad de camino estalla en luz y chispas y sale **convertida en la gema de la etapa siguiente**, se encoge
  en el título y este da un salto; luego el código aparece apilado en su nueva columna. Con «reducir animaciones», sin
  vuelo: el cambio es directo. Después de la animación la gema queda solo en el título (D3).
- **D10. Ficha.** Cabecera con la foto de fondo, el monto, el ponderado y la línea de etapas con la fecha de cada una.
  Pestaña «Resumen» con: **Recorrido de los documentos** (costeo → cotizaciones con sus versiones → la aceptada → la
  copia en el proyecto → el proyecto, con lo que falta punteado; depende del ADR 0023), **qué falta para avanzar**
  (la compuerta), **cómo se fue negociando** (el total de cada versión de cotización en un gráfico, y el margen solo
  con `campos.ver-costos`), la **línea de tiempo** de la oportunidad, **atención** (próxima acción vencida, atascada,
  buena pro cerca, margen bajo), competencia y contacto decisor.
- **D11. Vistas.** Tarjetas, Tablero y Línea de tiempo, con los filtros laterales con su cantidad (Atención, Etapa,
  Ejecutivo, Tipo de proceso, Cliente) y el embudo arriba con monto, probabilidad y cuántas necesitan atención por
  etapa. La Faja 3D sigue como vista extra (2.21c, D10).

## Respondidas (desarrollador, 2026-11-16)

- **D12 (era P1). Va antes del corte de reparto.** «Va antes del corte». Agregado a `corte-reparto.md`, como 2.61.
- **D13 (era P2). La foto la cambian el ejecutivo de la oportunidad y Gerencia Comercial** («lo cambian ambos»).
- **D14 (era P3). La próxima acción espera a CRM-05.** «Si no es necesaria, que espere»: la compuerta no la pide en
  esta tarea; su lugar queda listo (2.21c, D8) y aparece cuando llegue CRM-05.
- **D15 (era P4). Se acepta el recorrido de documentos** con el ADR 0023 (numeración por dueño y copia al ganar). Si la
  copia al ganar no está construida al hacer esta tarea, el recorrido muestra costeo y cotizaciones y deja punteados
  la copia y el proyecto.

## Pasos

- [x] 1. Plan con el prototipo aprobado (D1–D11) y lo que falta decidir (P1–P4).
- [x] 2. P1–P4 respondidas (D12–D15).
- [x] 3. Foto de la oportunidad (migración, servicio y acción), como la de Proyectos (2.61).
- [x] 4. Tarjetas de la portada con foto o dibujo por línea de solución; sin la barra de etapas.
- [x] 5. Tablero: gema en el título, botonera, tabla de la etapa, hasta 5 códigos apilados (recordados por persona).
- [x] 6. Avance desde la tabla (una sola marcada) y arrastrando, con la compuerta que se rellena; misma acción que la
  ficha.
- [ ] 7. La animación de la gema (con «reducir animaciones», sin vuelo).
- [x] 8. Ficha: cabecera, recorrido, compuerta, negociación por versiones, línea de tiempo, atención.
- [x] 9. Pruebas: máximo 5 por etapa, avanzar solo con una marcada, compuerta que no deja pasar con un requisito
  vacío, margen oculto sin permiso de costos, escenarios del v4 que ya existen.
- [x] 10. `composer check:tarea` en verde.

## Preparado para lo diferido

- Requisitos de Propuesta → Negociación: se registran en `RequisitosDeEtapa` y aparecen en la compuerta sin tocar las
  pantallas (2.21c, D7).
- Próxima acción (CRM-05): la compuerta tiene su lugar listo (P3).
- Recorrido: cada eslabón lo da su servicio (C3, C8 y el proyecto); la copia al ganar lo completa (ADR 0023).

- **Deuda (revisión de arquitectura, 2026-11-16):** las preferencias de orden y «Mías» de Ganadas y Perdidas
  (`TableroDeGemas::preferencias`, `OrdenDeSala`, `VistaDeSala`) quedaron declaradas sin pantalla al retirar el tablero
  de gemas; se quitan cuando se limpie la 2.21c (con el reparto, quien lleve el área 2). La tabla de una etapa trae 50
  filas sin paginar: con más, se pagina cuando haga falta.

## Criterio de aceptación

- Las pruebas del paso 9 en verde.
- En pantalla, la portada, el tablero y la ficha como en el prototipo aprobado, con los datos que existen.

## Bitácora

- 2026-11-16 — Plan escrito desde el chat con el prototipo aprobado.
- 2026-11-16 — P1–P4 respondidas: antes del corte, la foto la cambian ejecutivo y Gerencia Comercial, la próxima acción
  espera a CRM-05, recorrido aceptado.
- 2026-11-16 — Entrega `entrega/crm-oportunidades-vitrina` desde `main`, con el plan traído por merge; corte y mapa al
  día (2.21d en curso, antes del reparto).
- 2026-11-16 — Paso 3. `oportunidades.foto_ruta` (migración `2026_11_16_100001`), `FotoOportunidadService` y las
  acciones `crm.oportunidades.cambiar-foto` y `quitar-foto`, con la misma forma que Proyectos (2.61): `RevisionDeFoto`,
  disco de adjuntos, carpeta `fotos-oportunidad` (sumada a `CARPETAS_DE_OTROS`), servida por `RegistroImagenes` con la
  clave `oportunidad` a quien ve oportunidades; no sube la versión ni entra en el historial. **D13 en el código:** el
  ejecutivo de la oportunidad, o quien edita todas (`crm.oportunidades.editar`, lo de Gerencia Comercial en el resto del
  módulo); se revisa otra vez dentro del bloqueo. El botón en la ficha va en el paso 8. Prueba `FotoOportunidadTest`.
- 2026-11-16 — Paso 4. Vista **«Tarjetas»** (`TarjetasOportunidades`, `/oportunidades/tarjetas`, vista
  `oportunidades::tarjetas.pantalla`), primera pestaña; el Tablero sigue siendo la entrada (2.21c, D12) y la Lista y la
  Faja quedan como vistas extra. Cada tarjeta: foto o el dibujo de su línea (`DibujoDeLinea`, los seis del prototipo
  por código y un genérico de capas para las demás, como «Software»), la línea y la etapa encima, anillo con la
  probabilidad, número, nombre, entidad, monto y ponderado, días en la etapa contra su plazo (ámbar «atascada») y el
  ejecutivo; **sin barra de etapas**. Tocar una abre la ficha en el panel (`MuestraLaFicha`). Filtros con su cantidad,
  contados sin su propio grupo y aplicados en la base (`PortadaOportunidades::filtrarVitrina`, `opcionesVitrina`,
  `ordenarVitrina`; `FiltrosOportunidades`): **Atención** (atascadas y cierre estimado vencido; «sin próxima acción»
  espera a CRM-05, D14, y «buena pro cerca» a que exista su fecha), Etapa, Ejecutivo, **Línea de solución** (en lugar de
  «Tipo de proceso», que no existe todavía) y **Cliente** (privado o nivel de gobierno, por `CuentasService`). El
  buscador busca por nombre, número o entidad (cuentas usables, por su servicio). Prueba `TarjetasOportunidadesTest`.
- 2026-11-16 — Paso 5 (y lo de pantalla del 6). El tablero pasa a ser de **botonera** (`oportunidades::tablero.botonera`,
  datos de `DibujoDeLaBotonera`): seis columnas con la gema solo en el título y **sin montos** (D3); la botonera con la
  cantidad despliega debajo la tabla de la etapa (`abrirEtapa`, `?etapa=`; código, oportunidad, entidad, monto y días, o
  cierre y motivo en Ganadas y Perdidas, D4); en la tabla se marcan y pasan al tablero **solo con su código**, apiladas,
  con su × (D5). **Hasta 5 por etapa:** tabla `oportunidades_en_tablero` (migración `2026_11_16_100002`, única por
  persona y oportunidad), `TableroDeBotonera` (el tope, revisado con bloqueo por persona) y las acciones
  `crm.oportunidades.fijar-en-tablero` / `soltar-del-tablero` (permiso de ver). Lo puesto se recuerda por persona en
  esa tabla: no se registró como preferencia de lista porque su contrato supone una acción que guarda la lista entera y
  esta nunca se muestra en «Mis preferencias». Al avanzar, el código se va con su oportunidad a la columna nueva.
  «Avanzar a …» de la tabla se activa con **una sola** puesta en esa etapa (D6) y, como arrastrar un código a la columna
  siguiente (D7), va por `tq-oportunidad-mover` → `MueveOportunidades` (el mismo camino de la ficha y la faja): no se
  saltan etapas ni se retrocede, y a Perdidas pide el motivo. **Se retiraron** el tablero de gemas de la 2.21c
  (`DibujoDelTablero`, las vistas `tablero/pantalla`, `tarjeta` y `cuadro`, el orden y «Mías» de las salas en la página)
  y sus pruebas (`TableroGemasPantallaTest`, `TableroSalasVistaTest`, `TableroCelularTecladoTest`): D3 y D4 los
  reemplazan. Las preferencias de orden y «Mías» de las salas siguen declaradas (sin mostrarse) y ya no se usan:
  quitarlas es un cambio del núcleo de preferencias que queda para cuando se limpie la 2.21c. `TableroRuedaTest` se
  adaptó al tablero nuevo. Prueba nueva `TableroBotoneraTest`. Oportunidades: 235 en verde.
- 2026-11-16 — Paso 6, la compuerta que se rellena ahí mismo (D8). **Lo de la oportunidad** va como campos del mismo
  avance (así la versión tomada al arrastrar no cambia en medio): al marcar la necesidad aparece **«¿Cómo se
  confirmó?»** (columna nueva `oportunidades.necesidad_detalle`, migración `2026_11_16_100003`), al marcar el presupuesto
  la **certificación presupuestal** (se adjunta a la oportunidad: `Oportunidad` pasa a llevar adjuntos, `ConAdjuntos`,
  los ve quien ve oportunidades y adjunta quien puede editarla), y el decisor se elige o se **registra ahí mismo** con
  `nucleo.parties.agregar-contacto` (marcado como quien decide). Lo ya cumplido al abrir no se vuelve a pedir. En el
  camino del arrastre y del botón de la tabla, el diálogo devuelve lo llenado al navegador; un archivo no puede ir y
  volver, así que ahí la certificación se adjunta al confirmar el diálogo (`nucleo.adjuntos.agregar`, sin tocar la
  versión) y el avance sigue con lo demás. **Lo de otro módulo:** contrato nuevo `SeCumpleEnLaCompuerta` +
  `FormaDeCumplir` en `Requisitos/` (público); `RequisitosDeEtapa::revisar` trae la forma de cada uno que falta y la
  compuerta muestra su botón (`CumpleRequisitos::cumplirRequisito`, que ejecuta la acción registrada del módulo) o su
  enlace. Cotizaciones: «Una cotización emitida» emite la última aprobada (`crm.cotizaciones.cotizacion.emitir`) o, si
  no hay, lleva a prepararla; «Una cotización aceptada» lleva a la emitida, donde «Aceptar» pide la fecha y el acta u
  orden con su archivo (por eso es enlace y no se repite su formulario). Los requisitos son los de siempre (2.21c, D7);
  Propuesta → Negociación sigue sin propios. Sin próxima acción (D14): su lugar queda en el panel. La compuerta no deja
  pasar con uno vacío: la transición lo rechaza y dice qué falta. **Núcleo:** `AccionFilament` deja que el control
  propio de un campo opcional conserve su `required()` (antes lo pisaba) y toma un `FileUpload` de un solo archivo que
  llega como lista. Pruebas: `CompuertaRellenaTest`; `OportunidadesPantallasTest`, `FajaAccionesTest` y
  `TableroFichaTest` al día (en pruebas, una subida falsa no vive en la carpeta de subidas: la certificación con archivo
  se prueba con la acción). CRM: verde salvo esas dos, ya corregidas.
- 2026-11-16 — Pasos 8 y 9. **Ficha** (`ResumenDeLaFicha`, vistas `ficha/cabecera` y `ficha/resumen`): cabecera con la
  foto de fondo (o el dibujo de su línea), número, línea, tipo de cliente, nombre, entidad, ejecutivo y fechas, el monto
  y el ponderado, y la línea de etapas con la fecha de entrada a cada una (del historial de estados). Pestaña
  **«Resumen» primera** (la que se llamaba así pasa a «Datos»): **recorrido** (costeo por `PresupuestosService::
  deOportunidad` → cotizaciones con sus versiones → al ganar → proyecto; la copia y el proyecto, punteados hasta la 2.12,
  D15), **compuerta** (los requisitos de la etapa siguiente con su forma de cumplir, días contra el plazo, «si avanza» y
  el lugar de la próxima acción, D14), **negociación** (gráfico del total por versión de la cotización principal y la
  tabla; el margen solo con `campos.ver-costos`, que sin él ni se arma), **línea de tiempo** (creación, cambios de etapa
  y cotizaciones emitidas), **atención** (atascada, cierre estimado vencido y margen bajo su mínimo con permiso de costos;
  próxima acción vencida y buena pro cerca esperan a sus datos), competencia y decisor. El puerto
  `CotizacionDeOportunidad` (C3) suma `versiones()` con `VersionDeCotizacion` (Cotizaciones lo implementa; sin el
  módulo, vacío). Botón **«Foto»** en la ficha para su ejecutivo y Gerencia Comercial (D13), con el encuadre a lo ancho
  en el navegador; «Quitar la foto» en «Más». Pruebas `FichaVitrinaTest` (incluye margen oculto sin permiso de costos).
  Paso 9: las del plan quedaron en `TableroBotoneraTest` (máximo 5, avanzar con una sola), `CompuertaRellenaTest`
  (no deja pasar con un requisito vacío), `FichaVitrinaTest` y los escenarios existentes del CRM: 422 en verde.
- 2026-11-16 — Revisión de arquitectura: **dos bloqueantes, arreglados.** (B1) La regla de D8 vivía solo en la pantalla:
  ahora `OportunidadesService::avanzar` exige «cómo se confirmó» al confirmar la necesidad y la certificación al
  confirmar el presupuesto (en ese avance o ya adjunta a la oportunidad), también sin pantalla. (B2) En el arrastre, la
  certificación se adjuntaba antes y fuera del avance: ahora, si el diálogo trae el archivo, el avance se ejecuta ahí
  mismo en el servidor con la versión tomada, en una sola transacción con el archivo, y el navegador solo termina
  (`concluir` en el controlador de la ficha). Pruebas sin pantalla para las dos (`CompuertaRellenaTest`); las pruebas
  viejas del CRM que confirmaban sin esos datos dicen ahora cómo y dan la certificación por adjunta. **Observaciones:**
  (1) la foto la cambian el ejecutivo de la oportunidad (aunque no tenga «editar las propias») y quien edita todas:
  es D13 al pie de la letra, distinto a propósito de editar y adjuntar; (2) Cotizaciones arma en sus `Services/` y
  requisitos la dirección de sus pantallas (`VerCotizacion::getUrl`) para el recorrido y la compuerta: aceptado y
  anotado, dentro del mismo módulo; si llega una API, la dirección la arma quien la muestra; (3) el cambio de
  `AccionFilament` lo cubren las pruebas de la compuerta; revisados los 38 usos de `controles:` (solo uno tenía
  `required()` propio, en un campo obligatorio); (4) un archivo repetido en el avance lo rechaza el núcleo y no se guarda
  nada; (6) el puerto C3 entrega el margen: cada pantalla nueva que lo use debe filtrarlo por `campos.ver-costos`;
  (9) el decisor nuevo toma la empresa de la cuenta desde el formulario: si alguien la cambia, `validarDecisor` lo
  rechaza al avanzar (el contacto queda en esa empresa, con su permiso de contactos). La deuda va arriba.
- 2026-11-16 — Cierre: todas las pruebas de la rama en verde, **3,339**, en 8 procesos (pedido del desarrollador:
  «usa todos los núcleos»; directo con `php artisan test --parallel --processes=8`, porque el script fija 6: las mismas
  pruebas, sin registrar la corrida verde de la copia). Pint, phpstan y migraciones en verde. **En pantalla falta la
  revisión del desarrollador** (portada, tablero con la animación, compuerta y ficha): no se pudo abrir la app desde la
  sesión.
- 2026-11-16 — **Arreglo** (`arreglo/oportunidades-linea-de-tiempo`, pedido del desarrollador: «la línea de tiempo
  manéjala como la propuesta»): D11 pedía la vista «Línea de tiempo» del prototipo y se había dejado la de la 2.21c.
  Ahora es la del prototipo (`DibujoDelCronograma`, vista `linea/cronograma`): **una fila por oportunidad** con una
  barra por cada etapa que pasó (de su historial de estados, del color de la etapa), lo que falta hasta el cierre
  esperado en rayado, el rombo del cierre esperado o ✓ / ✕ en el cierre, los meses, la línea de hoy y la leyenda; la
  bandera de la buena pro espera a su fecha. Pedido además: **de 10 en 10** con páginas 1, 2, 3, y un **buscador** que
  mientras se escribe sube arriba la que coincide (por palabras, sin acentos: primero la que empieza con todo lo
  escrito) y vuelve a la página 1. Se retiran `DibujoDeLaLineaDeTiempo`, la vista `linea/pantalla` y su prueba;
  `TableroDeGemas::lineaDeTiempo()` queda sin pantalla, con la deuda de la 2.21c. Prueba `CronogramaOportunidadesTest`.
- 2026-11-16 — **Arreglo** (`arreglo/tarjetas-abren-la-ficha`, pedido del desarrollador): en la vista «Tarjetas», tocar
  una tarjeta lleva a la **ficha completa** de la oportunidad (enlace con `wire:navigate`), no al panel lateral. El
  tablero y la línea de tiempo siguen abriendo el panel.

