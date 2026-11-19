# Plan: el tablero de gemas y las vistas de Oportunidades (CRM-02)

- **Estado:** terminada (2026-11-11): `composer check:tarea` en verde; rama subida. El `composer check` completo va al
  cerrar la entrega `entrega/crm-embudo`, antes del PR (a cargo de Sebastián)
- **Responsable:** Sebastián (Comercial; modo de trabajo solo, con sesiones en paralelo)
- **Entrega:** `entrega/crm-embudo` (CRM-02 y lo que siga de Comercial)
- **Módulo(s):** Crm/Oportunidades (pantallas y lecturas de su servicio)
- **Fase:** F2
- **Creado:** 2026-11-11 · **Actualizado:** 2026-11-11

## Objetivo

Oportunidades tiene cuatro vistas: **Tablero** (por defecto, el embudo con gemas), **Línea de tiempo**, **Lista** y
**Faja 3D** (la de la 2.21b, como vista extra). Arrastrar una tarjeta en el tablero o pulsar un botón ejecuta la misma
acción registrada de la 2.21. Tarea 2.21c del mapa: corrige y completa el pedido de la 2.21b (pedido del
desarrollador, 2026-11-11; donde no coincidan, manda este).

## Contexto que leí

- El pedido del 2026-11-11 (en el Anexo, completo).
- Prototipos de referencia (visual y de comportamiento, no código para copiar):
  - `docs/diseno/fuentes/prototipos/tablero-oportunidades.html`, la vista por defecto. **Falta guardarlo en el
    repositorio:** lo guarda el desarrollador, como el de la faja.
  - `docs/diseno/fuentes/prototipos/faja-vidrio-oportunidades.html`, la faja (ya guardado). Si el desarrollador tiene
    una versión nueva con el cambio de tema, la reemplaza.
- Plan 2.21b (`docs/planes/completados/2026-11-09-crm-faja-vidrio.md`): la faja, `FajaDeVidrio`, `TablaDeLaFaja`,
  `DespieceDeOportunidad`, `LimiteCajasFaja`, `OportunidadesService::tablaDeLaFaja`, `totalesDeLaFaja`,
  `rechazoAlMoverla`.
- Plan 2.21 (`docs/planes/completados/2026-11-06-crm-oportunidades.md`): acciones, `RequisitosDeEtapa` (D9),
  perder con motivo, ganador y precio, `PortadaOportunidades` (cifras, días en la etapa, `pasaElMaximo`).
- `docs/decisiones/pendientes.md`, fila «Embudo comercial»: qué exige pasar de Propuesta a Negociación está
  **«por definir»** y es una pregunta abierta para Gerencia Comercial (ver D7).
- `docs/convenciones/interfaz.md`: patrones de pantalla, colores, reducir animaciones; y la excepción de la faja.

## Decisiones

Del desarrollador en el pedido del 2026-11-11, salvo las marcadas «propuesta del agente».

- **D1. Cuatro vistas, en pestañas:** Tablero (por defecto) · Línea de tiempo · Lista · Faja 3D. La faja deja de
  ser el «Tablero»: es una vista adicional («modo curiosidad»). Corrige la D2 de la 2.21b. La Lista es la Tabla que ya
  existe. Arriba de las cuatro, la fila de cifras: embudo abierto, ponderado, cierran este mes, atascadas y cierre
  estimado vencido (de `PortadaOportunidades`, que ya calcula las primeras).
- **D2. El tablero:** una columna por etapa abierta, con su probabilidad (Prospecto 10 %, Calificada 25 %, Propuesta
  enviada 50 %, Negociación 75 %; los porcentajes salen de los datos, no del código). Al final, una bifurcación con dos
  tarjetas apiladas: Ganadas arriba, Perdidas abajo.
  - **Cuatro por etapa:** primero las atascadas (las que pasan los días máximos de su etapa, `pasaElMaximo`) y luego
    las de mayor monto (en soles, al T.C. de compra, como la faja). En un cuadrado 2×2 con un hueco en cruz y, en el
    centro, un círculo con el total de la etapa que abre encima la tabla de todas las de esa etapa (buscador y orden
    por columna; la de la faja, `TablaDeLaFaja`).
  - Con menos de 4, el grupo va centrado y el círculo no tapa ninguna: con 1 o 2, debajo; con 3, entre las dos de
    arriba y la de abajo. Con más de 4, una línea dice «Se ven 4 de N».
  - Ganadas: base verde con cuadrícula tipo escáner; Perdidas: base roja igual. El mismo 2×2 en pequeño, con su
    círculo y su tabla (las del período, como las salas de la faja).
- **D3. La gema:** cada oportunidad es una gema que se talla al pasar de etapa. Prospecto en bruto (gris mate, sin
  forma); Calificada desbastada (violeta tenue, caras visibles); Propuesta enviada tallada (corte de diamante);
  Negociación pulida (intensa, con un reflejo que la recorre); Ganada brillante (turquesa de la marca, destellos y
  halo); Perdida fisurada (gris, sin brillo, con una grieta). El tamaño crece con el monto. Es un componente Blade
  reutilizable en SVG, sin imágenes externas, y aparece en la tarjeta, las tablas, la ficha, la línea de tiempo y el
  encabezado de cada etapa con su estado («En bruto», «Desbastada»…).
  - **Propuesta del agente:** el estado de la gema sale del código de la etapa (un enum `TallaDeGema` con su nombre y
    su dibujo), y el tamaño de una escala por monto con mínimo y máximo, para que una oportunidad enorme no rompa la
    tarjeta.
- **D4. Pasar de etapa:** entre etapa y etapa, una «rueda de tallado». Al arrastrar una tarjeta a la etapa siguiente,
  la rueda valida los requisitos: si falta alguno, se pone roja, no deja pasar y un aviso dice qué falta; si están,
  gira y la gema llega tallada a la nueva etapa. Solo a la siguiente; no se saltan etapas. A Ganadas solo desde
  Negociación; a Perdidas desde cualquier etapa. Perder pide motivo de una lista cerrada, ganador y precio (plan 2.21).
- **D5. Una sola lógica:** el arrastre y los botones de la ficha (Pasar, Ganar, Perder) ejecutan la misma acción
  registrada por `EjecutorAcciones`, con el bloqueo optimista y el aviso previo de la faja (`rechazoAlMoverla`,
  `RequisitosDeEtapa::faltantes`). Nada de lógica en Filament (reglas 2 y 7). El arrastre siempre tiene la alternativa
  del botón (accesibilidad y celular).
- **D6. La ficha:** panel deslizante (patrón de `interfaz.md`) con la gema en grande, los datos, los requisitos de la
  siguiente transición (cumple o falta) y los botones de acción.
- **D7. Requisitos de cada transición: no se inventan** (regla 12). Lo que hay hoy (plan 2.21, D9):
  - Prospecto → Calificada: necesidad confirmada, decisor identificado y presupuesto confirmado (del diseño del 13 de
    octubre y del v4). Ya se exigen.
  - Calificada → Propuesta enviada: además, una cotización emitida (v4). La registra CRM-03.
  - Propuesta enviada → Negociación: **por definir** en el diseño; pregunta abierta en `pendientes.md`. Los del
    prototipo eran ejemplos.
  - Negociación → Ganada: una cotización aceptada (v4, plan 2.21 D9.4). La registra CRM-03.
  - **Decidido por el desarrollador (2026-11-11):** mientras Gerencia Comercial no defina Propuesta → Negociación, la
    rueda de esa transición no exige nada propio (solo los acumulados) y su aviso no muestra requisitos inventados.
    Cuando se defina, se registra en `RequisitosDeEtapa` sin tocar las pantallas.
- **D8. Próxima acción:** pertenece a CRM-05. Queda preparada (`FajaDeVidrio::conProximaAccion()` y el punto de
  `SeccionesDeOportunidad`), oculta en el tablero y en la ficha hasta que llegue.
- **D9. Línea de tiempo:** por fecha de cierre estimada. Un carril por etapa con su nombre fijo a la izquierda al
  desplazarse; gemas como marcadores, con tamaño según el monto; línea de «hoy» y, al abrir, se desplaza hasta hoy;
  las vencidas, a la izquierda de hoy y en ámbar. Clic en una abre su ficha (D6).
  - **Propuesta del agente:** muestra solo las abiertas (las cerradas no tienen cierre estimado pendiente), y las que
    tienen la misma fecha se apilan en el carril sin taparse.
- **D10. La faja 3D se mantiene como en la 2.21b; solo cambia el tema.** El mundo 3D (faja, cajas, escáneres,
  tubería, salas y el despiece de una caja) es oscuro siempre. Todo lo demás sigue el tema claro u oscuro: cabecera y
  cifras, botones de arriba (Ver ganadas, Ver perdidas), ayudas, avisos y etiquetas de lugar, barra de etapas de
  abajo, diálogos y tablas emergentes, y el marco y la ficha de la derecha al abrir una caja.
- **D11. Tema y estilo:** los colores y componentes del sistema (`interfaz.md`): Inter, secciones blancas con borde
  fino, radio 12, sombra suave, azul noche como primario y el morado del CRM en los detalles; claro y oscuro con las
  variables del panel. Sin selector de tema propio: el tema lo cambia el usuario desde Filament (el del prototipo es
  solo para revisarlo). Con «reducir movimiento»: sin brillos animados, giros ni desplazamientos.
  - Con esto, el tablero vuelve al patrón general; la excepción de `interfaz.md` queda solo para el mundo 3D de la
    faja.
- **D12. Celular:** las etapas se apilan en orden y Ganadas y Perdidas van al final. Nada de desplazamiento horizontal
  de la página. El tablero es la vista por defecto también en el celular (corrige la D2 de la 2.21b, que abría la
  Tabla); la faja sigue mostrando su aviso en pantallas chicas.
- **D13. «Pasar a…» en la ficha abre el diálogo aunque falte un requisito** (decidido por el desarrollador,
  2026-11-11; confirma la decisión del agente del paso 6 y precisa la D5): el diálogo deja completar ahí la necesidad,
  el presupuesto y el decisor, y al guardar el servidor vuelve a revisar los requisitos (`CumpleRequisitosDeEtapa` en
  `DefinicionOportunidad`). El arrastre y la rueda sí bloquean (D4).
- **D14. Rango de la línea de tiempo: 3 meses atrás y 12 adelante** (decidido por el desarrollador, 2026-11-11;
  confirma la propuesta del paso 8): una fecha fuera del rango se dibuja en el borde con su fecha real en el texto.
- **D15. Ganadas y Perdidas del tablero** (decidido por el desarrollador, 2026-11-11; las etapas abiertas no cambian:
  atascadas primero y luego monto):
  - Por defecto, las 4 **más recientes** del período (cierre real de la última a la primera, luego id de mayor a menor).
  - Arriba de cada una, un selector del orden: Ganadas «Más recientes» / «Mayor monto»; Perdidas «Más recientes» /
    «Mayor monto» / «Por motivo de pérdida».
  - **«Por motivo de pérdida» (propuesta del agente, interpretación):** las 4 van agrupadas por motivo, el motivo más
    frecuente de las del período primero (con «Mías», entre las propias) y, dentro de un motivo, las más recientes; la
    tarjeta pequeña dice su motivo. Las que no tuvieran motivo, al final.
  - **«Mías»** (botón que se prende y se apaga, uno por sala): solo las oportunidades cuyo ejecutivo es quien mira. La
    cantidad, el total, «Se ven 4 de N» y la tabla del círculo siguen el filtro (la tabla con su propio orden).
  - El orden y «Mías» **se recuerdan por persona** (en su cuenta, en cualquier equipo), con el mecanismo de
    preferencias del núcleo (`RegistroPreferencias`, `PreferenciasUsuarioService`, la acción general
    `nucleo.cambiar-preferencia`): cuatro preferencias que declara el módulo en Navegación, sin mostrarse en «Mis
    preferencias» (`sinMostrar`, como el ancho de la barra); «Restablecer» de Navegación las vuelve al defecto. Sin
    tabla ni migración nuevas y sin tocar código del núcleo.
  - La lógica en el servicio (`TableroDeGemas::columna`/`vistaDeSala`, `OportunidadesService::destacadasDeLaFaja`), no
    en Filament. Controles con etiqueta en castellano, de teclado, en claro y oscuro y en el celular.
- **D16. Montos cortos en las vistas de Oportunidades** (pedido del desarrollador, 2026-11-11): «S/ 27k» en lugar de
  «S/ 27,000.00» en las tarjetas, encabezados y círculos del tablero, Ganadas y Perdidas, la fila de cifras, las marcas
  y totales de la línea de tiempo, la ficha y la faja (cajas, totales, terminales, salas y la cabecera del despiece).
  Un solo formateador, `Filament/MontoCorto`: menos de mil, entero («S/ 850»); miles, «k» con un decimal solo si
  importa debajo de 100 mil («S/ 27k», «S/ 27.5k») y entero desde 100 mil («S/ 150k»); millones, «M» con hasta dos
  decimales sin ceros de sobra («S/ 1.2M», «S/ 1.25M»); si al redondear llega al escalón de arriba, sube («S/ 1k»,
  «S/ 1M»); punto decimal como el resto del sistema; desde el texto decimal con bcmath, nunca float. El monto exacto
  queda en el `title` y para el lector de pantalla (componente `<x-oportunidades::monto>` o el `aria-label` de la
  tarjeta, la caja o la marca). Las tablas emergentes, la Lista, los formularios y el resumen del despiece siguen con
  el monto completo.
- **D17. En las ventanas laterales, el monto completo** (pedido del desarrollador, 2026-11-12): las siglas «k» y «M»
  son un recurso visual para cuando el área es poca o para no recargar la pantalla. Al abrir una gema, la ficha del
  panel lateral muestra el monto completo; igual la cabecera del despiece de la faja. La tapa de la capa Resumen del
  despiece, que es chica, sigue corta.
- **D18. Filtro «Etapa» en la Lista** (pedido del desarrollador, 2026-11-12): el filtro de etapa de la tabla acepta
  varias a la vez. Ofrece los estados del documento (Prospecto a Negociación, Ganada, Perdida y Anulada, porque la Lista
  muestra las anuladas en su pestaña y en «Todas»); se combina con las pestañas y con «Revisar» (`?aviso=` sigue igual).
- **D19. Arrastrar para mover la línea de tiempo** (pedido del desarrollador, 2026-11-12): con el mouse o el lápiz, se
  toma la línea y se arrastra a los lados (cursor de mano). Eventos de puntero, oyentes que se ponen al presionar y se
  quitan al soltar o cancelar, sin bucle de cuadros. Umbral de 5 px: un clic en una gema abre su ficha, un arrastre que
  termina sobre ella no. El dedo desplaza como antes y las flechas también. La rueda vertical no se convierte en
  horizontal: quitaría el desplazamiento de la página sobre la caja (Mayús + rueda ya lo hace).

## Pasos

- [x] 1. El prototipo del tablero guardado en el repositorio (la D7 ya está respondida).
- [x] 2. Servicio: las cuatro visibles por etapa (atascadas, luego monto), totales por etapa y del embudo ponderado,
      cierre estimado vencido, datos de la línea de tiempo. Con pruebas.
- [x] 3. El componente de la gema (SVG, seis tallas, tamaño por monto) y su prueba.
- [x] 4. Las pestañas de vista y la fila de cifras; la Lista y la Faja 3D en su pestaña.
- [x] 5. El tablero: columnas, 2×2 con el círculo, «Se ven 4 de N», bifurcación de Ganadas y Perdidas, tablas.
- [x] 6. La rueda de tallado: arrastre, validación, botones de la ficha, diálogos de perder y ganar.
- [x] 7. La ficha en el panel deslizante.
- [x] 8. La línea de tiempo.
- [x] 9. El tema de la faja (D10).
- [x] 10. Celular, teclado y reducir movimiento.
- [x] 11. `interfaz.md` (la excepción se reduce al mundo 3D), revisor de arquitectura, `composer check:tarea`; y las
      decisiones nuevas del desarrollador (D13–D16).

## Preparado para lo diferido

- Próxima acción (CRM-05): oculta en el tablero y en la ficha, con su punto de extensión (D8).
- Cotización emitida y aceptada (CRM-03): se registran en `RequisitosDeEtapa`; la rueda y la ficha las muestran solas.
- Requisitos de Propuesta → Negociación: cuando Gerencia Comercial los defina, se registran en `RequisitosDeEtapa`
  sin tocar las pantallas.

## Criterio de aceptación

- Pruebas: pasar con requisitos completos e incompletos; no saltar etapas; ganar solo desde Negociación; perder con
  motivo, ganador y precio; selección de las 4 visibles (atascadas primero, luego monto); totales de cada etapa y del
  embudo ponderado. Datos y nombres de ejecutivos inventados.
- El escenario de aceptación del v4 de Oportunidades sigue pasando.
- A la vista: el tablero abre por defecto, en claro y oscuro, y en el celular se apila sin desplazamiento horizontal.

## Bitácora

- 2026-11-11 — Plan escrito con las decisiones del pedido. Antes del código, dos cosas del desarrollador: guardar
  `tablero-oportunidades.html` en `docs/diseno/fuentes/prototipos/`, y responder la D7 (Propuesta → Negociación).
- 2026-11-11 — D7 respondida: Propuesta → Negociación no exige nada propio hasta que Gerencia Comercial lo defina.
- 2026-11-11 — Paso 2: `Services/TableroDeGemas` (final readonly; constante `VISIBLES = 4`) reúne las lecturas sin
  reglas propias: `columnas()` (las seis, por valor de `LugarFaja`) y `columna(LugarFaja)` → `ColumnaTablero` (`lugar`,
  `visibles` como `list<FilaFaja>`, `cantidad`, `totalEnSoles`, `sinTipoCambio`, `probabilidad` y `ponderadoEnSoles`,
  nulos en las salas; `ocultas()` para «Se ven 4 de N»); `cifras()` → `CifrasTablero` (`embudo`, `esteMes`,
  `atascadas`, `vencidas`, cada una un `EmbudoEnSoles` con cantidad, total y ponderado; las dos primeras son las de
  `PortadaOportunidades::tarjetas`); `lineaDeTiempo()` → `list<MarcaLineaDeTiempo>` (`oportunidad` como `FilaFaja` y
  `vencida`). En `OportunidadesService`, dos lecturas nuevas que reúsan las filas y el T.C. de la faja:
  `destacadasDeLaFaja(LugarFaja, int)` (atascadas primero con la regla de `estancadas`, luego monto en soles; en las
  salas, solo monto) y `abiertasPorCierreEstimado()`. Probabilidad y máximo salen de los parámetros; sin anuladas;
  todos ven todas, como la faja. Desvíos: (1) en las salas, las cuatro que se ven son las de mayor monto (la faja
  dibuja las más recientes); (2) arreglado un error de la faja: ordenar por monto un lugar vacío mandaba
  «ORDER BY NULL» y PostgreSQL lo rechaza (ahora `CAST(NULL AS numeric)`). Pruebas: `TableroGemasConsultasTest` (7) y
  las de la faja y la lectura, en verde.
- 2026-11-11 — Paso 3: la gema. `Enums/TallaDeGema` (`en_bruto`…`fisurada`; `desdeEtapa(string)`, que rechaza una
  etapa sin gema como «anulada»; `desdeLugar(LugarFaja)`; `etiqueta()` «En bruto»…; `textoAccesible()` «Gema
  tallada»). `Filament/EscalaDeGema::tamano(?string $montoEnSoles, int $minimo = 44, int $maximo = 56)`: raíz del monto
  hasta `MONTO_TOPE` (500 000 soles), sin monto el mínimo. `Filament/DibujoDeGema::de(TallaDeGema, int $semilla)`: la
  geometría y los colores del prototipo (violetas del CRM, `ColoresMarca::MORADO` en la pulida, `TURQUESA` en la
  brillante). Componente anónimo `<x-oportunidades::gema :talla :monto :tamano :minimo :maximo :semilla :decorativa />`
  (`resources/views/componentes/gema.blade.php`, registrado con `Blade::anonymousComponentPath` en el proveedor):
  `role="img"` con «Gema …», o `aria-hidden` si `decorativa`; id de recorte al azar por gema; ancho por `tamano` fijo o
  por el monto, alto 0,88 del ancho; estilos una vez por página (`@once`). Desvío: `interfaz.md` dice «ningún bucle»,
  así que el reflejo pasa 2 veces y los destellos 3, y vuelven al pasar el mouse sobre la gema o sobre un contenedor
  con `data-tq-gema-anfitrion` (la tarjeta, paso 5); con «reducir movimiento» o `tq-sin-animaciones`, quietos (el
  reflejo, oculto). Si el desarrollador los quiere en bucle, se anota la excepción en `interfaz.md` (paso 11). Pruebas:
  `GemaTest` (20), en verde.
- 2026-11-11 — Paso 4: las cuatro vistas, cada una una página del recurso con su dirección: **Tablero**
  `/oportunidades` (la de entrada: `index`, la entrada del menú y la caja del inicio; también en el celular, D12),
  **Línea de tiempo** `/oportunidades/linea-de-tiempo` (`LineaDeTiempoOportunidades`; por ahora solo pestañas, cifras y
  un estado vacío «en construcción» en `oportunidades::linea.pantalla`, que llena el paso 8), **Lista**
  `/oportunidades/lista` (`ListarOportunidades`, la de antes) y **Faja 3D** `/oportunidades/faja` (la de la 2.21b,
  renombrada `TableroOportunidades` → `FajaOportunidades`; el nombre `TableroOportunidades` es ahora el tablero de
  gemas). Direcciones viejas: `/oportunidades/tablero` lleva al Tablero (`TableroAnterior`, una página que solo
  redirige) y `/oportunidades?aviso=…` a la Lista ya filtrada. Lo común, en `Filament/VistasDeOportunidades`
  (`pestanas($activa)`, `cifras()` con el texto de `TableroDeGemas::cifras()`, `simboloLocal()`), el trait
  `Filament/MuestraLasVistas` (cifras `#[Locked]`, calculadas al abrir y tras un cambio; `cabeceraDeVistas()`) y la
  vista `oportunidades::vistas.cabecera`: las pestañas son las de Filament (`x-filament::tabs`, enlaces con
  `aria-current="page"` en la activa, `data-vista`) y las cinco cifras son secciones de Filament (`data-cifra`:
  embudo, ponderado, este-mes, atascadas, vencidas; las dos últimas en ámbar si hay alguna). `verLugar` pasa a un trait
  (`Filament/AbreLaTablaDeUnLugar`) para que el tablero lo reúse. Desvíos: (1) la fila de cifras reemplaza a las
  tarjetas de la Lista («Embudo total» pasa a «Embudo abierto»; las notas de T.C. siguen); (2) se quitaron los botones
  «Tablero» y «Lista» de las cabeceras (los reemplazan las pestañas); (3) el aviso de la faja en pantallas chicas ahora
  lleva al Tablero. Pruebas: `VistasOportunidadesTest` (4: direcciones y pestaña activa en cada vista, 403 sin
  permiso en las cuatro y en la vieja, redirecciones, cifras en las cuatro) y las de la faja y la lista con sus
  direcciones nuevas. **A la vista:** las pestañas y las cifras en claro y oscuro; el menú abre el Tablero.
- 2026-11-11 — Paso 5: el tablero en `/oportunidades`. `Filament/DibujoDelTablero` (final readonly) arma la vista desde
  `TableroDeGemas::columnas()` sin reglas: por columna, nombre, talla, probabilidad, cantidad, total y ponderado en
  texto, cuántas sin T.C., `ocultas` y `colocacion` (`vacia`, `una`, `dos`, `tres`, `cuatro`); por tarjeta, id, número
  OP, nombre, cuenta, monto con su símbolo, monto en soles (tamaño de la gema), días, atascada, versión y la URL de la
  ficha. Vistas `oportunidades::tablero.pantalla` (columnas, lugares de la rueda, bifurcación y salas), `tablero.cuadro`
  (las que se ven y el círculo) y `tablero.tarjeta`. El acomodo es una cuadrícula de CSS: con 4, 2×2 con hueco en cruz y
  el círculo en la cruz; con 1 o 2, el círculo en su propia fila debajo; con 3, el círculo en la fila del medio y la
  tercera tarjeta abajo, centrada; nunca tapa una tarjeta. «Se ven 4 de N: primero las atascadas, luego por monto» (en
  las salas, «las de mayor monto»). El círculo (`data-circulo`) llama `mountAction('verLugar', {lugar})`, la ventana
  con `TablaDeLaFaja` (buscador, orden, páginas). Ganadas (base verde con cuadrícula) arriba y Perdidas (base roja)
  abajo, con tarjetas pequeñas (gema, nombre, monto). Estilo del sistema con variables del panel (claro y oscuro,
  radio 12, el % en una insignia `morado`); con «reducir movimiento» o «reducir animaciones», nada crece ni se
  desliza; la base no lleva el barrido animado del prototipo (ningún bucle). Celular y tablet (bajo
  `BarraLateral::CORTE_ESCRITORIO`): una sola columna en orden, Ganadas y Perdidas al final; en escritorio angosto el
  tablero se desplaza dentro de su caja, no la página. Próxima acción oculta (D8). **Ganchos:** paso 6, los lugares de
  la rueda son `data-rueda="<etapa de la que sale>"` (`.tq-tablero-paso` entre etapas y `.tq-tablero-bifurcacion` tras
  Negociación, con `aria-hidden` hasta que funcionen), cada tarjeta lleva `data-tarjeta` (id) y `data-version`, cada
  columna `data-etapa`/`data-sala`, y las reglas del movimiento siguen en `FajaOportunidades::accionDeLaFaja` y
  `rechazoAlMover` (estáticas, para reusarlas o moverlas a un lugar común); paso 7, tocar una tarjeta (un enlace a
  `VerOportunidad`) o una fila de las tablas emite `tq-tablero-abrir-ficha` ({ id, url }, cancelable, en la raíz
  `data-tablero`): quien lo cancele abre el panel; si nadie, se sigue el enlace (Ctrl+clic, otra pestaña). Desvíos:
  (1) sin la viñeta «N atascadas» por etapa del prototipo (la cifra total está arriba); (2) las tablas aún no muestran
  la gema ni marcan las que se ven (paso 7 u 8 si se quiere). Pruebas: `TableroGemasPantallaTest` (7: 403, columnas con
  %, gema y estado, 4 visibles con atascadas primero y «Se ven 4 de N», acomodo con 1/2/3/4, Ganadas y Perdidas,
  círculo y su tabla, aviso de la ficha). **A la vista:** el 2×2 y el círculo con 1, 2, 3 y 4; Ganadas y Perdidas en
  claro y oscuro (que la cuadrícula se lea sin cansar); celular de 360 px apilado; que el círculo no tape tarjetas.
- 2026-11-11 — Paso 6: la rueda de tallado. **Un solo camino para mover** (D5): las reglas de la faja salen de
  `FajaOportunidades` a `Filament/MovimientoDeOportunidad` (estática, solo conecta: `accion($desde, $hacia)` elige la
  acción o explica por qué no —saltar, retroceder, ganar fuera de Negociación—, `rechazo($id, $codigo)` es el aviso
  anticipado de permiso y «propias», `faltantes($id, $hacia)` lo que falta de `RequisitosDeEtapa`, `puede()` para los
  botones; sus constantes `GANADA`, `PERDIDA`, `NO_SE_SALTA`, `NO_SE_RETROCEDE`, que `FajaOportunidades` repite como
  alias) y al trait `Filament/MueveOportunidades` (`abrirMovimiento($id, $desde, $hacia, $version, $exigirRequisitos)`
  revisa versión, permiso, «propias» y, con `$exigirRequisitos`, los requisitos —si falta algo devuelve `faltan` y el
  texto «Para pasar a X falta: …» sin abrir nada—, y abre el diálogo; los cinco diálogos `calificar`…`perder`, que
  devuelven lo llenado con `tq-oportunidad-dialogo-listo`; `moverOportunidad(...)` `#[Renderless]` ejecuta por
  `EjecutorAcciones` con la versión tomada). La faja, el despiece y el tablero usan los mismos; `abrirEnFaja` y
  `moverEnFaja` pasan a llamarse así (vista de la faja y `FajaAccionesTest` al día, sin cambiar lo que prueban). En el
  tablero (`tablero.pantalla`, `tqTablero`): Pointer Events, el arrastre empieza a los 5 px (antes es un clic, que abre
  la ficha); una copia sigue al puntero, la columna destino se marca (azul si puede, roja si no) y su rueda apunta; al
  soltar, la etapa siguiente, Ganadas (solo desde Negociación) o Perdidas avisan `tq-oportunidad-mover` con
  `exigir: true` y la versión de `data-version` tomada al presionar; saltar, retroceder o soltar fuera vuelve con una
  línea sin llamar a nada; Esc cancela. El controlador (`tqMovimientoOportunidad`, en `ficha/panel`, para que la ficha
  y la línea de tiempo lo tengan) pregunta al servidor: falta algo → rueda roja que vibra y el aviso dice qué; todo
  está → el diálogo de la acción (los de la faja: Perder con motivo de la lista, ganador y precio; Ganar con fecha y
  monto final) → `moverOportunidad` → la rueda gira, `despuesDeMover` (cifras y versión de la ficha) redibuja y la
  gema llega tallada (`tq-tablero-llega`); falla o conflicto → rueda roja, el mensaje y la tarjeta vuelve (con
  conflicto, se redibuja). Con «reducir movimiento», sin giro, vibración ni deslizamiento. En el celular el dedo
  desplaza la página (las tarjetas no capturan el toque): pasar va por los botones de la ficha. Las ruedas siguen
  `aria-hidden` (no son interactivas: la alternativa son los botones). **Decisión del agente (a confirmar):** la rueda
  del arrastre bloquea si falta un requisito (D4), pero el botón «Pasar a …» de la ficha abre el diálogo aunque falte
  algo, porque ese diálogo deja completar ahí mismo la calificación (necesidad, presupuesto, decisor) y la acción
  vuelve a revisar al ejecutar; el aviso de la rueda dice qué falta. Si se quiere que el botón también bloquee, es
  pasar `exigir: true` en `ficha/panel`. Pruebas: `TableroRuedaTest` (9: pasa con requisitos completos y el diálogo
  devuelve lo llenado sin la versión; incompletos → `faltan` sin diálogo ni movimiento; Propuesta → Negociación sin
  requisitos propios; no salta ni retrocede; Ganadas solo desde Negociación; Perdidas con motivo, ganador y precio;
  conflicto de versión; «propias» de otro ejecutivo; tarjetas con `data-desde` y versión, cerradas sin arrastre). Las
  de la faja, en verde tras la extracción. **A la vista:** arrastrar con mouse, dedo (tablet en escritorio) y lápiz;
  que un clic sin mover abra la ficha y no siga el enlace; la rueda roja con el aviso abajo; el giro y la gema que
  llega; Esc durante el arrastre; con «reducir animaciones», todo instantáneo; en modo oscuro, la copia que sigue al
  puntero.
- 2026-11-11 — Paso 7: la ficha en el panel deslizante. El trait `Filament/MuestraLaFicha` (usa
  `MueveOportunidades`): `ficha` (`#[Locked]`, `#[Url(as: 'ficha')]`: `?ficha=<id>` la abre directo y la dirección sigue
  al abrir y cerrar), `versionDeLaFicha` (`#[Locked]`, leída al abrir: la llevan sus botones), `abrirFicha($id)` (403 sin
  ver oportunidades; una anulada no abre: aviso «Esa oportunidad ya no está…», `FICHA_NO_ESTA`), `cerrarFicha()`
  (devuelve el foco a `[data-tarjeta="id"]` o `[data-ficha-origen="id"]`), `despuesDeMover($id)` (cifras y versión de
  la ficha al día; redibuja) y `fichaDeOportunidad()`, el componente para el contenido de la página. Los datos, en
  `Filament/FichaDeOportunidad::datos($id)` (de `filaDeLaFaja`, sin reglas: número, nombre, cuenta, ejecutivo, monto,
  etapa, probabilidad, cierre estimado, días en la etapa con «atascada»; la transición siguiente con
  `RequisitosDeEtapa::revisar` y los botones Pasar a «siguiente» / Ganar (solo en Negociación) / Perder filtrados por
  `MovimientoDeOportunidad::puede`; cerrada: su cierre, de `DespieceDeOportunidad::cierre`, ahora pública, sin
  requisitos ni botones). Vista `oportunidades::ficha.panel`: panel fijo a la derecha bajo la barra superior (26 rem,
  pantalla completa en el celular), el tablero atrás atenuado y usable (`tq-tablero-detras`), la gema de 104 px con
  su talla, «Cumple»/«Falta» con ícono y texto, «Abrir la ficha completa» (VerOportunidad); se cierra con la ✕ o Esc
  deslizándose afuera; el foco va al título al abrir. Próxima acción oculta (D8). El tablero cancela
  `tq-tablero-abrir-ficha` y llama `$wire.abrirFicha` (tarjeta o fila de las tablas; la fila cierra la ventana de la
  tabla); Ctrl+clic sigue el enlace. Las tarjetas son enlaces: Enter abre la ficha. **Para el paso 8 (línea de
  tiempo):** la página usa `MuestraLaFicha` (con `MuestraLasVistas`), pone `$this->fichaDeOportunidad()` en su
  `content()` y llama `$this->abrirFichaDeLaDireccion()` en `mount()`; cada marcador llama `$wire.abrirFicha(id)` y
  lleva `data-ficha-origen="id"` para recibir el foco al cerrar. Los botones y el controlador de movimientos vienen
  con la vista (sin ruedas, nada gira). Desvíos: (1) el panel no es un `slideOver` de Filament sino el patrón propio de
  `interfaz.md` (Catálogos, Alertas), fijo para que se vea aunque se haya desplazado el tablero; (2) con una acción
  montada, Livewire en pruebas no redibuja la ficha tras mover (en la pantalla real el diálogo ya se cerró): la prueba
  sigue el flujo real (`callMountedAction` antes de `moverOportunidad`). Pruebas: `TableroFichaTest` (10: datos,
  gema y requisitos; botones por etapa y versión tomada; el botón por el mismo camino del arrastre y la ficha al día
  tras mover; sin permiso y «propias» sin botones; cerrada con su cierre y sin botones; anulada no abre ni por el
  enlace; `?ficha=` y cerrar; 403; atenuado y Esc; conflicto con la versión vieja). **A la vista:** abrir con clic,
  Enter y desde una fila de la tabla; Esc y la ✕ (el foco vuelve a la tarjeta); otra tarjeta con el panel abierto;
  que el panel no tape la barra lateral en escritorio; celular de 360 px; claro y oscuro; «Pasar a…» abre el diálogo
  y, al confirmar, la rueda gira y la ficha muestra la etapa nueva.
- 2026-11-11 — Paso 8: la línea de tiempo en `/oportunidades/linea-de-tiempo`. `Filament/DibujoDeLaLineaDeTiempo`
  (final readonly) arma la vista desde `TableroDeGemas::lineaDeTiempo()` sin reglas (qué está vencida lo dice el
  servicio): solo texto y medidas de pantalla en píxeles enteros (no es dinero; los totales por carril, con `bcadd`).
  Un día mide `PX_POR_DIA` (10) y la gema se centra en su día (`x()`); `filas()` baja a otra fila del carril la gema que
  taparía a la anterior (`ANCHO_MARCA` 200 px con su texto): dos de la misma fecha nunca comparten fila, y el carril
  crece (`ALTO_FILA` 48). El rango va del mes del cierre más antiguo al mes siguiente al más lejano, con hoy siempre
  dentro, al menos tres meses hacia adelante y con topes (`MESES_ATRAS` 3, `MESES_ADELANTE` 12): una vencida de hace
  mucho o un cierre muy lejano se dibujan en el borde, y su texto dice la fecha real. Meses («noviembre 2026») y lunes
  en el eje, en castellano. Vista `oportunidades::linea.pantalla`: una sección del sistema (claro y oscuro con las
  variables del panel) con el título, «N con el cierre vencido» en ámbar con ícono y «el tamaño de la gema sigue al
  monto»; un carril por etapa abierta con su nombre, gema, cantidad y total fijos a la izquierda (`position: sticky`);
  la reja de meses y semanas, lo vencido rayado en ámbar suave y la línea de «hoy» con su etiqueta; cada abierta, un
  `<button>` con su gema (24 a 40 px por monto), nombre y «monto · fecha» (`vencida 3 nov` en ámbar), con `aria-label`
  (número, nombre, monto, cierre estimado y «vencida») y `data-ficha-origen`. Al abrir, la caja se desplaza hasta hoy
  (suave; al instante con «reducir movimiento» o «reducir animaciones»). Clic o Enter: `$wire.abrirFicha(id)` (la
  página usa `MuestraLaFicha`, `?ficha=<id>` la abre directo, la línea se atenúa detrás y al cerrar el foco vuelve a la
  gema). Celular: la línea se desplaza dentro de su caja (`contain: inline-size`), el rótulo se angosta. Sin abiertas,
  un estado vacío. Desvíos: (1) la leyenda no repite las cuatro gemas por etapa del prototipo (cada carril ya la
  lleva); (2) los topes del rango son propuesta del agente. Pruebas: `LineaDeTiempoPantallaTest` (9: 403; carriles por
  etapa con su gema y total, nombre fijo; marcas con talla, `aria-label` y botón; vencidas a la izquierda, ámbar y
  «vencida»; la misma fecha apilada en filas distintas; hoy en su día, desplazamiento y eje en castellano; solo
  abiertas; estado vacío; la ficha con `abrirFicha` y `?ficha=`). **A la vista:** que al abrir se vea hoy cerca de la
  izquierda; desplazar a lo ancho con el nombre del carril fijo; varias del mismo día apiladas; vencidas en ámbar en
  claro y oscuro (que la trama rayada no canse); Tab y Enter sobre las gemas y el foco al cerrar la ficha; celular de
  360 px sin desplazamiento de la página.
- 2026-11-11 — Paso 9: el tema de la faja (D10). La faja funciona igual que en la 2.21b; solo cambian colores en
  `faja/pantalla.blade.php`. Los colores del mundo (`FajaDeVidrio::colores()`) siguen iguales en los dos temas y el
  oscuro no los toca; `.tq-faja-mundo` y `.tq-despiece-escena` llevan `color-scheme: dark`, y la escena del despiece
  pinta su propio fondo de mundo (cielo a noche). Todo lo demás sale de `--tq-faja-ui-*`, ahora con las variables del
  panel (claro: blanco, `--gray-950`/`--gray-500`, borde `--gray-200`, acento `--morado-600`, verde/rojo/ámbar
  `--success-600`/`--danger-600`/`--warning-700`; oscuro: `--gray-900`, blanco/`--gray-400`, borde blanco al 10 %,
  `--morado-400` con letra oscura encima, y los 400): los botones de arriba (Vista general, ±, Ver en tabla, Ver
  ganadas, Ver perdidas, con sombra suave y foco visible), la ayuda de la rueda, el aviso de abajo, la etiqueta del
  lugar (Ganadas, Sótano de perdidas), la barra de etapas, y del despiece el marco (`--tq-faja-ui-marco`: `--gray-50` o
  `--gray-950`), la cabecera (cuenta, nombre, número, pastilla de etapa en morado, «Atascada» en ámbar), «Volver · Esc»
  y el panel de lectura (ya seguía el tema; ahora con los tonos del panel, «Cumple» y «Perder» en verde y rojo legibles
  en claro). Antes, la etiqueta del lugar, la ayuda y el aviso eran siempre oscuros, y el marco y la cabecera del
  despiece también. Nada fuerza el modo oscuro fuera del mundo: los diálogos y las tablas emergentes son los de
  Filament y siguen el tema. Siguen oscuros (son del mundo): las viñetas con el número sobre las cajas, el grabado de
  las cajas, las pilas y las capas del despiece. «Reducir movimiento» sin cambios. Desvío: el acento de la faja pasa de
  `ColoresMarca::MORADO_CLARO`/`MORADO` en hexadecimal a la paleta `morado` del panel (la misma marca). Pruebas:
  `FajaPantallaTest` (una nueva: los colores del mundo no cambian con el tema y la escena es oscura; los de alrededor
  usan las variables del panel en los dos temas; botones, lugar, ayuda, aviso, barra, marco y panel del despiece sin el
  oscuro fijo; ninguna clase `dark` ni otro `color-scheme` fuera del mundo); ninguna prueba vieja afirmaba el oscuro
  fijo. **A la vista:** la faja en claro y en oscuro (cambiando el tema desde Filament): los botones y la barra sobre el
  mundo oscuro, que se lean y no parezcan pegados; la etiqueta de la sala y el aviso; abrir una caja: marco y cabecera
  claros con la escena oscura en medio y el panel de la derecha; «Ver en tabla» y un diálogo de mover en claro.
- 2026-11-11 — Paso 10: celular, teclado y reducir movimiento (revisión de las cuatro vistas, la cabecera, la gema y
  la ficha). **Reducir movimiento:** una sola regla por vista, como la de la faja (el sistema y `tq-sin-animaciones`):
  `.tq-tablero-vista *` (rueda que gira y vibra, copia que vuelve, gema que llega, atenuado; la copia del arrastre cuelga
  de la raíz, así que la alcanza), `.tq-ficha-raiz *` (el panel y el aviso aparecen y se van sin deslizarse) y
  `.tq-linea-vista *` (con `scroll-behavior: auto`; el JS ya iba a hoy al instante); además, nada crece al pasar el
  mouse (tarjeta y círculo). La gema sigue con lo suyo (reflejo y destellos finitos; quietos y el reflejo oculto).
  **Teclado:** cada tarjeta lleva `aria-label` con número, nombre, cuenta, monto, días en la etapa y «atascada» (las de
  Ganadas y Perdidas, nombre y monto) y abre la ficha también con Espacio (Enter ya, por ser enlace); el tablero es una
  región («Tablero de oportunidades por etapa») con una ayuda para el lector: Enter o Espacio abre la ficha y pasar,
  ganar o perder sin arrastrar va por los botones de la ficha; círculos y marcas ya tenían su `aria-label` y las ruedas
  siguen `aria-hidden`. Los avisos de la rueda: antes la región `aria-live` aparecía con `x-show` a la vez que su texto
  (no siempre se anuncia); ahora una región `role="status"` siempre presente (`data-anuncio-movimiento`, se vacía antes
  para repetir un aviso igual) y la burbuja visible `aria-hidden`. Pestañas de vista: foco visible propio (Filament solo
  aclaraba el fondo, igual que la activa). Línea de tiempo: la caja que se desplaza es una región con `tabindex="0"`
  (las flechas la mueven) y las gemas, con `scroll-margin`, no quedan debajo del nombre fijo del carril al recibir el
  foco. **Celular:** el CSS del tablero ahora es primero celular: una columna en orden con Ganadas y Perdidas al final, y
  las medidas anchas (las 9 columnas de ~1370 px, `min-width: min-content`, `overflow-x`, `touch-action: none`) solo
  dentro de `@media (min-width: 1024px)` (`BarraLateral::consultaEscritorio()`). Revisado sin cambios: cifras
  (`auto-fit` de 11 rem, una columna a 360 px), pestañas (se desplazan en su caja), ficha a pantalla completa
  (`min(100%, 26rem)`), línea dentro de su caja, aviso de la faja con el enlace al Tablero. **Rendimiento:** mover y
  soltar ya no se escuchan siempre en la ventana: el tablero los agrega al presionar una tarjeta y los quita al soltar,
  al cancelar con Esc y al destruirse; ni el tablero ni la línea usan `requestAnimationFrame` ni `setInterval`; el
  estilo de la gema va una vez por página (`@once`). Desvíos: (1) **la ficha no atrapa el foco**: sigue el patrón de la
  casa (`interfaz.md`: lo de atrás y la barra de la izquierda siguen usables), con el foco al título al abrir, Esc y la ✕
  para cerrar y el foco de vuelta a la tarjeta o la marca; (2) el aviso de la faja en pantallas chicas sigue con su
  corte de 767 px de la 2.21b (no se tocó la faja); (3) la ayuda del tablero es solo para el lector (no se agregó texto
  visible: la interfaz no se rediseña). Pruebas: `TableroCelularTecladoTest` (5: la regla de reducir movimiento de cada
  vista y la de la gema; la región `aria-live`; `aria-label` de tarjetas, círculos, marcas y la ayuda; ningún ancho
  mínimo de escritorio fuera del bloque de 1024 px; escuchas solo durante el arrastre, sin temporizadores y el estilo de
  la gema una vez con muchas gemas). **Sin navegador: a la vista**, el desarrollador revisa a 360 y 768 px el tablero
  apilado (sin desplazamiento horizontal de la página), las pestañas y las cifras, la ficha a pantalla completa y la
  línea desplazándose en su caja; en escritorio, que el tablero siga en fila (el CSS cambió de orden) y el arrastre con
  mouse; con Tab: pestañas (anillo visible), tarjetas en orden por columnas, círculos, Ganadas y Perdidas, Espacio y
  Enter abren la ficha, Esc la cierra y el foco vuelve; un lector de pantalla que lea el aviso de la rueda; y con
  «reducir animaciones» (y con el del sistema), nada gira, vibra, se desliza ni brilla.
- 2026-11-11 — Paso 11: cierre. **Decisiones del desarrollador** escritas como D13 (la ficha abre el diálogo aunque
  falte un requisito; el servidor revisa al guardar), D14 (rango de la línea: 3 meses atrás y 12 adelante), D15
  (Ganadas y Perdidas con orden y «Mías» recordados por persona) y D16 (montos cortos).
  **D15:** `Enums/OrdenDeSala` (recientes, monto, motivo; `de($sala)`: Ganadas sin «motivo»), `Services/VistaDeSala`
  (orden y `soloDe`). `OportunidadesService::destacadasDeLaFaja($lugar, $cuantas, $ordenSala, $soloDe)`: en una sala,
  más recientes (cierre real desc, id desc), mayor monto (en soles, como una zona) o por motivo (el del último paso a
  «perdida» del historial; primero el motivo más frecuente de la consulta —`count(*) OVER (PARTITION BY …)`—, sin
  motivo al final, y dentro las más recientes); `tablaDeLaFaja` y `totalDeLugarDeLaFaja` reciben `$soloDe`.
  `TableroDeGemas::columna($lugar, ?VistaDeSala)`, `columnas($vistas)`, `vistaDeSala($quien, $sala)` y
  `vistasDeSalas`; `ColumnaTablero::vista`. **Recordado por persona** con el mecanismo del núcleo, sin tocarlo: el
  proveedor del módulo declara en `RegistroPreferencias` cuatro preferencias (`TableroDeGemas::preferencias()`:
  `navegacion.oportunidades_{ganadas|perdidas}_{orden|mias}`, opción y sí/no, `sinMostrar`, como el ancho de la barra),
  se leen con `PreferenciasUsuarioService` y se cambian con la acción general `nucleo.cambiar-preferencia` por
  `EjecutorAcciones` (`TableroOportunidades::elegirOrdenDeSala` y `alternarMias`). Sin migración, sin seeder ni permiso
  nuevo (esa acción no lleva permiso). Pantalla: arriba de cada sala, «Ver» con el select de Filament
  (`x-filament::input.select`, `aria-label` «Qué ganadas se ven») y el botón «Mías» con `aria-pressed`; la tarjeta
  pequeña dice su motivo en «Por motivo»; «Se ven 4 de N: …» según el orden; el círculo abre la tabla con el mismo
  filtro (`AbreLaTablaDeUnLugar::soloDeEnLaTabla`, que la faja deja en nulo; `TablaDeLaFaja::$soloDe` bloqueado) y el
  título dice «solo las mías». Pruebas: `TableroSalasVistaTest` (5).
  **D16:** `Filament/MontoCorto::de(?string, ?string)` con bcmath y el componente `<x-oportunidades::monto>` (corto a
  la vista, exacto en el `title` y en un texto solo para el lector). Usado en las tarjetas, encabezados y círculos del
  tablero, Ganadas y Perdidas, la fila de cifras, marcas y totales de la línea de tiempo, la ficha, y en la faja
  (cajas, rótulos, terminales, salas y la cabecera del despiece); los `aria-label` de tarjetas, cajas, marcas y círculos
  llevan el exacto. Siguen completos: las tablas emergentes (y su título), la Lista, los formularios y el resumen del
  despiece. Pruebas: `MontoCortoTest` (23 casos con los bordes) y las de pantalla al día.
  **Revisión de arquitectura:** (1, bloqueante) `DibujoDelTablero` y `DibujoDeLaLineaDeTiempo` ya no reciben
  `TableroDeGemas` en el constructor: va como parámetro de `datos()` (páginas y pruebas al día). (2) La fila de cifras
  de la faja quedaba vieja tras mover: `MuestraLasVistas::actualizarCifras()` es ahora el camino común; la faja lo
  llama al recargar (en lugar de `$refresh`) y `MuestraLaFicha::despuesDeMover` lo usa; prueba nueva en
  `FajaAccionesTest`. (3) `TableroAnterior` redirige con la consulta (`?ficha=`, `?aviso=`); prueba en
  `VistasOportunidadesTest`. (4) `TallaDeGema` usa `LugarFaja::Ganadas/Perdidas->estado()` (la fuente del módulo; no
  `MovimientoDeOportunidad`, que es de Filament). (5) La gema rechaza un monto que no sea texto, entero o nulo (un
  float falla con su mensaje); prueba en `GemaTest`. (6) La cabecera del plan, al día.
  **interfaz.md:** la excepción queda solo para el mundo 3D de la faja; la gema como componente con brillo finito (no
  es un bucle); los montos cortos de Oportunidades. Desvío: la vista se ordena por defecto «más recientes» en las salas
  (antes, mayor monto: paso 2). `composer check:tarea`: pint y phpstan en verde, 68 migraciones bien, 25 de 201
  archivos más Arch y Feature: **374 pruebas, 374 en verde (3059 aserciones)**. **A la vista:** el selector y «Mías»
  en claro y oscuro, a 360 px y con Tab; que lo elegido siga al volver a entrar desde otro equipo; los montos cortos y
  su globo con el exacto.
- 2026-11-12 — D17: la ficha (`FichaDeOportunidad`, fila «Monto») y la cabecera del despiece muestran el monto
  completo. `TableroFichaTest` revisa que la ficha no lleve «k». Pruebas de lo tocado: `TableroFichaTest`,
  `FajaDespieceTest`, `MontoCortoTest`, `LineaDeTiempoPantallaTest`, 52 en verde.
- 2026-11-12 — D18 y D19 (pedido del desarrollador): el filtro «Etapa» de la Lista, de selección múltiple
  (`OportunidadResource::table()`), y arrastrar para mover la línea de tiempo (`linea/pantalla.blade.php`). Pruebas
  nuevas en `OportunidadesPantallasTest` y `LineaDeTiempoPantallaTest`: 27 en verde; pint y phpstan en verde.
  **A la vista:** arrastrar la línea con el mouse (mano abierta y cerrada), que un clic en una gema la abra y un
  arrastre que suelta sobre ella no; en el celular, el dedo desplaza como antes.

## Anexo: el pedido del desarrollador (2026-11-11), completo

> Contexto: CRM-02 Oportunidades. Esto corrige y completa el prompt anterior sobre el tablero. Donde no coincidan,
> manda este.
>
> Prototipos de referencia (docs del proyecto de claude.ai; son la referencia visual y de comportamiento, no código
> para copiar): `claude/prototipos/tablero-oportunidades.html` → vista POR DEFECTO;
> `claude/prototipos/faja-vidrio-oportunidades.html` → vista EXTRA («modo curiosidad»).
>
> Corrección importante: la faja 3D NO reemplaza al tablero. El tablero de gemas es la vista principal; la faja es
> una vista adicional para quien quiera explorarla. Se llega a ella desde las pestañas de vista.
>
> 1. Pestañas de vista: Tablero (por defecto) · Línea de tiempo · Lista · Faja 3D. Arriba, la fila de cifras: embudo
>    abierto, ponderado, cierran este mes, atascadas y cierre estimado vencido.
> 2. Tablero (vista principal): una columna por etapa (Prospecto 10 %, Calificada 25 %, Propuesta enviada 50 %,
>    Negociación 75 %). Al final, una bifurcación de dos tarjetas apiladas: Ganadas arriba y Perdidas abajo. Cada etapa
>    muestra solo 4 oportunidades: primero las atascadas y luego las de mayor monto. Van en un cuadrado 2×2 con un
>    hueco en cruz al centro. En el centro hay un círculo con el total de la etapa. Al pulsarlo se abre, encima, la
>    tabla con todas las de esa etapa (buscador y orden por columna). Con menos de 4, el grupo va centrado y el círculo
>    no tapa ninguna tarjeta: con 1 o 2 va debajo; con 3, entre las dos de arriba y la de abajo. Si la etapa tiene más
>    de 4, una línea dice «Se ven 4 de N». Ganadas: base verde con cuadrícula tipo escáner. Perdidas: base roja igual.
>    Las dos usan el mismo 2×2 en pequeño, con su círculo que abre su tabla.
> 3. Metáfora visual: cada oportunidad es una gema que se talla al pasar de etapa. Prospecto: en bruto (gris mate, sin
>    forma). Calificada: desbastada (violeta tenue, caras visibles). Propuesta enviada: tallada (corte de diamante).
>    Negociación: pulida (intensa, con un reflejo que la recorre). Ganada: brillante (turquesa de la marca, destellos y
>    halo). Perdida: fisurada (gris, sin brillo, con una grieta). El tamaño de la gema crece con el monto. La misma gema
>    aparece en la tarjeta, las tablas, la ficha, la línea de tiempo y el encabezado de cada etapa (con su estado: «En
>    bruto», «Desbastada»…). Hazlas como SVG en un componente Blade reutilizable. Nada de imágenes externas.
> 4. Pasar de etapa: entre etapa y etapa hay una «rueda de tallado». Al arrastrar una tarjeta a la etapa siguiente, la
>    rueda valida los requisitos. Si falta alguno: la rueda se pone roja, no deja pasar y un aviso dice qué falta. Si
>    están completos: gira y la gema llega tallada a la nueva etapa. Solo a la etapa siguiente; no se saltan etapas. A
>    Ganadas solo desde Negociación; a Perdidas desde cualquier etapa. Perder pide motivo de una lista cerrada, ganador y
>    precio, como dice el plan 2.21. El arrastre y los botones de la ficha (Pasar, Ganar, Perder) ejecutan la MISMA
>    acción registrada del servicio. Nada de lógica en Filament (reglas 2 y 7). El arrastre siempre tiene una
>    alternativa con botón (accesibilidad y celular). Requisitos de cada transición: los de Prospecto → Calificada y
>    Calificada → Propuesta salen del v4. Los de Propuesta → Negociación y Negociación → Ganada eran ejemplos del
>    prototipo. Si no están definidos en el v4 ni en el plan 2.21, no los inventes: revisa
>    docs/decisiones/pendientes.md y pregunta (regla 12).
> 5. Ficha: panel deslizante (patrón de docs/convenciones/interfaz.md) con la gema en grande, los datos, los requisitos
>    de la siguiente transición y los botones de acción.
> 6. Próxima acción: pertenece a CRM-05 (Actividades). Déjala preparada (punto de extensión listo), pero oculta en el
>    tablero y en la ficha hasta que llegue CRM-05.
> 7. Vistas complementarias. Línea de tiempo: por fecha de cierre estimada. Un carril por etapa, con su nombre fijo a la
>    izquierda al desplazarse. Gemas como marcadores, con tamaño según el monto. Línea de «hoy»; al abrir, se desplaza
>    hasta hoy. Las vencidas, a la izquierda de hoy y en ámbar. Clic en una abre su ficha. Lista: tabla completa con
>    buscador y orden.
> 8. Faja 3D (vista extra): se mantiene como estaba en el prompt anterior; solo cambia el tema. El MUNDO 3D (faja,
>    cajas, escáneres, tubería, salas, el despiece de una caja) es oscuro siempre, en los dos temas. TODO LO DEMÁS sigue
>    el tema claro u oscuro: cabecera y cifras; botones de arriba (Ver ganadas, Ver perdidas); ayudas, avisos y
>    etiquetas de lugar; barra de etapas de abajo; diálogos y tablas emergentes; el marco y la ficha de la derecha al
>    abrir una caja.
> 9. Tema y estilo: todo usa los colores y componentes del sistema (docs/convenciones/interfaz.md): letra Inter,
>    secciones blancas con borde fino, radio 12, sombra suave, azul noche como primario y el morado del CRM en los
>    detalles. Modo claro y modo oscuro con las variables del panel. En el sistema real NO se agrega un selector de tema
>    propio: el tema lo cambia el usuario desde Filament, como en el resto del sistema. El selector del prototipo existe
>    solo para revisarlo. Respeta «reducir movimiento»: sin brillos animados, giros ni desplazamientos.
> 10. Celular: las etapas se apilan en orden; Ganadas y Perdidas van al final. Nada de desplazamiento horizontal de la
>     página.
>
> Pruebas: pasar con requisitos completos o incompletos; no saltar etapas; ganar solo desde Negociación; perder con
> motivo, ganador y precio; selección de las 4 visibles: atascadas primero, luego monto; totales de cada etapa y del
> embudo ponderado; datos inventados; nombres de ejecutivos inventados.
>
> Antes de escribir código, actualiza el plan de la tarea en docs/planes/activos/ con estas decisiones, en su sección
> «Decisiones».
