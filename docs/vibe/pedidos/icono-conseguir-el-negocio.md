# Ícono de la caja «Conseguir el negocio»

Pedido de Sebastián (20 de noviembre de 2026). Regístralo como decisión mía en `docs/traspaso/decisiones.md`, con
alcance «ficha», y anótalo en la ficha que lleve el inicio por procesos.

## Qué quiero

La caja **Conseguir el negocio** (franja Misionales) lleva un ícono propio en lugar del genérico de Heroicons:
**una montaña de dos picos con una bandera clavada en la cima del pico principal**: ganar el negocio es llegar a
la cima.

**Solo el dibujo, sin ningún texto**: ni en el ícono ni debajo de él. La caja muestra su nombre de siempre,
«Conseguir el negocio», como las demás.

La imagen de referencia va adjunta: `icono-conseguir-el-negocio.png`. Guárdala en `docs/vibe/referencias/` para
que quede en el repositorio.

## Cómo se ve en la referencia

- **Dos picos:** el de la izquierda, más alto, es el principal; el de la derecha, más bajo, queda detrás y a la
  derecha. Abajo, una base recta común.
- **Nieve en las cimas:** una línea quebrada (en zigzag) cruza cada pico un poco debajo de la punta.
- **Una bandera:** un mástil recto sale de la cima del pico principal hacia arriba. La bandera ondea a la derecha
  y su borde libre tiene una muesca en «V», como un banderín.
- **Estilo:** de línea (contorno), sin relleno, con las puntas y las uniones redondeadas. La referencia usa dos
  tonos, verde y azul oscuro, pero aquí manda el estilo del sistema.

## Cómo debe quedar en el sistema

- **Igual que los demás íconos de las cajas**: contorno con el trazo 1,9 de `docs/convenciones/interfaz.md`, en
  `currentColor`, para que tome el color de su franja y de la estación, y funcione con el degradado de la caja
  (`tq-trazo-<clave>`) y con el brillo del ícono como los demás. **Un solo color, no dos tonos**: el color lo pone
  la franja.
- Lienzo de 24 × 24, como los de Heroicons, para que tenga el mismo tamaño y peso visual que sus vecinos.
- El mismo ícono se usa en la caja del inicio, en la barra lateral y en la portada de Conseguir el negocio.
- Revísalo en modo claro y oscuro y en las cuatro estaciones: tiene que leerse igual de bien que los demás.

## Un punto de partida en SVG

Dibújalo fino si hace falta; esto es solo la base, en el estilo de Heroicons:

```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"
     stroke-linecap="round" stroke-linejoin="round">
  <!-- montañas -->
  <path d="M2.5 20.5 9 9.5l4 6.2 2.5-3.7 6 8.5H2.5Z" />
  <!-- nieve -->
  <path d="m6.9 13 1.3 1 1-1.2 1 1.2 1.1-.9" />
  <!-- mástil -->
  <path d="M9 9.5V2.75" />
  <!-- bandera con muesca -->
  <path d="M9 3h6l-1.6 2 1.6 2H9" />
</svg>
```

Cuando esté, dime qué abrir para verlo en el inicio.
