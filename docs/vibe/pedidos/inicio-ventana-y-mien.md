# Inicio centrado: la ventana del saludo y la estrella de Mien

Pedido de Sebastián (20 de noviembre de 2026). Regístralo como decisión mía en `docs/traspaso/decisiones.md`, con
alcance «ficha», y anótalo en la ficha que lleve el inicio por procesos (sube su versión). Va **después** de la
ronda de reorganización por procesos: la parte de abajo del inicio (las franjas en dos columnas) es la de esa ronda.

La propuesta visual aprobada está en el lienzo «Mien · propuesta visual de la estrella»:
https://claude.ai/artifact/QvZvCuBrPV7VLCCcf7ZraW (tablero 1: el inicio; tablero 2: los estados de la estrella). Lo
que sigue la describe para que la construyas igual.

## 1. Todo lo de arriba del inicio va centrado

De arriba abajo y centrado en el ancho de la página:

1. **La ventana** con la animación del momento del día (punto 2).
2. **El saludo**, grande: el mismo saludo de hoy («¡Buenos días, Sebastián!»), en negrita, unos 40 px.
3. **La frase**, debajo, en cursiva y en gris suave, el mismo texto que hoy, con un ancho máximo de unos 620 px.
4. **Mien**, sin caja (punto 3).
5. Debajo, las franjas en dos columnas, como dejó la ronda de reorganización.

## 2. La ventana del saludo

La animación del saludo ya no va suelta: se ve **a través de una ventana**, como si se mirara afuera.

- **Forma:** base recta, lados rectos y arco de medio punto arriba; un alféizar (una línea un poco más ancha) debajo
  de la base. Tamaño aprobado: **168 × 180 px**. El arco ocupa casi la mitad de la altura.
- **Travesaños, pocos:** uno horizontal donde empieza el arco, otro horizontal a media altura de la parte recta, y un
  parante vertical al centro, de arriba abajo. Nada más.
- **Marco:** tono madera oscuro (`#6B4A2E` en la propuesta), contorno de 5 px y travesaños de 3 px. En modo oscuro,
  un tono que se lea sobre el fondo carbón.
- **Lo de adentro:** la escena del momento del día, **recortada por la forma de la ventana** (lo que queda fuera del
  marco no se ve). Usa la ambientación que el saludo ya tiene, y conéctala con la transición del cielo
  (`t3b-transicion-cielo`): la ventana muestra el cielo de la sede según la hora (amanecer, día, atardecer, noche) y,
  si el sistema lo sabe, el clima. Las fechas especiales (el gran momento) también se ven dentro de la ventana.
- **Movimiento:** suave y lento (en la propuesta, el sol sube y baja unos px en 6 s). Con «reducir movimiento» o
  «reducir animaciones», quieta.
- La ventana es decorativa: `aria-hidden="true"`.

SVG de la propuesta (escena de la mañana), como base:

```svg
<svg width="168" height="180" viewBox="0 0 168 180" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <defs>
    <clipPath id="tqVentana"><path d="M14 172V84a70 70 0 0 1 140 0v88z"/></clipPath>
    <linearGradient id="tqCielo" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#BFE3F5"/><stop offset="1" stop-color="#FCEFD2"/>
    </linearGradient>
  </defs>
  <g clip-path="url(#tqVentana)">
    <rect width="168" height="180" fill="url(#tqCielo)"/>
    <g class="tq-sol"><circle cx="84" cy="92" r="18" fill="#F7C948"/><circle cx="84" cy="92" r="27" fill="#F7C948" opacity=".25"/></g>
    <path d="M38 50c3-5 11-6 15-1 5-1 9 3 8 7H34c-1-3 1-6 4-6z" fill="#fff" opacity=".85"/>
    <path d="M0 136c26-14 52-16 78-6s56 10 90-4v54H0z" fill="#9CC9A0"/>
    <path d="M0 152c30-10 60-10 88-2s52 6 80-2v32H0z" fill="#7DB582"/>
  </g>
  <path d="M14 172V84a70 70 0 0 1 140 0v88z" stroke="#6B4A2E" stroke-width="5"/>
  <path d="M14 84h140M84 14v158M14 128h140" stroke="#6B4A2E" stroke-width="3"/>
  <path d="M6 176h156" stroke="#6B4A2E" stroke-width="5"/>
</svg>
```

Los ids del `clipPath` y del degradado tienen que ser únicos en la página.

## 3. Mien: solo la estrella, sin caja

Mien deja de ser una caja. Es **una estrella dorada grande, centrada**, con un diseño especial:

- **Estrella** de cinco puntas, unos 112 px, rellena con un degradado de oro (`#F9D77E` arriba a `#D9A21F` abajo) y
  un contorno fino en el dorado del sistema (`ColoresMarca::DORADO`, `#936600`). Un brillo pequeño en la punta de
  arriba.
- **Halo:** un resplandor dorado radial detrás, de unos 168 px, que late despacio (4 s: crece un poco y se aclara).
- **Destellos:** tres destellos pequeños de cuatro puntas alrededor, que titilan desfasados (3 s cada uno).
- **Número de pendientes:** un círculo en la punta superior derecha con cuántas cosas esperan a la persona (en claro,
  fondo tinta y número blanco; en oscuro, fondo dorado `#F2C14E` y número oscuro). Sale de las mismas tarjetas de Mien.
- **Debajo:** «Mien» en negrita, en dorado, y una línea corta en gris con el resumen: «5 pendientes · 2 esperan tu
  firma».
- **Toda la estrella es un enlace a Mien** (`<a>` con `aria-label` que diga cuántos pendientes hay).

**Estados:**

| Estado | Cómo se ve |
|---|---|
| En reposo | Halo que late suave, destellos titilando |
| Al pasar el mouse (o con foco) | La estrella crece un poco (escala 1,06) y el halo se enciende más |
| Con pendientes | Aparece el número |
| Todo al día | Sin halo ni número, la estrella más pálida: no hay nada que atender |

- **Modo oscuro:** la estrella y el texto en `ColoresMarca::DORADO_OSCURO` (`#F2C14E`), el halo un poco más tenue.
- **Reducir movimiento / animaciones:** halo y destellos quietos; el aumento al pasar el mouse, sin animación.
- En la **barra lateral**, Mien sigue con su ícono de estrella dorada de siempre; lo especial es solo en el inicio.

## 4. Los colores de las franjas

Decisión aparte (regístrala con su propio número, alcance «cruza módulos»): reemplaza los colores por área (CRM
morado, ERP turquesa, Núcleo azul noche). Cada franja queda en un escalón de claridad distinto, para que se
distingan aunque alguien no perciba bien los colores, y el oro queda solo para Mien:

| Franja | Color | Constante de `ColoresMarca` |
|---|---|---|
| Misionales | Turquesa | `TURQUESA`; como texto en modo claro, siempre la versión oscura (`TURQUESA_LOGO_CLARO` o más oscura si no llega a 4,5:1) |
| Estratégicos | Azul noche | `AZUL_NOCHE` (en modo oscuro, una versión clara que se lea sobre carbón) |
| De Apoyo | Morado | `MORADO` (`MORADO_CLARO` en modo claro, como hoy) |
| Plataforma | Gris neutro | Nuevo: un gris de Filament, sin color de marca |
| Mien | Oro | `DORADO` / `DORADO_OSCURO`, como hoy |

**Modo oscuro** (tablero «Inicio centrado, modo oscuro» del lienzo). El azul noche no se puede quedar igual: sobre el
fondo carbón no se ve. En oscuro cada franja usa su versión clara, y siguen en escalones distintos:

| Franja | Modo claro | Modo oscuro |
|---|---|---|
| Misionales | turquesa (texto: versión oscura) | `#00DAFF` |
| Estratégicos | `#01006F` | `#6F9BFF` (azul medio) |
| De Apoyo | `#7C6EFF` / `#B35FD0` | `#C3A6FF` (lila claro) |
| Plataforma | gris `#6E7781` | gris `#9DA5AE` |
| Mien | `#936600` | `#F2C14E` |

El azul y el lila de modo oscuro se parecen más que en claro: si al verlos en el sistema cuesta distinguirlos, aclara
el lila o baja el azul antes de preguntarme. En modo oscuro, el marco de la ventana va en una madera más clara
(`#A07850`) y la escena es la de la noche (cielo azul profundo, luna, estrellas).

Todo lo que hoy toma el color del área (cajas, barra lateral, «En curso», estaciones) lo toma ahora de su franja.
Revisa el contraste en las cuatro estaciones y en los dos modos.

## Cuando esté

Avísame qué abrir para verlo, en modo claro y oscuro, y cuéntame si conectaste la ventana con la transición del
cielo o si quedó solo con la ambientación del saludo.
