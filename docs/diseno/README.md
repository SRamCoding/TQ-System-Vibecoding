# Diseño funcional

Describe **qué** construir. **Cómo** se construye lo dictan `docs/convenciones/` y `ARCHITECTURE.md`.

## Fuentes de verdad (en este orden)

| Archivo | Qué es |
|---|---|
| `v4/` | Especificación funcional v4 (21 set 2026) dividida por partes y por flujo. **Léela por partes**, no el PDF |
| `flujograma-gastos.md` | Cómo se obtiene cada tipo de gasto y el selector de imputación |
| `fuentes/crm-pantallas-2026-10-13/` | Diseño de 63 pantallas del CRM (13 oct 2026). Para el CRM manda sobre la v4 parte 2 (ADR 0014). Inventario: `docs/analisis/bloques/crm-pantallas.md` |
| `fuentes/techquk-prototipo_4_.html` | Prototipo navegable: pantallas y roles |
| `fuentes/*.pdf` | Los originales, para personas. El agente usa los extractos en Markdown |

## Modelo y documentos actualizados

| Archivo | Para qué |
|---|---|
| `techquk_modelo.dbml` | Modelo de datos v2: 210 tablas activas, tipos de PostgreSQL, grupos por módulo, 5 obsoletas en `ZZ_Obsoletas_v4` |
| `00-Reconciliacion-con-v4.md` | Qué cambió respecto de agosto, estado de las decisiones, inconsistencias detectadas |
| `06-Arquitectura-Nucleo-Datos.md` | Núcleo; la §17 detalla imputación, IA, alertas, aprobaciones y permisos del v4 |
| `08-Detalle-Columnas-y-Funcionamiento.md` | Reglas por columna; su anexo cubre las tablas nuevas |
| `Techquk-Flujo-Central.md` | El recorrido del contrato al margen real |
| `09B-…`, `10-…`, `01-…`, `05-…` | Documentos de agosto actualizados a v3.0 con las secciones reemplazadas marcadas |

## Advertencias

- Las marcas del v4: **POR VALIDAR** se construye configurable; **FASE POSTERIOR** no entra al MVP;
  **AJUSTE SET 2026** es algo nuevo respecto de agosto.
- Las tablas marcadas `[S]` en el DBML son diseño propuesto: revísalas con el flujo que las usa.
- El CRM (v4 parte 2) no se cotejó columna por columna contra el modelo: revisarlo al construirlo.
- Los fragmentos de código de los documentos son ilustrativos.
