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

### MV-001 · 2026-11-19 · La cartera de demostración son los nueve proyectos del plan viejo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «Los 9 del plan» entre reutilizarlos, reutilizarlos con fechas recientes o una cartera
  más chica.
- **Decisión:** la demo usa la cartera P1–P9 del plan `2026-11-16-datos-desarrollo-cartera.md` tal cual: sus montos,
  partidas, márgenes, fechas históricas 2024–2026 y estados (cinco cerrados, tres en ejecución, uno en preparación).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-002 · 2026-11-19 · El hospital de la demo es inventado

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** «Inventarlo», en vez de mantener el nombre real que permitía la excepción D2 del plan viejo.
- **Decisión:** P6 y P9 son del «Hospital Regional de Valle Norte», inventado. En los datos de demo no queda ningún
  nombre real; la excepción D2 del plan viejo no se trae.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-003 · 2026-11-19 · Gastos de demostración como origen provisional del costo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «Gasto de demostración» entre eso, usar solo la PC-99 o no tener gastos hasta el tramo 3.
- **Decisión:** mientras no existan órdenes, tareo ni rendiciones, el costo de los proyectos de demo entra por un
  documento mínimo «gasto de demostración», imputado a una partida de control (o a la PC-99) con sus montos
  comprometido, ejecutado y pagado. Desaparece cuando lleguen las órdenes de compra (tramo 3).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-004 · 2026-11-19 · Embudo abierto de la demo

- **Ficha:** `fichas/t0-datos-demo.md`
- **Dijo Sebastián:** eligió «~15 abiertas + 3 perdidas».
- **Decisión:** además de las nueve ganadas, la demo trae unas quince oportunidades abiertas repartidas en todas las
  etapas y tres perdidas, con cotizaciones en distintos estados, sobre unas quince cuentas públicas y privadas.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T0: datos de demostración»

### MV-005 · 2026-11-19 · El tramo 1 empieza por el puente al ERP

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** eligió «Puente al ERP» entre empezar por él, por licitaciones o por la agenda.
- **Decisión:** el orden del tramo 1 es: puente al ERP (ganar → proyecto con presupuesto copiado y comparación),
  después licitaciones, agenda, catálogo y leads.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-006 · 2026-11-19 · El proyecto nace solo al ganar

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** «Solo, al ganar», en vez de un botón «Crear proyecto» aparte.
- **Decisión:** ganar la oportunidad, crear su proyecto y copiar lo cotizado como su presupuesto ocurren en un solo
  paso; si la copia falla, no se gana (confirma el ADR 0023 del repo original).
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-007 · 2026-11-19 · Numeración por dueño desde ahora

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** «Sí, ahora», aunque haya que reconstruir la base.
- **Decisión:** costeos, cotizaciones y presupuestos se numeran por su dueño como dice el ADR 0023:
  «OP-2026-0007 · COT-01 v1», «OP-2026-0007 · PRE-01 v1», «PRY-2026-0013 · PRE-01 v1»; sin dueño, «PRE-2026-0031 v1».
- **Alcance:** cruza módulos
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-008 · 2026-11-19 · La comparación ofertado contra presupuesto va en su propia pestaña

- **Ficha:** `fichas/t1-puente-erp.md`
- **Dijo Sebastián:** eligió «Pestaña propia» entre eso, columnas dentro del presupuesto o un panel deslizante.
- **Decisión:** la ficha del proyecto tiene una pestaña «Ofertado vs. presupuesto», partida por partida, junto a la del
  presupuesto; el tablero muestra la diferencia total y lleva a esa pestaña.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «T1: puente al ERP»

### MV-009 · 2026-11-19 · Hasta terminar el sistema, cada ficha se da por aprobada

- **Ficha:** general
- **Dijo Sebastián:** «al final que termines con todo el sistema allí empezaremos a hacer cambios, por ahora asume que
  sí me gusta».
- **Decisión:** el agente construye la ruta completa sin detenerse a pedir aprobación: cada ficha pasa a «aprobada»
  con su etiqueta al terminarla. Lo que normalmente se preguntaría se resuelve con la opción recomendada y queda en
  «Supuestos sin confirmar» de su ficha. Los cambios de Sebastián llegan al final, con fichas en versión nueva.
- **Alcance:** ficha (forma de trabajo de este repositorio; no es una decisión del producto)
- **Estado:** vigente
- **Commit:** el de «Fichas t0-datos-demo v2 y t1-puente-erp aprobadas»

### MV-010 · 2026-11-19 · El modo «Sistema» sigue el cielo de la sede

- **Ficha:** `fichas/transicion-cielo.md` (se abre al construirla)
- **Dijo Sebastián:** «en modo sistema quiero que exista una transición pues viendo el clima de la ubicación y también
  el transicionamiento de día a noche»; para la ubicación eligió «sede» (en vez de la del navegador).
- **Decisión:** en el modo de apariencia «Sistema», la pantalla deja de copiar al sistema operativo y sigue el cielo
  de la sede de la empresa: claro u oscuro según la salida y puesta del sol de ese lugar y fecha, con una transición
  gradual al amanecer y al atardecer, y el clima la matiza (nublado o lluvia más apagado y atardecer más temprano).
  «Claro» y «Oscuro» siguen fijos.
- **Alcance:** cruza módulos (interfaz del núcleo)
- **Estado:** reemplazada por MV-012
- **Commit:** se anota al construirla

### MV-011 · 2026-11-19 · La transición del cielo va después del tramo 3

- **Ficha:** general
- **Dijo Sebastián:** «hazlo después del tramo 3».
- **Decisión:** el orden sigue: tramo 3 (flujo 2) y, al terminarlo, la transición del cielo (MV-010); después, el tramo 4.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** —

### MV-012 · 2026-11-19 · La ubicación del navegador manda si ya está permitida

- **Ficha:** `fichas/transicion-cielo.md` (se abre al construirla)
- **Dijo Sebastián:** «ahora si ya tiene la ubicación permitida pues esa manda».
- **Decisión:** igual que MV-010 (en «Sistema», la pantalla sigue el cielo: sol, transición gradual y clima), pero la
  ubicación es la del navegador si la persona ya dio el permiso; si no lo dio, la de la sede de la empresa. El sistema
  no pide el permiso solo para esto.
- **Alcance:** cruza módulos (interfaz del núcleo)
- **Estado:** vigente
- **Commit:** se anota al construirla

### MV-013 · 2026-11-20 · El cielo cambia como en la realidad, sin saltos

- **Ficha:** `fichas/t3b-transicion-cielo.md`
- **Dijo Sebastián:** «algo que sea similar a lo que pasa en realidad, el sol se va escondiendo y el ambiente es como
  de un color y luego más oscuro poco a poco hasta que en una hora en particular ya está oscuro».
- **Decisión:** en el tema «Sistema», el ambiente sigue la altura del sol de forma continua: luz dorada mientras baja,
  naranja y rosado al ocultarse, la hora azul y luego oscuro, sin un cambio de tema perceptible; al amanecer, al revés.
  Precisa MV-012 (la ubicación sigue igual).
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** el de «Transición del cielo»

### MV-014 · 2026-11-20 · Todo lo pendiente entra al MVP

- **Ficha:** general
- **Dijo Sebastián:** «todas de una vez», sobre las partes simuladas (XML, SUNAT, notas por foto con IA), lo que faltaba
  del tramo 5 (moneda extranjera, servicios con vigencia, liquidación, consorcios) y los opcionales (gran momento,
  asistente de ayuda con IA, solicitudes de permiso).
- **Decisión:** se construyen todas antes de cerrar el MVP. La validación con SUNAT queda lista para activarse con las
  credenciales de la empresa; mientras, simulada.
- **Alcance:** ficha
- **Estado:** vigente
- **Commit:** los de «Cierre del tramo 5» y «Opcionales»
