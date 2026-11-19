// Complementos de Markdown: dejan que los documentos se escriban como siempre (para GitHub y los agentes)
// y se vean bien en el sitio.
import { visit } from 'unist-util-visit';
import { fromMarkdown } from 'mdast-util-from-markdown';
import { anclaMkdocs, resolverEnlace } from './rutas.mjs';

/** Los enlaces relativos a .md, carpetas y archivos pasan a direcciones del sitio. */
export function remarkEnlaces() {
  return (arbol, archivo) => {
    const actual = archivo.path || archivo.history?.[0];
    if (!actual) return;
    visit(arbol, ['link', 'image', 'definition'], (nodo) => {
      const nuevo = resolverEnlace(nodo.url, actual);
      if (nuevo) nodo.url = nuevo;
    });
    // Enlaces escritos en HTML dentro del Markdown (poco común, pero existe).
    visit(arbol, 'html', (nodo) => {
      nodo.value = nodo.value.replace(/(href|src)="([^"]+)"/g, (todo, atributo, destino) => {
        const nuevo = resolverEnlace(destino, actual);
        return nuevo ? `${atributo}="${nuevo}"` : todo;
      });
    });
  };
}

const escapar = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** Los bloques ```mermaid se dibujan en el navegador en vez de mostrarse como código. */
export function remarkMermaid() {
  return (arbol) => {
    visit(arbol, 'code', (nodo, i, padre) => {
      if (nodo.lang !== 'mermaid' || !padre) return;
      padre.children[i] = {
        type: 'html',
        value: `<figure class="diagrama"><pre class="mermaid">${escapar(nodo.value)}</pre></figure>`,
      };
    });
  };
}

const TIPOS_AVISO = { note: 'Nota', info: 'Nota', tip: 'Consejo', warning: 'Atención', danger: 'Cuidado', important: 'Importante' };

/**
 * Los avisos al estilo MkDocs:  !!! warning "Título"  seguido de un bloque con sangría.
 * Para Markdown común, el bloque con sangría es código; aquí se vuelve a leer como texto.
 */
export function remarkAvisos() {
  return (arbol) => {
    visit(arbol, 'paragraph', (nodo, i, padre) => {
      const primero = nodo.children[0];
      if (!padre || primero?.type !== 'text') return;
      const m = primero.value.match(/^!!!\s+(\w+)(?:\s+"([^"]*)")?\s*$/);
      if (!m) return;
      const tipo = m[1].toLowerCase();
      const titulo = m[2] || TIPOS_AVISO[tipo] || 'Nota';
      const siguiente = padre.children[i + 1];
      const cuerpo = siguiente?.type === 'code' && !siguiente.lang ? fromMarkdown(siguiente.value).children : [];
      const aviso = {
        type: 'blockquote',
        data: { hName: 'aside', hProperties: { className: ['aviso', `aviso-${tipo}`], role: 'note' } },
        children: [
          { type: 'paragraph', data: { hProperties: { className: ['aviso-titulo'] } }, children: [{ type: 'text', value: titulo }] },
          ...cuerpo,
        ],
      };
      padre.children.splice(i, cuerpo.length ? 2 : 1, aviso);
    });
  };
}

const textoDe = (nodo) =>
  nodo.type === 'text' ? nodo.value : (nodo.children || []).map(textoDe).join('');

/**
 * Cada título lleva también el ancla que generaba MkDocs (sin tildes), para que los enlaces viejos sigan
 * llegando, y un enlace "#" para copiar la dirección de esa sección.
 */
export function rehypeAnclas() {
  return (arbol) => {
    visit(arbol, 'element', (nodo) => {
      if (!/^h[2-4]$/.test(nodo.tagName) || !nodo.properties?.id) return;
      const id = String(nodo.properties.id);
      const vieja = anclaMkdocs(textoDe(nodo));
      if (vieja && vieja !== id) {
        nodo.children.unshift({ type: 'element', tagName: 'span', properties: { id: vieja, className: ['ancla-vieja'] }, children: [] });
      }
      nodo.children.push({
        type: 'element',
        tagName: 'a',
        properties: { href: `#${id}`, className: ['ancla'], ariaLabel: 'Enlace a esta sección', dataPagefindIgnore: '' },
        children: [{ type: 'text', value: '#' }],
      });
    });
  };
}

/** Las tablas anchas se desplazan dentro de su caja y no rompen la página en el celular. */
export function rehypeTablas() {
  return (arbol) => {
    visit(arbol, 'element', (nodo, i, padre) => {
      if (nodo.tagName !== 'table' || !padre || padre.properties?.className?.includes?.('tabla')) return;
      padre.children[i] = {
        type: 'element',
        tagName: 'div',
        properties: { className: ['tabla'], tabIndex: 0, role: 'region', ariaLabel: 'Tabla' },
        children: [nodo],
      };
    });
  };
}
