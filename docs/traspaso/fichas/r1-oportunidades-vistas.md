# Ficha · Las vistas de Oportunidades

- **Estado:** en curso (ronda de cambios; espera su «así me gusta»)
- **Versión:** v10
- **Tramo de la ruta:** observaciones de Sebastián, quinta tanda
- **Fuentes del diseño:** `docs/vibe/pedidos/observaciones-5.md` (puntos 3, 4 y 6) y sus lienzos copiados en
  `docs/vibe/pedidos/lienzos/` (`embudo/`, `linea-de-tiempo/`); las vistas del repo original (planes 2.21b a 2.21d).

## Qué hace

Oportunidades tiene tres vistas, con pestañas arriba: **Mis oportunidades** (la que se abre), **Embudo de
oportunidades** y **Línea de tiempo**. Encima de las tres, la fila de cifras de siempre. Desde v5 (MV-074) ya no hay
pestañas de portada: los leads y las licitaciones por decidir son la primera etapa, **«Por calificar»**, dentro de la
misma lista, y un filtro **TIPO** separa lo privado de las licitaciones.

## Decisiones de Sebastián

- **MV-095** (ficha) · Mis oportunidades, Embudo y Convocatorias usan el ancho normal de las páginas (como Proyectos); Línea de tiempo y Campañas siguen a todo lo ancho, con paso suave entre anchos.

- **MV-083** (ficha) · Sin título de página en las tres vistas; «+ Nueva oportunidad» a la derecha en la fila de las pestañas.

- **MV-082** (ficha) · Panel de filtros con desplazamiento propio, grupos plegables recordados, buscador en Cliente y Ejecutivo y «Quitar filtros» arriba.

- **MV-081** (ficha) · Embudo: «Por calificar» abre su panel con lo que falta calificar y sus botones.

- **MV-080** (ficha) · Mis oportunidades: 3 columnas como máximo; las tarjetas crecen y la foto mantiene su proporción.

- **MV-074** (cruza módulos) · Una sola lista: «Por calificar» como primera etapa con su gema, filtro TIPO, plazos de
  la licitación en la tarjeta y en la urgencia; sin pestañas de portada (reemplaza parte de MV-023 y MV-024).
- **MV-061** (ficha) · El Embudo muestra solo la fila de cinco cifras de la cabecera.
- **MV-060** (ficha) · La Línea de tiempo es el cronograma refinado, con «Por urgencia» por defecto, otros órdenes y filtros.
- **MV-058** (ficha) · El Embudo es el embudo clásico con sus gemas (lienzo «A refinada»); las gemas, con el dibujo nuevo.
- **MV-057** (ficha) · Mis oportunidades por defecto; Embudo de oportunidades; sin Lista ni Faja 3D.

## Pantallas y navegación

- **v9 · Cabecera de las vistas** (MV-083): ninguna de las tres vistas muestra título de página (tampoco al entrar por enlace ni al navegar sin recargar). Los botones de la página («+ Nueva oportunidad» y, en Mis oportunidades, «Por calificar») van a la derecha en la fila de las pestañas de vistas; la Línea de tiempo también tiene «+ Nueva oportunidad». En pantallas angostas, arriba de las pestañas.

- **v8 · Panel de filtros de Mis oportunidades** (MV-082): alto máximo la pantalla bajo el encabezado y su propia barra (invisible hasta pasar el puntero; sin arrastrar la página). «Quitar filtros» arriba si hay alguno. Cada grupo con su título como botón plegable y «· N» filtros activos; por defecto abiertos Atención y Etapa; lo que la persona abre o pliega se guarda para ella. Cliente y Ejecutivo con más de 6 opciones: buscador pequeño, las 6 con más oportunidades (y las marcadas) y «Ver todos (N)».

- **v7 · Embudo, panel «Por calificar»** (MV-081): pulsar la banda «Por calificar» la elige (`?etapa=por_calificar`) y el panel de la derecha muestra la cantidad, cuántos leads y licitaciones y el valor referencial sumado; luego cada elemento con la gema en la roca, nombre, cuenta u organización con su origen («Convocatoria del SEACE», «Registrada a mano», «Campaña …» o el origen del lead), monto o valor referencial, el próximo plazo (coral a menos de 5 días) y cuánto lleva esperando (coral desde 3 días), con Calificar / Descartar o Participar / No participar. Al resolver uno, sale con la animación de filas y las cifras se recalculan. «Ver todas» lleva a Mis oportunidades con «Por calificar» puesto.

- **Mis oportunidades** `/oportunidades` (antes «Tarjetas», en `/oportunidades/tarjetas`): las tarjetas de siempre.
  Desde v5 (MV-074):
  - **Filtros del costado:** Atención · **Tipo** (Privada · Licitación pública · Licitación privada por convocatoria,
    esta última solo si hay alguna) · **Etapa** (Por calificar · Prospecto · Calificada · Propuesta enviada ·
    Negociación · Ganada · Perdida) · Ejecutivo · Línea de solución · Cliente. Cada opción con su número (cuentan
    también las tarjetas por calificar).
  - **Tarjetas «Por calificar»** primero (solo en la primera página), con la gema en la roca: dicen «Por calificar ·
    Lead» o «· Licitación», el nombre (o el objeto), la entidad o la organización, el valor referencial si lo hay, lo que
    pidió el lead, el origen (con la campaña) o la nomenclatura, y el plazo («Absolución en 4 días», coral a menos de 5)
    o los días del lead («hace 5 días · sin atender»). Botones a la mano: **Calificar** (el formulario de convertir:
    coincidencias de cuenta, nombre, monto, línea y cierre; nace en Prospecto) y **Descartar** (motivo) en los leads;
    **Participar** (responsable; nace en Prospecto) y **No participar** (motivo) en las licitaciones, más «Ver».
  - **Tarjetas de oportunidades de licitación:** en lugar de «En la etapa desde…», el próximo plazo de su cronograma
    con el ícono de licitación («Integración de bases hoy», coral a menos de 5 días o vencido).
  - **Orden «Por urgencia (cierre o plazo)»** (antes «Cierre más cercano»): la fecha más cercana entre el cierre
    estimado y el próximo plazo de la licitación; ganadas y perdidas al final.
  - **Cabecera:** «Nueva oportunidad» y el menú «Por calificar» con **Nuevo lead** y **Registrar convocatoria**
    (licitación a mano, pública o privada).
  - `/leads` lleva a `/oportunidades?etapa[0]=por_calificar` y `/licitaciones` a `/oportunidades?tipo[0]=licitacion_publica`;
    la ficha de la licitación (`/licitaciones/{id}`) sigue.
- **Embudo de oportunidades** `/oportunidades/embudo` (antes «Tablero», que era la de entrada en `/oportunidades`). MV-058:
  - **Cifras (MV-061):** solo la fila de cinco de la cabecera de las vistas (Embudo abierto, Ponderado, Cierran este
    mes, Atascadas, Cierre estimado vencido); la fila propia de cuatro del lienzo se quitó.
  - **El embudo**, a la izquierda (11 de 20 columnas): cuatro franjas de 82 px de alto en trapecio (recorte 5 % por
    lado abajo, esquinas de 6 px) de 100 %, 86 %, 72 % y 58 % del ancho, con degradado vertical: Prospecto `#A6F2FF` →
    `#7FE9FF`, Calificada `#7FE9FF` → `#4DDDF5`, Propuesta enviada `#4DDDF5` → `#1FCDEB`, Negociación `#1FCDEB` →
    `#00B8D9`. Cada una: su gema (40 px), el nombre (16 px, 700, `#06262D`) y «Gema desbastada · 25 %» (12 px, 600,
    `#0E4651`); a la derecha el monto (18 px, 800) y «N oportunidades». Entre franjas, una píldora «71 % avanza» con
    una flechita turquesa. Debajo (62 % del ancho), **Ganadas** (gema brillante, borde `#7DB582`, fondo verde al 12 %) y
    **Perdidas** (gema fisurada, borde `#C9715A`, fondo coral al 10 %), con su cantidad y monto del año.
  - **El panel**, a la derecha (9 de 20): la gema de la etapa (48 px), su nombre y el resumen («4 oportunidades · S/ … ·
    ponderado S/ …»); la lista con gema chica, nombre, cliente, la próxima acción (turquesa; coral si venció o no hay),
    monto y días en la etapa (en Ganadas y Perdidas, la fecha en que se cerró); al final «Ver todas en Mis
    oportunidades →». Entra deslizándose 8 px (0,28 s).
  - **Cómo se usa:** cada franja (y Ganadas o Perdidas) es un botón con `aria-pressed` y su `aria-label` («Propuesta
    enviada, 4 oportunidades, S/ …»); la elegida tiene borde blanco por dentro, un 1,5 % más grande y un brillo
    turquesa. Al entrar se elige la etapa con más atascadas; si no hay, Propuesta enviada. Cada oportunidad del panel
    abre su ficha deslizante. Con «reducir movimiento», sin animaciones. Por debajo de 1100 px de ancho, el panel va
    debajo del embudo.
- **Las gemas (MV-058)** tienen el dibujo del lienzo «Gema» (32 × 32) en todas las vistas y la ficha: en bruto (piedra
  irregular gris azulada con vetas), desbastada (turquesa apagado con primeras caras), tallada (diamante con degradado
  turquesa), pulida (más claro, con reflejo blanco), brillante (casi blanco, destellos y halo verde) y fisurada (coral
  apagado con una grieta). Ya no varían por oportunidad (la «semilla» no cambia el dibujo).
- **Línea de tiempo** `/oportunidades/linea-de-tiempo` (MV-060):
  - **Filas:** a la izquierda (330 px; 200 en pantallas angostas) la gema (26 px), el nombre y «cliente · monto» (y
    «venció hace N días» en coral o «sin fecha»); a la derecha, un tramo de 14 px de alto y esquinas de 7 px por cada
    etapa recorrida, del color de su etapa (`#A6F2FF`, `#7FE9FF`, `#4DDDF5`, `#1FCDEB`: más oscuro al avanzar), la línea
    punteada desde hoy hasta el cierre y el rombo dorado (`#F2C14E`) con «cierre» debajo; si ya pasó, sin punteado y el
    rombo coral con «venció». La línea «Hoy · 22 nov» atraviesa todo. Arriba, los meses del eje y la leyenda. Cada fila
    abre la ficha deslizante.
  - **Orden «Por urgencia»** (por defecto): vencidas (la más vencida primero), por vencer (la más próxima primero), a
    igual día la de mayor monto, al final las sin fecha de mayor a menor monto; un separador «↑ 2 vencidas» donde
    termina lo atrasado. Las ganadas y perdidas no aparecen (se ven con «Cerradas»).
  - **Ordenar por:** monto, valor esperado (monto × probabilidad de la etapa), etapa (de Negociación a Prospecto), días
    estancada, cliente (A a Z); el empate, por el cierre más próximo. En estos órdenes las vencidas no suben solas pero
    siguen en coral.
  - **Filtros** (chips; el activo en el azul de resaltado, otro clic lo quita): buscador (nombre o cliente);
    vencimiento (Vencidas, Esta semana, Este mes, Sin fecha); etapa (un chip por etapa con su gema, y Cerradas);
    responsable (Mías por defecto, Todas, o una persona del menú); rango del eje (Mes, Trimestre, Semestre). Sin
    coincidencias: «Ninguna oportunidad coincide.» y «Quitar filtros». El orden y los filtros quedan en la dirección.
  - **Animación:** al cambiar el orden o un filtro, cada fila se desliza de su lugar viejo al nuevo (350 ms, 25 ms de
    escalonado entre filas), con sus tramos y su rombo; las que salen se desvanecen y se recogen, las que entran suben.
    La línea «Hoy», el eje y los filtros no se mueven. Sin animación con «reducir movimiento».
  - **Demo:** dos oportunidades abiertas vencidas hace 12 y 4 días.
- **Direcciones viejas:** `/oportunidades/lista`, `/faja` y `/tarjetas` llevan a Mis oportunidades; `/tablero`, al
  Embudo. Un aviso que antes abría la Lista con su filtro ahora abre Mis oportunidades.
- Entrar al proceso Conseguir el negocio abre Mis oportunidades (su caja del medio).

## Supuestos sin confirmar

- **v10 · Espacio del muelle:** se reserva en todas las páginas (también Proyectos) cuando el muelle está en el borde derecho, para que las dos sigan iguales. El paso suave entre anchos dura 320 ms y se apaga con «reducir movimiento».

- **v8 · Lo abierto o plegado** se guarda en la base por persona (tabla `estado_interfaz_usuario`, clave `oportunidades.filtros_abiertos`), no en «Mis preferencias». En pantallas angostas el panel no se pega ni se recorta.

- **v7 · «Cuánto lleva esperando»** se cuenta desde que se registró el lead o la licitación.

- **v5 · La gema de «Por calificar»:** la roca gris redondeada con una veta turquesa a la vista («Gema en la roca»).
- **v5 · Las cinco cifras no cuentan lo por calificar** (como ya era): no tiene monto ni probabilidad propia.
- **v5 · Un lead es de tipo «Privada»**; una licitación por decidir es pública o privada según su convocatoria.
- **v5 · «Próximo plazo» de una licitación:** el primer hito del cronograma de hoy en adelante; si ya pasaron todos, el
  último, como vencido. Una licitación por decidir con un plazo vencido cuenta en «Cierre estimado vencido»; un lead
  sin atender más de 3 días cuenta en «Atascadas».
- **v5 · Con filtros de línea o cliente**, los leads no aparecen (no tienen línea ni cuenta todavía).
- **v5 · Embudo:** «Por calificar» va como la boca del embudo, en gris piedra, con cuántos leads y licitaciones hay y el
  valor referencial sumado; lleva a esas tarjetas (no abre el panel de la derecha).
- **v5 · Línea de tiempo:** sin filtro de etapa se ven también las licitaciones por decidir (sus plazos apuran); con el
  chip «Por calificar», además los leads. Su tramo es gris desde que llegaron; clic lleva a la licitación o a la lista.
  Se ven con «Mías» y con «Todas» (aún no tienen dueño fijo). En las oportunidades de licitación, el rombo marca el
  plazo si llega antes que el cierre estimado.

- **Rango del eje (MV-060):** Mes abarca 45 días, Trimestre 90 y Semestre 180, con hoy a un tercio (como en el lienzo);
  por defecto, Semestre (el lienzo muestra unos cinco meses).
- **«Sin fecha»** nunca coincide hoy: la fecha de cierre esperada es obligatoria en la base.
- **«Quitar filtros»** deja el responsable en «Todas» (si no, «Mías» vacía seguiría vacía).

- **«N % avanza»** se calcula con lo que hay hoy: las oportunidades que están más adelante (y las ganadas) sobre las que
  llegaron a esa etapa o más allá; no hay historial de conversión por etapa.
- **Ganadas y Perdidas «del año»** cuentan las cerradas este año por su fecha de cierre real, sumando el monto estimado
  sin convertir monedas.
- **La próxima acción** sale de la agenda (la próxima actividad pendiente de la oportunidad); sin ella dice «Sin
  próxima acción» en coral.
- **En modo claro** (el lienzo solo trae el oscuro): tarjetas y panel blancos con borde gris claro; las franjas con el
  mismo degradado y texto oscuro; las gemas iguales.

- **«Mis oportunidades»** cambió de nombre pero no de contenido: muestra las mismas tarjetas y filtros que «Tarjetas»
  (no se filtró a las de la persona).
- **Lo que hacía solo la Lista** se perdió con ella: la tabla con «Reasignar» en lote y los avisos de revisión con «Ver
  cuáles». Reasignar sigue en cada ficha.

## Atajos tomados

- **v9:** los botones de la página se llevan a la fila de pestañas con CSS (la cabecera de Filament queda encima, alineada a la derecha).

- **v7:** las acciones de calificar viven en un rasgo (`DecideLoPorCalificar`) que usan Mis oportunidades y el Embudo.

- **v5:** la página de Oportunidades arma las acciones de leads y licitaciones con las de sus pantallas
  (`LeadResource::accionConvertir`, `AccionesLicitacion::todas`) y lee sus modelos para abrirlas; el orden por urgencia
  pasa las fechas de los plazos a la consulta. Lo por calificar se filtra en memoria.

- **Ganadas y Perdidas del año** se consultan directo en la página del Embudo.
- **Se borró** la botonera del Tablero (`DibujoDeLaBotonera`, `tablero/botonera`) y el dibujo procedural de las gemas
  (`DibujoDeGema`); las acciones de fijar o soltar en el tablero quedan en el código sin pantalla.

- **Lo que se borró:** las páginas `ListarOportunidades`, `FajaOportunidades` y `TableroAnterior`, el rasgo
  `AbreLaTablaDeUnLugar`, la clase `FajaDeVidrio` y las vistas `faja/pantalla`, `camara`, `hombrecito`, `caja`, `pila` y
  `despiece-script`. **Se quedó** lo que usa la ficha deslizante de las otras vistas: `TablaDeLaFaja`,
  `DespieceDeOportunidad` (con su vista `faja/despiece`) y los servicios `LugarFaja`, `FilaFaja`, `PaginaFaja`.
- Las direcciones viejas se desvían con una ruta registrada antes que las del panel.

## Dónde está en el código

`app/Modules/Crm/Oportunidades/Filament/VistasDeOportunidades.php` (las pestañas), `Filament/Resources/Oportunidades/`
(`OportunidadResource::getPages`, `Pages/TarjetasOportunidades.php`, `TableroOportunidades.php`,
`LineaDeTiempoOportunidades.php`), `resources/views/vistas/cabecera.blade.php`, la desviación en
`OportunidadesServiceProvider::register`.

## Cambios desde la versión anterior

- **v10** (MV-095): ancho normal en Mis oportunidades y Embudo.

- **v9** (MV-083): sin título de página; botones en la fila de las pestañas.

- **v8** (MV-082): panel de filtros con desplazamiento propio y grupos plegables.

- **v7** (MV-081): el panel de «Por calificar» en el Embudo.

- **v6** (MV-080): tarjetas en 3 columnas como máximo, foto con proporción fija.

- **v5** (MV-074): sin pestañas de portada; «Por calificar» como primera etapa con la gema en la roca (lista, embudo y
  línea de tiempo), filtro TIPO, plazos de licitación en la tarjeta y en la urgencia.

- **v4** (MV-061): sin la segunda fila de cifras en el Embudo.

- **v3** (MV-060): la Línea de tiempo con el cronograma refinado, su orden y sus filtros; sin paginación.

- **v2** (MV-058): el Embudo con sus gemas y el dibujo nuevo de las gemas.
