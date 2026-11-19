// Lo que la portada y los índices leen de los documentos: estado de los bloques, la historia, las etapas,
// los planes y las decisiones. Todo sale de docs/; nada se escribe a mano en el sitio.
import GithubSlugger from 'github-slugger';
import { cargar, textoPlano, type Doc } from './sitio';

const cuerpo = async (rel: string) => (await cargar()).docs.get(rel)?.entrada.body ?? '';
const celdas = (fila: string) => fila.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').map((c) => c.trim());

/** Primera oración de un párrafo, sin marcas. */
const primeraOracion = (s: string, max = 260) => {
  const t = textoPlano(s.replace(/\s+/g, ' '));
  // Termina en un punto seguido de mayúscula, y no en una sigla como «S.A.C.».
  const m = t.match(/^(.+?[a-záéíóúñ0-9)»"'][.!?])(?=\s+[A-ZÁÉÍÓÚÑ¿¡«(]|\s*$)/);
  const o = m ? m[1] : t;
  if (o.length <= max) return o;
  const corte = o.slice(0, max);
  return `${corte.slice(0, corte.lastIndexOf(' ')).replace(/[,;:(]$/, '')}…`;
};

const mayuscula = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

export type Area = 'crm' | 'erp' | 'comun';

export async function bloques() {
  const md = await cuerpo('analisis/README.md');
  const al = md.match(/^\*Al (.+?)\.\*$/m)?.[1] ?? '';
  const filas = md.split('\n').filter((l) => /^\|\s*\[\*\*/.test(l));
  return {
    al,
    lista: filas.map((f) => {
      const [bloque, version, etapa, sigue] = celdas(f);
      const nombre = textoPlano(bloque).split('·')[0].trim();
      const que = textoPlano(bloque).split('·')[1]?.trim() ?? '';
      const area: Area = /crm/i.test(nombre) ? 'crm' : /erp/i.test(nombre) ? 'erp' : 'comun';
      const enlace = bloque.match(/\]\(([^)]+)\)/)?.[1] ?? '';
      return { nombre, que, version: textoPlano(version), etapa: textoPlano(etapa), sigue: textoPlano(sigue), area, enlace };
    }),
  };
}

/** La escala de madurez del análisis (versionado.md): 0.1 Diagnóstico … 1.0 Línea base. */
export async function escala() {
  const md = await cuerpo('analisis/versionado.md');
  const pasos = md
    .split('\n')
    .filter((l) => /^\|\s*\*\*0\.\d/.test(l))
    .map((f) => {
      const [v, etapa] = celdas(f);
      return { version: textoPlano(v), etapa: textoPlano(etapa) };
    });
  return [...pasos, { version: '1.0', etapa: 'Línea base' }];
}

export async function capitulos() {
  const md = await cuerpo('analisis/historia.md');
  const slugger = new GithubSlugger();
  const partes = md.split(/^## /m).slice(1);
  return partes
    .map((p) => {
      const [cabeza, ...resto] = p.split('\n');
      const ancla = slugger.slug(cabeza.trim());
      const m = cabeza.match(/^(\d+)\.\s+(.+)$/);
      if (!m) return null;
      const [cuando, que] = m[2].includes(':') ? m[2].split(/:\s*/, 2) : ['', m[2]];
      const texto = resto.join('\n').trim();
      const versiones = texto.match(/^\*\((.+?)\)\*/)?.[1] ?? '';
      const parrafo = texto.replace(/^\*\(.+?\)\*\s*/, '').split(/\n\s*\n/)[0] ?? '';
      return { n: Number(m[1]), cuando: cuando.trim(), que: que.trim(), ancla, versiones, resumen: primeraOracion(parrafo) };
    })
    .filter((c): c is NonNullable<typeof c> => c !== null);
}

export async function etapas() {
  const md = await cuerpo('planes/mapa-etapas.md');
  const lista = md
    .split('\n')
    .filter((l) => /^\|\s*\*\*\d+\s*·/.test(l))
    .map((f) => {
      const [cab, contenido, lista] = celdas(f);
      const n = Number(cab.match(/\*\*(\d+)/)?.[1]);
      const nombre = cab.match(/\*\*\d+\s*·\s*([^*]+)\*\*/)?.[1].trim() ?? '';
      const cerrada = cab.includes('✅');
      const fecha = cab.match(/(\d{4}-\d{2}-\d{2})/)?.[1];
      return { n, nombre, cerrada, fecha, contenido: textoPlano(contenido), lista: textoPlano(lista) };
    });
  const actual = lista.find((e) => !e.cerrada);
  // Las tareas de la etapa en curso: filas «| 1.4g ✅ | … |» de su tabla.
  const tareas = md
    .split('\n')
    .filter((l) => actual && new RegExp(`^\\|\\s*${actual.n}\\.\\d+[a-z]?\\b`).test(l))
    .map((f) => {
      const [id, desc] = celdas(f);
      return { id: id.replace('✅', '').trim(), hecha: id.includes('✅'), desc: primeraOracion(desc, 140) };
    });
  return { lista, actual, tareas };
}

export interface Plan {
  doc: Doc;
  titulo: string;
  fecha: string; // AAAA-MM-DD o AAAA-MM
  estado: string;
  activo: boolean;
}

export async function planes(): Promise<Plan[]> {
  const { docs } = await cargar();
  const lista: Plan[] = [];
  for (const d of docs.values()) {
    const m = d.rel.match(/^planes\/(activos|completados)\/(\d{4}-\d{2}(?:-\d{2})?)/);
    if (!m) continue;
    const md = d.entrada.body ?? '';
    lista.push({
      doc: d,
      titulo: mayuscula(d.titulo.replace(/^Plan:\s*/i, '')),
      fecha: m[2],
      estado: textoPlano(md.match(/\*\*Estado:\*\*\s*(.+)/)?.[1] ?? (m[1] === 'activos' ? 'en curso' : 'terminado')),
      activo: m[1] === 'activos',
    });
  }
  return lista.sort((a, b) => b.fecha.localeCompare(a.fecha) || b.doc.rel.localeCompare(a.doc.rel));
}

export interface Decision {
  doc: Doc;
  numero: string;
  titulo: string;
  estado: string;
  tipo: 'aceptada' | 'propuesta' | 'reemplazada' | 'otra';
  fecha: string;
  decide: string;
  resumen: string;
}

export async function decisiones(): Promise<Decision[]> {
  const { docs } = await cargar();
  const lista: Decision[] = [];
  for (const d of docs.values()) {
    const m = d.rel.match(/^decisiones\/(\d{4})-/);
    if (!m) continue;
    const md = d.entrada.body ?? '';
    const campo = (n: string) => textoPlano(md.match(new RegExp(`\\*\\*${n}:\\*\\*\\s*(.+)`))?.[1] ?? '');
    const estado = campo('Estado');
    const tipo = /reemplaz/i.test(estado) ? 'reemplazada' : /propuesta/i.test(estado) ? 'propuesta' : /aceptada/i.test(estado) ? 'aceptada' : 'otra';
    const seccion = md.split(/^## Decisi[oó]n\s*$/m)[1] ?? '';
    const parrafo = seccion.trim().split(/\n\s*\n/)[0] ?? '';
    lista.push({
      doc: d,
      numero: m[1],
      titulo: d.titulo.replace(/^\d{4}\s*[—-]\s*/, ''),
      estado,
      tipo,
      fecha: campo('Fecha'),
      decide: campo('Decide'),
      resumen: primeraOracion(parrafo.replace(/^[-*]\s+/gm, '')),
    });
  }
  return lista.sort((a, b) => a.numero.localeCompare(b.numero));
}

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
/** 2026-10-22 → 22 oct 2026 · 2026-10 → oct 2026 */
export const fechaCorta = (f: string) => {
  const [a, m, d] = f.split('-');
  const mes = MESES[Number(m) - 1] ?? m;
  return d ? `${Number(d)} ${mes} ${a}` : `${mes} ${a}`;
};
