# Plan: numeración por dueño, copia al ganar y comparación ofertado contra presupuesto (2.12)

- **Estado:** plan, sin empezar
- **Responsable:** Sebastián (área 1, Presupuesto y costo; la parte de Comercial la revisa el dueño del área 2)
- **Entrega:** por definir al empezar (`entrega/presupuesto-copia-al-ganar` propuesta)
- **Módulo(s):** `Erp/Presupuestos`, `Crm/Cotizaciones` (numeración), `Crm/Oportunidades` (al ganar), Nucleo
  (`NumeracionService`, ámbito)
- **Fase:** —
- **Creado:** 2026-11-16 · **Actualizado:** 2026-11-16

## Objetivo

Los costeos, cotizaciones y presupuestos se numeran por su dueño (oportunidad o proyecto), al ganar una oportunidad la
cotización aceptada se copia como presupuesto PRE-01 v1 del proyecto en el mismo paso que la marca ganada, y la ficha
del proyecto compara, partida por partida, lo ofertado contra el presupuesto vigente.

## Contexto que leí

- **ADR 0023** (aceptado el 2026-11-16; hoy en la rama `entrega/proyectos-portada`): numeración por dueño, copia al
  ganar, recorrido de documentos, formato visible y contenido de la copia.
- Plan 2.9 (presupuesto con APU, estados y versiones): hoy el presupuesto ofertado «pasa al proyecto sin copiarse»
  (`es_vigente`); esta tarea lo cambia.
- Plan 2.11 / árbol desde el presupuesto aprobado: pasa a leer la copia del proyecto.
- `NumeracionService::siguiente(..., $ambito)` (plan 1.6, D4): el ámbito ya existe y tiene su propio contador.

## Decisiones

- **D1.** (ADR 0023) Numeración por dueño:
  - Costeo de una oportunidad: `OP-2026-0007 · PRE-01 v1`, v2…
  - Cotización: `OP-2026-0007 · COT-01 v1`, v2… (COT-02, alternativas).
  - Presupuesto de un proyecto: `PRY-2026-0013 · PRE-01 v1`, v2…
  - Sin dueño: `PRE-2026-0031 v1`, ascendente de la empresa.
  - Se guarda con el ámbito sin guiones (`COT-OP2026.0007-01`) y se muestra «OP-2026-0007 · COT-01 v3»; dentro de la
    ficha de su dueño, solo «COT-01 v3».
- **D2.** (ADR 0023) **Copia al ganar, en el mismo paso:** marcar ganada, crear el proyecto y copiar van en una sola
  transacción; si la copia falla, no se gana. La copia guarda su origen (cotización y versión).
  - Cotización con costeo enlazado: se copia ese costeo con su APU y costos.
  - Solo de catálogo: una partida por línea, con el costo del catálogo.
  - Mixta: el costeo más las líneas de catálogo como partidas aparte.
- **D3.** (ADR 0023) **El costeo de una oportunidad ganada queda congelado:** no admite «Nueva versión». Las versiones
  siguen en el presupuesto del proyecto.
- **D4.** (desarrollador, 2026-11-16, «sí») **Comparación «Ofertado vs. Presupuesto vigente»** en la ficha del proyecto,
  partida por partida: lo ofertado (la copia v1, que es lo vendido), el presupuesto vigente (vN) y la diferencia, con
  el margen de cada uno (solo con `campos.ver-costos`). Si alguien baja o sube la meta, se ve.
- **D5.** En pantallas, el de la oportunidad se llama **Costeo** y el del proyecto **Presupuesto** (ADR 0023).

## Por decidir

- ~~**P1.**~~ **Decidida (Sebastián, 2026-11-16):** la versión nueva del árbol se permite **por una adenda o por una
  corrección interna** elegida de una lista (error de metrado, cambio de alcance interno, partida faltante), y **siempre
  la aprueba Gerencia**. Puede ir en esta tarea o en una aparte.
- ~~**P2.**~~ **Decidida (Sebastián, 2026-11-16): no aplica.** No hay ningún despliegue con datos reales: se editan las
  migraciones (ADR 0010) y las semillas y pruebas toman el formato nuevo.

## Pasos

- [x] 1. Plan desde el ADR 0023 y la decisión del chat (D4).
- [ ] 2. P1 y P2 respondidas.
- [ ] 3. Formato y regla de la base (`CHECK`) de `presupuestos.numero` y `cotizaciones.numero` con ámbito; numeración
  con el dueño en los servicios.
- [ ] 4. Copia al ganar (efecto de la transición a ganada) con su origen, en la misma transacción; los tres casos de D2.
- [ ] 5. Costeo de oportunidad ganada sin «Nueva versión».
- [ ] 6. El árbol desde el presupuesto aprobado lee la copia del proyecto.
- [ ] 7. Comparación «Ofertado vs. Presupuesto vigente» en la ficha del proyecto (registro de tarjetas o pestaña de
  2.61).
- [ ] 8. Pruebas: los números por dueño, los tres casos de copia, que si falla la copia no se gana, que el costeo ganado
  no tiene nueva versión, la comparación con el escenario del flujo 1 (19,525.00) y el margen oculto sin permiso.
- [ ] 9. `composer check:tarea` en verde.

## Preparado para lo diferido

- Contratos (C10): cuando exista, el monto contratado y sus adendas entran a la comparación de D4 como «Contratado».
- Recorrido de documentos (2.21d, D15): cada eslabón lo da su servicio; esta tarea completa la copia y el proyecto.

## Criterio de aceptación

- Las pruebas del paso 8 en verde.
- En pantalla: una oportunidad ganada muestra su recorrido completo y el proyecto su comparación ofertado contra
  presupuesto.

## Bitácora

- 2026-11-16 — Plan escrito desde el chat (ADR 0023 aceptado; D4 pedida por el desarrollador).
