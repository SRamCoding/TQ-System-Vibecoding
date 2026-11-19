# ERP · la operación

- **Versión:** 0.3.1 · **Etapa:** especificación
- **Desde:** 9 de octubre de 2026
- **Valida:** cada flujo con su área (ver la tabla)
- **Aprueba la línea base:** Gerencia General

## Qué cubre

El ERP es la parte del sistema que se ocupa de **cumplir y cobrar**: convertir un contrato en un
proyecto, comprar y llevar el material a obra, registrar las horas de la gente, cobrar, pagar,
cerrar el proyecto y responder la pregunta que justifica todo: **cuánto se ganó realmente**. Arranca
donde termina el CRM, en el traspaso de una oportunidad ganada a contrato.

## Dónde está escrito

1. **Especificación v4** (21 set 2026), partida en `docs/diseno/v4/`: los flujos en la parte 4, las
   fichas de cada documento en la parte 6 y los números que deben dar las pruebas en la parte 8.
2. **Flujograma de gastos** (`docs/diseno/flujograma-gastos.md`): cómo se obtiene cada tipo de gasto y
   el selector de imputación.
3. **Prototipo 4** (`docs/diseno/fuentes/techquk-prototipo_4_.html`): pantallas y roles.
4. **Modelo de datos v2** (`docs/diseno/techquk_modelo.dbml`): 210 tablas.
5. **Documentos de agosto actualizados a 3.0** (`docs/diseno/01` a `10`): solo en lo que las fuentes
   anteriores no contradicen. Qué quedó superado está en `docs/diseno/00-Reconciliacion-con-v4.md`.

## Los flujos y su estado

| Flujo | De qué trata | Se construye en | Lo valida | Estado |
|---|---|---|---|---|
| **F01** | Del contrato al proyecto listo para ejecutar | Etapa 2 | Adm. de Contratos, Robert y especialistas (árbol de partidas) | Propuesto |
| **F02** | De la necesidad al costo: requerimientos, cuadro comparativo, órdenes, almacén | Etapa 2 (importación en etapa 5) | Logística y compras, Almacén | Propuesto |
| **F03** | Las horas de la gente: habilitación, asignación y tareo | Etapa 2 | Por definir | Propuesto |
| **F04** | De la conformidad al cobro | Etapa 3 | Contabilidad (facturador actual) | Propuesto |
| **F05** | El dinero que sale y la contabilidad | Etapa 3 | Lisset (tesorería) y el contador | Propuesto |
| **F06** | El cierre del proyecto | Etapa 3 | Por definir | Propuesto |
| **F07** | Gastos generales, no presupuestados y servicios | Etapa 2 | Contabilidad (centros de costo) | Propuesto |
| **F08** | La postventa: tickets, garantías y suscripciones | Etapa 5 | Gerencia | Propuesto |
| **F09** | Las personas: de la necesidad a la salida, con planilla | Etapa 4 | Gerencia, RRHH y el contador | Propuesto |
| **F10** | Los contratos de obra pública: adelantos, valorizaciones, reajuste, liquidación, consorcios | Etapa 3 | Gerencia y Adm. de Contratos | Propuesto |

Ninguno se ha revisado todavía con su área, por eso todos están en *propuesto*. La columna «Lo
valida» sale de la lista de pendientes del negocio (`docs/decisiones/pendientes.md`); donde esa lista
no nombra a nadie, dice «por definir».

## Lo que falta para llegar a 0.4

El ERP está bien especificado, pero tiene dos cosas por ordenar antes de salir a validar:

**Una sola versión de la verdad.** Hoy, para saber qué vale sobre un tema, a veces hay que leer la v4,
el documento de agosto con sus marcas de superado y la reconciliación. El siguiente paso es reescribir
cada flujo en presente, en un solo documento por flujo, y pasar los documentos de agosto a un
histórico. No se pierde nada: quedan en el historial de git y en la carpeta de histórico.

**Identificadores para las reglas.** Cada regla de negocio necesita un nombre estable (por ejemplo
`F02-R03`) para que una prueba pueda decir qué regla verifica. Con eso la cadena se cierra: regla,
flujo, plan, PR y prueba.

## Lo que falta para llegar a 1.0

Recorrer cada flujo con su área, con el documento en la mano, y anotar el resultado en el
[registro de validaciones](../validaciones.md). El plan de levantamiento acordado en septiembre sigue
siendo la guía: primero las metas de Gerencia, luego el pasado (contratos en SEACE y ventas), luego las
sesiones con cada área, y al final recorrer cinco proyectos reales por los flujos para ver si el
sistema los explica. La Gerencia General aprueba la línea base.

Varias decisiones del negocio bloquean partes concretas: el facturador actual y su XML (etapa 3), la
normativa de obra pública (etapa 3), cómo se calcula hoy la planilla (uso real de la planilla) y qué
información puede salir a un servicio de IA (cualquier proceso de IA). El detalle está en
`docs/decisiones/pendientes.md`.

## Pendientes de diseño que vienen de septiembre

- **La sede como dimensión** del selector de imputación y de los gastos generales: falta saber cuántas
  sedes hay y si se abrirán más.
- **Dos carriles por contrato** (completo y simple). Se comparte con el CRM; ver [Común](comun.md).
