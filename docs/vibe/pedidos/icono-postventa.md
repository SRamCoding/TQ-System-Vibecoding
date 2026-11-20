# Ícono de la caja «Postventa»

Pedido de Sebastián (20 de noviembre de 2026). Regístralo como decisión mía en `docs/traspaso/decisiones.md`, con
alcance «ficha», y anótalo en la ficha que lleve el inicio por procesos. Es el mismo criterio que el ícono de
«Conseguir el negocio».

## Qué quiero

La caja **Postventa** (franja Misionales) lleva un ícono propio en lugar del genérico de Heroicons: **un
rompecabezas de cuatro piezas encajadas, con una flecha que sale de la pieza de abajo a la derecha y sigue
ondulando hacia la derecha**. La idea: el proyecto ya está armado y completo, pero la relación con el cliente
continúa (tickets, garantías y renovaciones).

**Solo el dibujo, sin ningún texto**: ni en el ícono ni debajo de él (la referencia trae «Garantía Extendida»
debajo: eso no va). La caja muestra su nombre de siempre, «Postventa», como las demás.

La imagen de referencia va adjunta: `icono-postventa.png`. Guárdala en `docs/vibe/referencias/` para que quede en
el repositorio.

## Cómo se ve en la referencia

- **Cuatro piezas en un cuadrado de 2 × 2**, encajadas: los bordes que se tocan llevan las lengüetas y huecos
  típicos de un rompecabezas (una lengüeta redonda que entra en la pieza vecina).
- **La flecha:** sale del borde derecho de la pieza de abajo a la derecha, hace dos o tres ondas suaves hacia la
  derecha y termina en una punta de flecha abierta (dos trazos en «>»), fuera del cuadrado.
- **Estilo:** de línea (contorno), sin relleno, con las puntas y las uniones redondeadas. La referencia usa dos
  tonos (verde arriba y azul oscuro abajo), pero aquí manda el estilo del sistema.

## Cómo debe quedar en el sistema

- **Igual que los demás íconos de las cajas**: contorno con el trazo 1,9 de `docs/convenciones/interfaz.md`, en
  `currentColor`, para que tome el color de su franja y de la estación, y funcione con el degradado de la caja
  (`tq-trazo-<clave>`) y con el brillo del ícono como los demás. **Un solo color, no dos tonos.**
- Lienzo de 24 × 24, como los de Heroicons. Como el dibujo es más ancho que alto por la flecha, el rompecabezas
  ocupa unos 16 × 16 a la izquierda y la flecha el resto; que no se vea más grande ni más pesado que sus vecinos.
- Con un trazo de 1,9 en 24 × 24 las lengüetas se pueden empastar: si pasa, deja una sola lengüeta por borde
  interior y hazlas un poco más grandes.
- El mismo ícono se usa en la caja del inicio, en la barra lateral y en la portada de Postventa.
- Revísalo en modo claro y oscuro y en las cuatro estaciones.

## Un punto de partida en SVG

Dibújalo fino si hace falta; esto es solo la base:

```svg
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"
     stroke-linecap="round" stroke-linejoin="round">
  <!-- contorno del rompecabezas -->
  <rect x="2" y="4" width="15" height="15" rx="1.5" />
  <!-- unión vertical con lengüeta a la derecha (arriba) y a la izquierda (abajo) -->
  <path d="M9.5 4v3.2a1.4 1.4 0 1 1 0 2.6V11.5" />
  <path d="M9.5 11.5v2a1.4 1.4 0 1 0 0 2.6V19" />
  <!-- unión horizontal con lengüeta hacia abajo (izquierda) y hacia arriba (derecha) -->
  <path d="M2 11.5h2.6a1.4 1.4 0 1 0 2.6 0h2.3" />
  <path d="M9.5 11.5h2.3a1.4 1.4 0 1 1 2.6 0H17" />
  <!-- flecha ondulada que sale de la pieza de abajo a la derecha -->
  <path d="M17 15.5c1 0 1-1.5 2-1.5s1 1.5 2 1.5" />
  <path d="m20.5 13.5 1.5 2-1.5 2" />
</svg>
```

Cuando esté, dime qué abrir para verlo en el inicio.
