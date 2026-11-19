# Sitio de documentación

Publica `docs/` como un sitio web en Netlify (privado: entra quien tiene acceso al proyecto en Netlify). Está hecho
en Astro y **no tiene contenido propio**: cada página sale de un archivo de `docs/`, que sigue siendo la fuente de
verdad. Para documentar algo, se edita `docs/`; el sitio no se toca.

## Qué se escribe dónde

| Quiero… | Dónde |
|---|---|
| Escribir o corregir documentación | `docs/`, en Markdown, como siempre. Los enlaces van relativos a `.md` (los que funcionan en GitHub); el sitio los convierte |
| Que un documento aparezca en el menú, con un nombre corto | `sitio/navegacion.yml`. Sin eso igual se publica y sale en el índice de su carpeta |
| Agregar una decisión o un plan | Nada más: los ADR de `docs/decisiones/` y los planes de `docs/planes/` entran solos al menú, a la portada y a sus índices |
| Cambiar el estado de un bloque, una etapa o la historia | `docs/analisis/README.md`, `docs/planes/mapa-etapas.md` o `docs/analisis/historia.md`. La portada los lee de ahí |
| Cambiar el diseño | `sitio/src/styles/global.css` y los componentes de `sitio/src/` |

La portada lee tablas de esos tres documentos. Si se cambia el formato de una (sus columnas o la marca ✅ de lo
cerrado), hay que revisar `sitio/src/lib/proyecto.ts`.

## Ver el sitio en local

```bash
cd sitio
npm install
npm run dev      # http://localhost:4321, se actualiza al editar docs/ (la búsqueda solo existe tras build)
npm run build    # el sitio completo en sitio/dist, con búsqueda y revisión de enlaces
```

`npm run build` falla si un enlace interno lleva a una página o archivo que no existe, y avisa (sin fallar) si
lleva a una sección que no existe dentro de una página.

## Qué hace con el Markdown

- Los enlaces a `.md`, a carpetas y a archivos de `docs/` pasan a direcciones del sitio; los que salen de `docs/`
  (código, `ARCHITECTURE.md`) van a GitHub.
- Los archivos que no son Markdown (CSV, PDF, lienzos HTML) se publican tal cual, en la misma ruta.
- Los bloques ```` ```mermaid ```` se dibujan como diagramas.
- Los avisos `!!! warning "Título"` (sintaxis de MkDocs, que usaba el sitio anterior) se muestran como avisos.
- Los títulos conservan también el ancla que generaba MkDocs, para que los enlaces viejos sigan llegando.

## Despliegue

`netlify.toml` (en la raíz) construye `sitio/` en cada cambio de `main`. Si el cambio no toca `docs/` ni `sitio/`,
Netlify no reconstruye.

## La idea del diseño

Cada documento es una lámina de un plano: marco doble, cajetín con su número de lámina (la sigla de la sección y su
orden en el menú) y cuadrícula de dibujo de fondo. En modo oscuro, cianotipo. Los colores son los del logo
(`docs/convenciones/interfaz.md`): azul noche, turquesa y morado, y las áreas mantienen su color (Común en azul
noche, CRM en morado, ERP en turquesa). La portada dibuja el recorrido del proyecto como un montante: un cable
vertical con cada hito conectado.
