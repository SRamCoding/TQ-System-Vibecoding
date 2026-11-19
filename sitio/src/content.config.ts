import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { EXCLUIDOS } from './lib/rutas.mjs';

// Todos los documentos de docs/, tal como están. El id es la ruta del archivo dentro de docs/.
const docs = defineCollection({
  loader: glob({
    pattern: ['**/*.md', ...EXCLUIDOS.map((e) => `!${e}`)],
    base: '../docs',
    generateId: ({ entry }) => entry,
  }),
});

export const collections = { docs };
