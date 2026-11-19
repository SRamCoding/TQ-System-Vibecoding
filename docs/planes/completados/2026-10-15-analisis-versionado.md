# Plan: versionado y orden del análisis de sistemas

- **Estado:** terminado
- **Módulo(s):** documentación (sin código)
- **Fase:** — (transversal a las etapas)
- **Creado:** 2026-10-15 · **Actualizado:** 2026-10-15

## Objetivo

Que el análisis de sistemas tenga un hogar único (`docs/analisis/`) que diga, para cada bloque
(CRM, ERP y lo común), en qué versión y etapa de madurez está, de dónde salió cada versión y qué
falta para la siguiente; y que el diseño del CRM traído de otra cuenta quede guardado como fuente
fechada.

## Contexto que leí

- `AGENTS.md`, `docs/README.md`, `docs/diseno/README.md`, `docs/planes/mapa-etapas.md`
- `docs/diseno/00-Reconciliacion-con-v4.md` §1 y §2 (precedencia y cambios de agosto a v4)
- `docs/decisiones/0006`, `0009`, `pendientes.md`
- Cabeceras de versión y fecha de los documentos de `docs/diseno/` y de `v4/`
- Historial de git de `main` (44 commits, del 2026-10-06 al 2026-10-10)
- Lienzo de diseño del CRM del 13 oct 2026 (otra cuenta), leído el 15 oct
- Decisiones pendientes que afectan: ninguna bloquea; la del dominio se registra como propuesta

## Pasos

- [x] 1. Rama `analisis-versionado` y este plan
- [x] 2. Guardar el diseño del CRM en `docs/diseno/fuentes/crm-pantallas-2026-10-13/` con su procedencia
- [x] 3. `docs/analisis/`: portada, versionado, historia, bloques (CRM, ERP, común), validaciones, registro de cambios
- [x] 4. ADR 0014 (versionado del análisis) y ADR 0015 (un solo dominio, propuesta)
- [x] 5. Enlaces desde `AGENTS.md` y `docs/README.md`; `mkdocs.yml` para el sitio
- [x] 6. Verificar: el sitio compila, los enlaces existen, cada fecha y número tiene respaldo
- [x] 7. `composer check`: no se corrió en local porque el cambio no toca código ni configuración de PHP; lo corre el CI del PR

## Criterio de aceptación

- Desde `docs/analisis/README.md` se sabe en menos de un minuto la versión de cada bloque y qué falta.
- Cada versión del registro de cambios cita su evidencia (archivo, commit o lienzo con fecha).
- `mkdocs build` termina sin errores.

## Bitácora

- 2026-10-15 — El prototipo HTML que trajo Sebastián es idéntico byte a byte al que ya estaba en
  `docs/diseno/fuentes/`. Lo nuevo es el lienzo del CRM: 63 pantallas y 5 componentes, con la misma
  numeración CRM-01 a CRM-07 de la v4.
- 2026-10-15 — Se decide versionar por bloque: el CRM ya está en validación (Cristhian, luego
  Miguel) mientras el ERP sigue en especificación.
- 2026-10-15 — `mkdocs build` sin advertencias ni enlaces rotos (mkdocs-material 9, mkdocs < 2: la 2.0
  rompe los plugins y el tema). Se revisaron a ojo la portada del análisis y la página de versionado.
- 2026-10-15 — Cada fecha y número del registro de cambios se cotejó contra su fuente: cabeceras de
  `docs/diseno/`, el commit `493d687` (ADR 0001 a 0010, reconciliación, DBML v2, v4 en Markdown) y el
  `canvas.json` del lienzo del CRM.
- 2026-10-15 — Quedan para próximas tareas: reescribir cada flujo del ERP en presente y pasar los
  documentos de agosto a un histórico; identificadores de reglas (`F02-R03`); publicar el sitio (antes,
  resolver si el repositorio pasa a privado, pendiente en `pendientes.md`); confirmar que los contactos
  de cliente del lienzo del CRM son de ejemplo.
