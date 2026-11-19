# Ficha · <nombre de la funcionalidad>

<!-- Archivo: docs/traspaso/fichas/<tramo>-<nombre-corto>.md (por ejemplo, t1-licitaciones.md).
     Se escribe para alguien que no vio nada de lo que pasó aquí: el agente del repo original. -->

- **Estado:** en curso | aprobada
- **Versión:** v1
- **Tramo de la ruta:** T<n>
- **Aprobada:** <fecha> · commit `<hash>` · etiqueta `aprobado/<ficha>-v1`
- **Fuentes del diseño:** <partes del v4, pantallas del CRM, planes viejos que inspiraron esto>

## Qué hace

En presente y en pocas frases, como se lo contarías a alguien de Techquk: para qué sirve, quién lo usa y qué problema
le resuelve.

## Decisiones de Sebastián

Las **vigentes**, cada una con su número del registro (`decisiones.md`), donde está su historia completa. Es lo más
valioso de la ficha: el repo original no las vuelve a discutir. Si una contradice el v4, una decisión aceptada o un
ADR del repo original, se dice aquí con el documento que contradice.

- **MV-0NN** (alcance) · <decisión en una línea>

## Pantallas y navegación

Por dónde se entra, qué se ve primero, a dónde lleva cada clic, qué se abre en panel y qué en página. Las URL de
este repo, para abrirlas con la etiqueta. Si Sebastián pidió algo visual concreto (un color, una animación, un
orden), va aquí.

## Operaciones

Lo que una persona puede hacer, una por una: nombre, campos que pide (con tipo y si son obligatorios), qué valida,
qué cambia y qué efectos tiene en otros módulos. En el repo original cada una es una acción registrada y un endpoint.

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|

## Estados

Si el documento tiene estados: cuáles, qué transición lleva de uno a otro, quién puede hacerla y qué efecto tiene
cada una (y qué se revierte al anular).

## Datos

Tablas y columnas nuevas o cambiadas respecto de la etiqueta `arranque` (el estado del 19 de noviembre de 2026), con su tipo y si son obligatorias o únicas.
Catálogos nuevos y sus valores. Lo que cambió del DBML.

## Reglas y números

Las reglas de negocio con un ejemplo con números exactos (montos, fechas, saldos). Cada ejemplo será una prueba en el
repo original, como los escenarios del v4.

## Supuestos sin confirmar

Lo que el agente decidió solo porque Sebastián no estaba o dijo «decide tú». El repo original no los construye como
definitivos sin preguntar.

## Atajos tomados

Lo que aquí se hizo rápido y el repo original tiene que hacer bien. Solo lo propio de esta ficha (lo general está en
`atajos-generales.md`). Por ejemplo: lógica escrita en la pantalla en vez de un servicio, un estado guardado como
texto, sin bloqueo optimista, un cálculo duplicado, una consulta lenta, un permiso que no se revisa, un dato
sensible a la vista, la IA sin tope de costo.

## Dónde está en el código

Carpetas y archivos principales de este repo, para quien quiera abrirlos.

## Cambios desde la versión anterior

(Solo desde la v2.)
