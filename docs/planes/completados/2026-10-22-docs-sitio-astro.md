# Plan: Sitio de documentación en Astro, de punta a punta

- **Estado:** terminado (2026-10-22)
- **Módulo(s):** ninguno de la aplicación; solo el sitio de documentación (`sitio/`) y su despliegue
- **Etapa:** fuera de las etapas del MVP · rama `docs/sitio-astro` (desde `main`, 3a3b9c2)
- **Creado:** 2026-10-22 · **Actualizado:** 2026-10-22

## Objetivo

La documentación de `docs/` se publica en Netlify como un sitio propio hecho en Astro, con la identidad visual de
Techquk (`docs/convenciones/interfaz.md`), y el sitio cuenta el proyecto de punta a punta: de la pregunta inicial y
S10 al análisis, la especificación v4, las decisiones y lo que se está construyendo. Reemplaza a MkDocs.

## Contexto que leí

- `docs/analisis/historia.md`, `docs/analisis/README.md` (estado de los bloques), `docs/planes/mapa-etapas.md`
- `docs/convenciones/interfaz.md` §Identidad visual (colores del logo, ADR 0017)
- `mkdocs.yml` (el menú curado) y el sitio Astro de `peru-procurement-data-platform` como referencia de nivel
- Pedido del desarrollador (2026-10-22): migrar a Astro, identidad propia de Techquk, y que el sitio muestre el
  proceso desde la idea inicial

## Decisiones de este plan

- **`docs/` sigue siendo la fuente de verdad y no se mueve.** El sitio vive en `sitio/` y lee `../docs` al construir.
  Los agentes siguen leyendo Markdown plano; ningún documento necesita cabecera nueva.
- **El menú se escribe una sola vez**, en `sitio/navegacion.yml` (lo que era el `nav` de `mkdocs.yml`). Un
  documento que no está en el menú igual se publica y aparece en el índice de su carpeta.
- **Los enlaces entre documentos se escriben como siempre** (rutas relativas a `.md`); el sitio los convierte. Los
  enlaces a archivos fuera de `docs/` van a GitHub.
- **Los archivos que no son Markdown** (CSV, HTML de los lienzos, PDF, DBML) se copian tal cual al sitio.
- **Búsqueda con Pagefind** (estática, sin servidor) y **diagramas Mermaid** dibujados en el navegador.

## Pasos

- [x] 1. Proyecto Astro en `sitio/` que publica cada `.md` de `docs/` con su ruta, menú y enlaces convertidos
- [x] 2. Tema visual Techquk (claro y oscuro, celular) y portada con el recorrido del proyecto
- [x] 3. Índices: decisiones con su estado, construcción con planes por fecha, estado de los bloques
- [x] 4. Búsqueda, Mermaid, archivos adjuntos, página 404
- [x] 5. `netlify.toml` apunta a `sitio/`; se retiran `mkdocs.yml` y `docs/requirements.txt`
- [x] 6. Verificar: build sin errores, cero enlaces internos rotos, revisión visual en escritorio y celular

## Criterio de aceptación

- `npm run build` en `sitio/` termina sin errores y el verificador de enlaces no encuentra rotos.
- Cada documento que publicaba MkDocs tiene su página, en la misma ruta.
- La portada responde de un vistazo: qué es el sistema, de dónde viene, en qué estado está cada bloque y qué se
  está construyendo ahora.

## Bitácora

- 2026-10-22 — Plan creado. El sitio anterior (MkDocs) quedó publicado en Netlify el mismo día (PR #26).
- 2026-10-22 — Hecho. Astro 5 en `sitio/`, colección con `glob` sobre `../docs` (id = ruta del archivo). Las mismas
  direcciones que tenía MkDocs; el README de la raíz de `docs/` pasa a `/documentacion/` porque la portada es propia.
  Portada, `/decisiones/` y `/construccion/` se arman leyendo tablas de `analisis/README.md`, `analisis/historia.md`,
  `analisis/versionado.md` y `planes/mapa-etapas.md` (`sitio/src/lib/proyecto.ts`): si cambia el formato de esas
  tablas, revisar ahí. Los ADR y los planes nuevos entran solos al menú.
- 2026-10-22 — Cuerpo en Public Sans y no en Atkinson Hyperlegible: esta trae el cero tachado y las fechas y montos se
  leían raro («2Ø26»). Títulos en Barlow Condensed (rótulo de plano).
- 2026-10-22 — Los diagramas Mermaid (17 documentos) ahora se dibujan; con MkDocs se veían como código. Los enlaces a
  anclas de `S10_firstSteps.md`, rotos en MkDocs, ahora llegan (el sitio genera anclas con tildes y además conserva
  las de MkDocs). Verificación: 0 enlaces internos rotos sobre 18,808; 124 páginas en la búsqueda.
- 2026-10-22 — Netlify solo reconstruye si cambian `docs/` o `sitio/` (`ignore` en `netlify.toml`): con `base =
  "sitio"`, Netlify por defecto solo miraría `sitio/` y no reconstruiría al cambiar la documentación.
