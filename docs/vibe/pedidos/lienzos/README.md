# Lienzos de diseño de la quinta tanda

Copia de los lienzos que cita `observaciones-5.md`, para leerlos sin acceso a claude.ai. Son HTML de diseño
(`.dc.html`): el marcado y los estilos son la referencia visual; `dc-import name="X"` incrusta el archivo `X.dc.html`
de la misma carpeta, y `sc-for` / `sc-if` repiten o muestran partes con los datos de ejemplo del bloque de datos.
Son ilustrativos: se copian los colores, medidas, formas y la disposición, no el código.

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 1. Ícono de la agenda | `agenda/` | `Main.dc.html` (oscuro) y `MainClaro.dc.html` (claro) |
| 4. Embudo de oportunidades | `embudo/` | `Refinada.dc.html` (la «A refinada con gemas»; `Gema.dc.html` dibuja cada gema) |
| 5. Colores que siguen al ambiente | `ambiente/` | `Main.dc.html` (compara «hoy» y «propuesta» en un ambiente frío y uno cálido; cada escena es `Escena.dc.html`) |
| 6. Línea de tiempo | `linea-de-tiempo/` | `Main.dc.html` (la «A · Cronograma refinado»; orden, filtros y animación están solo en el documento) |

## Sexta tanda

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 6.4 La carita del asistente | `asistente/` | `A.dc.html` (la «Gota de miel» elegida); `C.dc.html` es el ámbar al que se va tornando con el trabajo seguido |

## Séptima tanda

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 7.3 Postulación (ruta por pasos) | `postulacion/` | `Main.dc.html` (la «A · Ruta por pasos»); `Oferta.dc.html`, la oferta desde el presupuesto |
| 7.5 Bandeja de Convocatorias del SEACE | `convocatorias/` | `Main.dc.html` (la «B · Bandeja con detalle»); `Configuracion.dc.html`, la configuración de la búsqueda |
| 7.6 Campañas | `campanias/` | `Main.dc.html` (campañas con su retorno), `Clientes.dc.html` (Mis clientes), `Pac.dc.html` (Radar del PAC, primero investigar) |

## Octava tanda

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 8.8 La agenda nueva | `agenda-nueva/` | `Main.dc.html` (la «A · Mes con actividades»; las vistas Semana y Lista no se construyen) |

## Novena tanda

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 9.5 Mejoras de Oportunidades | `oportunidades-mejoras/` | Cada pizarra lleva el número de la mejora. **Sin la barrita del margen** de `Main.dc.html`: solo la etiqueta (observación 9.5, punto 1) |
| 9.4, 9.9 a 9.12 Campañas y Canales | `campanias-vista/` | `Main.dc.html` (Campañas), `Canales.dc.html`, `Clientes.dc.html` («Volver a vender»). **Manda el texto** donde difiere del dibujo: contactos sin barra y «N de X» en oportunidades y ganadas (9.9), retorno esperado (9.11) |

## Décima tanda

| Observación | Carpeta | Archivo que manda |
|---|---|---|
| 10.1 Almacén | `almacen/` | `Main.dc.html` (portada y kardex), `Movimientos.dc.html`, `Conteo.dc.html`, `Series.dc.html`. **Las series usan las de Postventa**, no se crean otras |
| 10.2 Abastecer | `abastecer/` | `Main.dc.html`, `Requerimiento.dc.html`, `Proveedor.dc.html`, `Entregas.dc.html`, `Plan.dc.html` |
| 10.3 Facturación y cobranza | `cobranza/` | `Main.dc.html`, `Documentos.dc.html`, `Cobranzas.dc.html`, `Conciliacion.dc.html`. Las fases del SIAF: primero investigar |
| 10.4 Postventa | `postventa/` | `Main.dc.html` y las demás pizarras de la carpeta |
