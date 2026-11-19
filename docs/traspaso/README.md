# Traspaso al repo original

Este repositorio existe para encontrar **cómo tiene que ser** el sistema: qué hace, cómo se navega, qué se ve y qué
decidió Sebastián en cada caso. Cuando algo queda como él quiere, el repo original
(`SRamCoding/TechQuk-IntegralSystem`) lo construye en serio: con sus pruebas, su seguridad, sus acciones registradas,
sus estados como datos y todas sus convenciones. El repo original **no vuelve a decidir nada de lo que se decidió
aquí**: solo se encarga de hacerlo bien por dentro.

Para que eso funcione, lo que se aprende aquí no puede quedar solo en el código (que se hizo rápido y sin red) ni en
la cabeza de Sebastián. Queda escrito en esta carpeta.

## Qué hay aquí

- **`COMO-IMPORTAR.md`**: los pasos para el final: la auditoría de cierre que hace el agente de aquí antes de
  entregar el zip, y lo que hace el agente del repo original al recibirlo. Con eso, la importación no necesita
  explicaciones.
- **`indice.md`**: la lista de fichas con su estado. Es lo primero que lee el repo original.
- **`fichas/`**: una ficha por funcionalidad (un módulo, una pantalla grande, un flujo), con la plantilla de
  `plantilla-ficha.md`. Dice qué hace en presente, las decisiones de Sebastián, la navegación, los datos, las reglas
  y sus números, los supuestos que nadie confirmó y **los atajos que se tomaron**, que son justo lo que el repo
  original tiene que resolver.
- **`decisiones.md`**: el registro de todas las decisiones de Sebastián, numeradas (`MV-001`…), que nunca se borran:
  si cambia de idea, se agrega otra y la vieja queda como reemplazada. Es el origen que citan los planes y los ADR
  del repo original.
- **`atajos-generales.md`**: los atajos que valen para todo el repositorio (no se repiten en cada ficha).
- **`propuesta-adr-repo-original.md`**: el texto del ADR que el repo original crea el día de la importación, ya
  aceptado. Nada de esta carpeta se sube al repo original antes de ese día.

## El ciclo de una ficha

1. **En curso.** Al empezar algo nuevo, el agente abre su ficha. Cada pedido de Sebastián sobre esa funcionalidad
   actualiza la ficha y el registro de decisiones en el mismo commit que el código. La ficha dice cómo es **ahora**;
   el registro guarda **cómo se llegó**, con cada cambio de idea.
2. **Aprobada.** Cuando Sebastián dice que así le gusta, el agente pasa la ficha a «aprobada», anota la fecha, la
   versión de la ficha y el commit, y crea la etiqueta `aprobado/<ficha>-v<n>` sobre ese commit. Esa etiqueta es la
   señal para el repo original: ese estado del código es la referencia viva de la ficha.
3. **Cambiada después de aprobada.** Si Sebastián vuelve a tocar algo aprobado, la ficha sube de versión (v2, v3…),
   dice en «Cambios desde la versión anterior» qué cambió, y al aprobarse lleva su nueva etiqueta. Así el repo
   original sabe si lo que ya construyó quedó viejo.

## Cómo llega al repo original, con trazabilidad completa

El repo original no se queda con «el resultado»: se queda con el resultado **y su historia**, en sus propios
documentos, como siempre trabajó. Cada pieza de aquí tiene su lugar allá:

- **La ficha aprobada** se vuelve una o varias tareas del mapa de etapas, cada una con su plan; la fila y el plan
  citan la ficha, su versión y su etiqueta.
- **Las decisiones de alcance «ficha»** entran a la sección «Decisiones» del plan, cada una con su origen
  (`MV-0NN`, etiqueta).
- **Las de alcance «cruza módulos»** se vuelven ADR nuevos en `docs/decisiones/`, aceptados con la fecha y las
  palabras de Sebastián del registro, como se hizo siempre (por ejemplo, el ADR 0025).
- **Las de alcance «negocio»** actualizan el análisis (`docs/analisis/`, con su versión nueva y su línea en el
  registro de cambios, origen: la ficha) y entran al registro de validaciones como «por validar» con la persona de
  Techquk que corresponda. La aprobación de Sebastián en este repo no es una validación con la empresa.
- **Los supuestos sin confirmar** se preguntan antes de construir (regla 12 del repo original) y, si dependen de la
  empresa, van a `docs/decisiones/pendientes.md`.
- **Las decisiones reemplazadas** no se construyen, pero se pueden citar en «Contexto» de un ADR cuando explican por
  qué se descartó un camino.
- **Los atajos** de la ficha y los generales se vuelven pasos y criterios de aceptación del plan.

Además, el repo original lleva un registro del traspaso (`docs/planes/traspaso-mvp.md`): qué ficha y qué versión se
trajo, qué tareas, ADR y versiones del análisis salieron de ella, y en qué estado está. Así, desde cualquier pieza
del sistema se puede volver a la decisión de Sebastián que la originó, y desde cualquier decisión, a lo que se
construyó con ella.

## Por qué las fichas no hablan de código

El repo original va a pasar a FastAPI y Vue (decisión del 19 de noviembre de 2026), y este repo es Laravel. Por eso
la ficha describe el comportamiento —pantallas, navegación, operaciones con sus campos, datos, reglas, números—, no
clases. Las operaciones de la ficha se convierten directamente en los endpoints del backend nuevo, y la navegación en
la del frontend. El código de aquí queda como referencia que se puede abrir y probar (con la etiqueta), y la sección
«Dónde está en el código» ayuda a encontrarlo.
