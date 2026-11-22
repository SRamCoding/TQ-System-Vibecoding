# Ficha · Las vistas de Oportunidades

- **Estado:** en curso (ronda de cambios; espera su «así me gusta»)
- **Versión:** v3
- **Tramo de la ruta:** observaciones de Sebastián, quinta tanda
- **Fuentes del diseño:** `docs/vibe/pedidos/observaciones-5.md` (puntos 3, 4 y 6) y sus lienzos copiados en
  `docs/vibe/pedidos/lienzos/` (`embudo/`, `linea-de-tiempo/`); las vistas del repo original (planes 2.21b a 2.21d).

## Qué hace

Oportunidades tiene tres vistas, con pestañas arriba: **Mis oportunidades** (la que se abre), **Embudo de
oportunidades** y **Línea de tiempo**. Encima de las tres, las pestañas de la portada (Por calificar · Oportunidades ·
Licitaciones) y la fila de cifras de siempre.

## Decisiones de Sebastián

- **MV-060** (ficha) · La Línea de tiempo es el cronograma refinado, con «Por urgencia» por defecto, otros órdenes y filtros.
- **MV-058** (ficha) · El Embudo es el embudo clásico con sus gemas (lienzo «A refinada»); las gemas, con el dibujo nuevo.
- **MV-057** (ficha) · Mis oportunidades por defecto; Embudo de oportunidades; sin Lista ni Faja 3D.

## Pantallas y navegación

- **Mis oportunidades** `/oportunidades` (antes «Tarjetas», en `/oportunidades/tarjetas`): las tarjetas de siempre.
- **Embudo de oportunidades** `/oportunidades/embudo` (antes «Tablero», que era la de entrada en `/oportunidades`). MV-058:
  - **Cuatro cifras** en tarjetas: Embudo abierto, Ponderado, Cierran este mes, Atascadas (las mismas cuentas de la fila
    de cifras de las vistas).
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

- **v3** (MV-060): la Línea de tiempo con el cronograma refinado, su orden y sus filtros; sin paginación.

- **v2** (MV-058): el Embudo con sus gemas y el dibujo nuevo de las gemas.
