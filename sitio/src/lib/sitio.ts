// Los documentos de docs/ y el menú, ya listos para las páginas.
import { getCollection, type CollectionEntry } from 'astro:content';
import fs from 'node:fs';
import path from 'node:path';
import YAML from 'yaml';
import { DOCS, RAIZ, rutaDeDocumento, url } from './rutas.mjs';

export type Entrada = CollectionEntry<'docs'>;

export interface Doc {
  rel: string; // ruta dentro de docs/, con .md
  ruta: string; // ruta del sitio, sin barras
  titulo: string; // el # del documento
  corto: string; // el nombre que le da el menú (o el título)
  seccion: string;
  codigo: string; // número de lámina: AN-03
  carpeta: string; // carpeta dentro de docs/ ('' en la raíz)
  entrada: Entrada;
}

export interface ItemMenu {
  titulo: string;
  rel?: string;
  hijos?: ItemMenu[];
}

export interface Seccion {
  titulo: string;
  codigo: string;
  items: ItemMenu[];
  rel?: string; // secciones de un solo documento (Glosario)
}

/** Siglas de cada sección: dan el número de lámina. */
const SIGLAS: Record<string, string> = {
  'Cómo se organiza docs/': 'DOC',
  'Análisis de sistemas': 'AN',
  Decisiones: 'DE',
  'Diseño funcional': 'DF',
  Convenciones: 'CV',
  Glosario: 'GL',
  Construcción: 'CO',
  Operaciones: 'OP',
};

/** La sección de un documento que no está en el menú, según su carpeta. */
const SECCION_DE_CARPETA: Record<string, string> = {
  analisis: 'Análisis de sistemas',
  decisiones: 'Decisiones',
  diseno: 'Diseño funcional',
  convenciones: 'Convenciones',
  planes: 'Construcción',
  operaciones: 'Operaciones',
};

/** Texto sin marcas de Markdown, para títulos. */
export const textoPlano = (s: string) =>
  s
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/[*_`]/g, '')
    .replace(/<[^>]+>/g, '')
    .trim();

export function tituloDe(cuerpo: string | undefined, rel: string) {
  const m = (cuerpo ?? '').match(/^#\s+(.+?)\s*#*\s*$/m);
  if (m) return textoPlano(m[1]);
  return path.basename(rel, '.md').replace(/[-_]+/g, ' ');
}

function leerMenu(): Seccion[] {
  const crudo = YAML.parse(fs.readFileSync(path.join(RAIZ, 'sitio/navegacion.yml'), 'utf8')) as unknown[];
  const item = (x: unknown): ItemMenu => {
    if (typeof x === 'string') return { titulo: '', rel: x };
    const [titulo, valor] = Object.entries(x as Record<string, unknown>)[0];
    if (typeof valor === 'string') return { titulo, rel: valor };
    return { titulo, hijos: (valor as unknown[]).map(item) };
  };
  return crudo.map((x) => {
    const i = item(x);
    const codigo = SIGLAS[i.titulo] ?? i.titulo.slice(0, 2).toUpperCase();
    return { titulo: i.titulo, codigo, items: i.hijos ?? [], rel: i.rel };
  });
}

/** Carpetas cuyos documentos nuevos entran solos al menú: [carpeta, sección, cómo se rotula]. */
const AUTOMATICAS: [string, string, (d: Doc) => string][] = [
  ['decisiones', 'Decisiones', (d) => d.titulo.replace(/\s*[—-]\s*/, ' ')],
  ['planes/completados', 'Construcción', (d) => `Hecho · ${d.titulo.replace(/^Plan:\s*/i, '')}`],
  ['planes/activos', 'Construcción', (d) => `En curso · ${d.titulo.replace(/^Plan:\s*/i, '')}`],
];

let cache: { docs: Map<string, Doc>; menu: Seccion[]; orden: Doc[] } | null = null;

export async function cargar() {
  if (cache) return cache;
  const entradas = await getCollection('docs');
  const menu = leerMenu();
  const docs = new Map<string, Doc>();

  for (const entrada of entradas) {
    const rel = entrada.id;
    const carpeta = path.posix.dirname(rel) === '.' ? '' : path.posix.dirname(rel);
    const titulo = tituloDe(entrada.body, rel);
    docs.set(rel, {
      rel,
      ruta: rutaDeDocumento(rel),
      titulo,
      corto: titulo,
      seccion: SECCION_DE_CARPETA[carpeta.split('/')[0]] ?? 'Documentación',
      codigo: '',
      carpeta,
      entrada,
    });
  }

  // El orden de lectura y los números de lámina salen del menú.
  const orden: Doc[] = [];
  for (const s of menu) {
    let n = 0;
    const marcar = (items: ItemMenu[]) => {
      for (const it of items) {
        if (it.rel) {
          const d = docs.get(it.rel);
          if (!d) throw new Error(`El menú (sitio/navegacion.yml) nombra un documento que no existe: docs/${it.rel}`);
          if (!it.titulo) it.titulo = d.titulo;
          if (!d.codigo) {
            d.seccion = s.titulo;
            d.corto = it.titulo;
            d.codigo = `${s.codigo}-${String(++n).padStart(2, '0')}`;
            orden.push(d);
          }
        }
        if (it.hijos) marcar(it.hijos);
      }
    };
    if (s.rel) marcar([{ titulo: s.titulo, rel: s.rel }]);
    marcar(s.items);
  }
  // Carpetas que crecen solas: lo nuevo entra al final de su sección aunque nadie lo agregue al menú.
  for (const [carpeta, seccionTitulo, rotulo] of AUTOMATICAS) {
    const s = menu.find((m) => m.titulo === seccionTitulo);
    if (!s) continue;
    const nuevos = [...docs.values()]
      .filter((d) => !d.codigo && d.carpeta === carpeta)
      .sort((a, b) => a.rel.localeCompare(b.rel, 'es'));
    let n = Number(orden.filter((d) => d.seccion === s.titulo).length);
    for (const d of nuevos) {
      const titulo = rotulo(d);
      s.items.push({ titulo, rel: d.rel });
      d.seccion = s.titulo;
      d.corto = titulo;
      d.codigo = `${s.codigo}-${String(++n).padStart(2, '0')}`;
      orden.push(d);
    }
  }

  for (const d of docs.values()) {
    if (!d.codigo) {
      const sigla = Object.entries(SIGLAS).find(([t]) => t === d.seccion)?.[1] ?? 'DOC';
      d.codigo = `${sigla}-s/n`;
    }
  }

  cache = { docs, menu, orden };
  return cache;
}

/** Las carpetas de docs/ y lo que tiene cada una (documentos y subcarpetas). */
export async function carpetas() {
  const { docs } = await cargar();
  const mapa = new Map<string, { docs: Doc[]; sub: Set<string> }>();
  const asegurar = (c: string) => {
    if (!mapa.has(c)) mapa.set(c, { docs: [], sub: new Set() });
    return mapa.get(c)!;
  };
  for (const d of docs.values()) {
    asegurar(d.carpeta).docs.push(d);
    let c = d.carpeta;
    while (c) {
      const padre = path.posix.dirname(c) === '.' ? '' : path.posix.dirname(c);
      asegurar(padre).sub.add(c);
      asegurar(c);
      c = padre;
    }
  }
  for (const v of mapa.values()) v.docs.sort((a, b) => a.rel.localeCompare(b.rel, 'es'));
  return mapa;
}

/** ¿La carpeta tiene su propio README? */
export const leemeDe = (lista: Doc[]) => lista.find((d) => /\/?(readme|leeme)\.md$/i.test(d.rel));

/** Dirección de una carpeta (su README o su índice automático). */
export const urlCarpeta = (c: string) => url(c);

export { url };
export const existeEnDocs = (rel: string) => fs.existsSync(path.join(DOCS, rel));
