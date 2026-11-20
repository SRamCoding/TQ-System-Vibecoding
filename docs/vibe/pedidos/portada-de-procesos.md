# Portada de cada proceso: frase, cajas centradas y contenido debajo

Pedido de Sebastián (20 de noviembre de 2026). Regístralo como decisión mía en `docs/traspaso/decisiones.md`, con
alcance «cruza módulos» (cambia la navegación de todas las cajas de proceso), y anótalo en la ficha del inicio por
procesos. **Reemplaza** lo decidido antes en esta misma sesión: la portada con cajas que llevan a otra pantalla, y las
pestañas con miga de pan dentro de cada pantalla interna.

## Hoy

Al abrir una caja de proceso (por ejemplo, Conseguir el negocio) aparece arriba a la izquierda el título, una
descripción, la etiqueta de la franja («MISIONALES») y las cajas internas alineadas a la izquierda. Al presionar una
caja, se va a otra pantalla.

## Qué quiero

1. **Arriba, centrado, una frase representativa** del proceso, como saludo de esa pantalla (lista abajo). Una sola
   línea, grande, **en el color de su franja** (turquesa en Misionales, azul en Estratégicos, morado en De Apoyo,
   gris en Plataforma), con la versión que corresponda a modo claro y oscuro según `inicio-ventana-y-mien.md`, y con
   contraste suficiente para leerse como texto (en claro, el turquesa en su versión oscura).
2. **Debajo, solo las cajas internas, juntas como un solo cuerpo**, y ese cuerpo **centrado arriba** en el ancho de
   la pantalla: el conjunto de cajas es la barra de navegación del proceso. Sin el título, sin la descripción y sin la
   etiqueta de la franja.
3. **Las cajas son la navegación de la pantalla.** Al presionar una, **no se va a otra página**: su contenido aparece
   **debajo de las cajas**, en la misma pantalla, y las cajas se quedan arriba. La caja elegida se marca (con el color
   de su franja) para que se sepa dónde se está. Presionar otra caja cambia el contenido de abajo.
4. **La dirección (URL) sí cambia** con la caja elegida (por ejemplo, `/conseguir-el-negocio/oportunidades`), para
   que las tarjetas de Mien, la barra lateral y los enlaces lleven directo a la caja con su contenido abierto, y para
   que el botón «atrás» del navegador funcione.
5. **El mismo patrón en todas las cajas de proceso**, las once.
6. Las cajas usan la altura pareja del pedido `altura-de-cajas.md`.
8. **Las cajas se pueden cambiar de posición**: arrastrando una caja a otro lugar dentro de la barra (y con teclado,
   para quien no use mouse). El orden queda guardado **para cada persona**, en sus preferencias, como el resto de lo
   personal; la caja del medio que se abre al entrar se calcula con el orden de esa persona. Una opción «Volver al
   orden original» lo deshace. El orden original de cada proceso lo define el sistema; en Conseguir el negocio:
   Convocatorias del SEACE, Oportunidades, Campañas (Oportunidades al centro).

## Frases propuestas (se pueden cambiar)

| Caja | Frase |
|---|---|
| Techquk | Así va Techquk. |
| Conseguir el negocio | ¡Vamos por esas oportunidades! |
| Gerencia de proyectos | Cada proyecto, bajo control. |
| Abastecer | Lo que la obra necesita, a tiempo. |
| Facturación y cobranza | Lo trabajado, cobrado. |
| Postventa | El cliente sigue siendo nuestro. |
| Presupuestar y cotizar | Números claros, ofertas que ganan. |
| Finanzas | La caja en orden. |
| Personas | La gente correcta en cada obra. |
| Directorio y catálogos | Todo en su sitio. |
| Sistema | Lo que hace funcionar todo. |

Las frases van en un lugar donde se puedan cambiar sin tocar el código (como las frases del saludo).

7. **Al entrar a una caja de proceso se abre siempre la caja del medio** de las que esa persona puede ver según su
   rol, con su contenido debajo. Con un número impar de cajas, la del centro (de 3, la 2.ª; de 5, la 3.ª). Con un
   número par, la de la izquierda de las dos del centro (de 4, la 2.ª; de 6, la 3.ª). Si se llega desde un enlace
   (Mien, la barra lateral), se abre la caja de ese enlace.

## Cuando esté

Dime qué abrir para verlo, con un ejemplo de una caja de proceso con muchas cajas internas (Finanzas) y una con pocas
(Postventa).
