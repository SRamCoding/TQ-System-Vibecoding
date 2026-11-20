# Observaciones de Sebastián, segunda tanda

Pedido de Sebastián (20 de noviembre de 2026). Cada observación es una decisión suya: regístrala en
`docs/traspaso/decisiones.md` con su alcance y anótala en la ficha que corresponda (sube su versión). Hazlas en
orden, un commit por observación. Si algo choca con lo ya construido, pregúntame antes.

## 1. Las cajas no se actualizan: solo se desliza la marca de la elegida

**Qué pasa hoy.** Al pasar de una caja a otra en la barra de un proceso (por ejemplo, de Requerimientos a Órdenes en
Abastecer), da la sensación de que toda la barra se vuelve a dibujar.

**Qué quiero.** Que la barra de cajas **se quede quieta** y lo único que cambie sea:

- **La marca de la elegida se desliza** de la caja anterior a la nueva: el fondo resaltado y la línea de abajo (en
  el color de la franja) se mueven en horizontal hasta la caja elegida, en unos 250 ms, suave al final. El texto y el
  ícono de la nueva caja toman el color de resaltado mientras la anterior vuelve a su estado normal.
- **El contenido de abajo se reemplaza** sin recargar la página (observación 1 de la primera tanda), con un cambio
  suave y corto (por ejemplo, el contenido nuevo aparece desvaneciéndose y subiendo unos pocos px).
- La barra no parpadea, no cambia de tamaño y no pierde su posición; tampoco la frase de debajo.
- Al usar «atrás» del navegador, la marca se desliza igual hacia la caja anterior.
- Con «reducir movimiento» o «reducir animaciones», la marca salta sin deslizarse.

**Pista técnica.** La barra de cajas queda **fuera** de la zona que se reemplaza (o persistida con `@persist`), y la
marca es un solo elemento que se mueve con `transform` según la caja elegida; el contenido de abajo se cambia con
`wire:navigate` o con un componente Livewire que cambia de vista sin rehacer la barra.

**Alcance:** cruza módulos (vale para las barras de cajas de los once procesos).

## 2. Lo mismo en las barras laterales internas

**Qué quiero.** El mismo comportamiento de la observación 1 en **las barras laterales internas** de cada módulo: por
ejemplo, en Listas (dentro de Directorio y catálogos) o en Configuración (dentro de Sistema), al pasar de una opción a
otra:

- **La barra lateral interna se queda quieta**: no se vuelve a dibujar, no parpadea, conserva su desplazamiento y lo
  que estaba abierto.
- **La marca de la opción elegida se desliza en vertical** de la opción anterior a la nueva (el fondo resaltado y la
  barrita, en el azul de resaltado de la observación 6 de la primera tanda), en unos 250 ms, suave al final.
- **Solo cambia el contenido** de la derecha, sin recargar la página, con el mismo cambio suave que en las cajas.
- Igual con «atrás» del navegador; con «reducir movimiento», la marca salta sin deslizarse.

Vale para todas las barras laterales internas del sistema. La barra lateral principal (la de los módulos) también
debería quedarse quieta al navegar (observación 1 de la primera tanda), aunque cambiar de módulo sí cambie todo el
contenido.

**Alcance:** cruza módulos.

## 3. Sin ruta; el nombre del módulo bajo la frase, con la letra de TECHQUK

**Qué pasa hoy.** Dentro de cada proceso, debajo de la barra de cajas y la frase, sigue apareciendo la ruta (la miga de
pan, por ejemplo «Listas › Catálogos › Listado») y un título grande («Catálogos»).

**Qué quiero.**

- **Quitar la ruta** en todas las pantallas.
- **Debajo de la frase** del proceso (por ejemplo, «Todo en su sitio.»), **el nombre del módulo**, es decir, de la caja
  de proceso en la que se está (por ejemplo, **DIRECTORIO Y CATÁLOGOS**), centrado.
- Ese nombre va con **la letra del logo de TECHQUK**: **Audiowide**, en mayúsculas, con el mismo espaciado de letras
  que el logo (la fuente ya se sirve desde `public/fonts/audiowide`; reusa la regla de `.tq-logo-nombre`). En el
  color de su franja, a un tamaño que se lea como título de la pantalla sin competir con el logo.
- El orden de arriba abajo queda: barra de cajas, frase, nombre del módulo, y el contenido.

- **Quitar también el título grande** de la pantalla interna («Catálogos» en la captura): la caja elegida y la opción
  de la barra lateral ya dicen dónde se está.
- **Tamaño del nombre del módulo, profesional y sobrio** (es un título, no un cartel):
  - escritorio: **22 px** (1,375 rem), interlineado 1,2, espaciado de letras 0,06 em; celular: **18 px**;
  - **una sola línea** siempre: los nombres más largos («FACTURACIÓN Y COBRANZA», «DIRECTORIO Y CATÁLOGOS») caben a
    ese tamaño; si en celular no entra, baja a 16 px antes de partirlo en dos líneas;
  - **más chico que el nombre TECHQUK del logo**, para que no compitan;
  - separación: unos 6 px debajo de la frase y unos 28 px antes del contenido;
  - en modo claro, la versión oscura del color de su franja si la normal no llega a 4,5:1 contra el fondo.

**Alcance:** cruza módulos.
