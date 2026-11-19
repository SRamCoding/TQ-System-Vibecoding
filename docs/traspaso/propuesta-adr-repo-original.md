# ADR para el repo original · El MVP rápido como fuente de requisitos

<!-- Texto del ADR que el agente del repo original crea el día de la importación (COMO-IMPORTAR.md, paso 3), con el
     número siguiente libre y el estado «aceptada», con la fecha y las palabras de Sebastián al pedir la importación.
     Junto con el ADR se agregan: la fila en «Dónde está cada cosa» de AGENTS.md, la regla de precedencia y el archivo
     docs/planes/traspaso-mvp.md con el encabezado de abajo. -->

- **Estado:** aceptada (<fecha de la importación>, por Sebastián: «<sus palabras>»)
- **Fecha:** <fecha de la importación> (el MVP rápido se abrió el 2026-11-19)
- **Decide:** Sebastián
- **Toca:** cómo nace una tarea (mapa, planes), la precedencia de `AGENTS.md`, los registros del análisis y
  `docs/decisiones/`

## Contexto

Construir cada parte con todas las garantías (plan, pruebas, revisión, entrega) es lento para descubrir cómo tiene que
ser el sistema: muchas decisiones de pantalla y de negocio solo aparecen cuando se ve algo funcionando. Sebastián
abrió un repositorio aparte, el **MVP rápido**, copia de este en el commit `a5e5972` (2026-11-19), donde los agentes
construyen sin pruebas ni seguridad hasta que el resultado le gusta.

El riesgo es perder lo que este repositorio siempre cuidó: poder responder, para cualquier parte del sistema, quién
la pidió, cuándo se decidió, dónde se construyó y qué prueba demuestra que funciona (`docs/analisis/README.md`). Este
ADR existe para que el MVP rápido sea una fuente más de esa cadena, no un atajo que la corte.

## Decisión

1. **El MVP rápido es la fuente de requisitos de lo que cubre.** Su carpeta `docs/traspaso/` trae, por
   funcionalidad, una **ficha** (qué hace, navegación, operaciones, estados, datos, reglas con números, supuestos y
   atajos) y un **registro de decisiones** numeradas (`MV-001`…) que nunca se reescribe: una decisión que cambió
   queda como «reemplazada por» la nueva.
2. **Solo se construye una ficha aprobada**, desde su etiqueta `aprobado/<ficha>-v<n>`. Lo que está en curso puede
   cambiar.
3. **Precedencia.** Las decisiones vigentes de Sebastián en el registro valen como decisiones aceptadas, por encima
   de la especificación v4 y de los documentos de agosto, y por debajo de las convenciones. Si una contradice un ADR
   aceptado, gana la más reciente, y el ADR viejo se marca como reemplazado. Los **supuestos sin confirmar** de las
   fichas no valen como decisiones: se preguntan (regla 12).
4. **Cada pieza llega a su lugar de siempre, con su origen citado:**
   - la ficha se vuelve tareas del mapa con su plan; la fila y el plan citan ficha, versión y etiqueta;
   - las decisiones de alcance «ficha» van a «Decisiones» del plan, cada una con su `MV-0NN`;
   - las de alcance «cruza módulos» se vuelven ADR, aceptados con la fecha y las palabras del registro;
   - las de alcance «negocio» actualizan el análisis (versión nueva del bloque, línea en el registro de cambios) y
     entran al registro de validaciones como «en preparación» con la persona de Techquk que corresponda: la
     aprobación de Sebastián en el MVP rápido no es una validación con la empresa;
   - los supuestos que dependen de la empresa van a `pendientes.md`;
   - los atajos de la ficha y de `atajos-generales.md` se vuelven pasos y criterios de aceptación del plan.
5. **Este repositorio hace la ficha bien por dentro:** pruebas con los números de la ficha, seguridad y permisos,
   acciones registradas, estados como datos, concurrencia, auditoría e IA con sus reglas. La pantalla y la
   navegación se reproducen como están en la etiqueta, salvo que Sebastián diga otra cosa.
6. **El código del MVP rápido es referencia, no base:** se lee para entender y se escribe de nuevo con las
   convenciones de aquí (y con FastAPI y Vue cuando llegue el cambio de stack).
7. **Registro del traspaso** en `docs/planes/traspaso-mvp.md`: una fila por ficha traída, con su versión y
   etiqueta, las tareas, ADR y versiones del análisis que salieron de ella, y su estado (por traer, en construcción,
   construida, desactualizada si la ficha subió de versión). Es la puerta de entrada: desde cualquier pieza se vuelve
   a la decisión que la originó, y desde cualquier decisión, a lo que se construyó con ella.

## Consecuencias

Las tareas se vuelven más previsibles: el «qué» llega cerrado y el plan se concentra en el «cómo». La trazabilidad no
se pierde: se alarga un eslabón (necesidad → **MVP rápido** → análisis → diseño → construcción → prueba). Hay que
vigilar que el MVP rápido no esconda decisiones en el código: **si algo no está en una ficha o en el registro, no
está decidido**, aunque el código del MVP lo haga.

---

## Encabezado de `docs/planes/traspaso-mvp.md`

```markdown
# Traspaso desde el MVP rápido

Qué se trajo del MVP rápido y qué salió de cada ficha (ver el ADR «El MVP rápido como fuente de requisitos»). Una fila por ficha y versión.

| Ficha · versión | Etiqueta | Decisiones (MV) | Tareas | ADR | Análisis | Estado |
|---|---|---|---|---|---|---|
```
