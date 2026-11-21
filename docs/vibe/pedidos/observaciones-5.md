# Observaciones de Sebastián, quinta tanda

Pedido de Sebastián (20 de noviembre de 2026). Cada observación es una decisión suya: regístrala en
`docs/traspaso/decisiones.md` con su alcance y anótala en la ficha que corresponda (sube su versión). Hazlas en
orden, un commit por observación. Si algo choca con lo ya construido, pregúntame antes.

## 1. El ícono de la agenda, estilo aplicación: la hoja del día

**Qué pasa hoy.** El ícono de la agenda en el muelle es de línea y neutro, mientras que a su lado hay íconos de
aplicaciones a todo color. Se pierde.

**Qué quiero.** Un ícono **estilo aplicación**, a todo color: la **opción A, «Hoja del día»**, del lienzo
«Ícono de la agenda · propuestas»: https://claude.ai/artifact/HjBp8UWVJRA7wrserWntJL (tablero de arriba, modo oscuro;
tablero de abajo, modo claro).

- **Forma:** un cuadrado de esquinas redondeadas (como un ícono de aplicación), blanco, con una **banda turquesa
  arriba** (`#00B8D9` a `#00DAFF`).
- **En la banda:** el **día de la semana abreviado** en mayúsculas, blanco, en negrita (VIE).
- **En el centro:** el **número del día** grande, en tinta (20).
- **Abajo a la derecha:** un **relojito dorado** (`#F2C14E`, el oro de Mien) con borde blanco y sus agujas en un
  marrón oscuro.
- **Cambia solo cada día:** muestra siempre el día y la fecha de hoy, según la zona horaria de la persona (Lima). Los
  días van en español: LUN, MAR, MIÉ, JUE, VIE, SÁB, DOM.
- **Modo claro:** la hoja blanca lleva un **borde fino gris cálido** (`#D9D4C9`) para que se recorte del fondo. En
  modo oscuro, sin borde.
- **Tamaño:** 32 px en el muelle, como las demás aplicaciones; que se lea bien el número a ese tamaño (si hace
  falta, número un poco más grueso).
- Es un ícono de aplicación: **no** toma el color de ninguna franja ni del tema; se ve igual en las cuatro
  estaciones.
- Va solo en el muelle (la agenda). Si en algún otro lugar se muestra la agenda (por ejemplo, la tarjeta «Mi agenda»
  de Mien), puede usar el mismo ícono.

SVG de partida (48 × 48, el día y el número los pone el sistema):

```svg
<svg viewBox="0 0 48 48" role="img" aria-label="Agenda: viernes 20">
  <defs>
    <linearGradient id="tqAgendaBanda" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#00B8D9"/><stop offset="1" stop-color="#00DAFF"/>
    </linearGradient>
  </defs>
  <rect x="3" y="3" width="42" height="42" rx="10" fill="#FFFFFF"/>
  <path d="M3 13a10 10 0 0 1 10-10h22a10 10 0 0 1 10 10v2H3z" fill="url(#tqAgendaBanda)"/>
  <text x="24" y="12.6" text-anchor="middle" font-family="Inter, sans-serif" font-size="7" font-weight="800" fill="#FFFFFF" letter-spacing=".6">VIE</text>
  <text x="24" y="36" text-anchor="middle" font-family="Inter, sans-serif" font-size="19" font-weight="700" fill="#1F2328">20</text>
  <circle cx="38" cy="38" r="7.5" fill="#F2C14E" stroke="#FFFFFF" stroke-width="1.6"/>
  <path d="M38 34.6V38l2.2 1.4" stroke="#5A3E00" stroke-width="1.5" stroke-linecap="round" fill="none"/>
</svg>
```

En modo claro, el primer `rect` lleva además `stroke="#D9D4C9" stroke-width="1"` (y `x="3.5" y="3.5" width="41"
height="41" rx="9.5"` para que el borde no se corte). El `aria-label` dice el día completo («Agenda: viernes 20»).

**Alcance:** ficha (la agenda del muelle).

## 2. El logo de la caja Techquk, en azul

**Qué pasa hoy.** El logo de la caja Techquk (símbolo y nombre TECHQUK) sale en gris claro, con el color del texto,
en vez del color de su franja.

**Qué quiero.** Que vaya **en el azul de la franja Estratégicos**, como decidí en la observación 4.5: `#6F9BFF` en modo
oscuro y azul noche (`#01006F`) en modo claro; el mismo azul del título «Estratégicos». El símbolo y el nombre, los
dos en ese azul. Solo en la caja y en la barra lateral principal: el logo del encabezado sigue como está.

Si al pasar el mouse o con la caja elegida el ícono cambia de color, que cambie como los íconos de las demás cajas
(más intenso), pero sin volverse blanco ni gris.

**Alcance:** ficha.

## 3. Oportunidades: «Mis oportunidades» por defecto y «Embudo de oportunidades»

**Qué quiero.** En las vistas de Oportunidades:

- La vista **Tarjetas** pasa a llamarse **«Mis oportunidades»** y es **siempre la que se abre por defecto** (hoy se abre
  el Tablero).
- La vista **Tablero** pasa a llamarse **«Embudo de oportunidades»**. Su diseño cambia según la observación siguiente.
- **Se eliminan las vistas «Lista» y «Faja 3D»**: sus pestañas, sus pantallas, sus rutas y el código que solo usaban
  ellas (por ejemplo, la Faja de vidrio y lo que la dibuja). Si algo de otra vista depende de ese código, se queda lo
  necesario y me avisas. Los enlaces que llevaban a esas vistas pasan a «Mis oportunidades».
- Quedan tres vistas, en este orden: **Mis oportunidades** (por defecto) · **Embudo de oportunidades** · **Línea de
  tiempo**.

**Alcance:** ficha.

## 4. El diseño del «Embudo de oportunidades»: embudo clásico con sus gemas

Propuesta aprobada: tablero **«A refinada · el embudo con sus gemas»** del lienzo
https://claude.ai/artifact/UfTYVjDHcu3qabKSHBBBB9 (las tablas B y C del mismo lienzo quedaron descartadas). Las gemas
están en el componente «Gema» del mismo lienzo.

**De arriba abajo:**

1. **Cuatro cifras** en tarjetas: embudo abierto (monto y cantidad), ponderado (según la probabilidad de cada etapa),
   cierran este mes, atascadas (más de 15 días sin moverse). Salen de las mismas cuentas que la fila de cifras de hoy.
2. **A la izquierda, el embudo clásico, de arriba abajo:** cuatro franjas en forma de trapecio que se angostan
   (Prospecto 100 %, Calificada 86 %, Propuesta enviada 72 %, Negociación 58 % del ancho), con un degradado de turquesa
   claro a turquesa intenso que se oscurece a medida que avanza la etapa. Cada franja lleva:
   - a la izquierda, **la gema de su etapa** (la talla que ya existe: en bruto, desbastada, tallada, pulida);
   - el nombre de la etapa y, debajo, «Gema tallada · 50 %» (la talla y la probabilidad);
   - a la derecha, el **monto** grande y la cantidad de oportunidades.
   Entre franja y franja, una píldora con una flechita: **«61 % avanza»** (cuántas pasan a la siguiente).
3. **Debajo del embudo, dos salidas:** **Ganadas** con la gema **brillante** (verde) y **Perdidas** con la gema
   **fisurada** (coral), con su cantidad y monto del año.
4. **A la derecha, el panel de la etapa elegida:** su gema grande, el nombre y el resumen (cantidad, monto,
   ponderado), y la lista de sus oportunidades: gema chica, nombre, cliente, **próxima acción** (en turquesa; en coral
   si venció o no hay), monto y cuántos días lleva en la etapa. Al final, «Ver todas en Mis oportunidades».

**Cómo se usa:**

- Al presionar una franja (o Ganadas o Perdidas), se marca (borde blanco por dentro, un poco más grande y con brillo)
  y el panel de la derecha cambia a esa etapa, con una entrada suave. Por defecto se abre la etapa con más
  oportunidades atascadas o, si no hay, Propuesta enviada.
- Cada oportunidad del panel abre su ficha (el panel deslizante que ya existe).
- Las franjas son botones de verdad (teclado y lector de pantalla: «Propuesta enviada, 7 oportunidades, S/ 540,000»).
- Con «reducir movimiento», sin animaciones.

**Las gemas** (dibujo profesional, no íconos genéricos), cada una más trabajada que la anterior:

| Etapa | Gema | Cómo se ve |
|---|---|---|
| Prospecto | En bruto | Piedra irregular, opaca, gris azulado, con vetas |
| Calificada | Desbastada | Forma ya ordenada, turquesa apagado, primeras caras |
| Propuesta enviada | Tallada | Diamante con sus caras, degradado turquesa |
| Negociación | Pulida | El mismo diamante más claro, con un reflejo blanco |
| Ganadas | Brillante | Diamante casi blanco, con destellos y halo verde |
| Perdidas | Fisurada | Diamante apagado en tono coral, con una grieta |

Las tallas ya existen (`TallaDeGema`): solo cambia su dibujo. Úsalas también donde ya aparecen (Mis oportunidades,
fichas).

**Modo claro:** fondo claro, tarjetas blancas; las franjas del embudo con el mismo degradado turquesa y texto oscuro;
las gemas iguales.

**Alcance:** ficha (Oportunidades).

## 5. Todos los colores siguen al ambiente (clima, hora y estación)

**Qué pasa hoy.** El sistema cambia su ambiente con el clima, la hora y la estación (el fondo se pone más cálido o más
frío), pero varios componentes tienen colores fijos y desentonan: por ejemplo, las cajas de arriba de cada proceso
quedan en un gris frío encima del fondo cálido, como un parche.

**Qué quiero.** Que **todos los colores de todos los componentes** se adapten al ambiente, como ya lo hace el fondo.
Propuesta aprobada en el lienzo «Colores que siguen al ambiente · antes y después»:
https://claude.ai/artifact/VbWMDg9oPkcmaKjy2uWPN8 (columna «Propuesta»; el componente «Escena» trae las fórmulas).

Tres piezas juntas:

1. **Las superficies nacen del fondo.** Ningún componente tiene su fondo, borde o texto escrito a mano: se calculan a
   partir del fondo del ambiente con `color-mix(in oklch, …)` sobre variables CSS, por ejemplo:
   - superficie (cajas, tarjetas, paneles): el fondo con un 7 % de blanco;
   - borde: el fondo con un 14 % de blanco;
   - superficie elevada o resaltada: el fondo con un 12 % de blanco;
   - texto suave: el fondo con un 55 % de blanco; texto principal: blanco cálido con un toque del tinte del ambiente.
   En modo claro, lo mismo pero hacia el negro. Así, si el fondo se calienta o se enfría, todo lo que se apoya en él
   cambia en la misma medida.
2. **Los colores de las franjas con temperatura.** Turquesa, azul, morado, gris y el oro de Mien mantienen su
   identidad, pero se mezclan con un **12 a 14 % del tinte del ambiente** (cálido al atardecer, frío de mañana o de
   noche). Siguen reconociéndose y dejan de pelear con el fondo. El azul de resaltado (observación 6 de la primera
   tanda) también.
3. **Vidrio en lo que flota:** el muelle, el globo de Mien, la agenda, los paneles deslizantes y los menús quedan
   semitransparentes (el fondo con transparencia) con desenfoque (`backdrop-filter`), para que dejen ver el ambiente.

**Lo que NO cambia:** los íconos de aplicación del muelle (la hoja del día de la agenda, y las aplicaciones de afuera)
y el logo del encabezado. Los colores de estado (sin atender, en curso, resuelto; ganada, perdida) toman un toque
leve del ambiente, pero siempre tienen que seguir distinguiéndose entre sí.

**Revisión de todo el sistema.** Busca **cada color escrito a mano** en los componentes (hex, `rgb()`, clases de color
fijas de Tailwind o de Filament) y pásalo a este mecanismo. Dime qué encontraste y dónde. El contraste tiene que
seguir cumpliendo (4,5:1 en textos) en todos los ambientes, en modo claro y oscuro: revisa los extremos (el más cálido
y el más frío).

**Alcance:** cruza módulos.

## 6. La «Línea de tiempo» de oportunidades: cronograma con orden y filtros

Me gusta la propuesta A, «Cronograma refinado» (lienzo: https://claude.ai/artifact/8LwWVwFrNuiRY5NBcLQT5D, primera
pizarra): una fila por oportunidad con su gema, nombre, cliente y monto; a la derecha, tramos redondeados por cada
etapa que recorrió (se oscurecen al avanzar), línea punteada desde hoy hasta un rombo dorado que es el cierre
esperado, y una línea vertical «Hoy». Si el cierre ya pasó, el rombo se pone coral y dice «venció». Reemplaza el
estilo actual de la vista.

**Orden por defecto, «Por urgencia»:**

1. Arriba, las **vencidas**: la que lleva más días vencida, primero. Su rombo en coral y, junto al monto, «venció hace
   N días».
2. Después, las **por vencer**: la que cierra más pronto, primero.
3. Si dos cierran el mismo día, primero la de **mayor monto**.
4. Al final, las que **no tienen fecha de cierre**, de mayor a menor monto, con un aviso suave «sin fecha».
5. Las **ganadas y perdidas no aparecen** por defecto (se ven con el filtro «Cerradas»).

Entre las vencidas y las por vencer va un separador fino con su cuenta («3 vencidas»), para que se note dónde termina
lo atrasado.

**Otros órdenes** (un selector «Ordenar por», al lado de los filtros):

- **Por monto:** de mayor a menor.
- **Por valor esperado:** monto × probabilidad de la etapa, de mayor a menor (lo que más pesa en el pronóstico).
- **Por etapa:** de la más avanzada (Negociación) a la menos (Prospecto).
- **Por días estancada:** la que lleva más tiempo en su etapa actual, primero.
- **Por cliente:** de la A a la Z.

En todos, el empate se rompe por la fecha de cierre más próxima. En estos órdenes las vencidas no suben solas, pero
siguen marcadas en coral.

**Filtros** (con el mismo estilo de chips que los de Tickets de ayuda, sin textos explicativos):

- **Buscador:** por nombre de la oportunidad o del cliente.
- **Vencimiento:** Vencidas · Esta semana · Este mes · Sin fecha.
- **Etapa:** un chip por etapa con su gema (Prospecto, Calificada, Propuesta enviada, Negociación) y «Cerradas»
  (Ganadas y Perdidas).
- **Responsable:** Mías (por defecto) · Todas · una persona.
- **Rango del eje:** Mes · Trimestre · Semestre (cambia cuánto tiempo abarca la parte derecha; «Hoy» siempre visible).

Un filtro activo se ve en el azul de resaltado; un clic más lo quita. Si nada coincide, la vista queda vacía con una
sola línea y un botón «Quitar filtros».

**Animación de «ordenando»:** al cambiar el orden o un filtro, las filas no se redibujan de golpe: cada una **se
desliza** desde su lugar viejo al nuevo (unos 350 ms, con un pequeño escalonado de fila en fila para que se vea que
se reacomodan). Las barras y el rombo viajan pegados a su fila. Las que salen por un filtro se desvanecen y la fila se
cierra; las que entran se abren y aparecen. La línea «Hoy», el encabezado del eje y los filtros no se mueven. Si la
persona pidió menos movimiento en su sistema, el cambio es directo, sin animación.

**Alcance:** ficha (Oportunidades, vista «Línea de tiempo»).
