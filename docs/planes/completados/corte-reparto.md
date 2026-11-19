# Corte para repartir la etapa 2

- **Estado:** cumplido; **reparto aplicado el 2026-11-16** (ADR 0020, «El reparto aplicado»). Vigente del 2026-11-12 al
  2026-11-16
- **Decidido por:** el desarrollador, 2026-11-12
- **Lo leen:** todas las sesiones de agentes, antes de abrir cualquier tarea nueva

## Para qué existe

El reparto de la etapa 2 ya está diseñado en el ADR 0020: cuatro áreas, cada una con su dueño, que se hablan solo por
las costuras. Mientras tanto, Sebastián trabaja solo con varias sesiones en paralelo (AGENTS.md, «Modo actual»), y en
ese modo es fácil seguir construyendo en las áreas de los demás.

Eso ya pasó con el área 2 (Comercial): Cuentas y Oportunidades están hechas y Cotizaciones va en camino, cerca de 13 de
sus 23 puntos. Las áreas 3 (Contratos y abastecimiento) y 4 (Obra y personas) siguen casi intactas. Cada tarea más que
se haga en un área ajena le quita trabajo propio a su futuro dueño y le deja código que no escribió.

Este documento fija **hasta dónde se trabaja antes de repartir**. El criterio es uno: se reparte cuando cada persona
puede empezar su primera tarea sin esperar a Sebastián. No se espera a que las áreas «queden más listas».

## Lo que sí se termina antes de repartir

Son seis cosas. Cada una dice cómo se comprueba que está hecha, para que cualquier sesión pueda revisarlo en el
repositorio sin preguntar.

1. **La entrega de Cotizaciones** (`entrega/crm-cotizaciones`, tarea 2.22 con sus partes 2.22a, 2.22b y 2.22c).
   Se termina porque dejarla a medias es peor que no haberla empezado, y porque de ella sale la oportunidad ganada
   con su cotización aceptada (C3), que es lo que Contratos necesita para arrancar.
   *Hecha cuando:* la entrega está fusionada en `main` y la fila 2.22 del mapa tiene ✅.

2. **La entrega de Plantillas** (`entrega/plantillas`, tarea 2.11, con el ADR 0022). Es núcleo y la van a usar las
   demás áreas.
   *Hecha cuando:* la entrega está fusionada en `main`, `docs/decisiones/0022-plantillas-personalizables.md` está en
   `main` y la fila 2.11 del mapa tiene ✅. La rama `plan/adr-plantillas` queda fusionada o borrada.

3. **Los selectores de catálogo** (tarea 2.0, rama `plan/nucleo-selectores-catalogo`). Su propio plan dice que va
   antes de que las áreas armen sus formularios: si no, cada área arma los suyos a su manera y luego hay que
   rehacerlos.
   *Hecha cuando:* su entrega está fusionada en `main` y la fila 2.0 del mapa tiene ✅.

4. **Las solicitudes de permiso** (tarea 2.0b, rama `plan/nucleo-solicitudes-permiso`). Sus decisiones están
   propuestas, ninguna aprobada. No hace falta construirla antes del reparto, pero sí **decidir qué pasa con ella**:
   o se hace ahora, o queda en espera con su punto de extensión anotado, para que nadie arme su propio botón de «pedir
   permiso».
   *Hecha cuando:* la tarea está fusionada, o su plan dice «en espera» con la decisión de Sebastián y la fecha.

5. **El patrón de carpetas para documentos con versiones** (`arreglo/patron-carpetas-versiones`). Es una convención
   de interfaz: conviene que todos arranquen con ella.
   *Hecha cuando:* la rama está fusionada en `main` y borrada.

6. **El `check:tarea` desde la última corrida verde** (plan
   `activos/2026-11-11-herramientas-pruebas-desde-ultima-verde.md`). Falta la verificación del desarrollador (paso 5).
   Es la herramienta con la que va a trabajar todo el equipo.
   *Hecha cuando:* el plan está en `completados/`.

## Lo que no se empieza antes de repartir

Ninguna sesión abre una tarea nueva de las áreas 2, 3 o 4 que no esté en la lista de arriba. En concreto, no se
empiezan Licitaciones, Actividades, las convocatorias del OECE, Contratos, Compras, Almacén, Subcontratos,
Valorizaciones, Personal ni Tareo.

Tampoco se adelantan los esqueletos de las costuras que faltan: el costo de las horas (C5), las compras y el almacén
como costo (C6), las certificaciones del personal (C9) y el contrato (C10). Son de sus dueños y son justamente su
primera tarea: Lennart empieza por el contrato, que esperan el margen del área 1 y las valorizaciones del área 4; Yair
empieza por el costo de las horas, que espera el APU.

Si una sesión cree que algo fuera de la lista es imprescindible para repartir, no lo empieza: lo propone a Sebastián
con su razón, y él decide si entra en la lista.

**Agregado por Sebastián (2026-11-15):** con las seis cosas ya fusionadas, la portada y la ficha de Proyectos (2.61,
`entrega/proyectos-portada`, junto con 1.4ab y 1.4ac de la interfaz) se terminan **antes** de repartir («hazlo después
de los PR… considera la repartición después de arreglar el módulo Proyectos»). El reparto se escribe al cerrar esa
entrega, con 2.61 ya descontada de los puntos del área 4.

**Agregado por Sebastián (2026-11-16):** Oportunidades con portada de fotos, tablero de botonera y ficha con recorrido
(2.21d, plan `completados/2026-11-16-crm-oportunidades-vitrina.md`) también se termina **antes** de repartir («va antes
del corte»), aunque es del área 2. El reparto descuenta sus puntos del área 2.

## Lo que sigue sin importar el corte

El área 1 (Presupuesto y costo) es de Sebastián antes y después del reparto, así que sus tareas pueden seguir en
paralelo: el árbol desde el presupuesto (2.9b, `entrega/presupuesto-arbol`), la reclasificación (2.8) y el margen.
Las tareas abiertas de la etapa 1 (1.4d, 1.4d2, 1.4k y 1.14) tampoco bloquean el reparto.

## Lo que decide Sebastián al repartir

Esto no lo resuelve ninguna sesión (AGENTS.md, regla 12); se deja escrito para no olvidarlo.

- **Cuántas personas.** Si son cuatro (Sebastián más tres) o tres.
- **El reacomodo de puntos.** Como el área 2 quedó a medias, el ADR 0020 ya no está equilibrado. Con cuatro personas,
  la propuesta del 2026-11-12 es: las convocatorias del OECE (8 puntos) pasan del área 4 al área 2, porque viven en el
  CRM y son trabajo comercial; los subcontratos (3 puntos) pasan del área 3 al área 4, porque viven en
  `Erp/ObraPublica` junto a las valorizaciones. Lo que falta quedaría en unos 18 puntos para Comercial, 21 para
  Contratos y abastecimiento y 16 para Obra y personas. Con tres personas, Sebastián se quedaría el área 1 más
  Licitaciones y Actividades, y el OECE volvería al área 4. Los puntos son relativos y se recalibran.

## Decidido por Sebastián (2026-11-16)

Se aplica cuando el corte esté cumplido (la 2.21d fusionada), con los cambios de «Cómo confirma…». Vale solo para la
etapa 2; las etapas 3, 4 y 5 se reparten al abrirlas.

- **Cuántas personas:** cuatro, pero **dos a tiempo completo** (Sebastián y Yair) y **dos a tiempo parcial** (Lennart y
  Ander). Se reparte por capacidad, contando el tiempo parcial como medio tiempo: reemplaza las dos propuestas de arriba
  (la de cuatro personas iguales y la de tres).
- **El reparto:**
  - **Ander (parcial):** lo que falta de Comercial, Licitaciones y Actividades (unos 10 puntos).
  - **Lennart (parcial):** Contratos y Compras (unos 13). Empieza por el contrato (C10), como estaba previsto.
  - **Yair (completo):** Obra y personas con Proyectos (fases, hitos, avance, cierre), valorizaciones, subcontratos,
    personal, tareo y catálogo, **más Almacén**, que pasa del área 3 (unos 24).
  - **Sebastián (completo):** el área 1 (presupuesto, árbol y dinero del proyecto; presupuesto de la empresa), el
    núcleo, la revisión de los PR y **las convocatorias del OECE** (unos 8 además de su área).
- **Por qué Almacén a Yair:** está pegado a la obra (almacén de obra, despacho, series) y Compras más Almacén a medio
  tiempo es demasiado. La costura C6 (compras y almacén como costo) queda entre Lennart y Yair.
- **Al aplicarlo:** el ADR 0020 se actualiza con este reparto y sus puntos, y las costuras que cambian de dueño (C6, el
  OECE) avisan a quien las usa.

## Cómo confirma una sesión que ya se puede repartir

Al cerrar cualquier tarea o entrega de la lista, la sesión hace esta revisión **sin esperar a que se la pidan**, y lo
mismo cuando Sebastián pregunte «¿ya puedo repartir?» (regla en AGENTS.md, desarrollador, 2026-11-12). Revisa en el
repositorio (con `git fetch` y `main` al día, no de memoria) cada una de las seis cosas con su criterio de «hecha», y
responde con el estado de cada una: hecha, o qué le falta y en qué rama está.

Si las seis están hechas, la respuesta empieza con **«Listo para repartir»** y propone, sin aplicarlos, los cambios
que hacen el reparto efectivo:

- En `AGENTS.md`, quitar el bloque «Modo actual» de «Trabajo en equipo».
- En el mapa, los responsables reales en las filas de la etapa 2 que siguen abiertas.
- En el ADR 0020, el reparto ajustado, con la fecha y la cantidad de personas que decidió Sebastián.
- Mover este documento a `docs/planes/completados/` con la fecha del reparto.

Si falta alguna, la respuesta empieza con **«Todavía no»** y dice cuántas faltan.
