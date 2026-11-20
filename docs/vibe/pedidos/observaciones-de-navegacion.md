# Observaciones de Sebastián sobre el uso del sistema

Pedido de Sebastián (20 de noviembre de 2026). Cada observación es una decisión suya: regístrala en
`docs/traspaso/decisiones.md` con su alcance y anótala en la ficha que corresponda (sube su versión). Hazlas en
orden, un commit por observación. Si algo no está claro, pregúntame antes de construir.

## 1. Cambiar de pantalla sin recargar toda la página

**Qué pasa hoy.** Al cambiar entre opciones de un panel, por ejemplo en Configuración, que tiene su barra lateral a la
izquierda, al elegir otra opción se recarga **toda la página**: parpadea, se vuelve a dibujar todo (la barra lateral,
el encabezado, el muelle) y se pierde el lugar donde estaba. Ese efecto es molesto.

**Qué quiero.** Que al cambiar de opción **solo cambie el contenido**, sin recargar la página entera:

- La barra lateral, el encabezado, el muelle y el resto del marco **se quedan quietos**; solo se reemplaza la zona
  del contenido, con una transición suave y corta (o ninguna), sin pantallazo en blanco.
- La barra lateral conserva su estado: lo que estaba abierto o cerrado y su posición de desplazamiento.
- Vale para **toda la navegación** del sistema: la barra lateral principal, las barras laterales internas (como la de
  Configuración), las cajas del inicio, las cajas de navegación de cada proceso (`portada-de-procesos.md`), y los
  enlaces de Mien y de las tarjetas.
- La dirección (URL) se actualiza igual, y el botón «atrás» del navegador funciona.
- Mientras carga el contenido nuevo, una señal discreta (una barra fina arriba, por ejemplo), no la página en blanco.

**Pista técnica.** Filament y Livewire ya lo permiten: el modo SPA del panel (`->spa()`) y `wire:navigate` en los
enlaces propios, con `@persist` para lo que debe quedarse vivo (el muelle, el reloj, la animación de la ventana). Revisa
que nada se rompa al no recargar: scripts que esperan la carga completa de la página, el modo desarrollo, las
estaciones, los atajos de teclado y lo que se inicia una sola vez.

**Alcance:** cruza módulos.

## 2. Ícono de «Tickets de ayuda»

**Qué quiero.** La caja **Tickets de ayuda** (dentro de Sistema) lleva un ícono propio en lugar del genérico: **un
ticket con unos audífonos de soporte**. Imagen de referencia adjunta: `icono-tickets-de-ayuda.png`; guárdala en
`docs/vibe/referencias/`.

**Cómo se ve en la referencia:**

- **El ticket:** un rectángulo horizontal de esquinas redondeadas, con una muesca en semicírculo en el centro del lado
  izquierdo y otra en el centro del lado derecho, como un boleto.
- **A la izquierda, adentro:** unos audífonos de soporte (diadema en arco, dos auriculares y un micrófono que sale
  del auricular izquierdo y se curva hacia abajo).
- **A la derecha, adentro:** dos líneas horizontales, como texto; la de arriba un poco más larga.

**Cómo debe quedar:** igual que los demás íconos propios (`icono-conseguir-el-negocio.md`): solo el dibujo, sin
texto; de línea, trazo 1,9, `currentColor` (el color de su franja), lienzo de 24 × 24, el mismo en la caja, la barra
lateral y donde aparezca; revisado en claro y oscuro y en las cuatro estaciones. Como el ticket es ancho y bajo, que
no se vea más pequeño que sus vecinos: que ocupe casi todo el ancho del lienzo.

SVG de partida:

```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"
     stroke-linecap="round" stroke-linejoin="round">
  <!-- ticket con muescas a los lados -->
  <path d="M3.5 6h17a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4V7a1 1 0 0 1 1-1z" />
  <!-- audífonos -->
  <path d="M6 13.5v-1.5a3 3 0 0 1 6 0v1.5" />
  <path d="M5.5 12.5h1.2v2.2H5.5zM11.3 12.5h1.2v2.2h-1.2z" />
  <path d="M6.2 14.7c0 1.3.9 1.8 2.3 1.8" />
  <!-- texto -->
  <path d="M14.5 10.5h4.5M14.5 13.5h3.5" />
</svg>
```

**Alcance:** ficha.

## 3. Quitar los textos explicativos de todas las pantallas

**Qué pasa hoy.** Muchas pantallas tienen, debajo del título, un texto que explica cómo funcionan. Por ejemplo, en
Tickets de ayuda: «Los abre el asistente «n_n» cuando alguien le cuenta un problema y lo confirma. Llevan la pantalla y
la conversación.» Es texto innecesario.

**Qué quiero.** **Quitar siempre esos textos**, en todas las pantallas: el subtítulo o descripción de la cabecera de
cada pantalla. Con el patrón de `portada-de-procesos.md`, cada pantalla interna queda con la frase del proceso arriba,
las cajas de navegación y el contenido, nada más. Si una explicación hace falta de verdad, va en la ayuda (el
asistente «n_n») o en un ícono de información que se abre al pasar el mouse, nunca como texto fijo en la pantalla.

Revisa todas las pantallas, también las de las fichas, los paneles y los formularios. No quites las etiquetas de los
campos ni los mensajes de error o de confirmación: solo los textos que explican la pantalla.

**Alcance:** cruza módulos.

## 4. Filtros en Tickets de ayuda

Propuesta visual aprobada en el lienzo «Tickets de ayuda · propuesta de filtros»:
https://claude.ai/artifact/MuDxcC3mfv47874foW8KFk

**Dónde:** en una franja fija **arriba de la lista**, siempre a la vista (no dentro del botón del embudo de Filament).

- **Estado, como pestañas con contador**, a la izquierda: **Pendientes** (sin atender + en curso; se abre al entrar)
  · Sin atender · En curso · Resueltos · Todos.
- **El estado «abierto» pasa a llamarse «Sin atender»** en todo el sistema (el ticket llegó y nadie lo tomó aún). Los
  tres estados quedan: Sin atender, En curso, Resuelto.
- **Buscador**, a la derecha: busca en el código, el resumen y la conversación.
- **Filtros en botones pequeños**, junto al buscador; un filtro activo se ve lleno con su valor y una «✕» para
  quitarlo, los demás con borde punteado y «+»:
  - **Proceso:** en qué caja de proceso pasó el problema, deducido de la pantalla donde estaba la persona, agrupado por
    las 11 cajas.
  - **Quién lo abrió:** solo para quien atiende soporte (cada persona ve solo sus tickets).
  - **Fecha:** hoy, esta semana, este mes o un rango.
  - **Esperando más de…:** 2 o 7 días.
  - **«Limpiar filtros».**
- **La lista:** código, problema, proceso y pantalla, quién lo abrió, estado (con color: sin atender en ámbar, en curso
  en azul, resuelto en verde, que también se distinguen por claridad) y cuánto lleva esperando (en ámbar desde 2
  días).
- **Orden:** los pendientes, del más antiguo al más nuevo (se atienden por turno); los resueltos, del más nuevo al
  más antiguo.
- **Se recuerdan los filtros** de cada persona al salir y volver.

Prioridad y quién lo está atendiendo **no** entran por ahora: el ticket no guarda esos datos.

**Alcance:** ficha.

## 5. Animación de las tablas al filtrar

**Qué quiero.** Cada vez que se presiona un filtro (una pestaña, un filtro en botón, el buscador, el orden), la
animación es la propia de una tabla: **las filas se mueven**, no aparece un cambio brusco.

- Las filas que **siguen** después del filtro se deslizan desde su posición anterior a la nueva.
- Las que **salen** se desvanecen y se recogen hacia arriba.
- Las que **entran** aparecen desde un poco más abajo, una tras otra, con un pequeño escalonado (unos 40 ms entre
  filas).
- Rápida: unos 300 ms en total, suave al final. Nada que haga esperar para leer.
- Con «reducir movimiento» o «reducir animaciones», sin animación.
- Vale para **todas las tablas y listas** del sistema que se filtran o se ordenan, no solo para Tickets de ayuda. Las
  vistas de tarjetas (tableros que se arrastran) usan la misma idea con las tarjetas.

En el lienzo de Tickets de ayuda se ve la entrada escalonada al cambiar de pestaña.

**Alcance:** cruza módulos.

## 6. El azul de resaltado, igual para todos

**Qué quiero.** El azul con el que hoy se resalta lo elegido (por ejemplo, la opción «Estados de documentos» en la
barra lateral de Configuración y los enlaces «Cambiar nombre y color») **me gusta y se queda**, y es **el mismo para
todo el sistema**: no cambia según la franja ni el módulo.

- Es el color principal del sistema (`ColoresMarca::paletaPrincipal()`: azul noche en modo claro, `#6F9BFF` en modo
  oscuro).
- Lo usan: la opción elegida en **las barras laterales internas** de cualquier pantalla (como la de Configuración),
  los enlaces de acción, el foco de los campos y del buscador, las pestañas y los filtros activos (también los de
  `Tickets de ayuda`, que en el lienzo están en gris: van en este azul).
- **La excepción es la barra lateral principal, la de los módulos:** esa sí sigue con el color de cada franja
  (turquesa, azul, morado, gris), como quedó en `inicio-ventana-y-mien.md`. También siguen con el color de su franja
  las cajas, la frase de cada proceso y los íconos de las cajas.

En resumen: **el color de la franja dice dónde estás; el azul dice qué elegiste.**

**Alcance:** cruza módulos.
