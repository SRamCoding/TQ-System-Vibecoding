# Registro de decisiones

Toda decisión que tome Sebastián en este repositorio, en orden, con un número que no se repite (`MV-001`, `MV-002`…).
Es el origen que citarán los planes, los ADR y los registros del repo original: por eso **nunca se borra ni se
reescribe una entrada**. Si Sebastián cambia de idea, se agrega una decisión nueva y la anterior pasa a «reemplazada
por MV-0NN». Solo cambia el estado.

Las fichas dicen qué decisiones están vigentes para cada funcionalidad; este registro dice cómo se llegó ahí.

## Cómo se anota

Una entrada por decisión, la más nueva abajo:

```markdown
### MV-0NN · AAAA-MM-DD · <título corto>

- **Ficha:** `fichas/<ficha>.md` (o «general»)
- **Dijo Sebastián:** sus palabras, citadas cortas, o lo que eligió entre las opciones que le dio el agente
- **Decisión:** qué queda decidido, en una o dos frases
- **Alcance:** ficha · cruza módulos · negocio
- **Estado:** vigente · reemplazada por MV-0NN · descartada
- **Commit:** `<hash>` donde se construyó
```

**Alcance** le dice al repo original dónde va cada una:

- **ficha:** se queda dentro de una funcionalidad (cómo se ve, cómo se navega, qué campo pide). Allá va a la sección
  «Decisiones» del plan de la tarea.
- **cruza módulos:** toca la estructura, una costura entre áreas, el núcleo o un mecanismo compartido. Allá se vuelve
  un ADR.
- **negocio:** dice cómo trabaja Techquk (una regla, un monto, quién aprueba). Sebastián la decidió, pero la empresa
  no la validó: allá entra al análisis y al registro de validaciones como «por validar» con la persona que
  corresponda.

Lo que el agente eligió solo **no va aquí**: va a «Supuestos sin confirmar» de su ficha. Si Sebastián después lo
confirma, recién entonces se vuelve una decisión con su número.

## Decisiones
