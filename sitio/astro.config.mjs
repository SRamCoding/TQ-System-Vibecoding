// @ts-check
// Sitio de documentación de TechQuk Integral System.
// La fuente es ../docs (no se mueve ni se edita para el sitio). Ver sitio/LEEME.md.
import { defineConfig } from 'astro/config';
import { rehypeHeadingIds } from '@astrojs/markdown-remark';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { DOCS } from './src/lib/rutas.mjs';
import { remarkAvisos, remarkEnlaces, remarkMermaid, rehypeAnclas, rehypeTablas } from './src/lib/markdown.mjs';

/** Copia al sitio los archivos de docs/ que no son Markdown (CSV, PDF, lienzos HTML…), en la misma ruta. */
function adjuntosDeDocs() {
  return {
    name: 'adjuntos-de-docs',
    hooks: {
      'astro:build:done': ({ dir, logger }) => {
        const destino = fileURLToPath(dir);
        let n = 0;
        const recorrer = (carpeta) => {
          for (const e of fs.readdirSync(carpeta, { withFileTypes: true })) {
            const origen = path.join(carpeta, e.name);
            if (e.isDirectory()) recorrer(origen);
            else if (!/\.md$/i.test(e.name) && e.name !== '.gitkeep') {
              const final = path.join(destino, path.relative(DOCS, origen));
              fs.mkdirSync(path.dirname(final), { recursive: true });
              fs.copyFileSync(origen, final);
              n++;
            }
          }
        };
        recorrer(DOCS);
        logger.info(`${n} adjuntos de docs/ copiados al sitio`);
      },
    },
  };
}

export default defineConfig({
  site: process.env.URL || 'https://techquk-integralsystem-docs.netlify.app',
  trailingSlash: 'always',
  build: { format: 'directory' },
  integrations: [adjuntosDeDocs()],
  markdown: {
    remarkPlugins: [remarkAvisos, remarkMermaid, remarkEnlaces],
    rehypePlugins: [rehypeHeadingIds, rehypeAnclas, rehypeTablas],
    syntaxHighlight: { type: 'shiki', excludeLangs: ['mermaid'] },
    shikiConfig: { themes: { light: 'github-light', dark: 'github-dark-dimmed' }, wrap: false },
  },
  vite: { server: { fs: { allow: ['..'] } } },
});
