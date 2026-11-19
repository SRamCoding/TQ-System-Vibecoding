# 0023 — Numeración de presupuestos y cotizaciones por su dueño, y copia al ganar

- **Estado:** aceptada (2026-11-16, por el desarrollador: numeración por dueño, copia al ganar y recorrido de
  documentos; el formato visible y el contenido de la copia, el mismo día: «ok»).
- **Fecha:** 2026-11-16
- **Decide:** desarrollador

## Contexto

Hoy `PRE-2026-0001` y `COT-2026-0001` son correlativos globales por año y la versión va aparte (`numero_version`). El
número no dice de quién es el documento. Además, al ganar una oportunidad el presupuesto aprobado «pasa al proyecto sin
copiarse» (plan 2.9, `es_vigente`).

El desarrollador (2026-11-16): «un presupuesto en la mayoría de casos pertenece a un proyecto, entonces debería de
identificarse el proyecto y su versión respecto al proyecto, no en general; si no pertenece a un proyecto, ahí sí una
indexación ascendente; una cotización pertenece a una oportunidad, entonces debería identificarse la cotización y su
versión». Y: «si una cotización ganó y pasa a presupuesto, simplemente se hace una copia de esa cotización ganada y ahí
se puede hacer que nace de un proyecto; ese tipo de acciones, que hicieron el proceso completo, quiero que se puedan
ver».

## Decisión

1. **El correlativo es por dueño.** `NumeracionService` ya acepta un ámbito (plan 1.6, D4) que va dentro del número y
   tiene su propio contador:
   - Cotización: ámbito = su oportunidad. COT-01, COT-02 (alternativas) dentro de la oportunidad, cada una con sus
     versiones.
   - Presupuesto de una oportunidad: ámbito = la oportunidad (PRE-01, PRE-02…).
   - Presupuesto de un proyecto: ámbito = el proyecto (PRE-01, PRE-02… del proyecto).
   - Presupuesto sin oportunidad ni proyecto: correlativo ascendente de la empresa, como hoy.
2. **Al ganar, se copia.** La cotización aceptada (con su presupuesto de origen) se **copia** como presupuesto nuevo
   del proyecto, que nace con el número del proyecto y versión 1, y guarda de dónde vino (`origen`: la cotización y su
   versión). Los documentos de la oportunidad no cambian ni se renumeran. Reemplaza al «pasa al proyecto sin
   copiarse» del plan 2.9.
3. **El recorrido se ve.** La ficha de la oportunidad y la del proyecto muestran la cadena: presupuesto de la
   oportunidad → cotizaciones y versiones → la aceptada → la copia en el proyecto → el proyecto. Prototipo:
   `docs/diseno/fuentes/prototipos/oportunidades-portada-ficha.html`, sección «Recorrido de los documentos».

## Confirmado después (desarrollador, 2026-11-16)

- **Techquk costea antes de cotizar.** El presupuesto con APU de la oportunidad existe y se llama **Costeo** en las
  pantallas de la oportunidad; **Presupuesto** es el del proyecto, el que nace por copia al ganar y gobierna el control.
- Ejemplo completo: `OP-2026-0007` → costeo `OP-2026-0007 · PRE-01 v1, v2` → `OP-2026-0007 · COT-01 v1…v3` → ganada →
  `PRY-2026-0013` → `PRY-2026-0013 · PRE-01 v1` (copia) → adenda → `PRE-01 v2`. Sin dueño: `PRE-2026-0031 v1`.
- Un presupuesto aprobado no se edita: cada cambio es «Nueva versión» (plan 2.9). Falta la versión nueva del árbol
  cuando cambia el presupuesto del proyecto (tarea aparte; preguntas abiertas: con qué motivos se permite y quién la
  aprueba).

## También decidido (desarrollador, 2026-11-16, «ok»)

- **Formato visible.** El ámbito no admite guiones (los usa el número para separar sus partes): se guarda
  `COT-OP2026.0007-01` y se muestra «OP-2026-0007 · COT-01 v3»; dentro de la ficha de la oportunidad, solo «COT-01 v3».
- **Qué se copia exactamente al ganar:** si la cotización enlaza un costeo, ese costeo con su APU y costos;
  si es solo de catálogo, una partida por línea con el costo del catálogo; si es mixta, el costeo más las líneas de
  catálogo como partidas aparte.

## Consecuencias

- Cambian las reglas de formato (`CHECK`) de `presupuestos.numero` y `cotizaciones.numero`; las migraciones aún se
  pueden editar (ADR 0010) si no hubo despliegue con datos reales.
- El plan 2.9 (presupuesto vigente del proyecto) y el árbol desde el presupuesto aprobado (2.11) leen la copia del
  proyecto, no el presupuesto de la oportunidad.
- Cruza dos áreas (Comercial y Presupuesto y costo): por eso es un ADR.
