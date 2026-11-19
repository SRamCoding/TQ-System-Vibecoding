# Cómo se importa este MVP al repo original

Este archivo es para el agente del repo original (`SRamCoding/TechQuk-IntegralSystem`) el día en que Sebastián le
entregue este repositorio terminado (`SRamCoding/TQ-System-Vibecoding`, por enlace o en zip) y le diga algo como «actualiza el proyecto en base a esto, pero aquí
con pruebas y seguridad». Ese agente **no tiene que preguntar qué es esto ni cómo se usa**: está todo aquí. Sigue los
pasos en orden.

La idea de fondo: aquí se decidió **qué** es el sistema; allá se construye **bien**, sin volver a decidir nada y sin
perder la trazabilidad que el repo original siempre llevó (quién lo pidió, cuándo se decidió, dónde se construyó y
qué prueba demuestra que funciona).

## Antes de entregar el zip (lo hace el agente de este repo)

Cuando Sebastián diga que el MVP está terminado, el agente de este repositorio hace una **auditoría de cierre** y
la deja escrita al final de `indice.md`, en «Cierre»:

1. Todas las fichas aprobadas, con su etiqueta. Las que no, listadas con el motivo.
2. Cada decisión del registro citada por alguna ficha (o marcada «general»), y cada ficha citando solo decisiones
   vigentes.
3. **Nada en el código que no esté en una ficha.** Se compara el código con la etiqueta `arranque`, el punto de
   partida de este repositorio (`git diff --stat arranque`): cada módulo, tabla, pantalla o regla nueva tiene que aparecer en alguna ficha. Lo que
   no aparece se agrega a su ficha o se le pregunta a Sebastián.
4. Los supuestos sin confirmar de todas las fichas, juntos en una sola lista, para preguntarlos de una vez.
5. `atajos-generales.md` al día.
6. Todo subido a GitHub (`SRamCoding/TQ-System-Vibecoding`), etiquetas incluidas. Si se entrega como zip, va **con
   la carpeta `.git`**: las etiquetas `aprobado/…` y los commits que cita el registro tienen que poder abrirse.

## Al recibir el zip (lo hace el agente del repo original)

### 1. Abrir y leer

Clonar el repo o descomprimir el zip en una carpeta aparte (nunca encima del repo original). Leer, en este orden: `indice.md` (con su
«Cierre»), `decisiones.md`, `atajos-generales.md` y las fichas. El código se abre solo para entender algo que la
ficha no deja claro, desde la etiqueta de la ficha.

### 2. Una sola ronda de preguntas

Juntar en un solo mensaje a Sebastián: los supuestos sin confirmar y las fichas no aprobadas. El MVP rápido lo trabajó
Sebastián solo y el repo original quedó quieto desde el commit `a5e5972`, así que no debería haber nada que conciliar;
igual se revisa `git log a5e5972..origin/main` en el repo original, y si apareciera algo, entra en esta misma ronda. Con sus respuestas, los supuestos confirmados se vuelven
decisiones con número nuevo, y los demás se descartan o pasan a `pendientes.md`. Después de esta ronda no se vuelve
a preguntar lo que ya está en el registro.

### 3. La decisión que lo habilita

Crear el ADR con el texto de `propuesta-adr-repo-original.md` (número siguiente libre), **aceptado** con la fecha del
día y las palabras de Sebastián al pedir la importación. Agregar a `AGENTS.md` la fila del registro del traspaso y la
regla de precedencia del ADR. Crear `docs/planes/traspaso-mvp.md` con su encabezado.

### 4. Pasar las decisiones a su lugar

Cada decisión vigente del registro, según su alcance (está explicado en `decisiones.md`):

- **cruza módulos** → un ADR por tema (se pueden juntar varias MV en uno), aceptado con la fecha y las palabras del
  registro, citando sus números MV;
- **negocio** → el análisis (`docs/analisis/`): versión nueva del bloque y su línea en el registro de cambios con
  origen «MVP rápido, MV-0NN»; y una entrada «en preparación» en el registro de validaciones para revisarla con la
  persona de Techquk que corresponda;
- **ficha** → espera al plan de su tarea (paso 6).

Las reemplazadas no se construyen; se citan en el «Contexto» de un ADR solo si explican un camino descartado.

### 5. El mapa

Rehacer lo que falta del mapa de etapas a partir de las fichas, en el orden de `docs/vibe/ruta-mvp.md`: cada ficha
aprobada da una o varias tareas, cada fila cita su ficha, versión y etiqueta. Llenar `traspaso-mvp.md` con una fila
por ficha. Lo que el repo original ya tenía construido y la ficha cambió es una tarea de ajuste, no de construcción.

### 6. Cada tarea, con el proceso de siempre

A partir de aquí se trabaja como el repo original manda (planes, entregas, pruebas por niveles, revisor), con tres
atajos de lectura que quitan casi todo el trabajo de análisis:

- **Decisiones del plan** = las decisiones vigentes de la ficha, copiadas con su número MV. No se rediscuten.
- **Criterios de aceptación y pruebas** = «Reglas y números» de la ficha, con los montos exactos, más el escenario del
  v4 si lo hay.
- **Pasos** = construir las «Operaciones» y los «Estados» de la ficha como acciones registradas y estados del
  núcleo, reproducir «Pantallas y navegación» tal como se ven en la etiqueta, y resolver uno por uno los «Atajos
  tomados» y los atajos generales que apliquen.

Si para entonces el repo original ya está en FastAPI y Vue, las «Operaciones» son la lista de endpoints y «Pantallas y
navegación» es la especificación del frontend; el código de este repo sigue sirviendo para ver cómo se comporta.

### 7. Cerrar el círculo

Cada tarea terminada actualiza su fila de `traspaso-mvp.md` (tarea, ADR, versión del análisis, estado). Cuando todas
las fichas están construidas, el MVP rápido queda como archivo: no se vuelve a tocar, pero sus etiquetas siguen
siendo la referencia citada.
