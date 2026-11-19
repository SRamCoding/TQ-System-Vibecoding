# 0014 — El análisis se versiona por bloques y por etapa de madurez

- **Estado:** aceptada
- **Fecha:** 2026-10-15
- **Decide:** Sebastián, analista funcional y responsable técnico

## Contexto

El análisis creció por capas sin un esquema de versiones: documentos de agosto en «v2.1», actualizados
después a «v3.0»; una especificación «v4» que reemplazó a un «ERP v3» que no se conserva; un diseño de
pantallas del CRM hecho en otra cuenta de Claude. Para saber qué es verdad hoy sobre un tema había que
leer varios documentos y aplicar la precedencia de [ADR 0009](0009-fuentes-de-verdad.md). Y no había
dónde ver qué partes se habían revisado con las personas de Techquk.

Además, el CRM y el ERP avanzan a ritmos distintos: el 15 de octubre de 2026 el CRM ya estaba en
revisión con el área comercial mientras el ERP seguía en especificación.

## Decisión

El análisis vive en `docs/analisis/` y se versiona en tres bloques independientes (CRM, ERP y Común)
con números `MAYOR.MENOR.PARCHE`. Antes de la línea base, el segundo número dice la etapa: 0.1
diagnóstico, 0.2 propuesta, 0.3 especificación, 0.4 validación. La 1.0 es la línea base aprobada;
desde ahí cada cambio entra con su origen. El detalle está en `docs/analisis/versionado.md`.

## Consecuencias

- El número de versión dice qué tan maduro está cada bloque, no cuántas veces se editó.
- El CRM puede llegar a 1.0 y construirse sin esperar al ERP.
- Toda revisión con una persona de Techquk se anota en `docs/analisis/validaciones.md`; lo que no está
  anotado no cuenta como validado.
- Cada cambio del análisis entra por PR, deja una línea en `docs/analisis/cambios.md` y se marca con
  una etiqueta de git (`analisis-crm-v0.4.0`).
- Las versiones anteriores al 15 oct 2026 se asignaron a posteriori y llevan la marca «reconstruida».
- [ADR 0009](0009-fuentes-de-verdad.md) sigue vigente para la precedencia general. Cada documento de
  bloque dice además cuáles son sus fuentes vigentes; para el CRM, el diseño de pantallas del 13 oct
  2026 va antes que la parte 2 de la v4, por ser posterior.

## Alternativas descartadas

- **Una sola versión para todo el análisis:** obliga al CRM a esperar al ERP para tener línea base, o
  a aprobar el ERP sin haberlo validado.
- **Seguir numerando por edición (v5, v6…):** no dice nada sobre si algo está validado, que es lo que
  importa para construir.
- **Wiki de GitHub o una herramienta externa:** guardan historial, pero viven separadas del código:
  sus cambios no pasan por PR ni quedan atados a la versión del sistema.
