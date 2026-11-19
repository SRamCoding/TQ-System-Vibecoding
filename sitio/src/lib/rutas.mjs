// Cómo se traduce un archivo de docs/ a una dirección del sitio.
// Lo usan las páginas y los complementos de Markdown, así que va en JavaScript simple.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const aqui = path.dirname(fileURLToPath(import.meta.url));

/** La carpeta docs/ del repositorio: la única fuente del sitio. */
export const DOCS = path.resolve(aqui, '../../../docs');
/** La raíz del repositorio. */
export const RAIZ = path.resolve(DOCS, '..');

export const REPO = 'https://github.com/SRamCoding/TechQuk-IntegralSystem';
export const RAMA = 'main';

/** Documentos que no se publican (plantillas). */
export const EXCLUIDOS = ['decisiones/0000-plantilla.md', 'planes/plantilla.md'];

/** El README de la raíz de docs/ no puede ser la portada (la portada es propia): vive aquí. */
export const RUTA_LEEME_RAIZ = 'documentacion';

const esLeeme = (nombre) => /^(readme|leeme)$/i.test(nombre);

/**
 * Ruta del sitio (sin barras al inicio ni al final) para un .md de docs/.
 * analisis/historia.md → analisis/historia · analisis/README.md → analisis · README.md → documentacion
 */
export function rutaDeDocumento(rel) {
  const sinExt = rel.replace(/\.md$/i, '');
  const partes = sinExt.split('/');
  const nombre = partes.at(-1);
  if (esLeeme(nombre)) {
    partes.pop();
    return partes.length ? partes.join('/') : RUTA_LEEME_RAIZ;
  }
  return sinExt;
}

/** Dirección absoluta dentro del sitio, con barra final. */
export const url = (ruta = '') => (ruta ? `/${ruta.replace(/^\/|\/$/g, '')}/` : '/');

/** Enlace a un archivo del repositorio en GitHub. */
export const enGithub = (relDesdeRaiz, editar = false) =>
  `${REPO}/${editar ? 'edit' : 'blob'}/${RAMA}/${relDesdeRaiz.split('/').map(encodeURIComponent).join('/')}`;

/**
 * Convierte el destino de un enlace escrito en un documento (como se escribe en GitHub) en una dirección del sitio.
 * Devuelve null si el enlace no se toca (externo, ancla local, correo).
 */
export function resolverEnlace(destino, archivoActual) {
  if (!destino || /^([a-z][a-z0-9+.-]*:|\/\/|#)/i.test(destino)) return null;
  const [crudo, ancla] = destino.split('#');
  let rutaRel;
  try {
    rutaRel = decodeURI(crudo);
  } catch {
    rutaRel = crudo;
  }
  const absoluto = path.resolve(path.dirname(archivoActual), rutaRel);
  const hash = ancla !== undefined ? `#${ancla}` : '';
  const relDocs = path.relative(DOCS, absoluto).split(path.sep).join('/');

  if (relDocs.startsWith('..') || path.isAbsolute(relDocs)) {
    // Fuera de docs/: código, ARCHITECTURE.md, AGENTS.md… se lee en GitHub.
    const relRaiz = path.relative(RAIZ, absoluto).split(path.sep).join('/');
    if (relRaiz.startsWith('..')) return null;
    return enGithub(relRaiz) + hash;
  }

  if (/\.md$/i.test(relDocs)) return url(rutaDeDocumento(relDocs)) + hash;

  // Una carpeta: su README, si tiene.
  if (fs.existsSync(absoluto) && fs.statSync(absoluto).isDirectory()) {
    const leeme = fs.readdirSync(absoluto).find((f) => /^(readme|leeme)\.md$/i.test(f));
    const relCarpeta = relDocs.replace(/\/$/, '');
    return url(leeme ? rutaDeDocumento(`${relCarpeta}/${leeme}`) : relCarpeta) + hash;
  }

  // Cualquier otro archivo (CSV, PDF, HTML de los lienzos…) se copia tal cual al sitio.
  return `/${relDocs.split('/').map(encodeURIComponent).join('/')}${hash}`;
}

/**
 * La forma en que MkDocs (Python-Markdown) armaba las anclas: sin tildes ni signos.
 * Los documentos escritos antes del cambio enlazan así; el sitio deja esa ancla también.
 */
export function anclaMkdocs(texto) {
  return texto
    .normalize('NFKD')
    .replace(/[^\x00-\x7F]/g, '')
    .replace(/[^\w\s-]/g, '')
    .trim()
    .toLowerCase()
    .replace(/[-\s]+/g, '-');
}
