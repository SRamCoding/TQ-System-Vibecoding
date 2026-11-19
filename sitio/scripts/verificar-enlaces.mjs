// Revisa que cada enlace interno del sitio construido llegue a algo que existe.
// Una página que no existe detiene el build; un ancla que no existe solo avisa.
// Uso: node scripts/verificar-enlaces.mjs   (después de astro build)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIST = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../dist');

const htmls = [];
const recorrer = (c) => {
  for (const e of fs.readdirSync(c, { withFileTypes: true })) {
    const p = path.join(c, e.name);
    if (e.isDirectory()) {
      if (e.name !== 'pagefind' && e.name !== '_astro') recorrer(p);
    } else if (e.name.endsWith('.html')) htmls.push(p);
  }
};
recorrer(DIST);

// Las páginas generadas por el sitio (no los lienzos HTML copiados de docs/, que se publican tal cual).
const esPagina = (archivo) => {
  const t = fs.readFileSync(archivo, 'utf8');
  return t.includes('class="lamina"');
};

const idsCache = new Map();
const idsDe = (archivo) => {
  if (!idsCache.has(archivo)) {
    const t = fs.readFileSync(archivo, 'utf8');
    idsCache.set(archivo, new Set([...t.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1])));
  }
  return idsCache.get(archivo);
};

const destinoDe = (ruta) => {
  const limpio = decodeURIComponent(ruta);
  const p = path.join(DIST, limpio);
  if (limpio.endsWith('/')) return fs.existsSync(path.join(p, 'index.html')) ? path.join(p, 'index.html') : null;
  if (fs.existsSync(p) && fs.statSync(p).isFile()) return p;
  if (fs.existsSync(path.join(p, 'index.html'))) return path.join(p, 'index.html');
  return null;
};

const rotos = [];
const anclas = [];
let revisados = 0;

for (const archivo of htmls.filter(esPagina)) {
  const desde = '/' + path.relative(DIST, archivo).split(path.sep).join('/').replace(/index\.html$/, '');
  const t = fs.readFileSync(archivo, 'utf8');
  for (const m of t.matchAll(/\s(?:href|src)="([^"]+)"/g)) {
    const enlace = m[1].replace(/&amp;/g, '&');
    if (/^([a-z][a-z0-9+.-]*:|\/\/)/i.test(enlace)) continue; // externo
    if (enlace.startsWith('/pagefind/')) continue; // se genera después de astro build
    revisados++;
    const [ruta, ancla] = enlace.split('#');
    const destino = ruta ? destinoDe(ruta.startsWith('/') ? ruta : path.posix.join(desde, ruta)) : archivo;
    if (!destino) {
      rotos.push(`${desde} → ${enlace}`);
      continue;
    }
    if (ancla && destino.endsWith('.html') && !idsDe(destino).has(decodeURIComponent(ancla))) {
      anclas.push(`${desde} → ${enlace}`);
    }
  }
}

const unicos = (l) => [...new Set(l)];
if (anclas.length) {
  console.warn(`\nAnclas que no existen (${unicos(anclas).length}). Revisa el título al que apuntan:`);
  unicos(anclas).forEach((a) => console.warn(`  · ${a}`));
}
if (rotos.length) {
  console.error(`\nEnlaces rotos (${unicos(rotos).length}): llevan a una página o archivo que no existe.`);
  unicos(rotos).forEach((r) => console.error(`  · ${r}`));
  process.exit(1);
}
console.log(`\nEnlaces internos revisados: ${revisados}. Ninguno roto.`);
