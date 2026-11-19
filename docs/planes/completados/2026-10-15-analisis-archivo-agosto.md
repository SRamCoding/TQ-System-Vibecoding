# Plan: archivar el análisis funcional de agosto

- **Estado:** terminado
- **Módulo(s):** documentación (sin código)
- **Fase:** — (transversal a las etapas)
- **Creado:** 2026-10-15 · **Actualizado:** 2026-10-15

## Objetivo

Que el primer entregable del análisis («AnalisisFuncional-TechQuk», 24 ago 2026) quede en el
repositorio como archivo congelado y fechado. Así el proyecto de Claude puede vaciarse sin perder
evidencia, y el repositorio queda como única fuente.

## Contexto que leí

- `AGENTS.md`, `docs/README.md`, `docs/analisis/README.md`, `historia.md` §2 y `cambios.md`
- `docs/planes/mapa-etapas.md` y `docs/decisiones/pendientes.md`: ninguna decisión pendiente afecta
- El zip «AnalisisFuncional-TechQuk» (31 entradas, todas del 24 ago 2026). Se revisó que no
  tuviera credenciales: solo aparecen nombres de columnas de S10 y el nombre del servidor de los
  respaldos

## Pasos

- [x] 1. Rama `analisis-archivo-agosto` y este plan
- [x] 2. Copiar el paquete íntegro a `docs/analisis/archivo/2026-08-24-analisis-funcional/`,
  renombrando solo la carpeta `Metodologia+Resultados` a `metodologia-y-resultados` por las URL
- [x] 3. Portada del archivo: qué es, que no manda y qué pasó con cada parte
- [x] 4. `historia.md` y `cambios.md` apuntan al archivo; fila en `AGENTS.md`; entrada en `mkdocs.yml`
- [x] 5. Verificar que `mkdocs build` compila

## Criterio de aceptación

- Ningún documento del análisis dice ya que el material de agosto «no está en el repositorio»,
  salvo lo que de verdad falta: el modelo de 149 tablas y «El CRM de Techquk».
- El sitio compila y el archivo aparece en la navegación.

## Bitácora

- 2026-10-15 — El modelo físico de 149 tablas (SQL Server) y su script no venían en el zip, y ya se
  habían borrado del proyecto de Claude. Se registra como no conservado; lo reemplazan el DBML v2
  y el ADR 0002.
- 2026-10-15 — No cambia la versión de ningún bloque: archivar evidencia no hace madurar el
  análisis. Solo se actualizan las líneas de origen del registro de cambios.
