# CRM · la cara comercial

- **Versión:** 0.4.2 · **Etapa:** validación
- **Desde:** 15 de octubre de 2026
- **Valida:** Cristhian (en curso) y luego Miguel, gerente comercial
- **Aprueba la línea base:** Gerencia Comercial

## Qué cubre

El CRM es la parte del sistema que se ocupa de **ganar**: saber quiénes son los clientes, qué
oportunidades hay con cada uno, cuánto se les cotizó, a qué licitaciones se postula y con qué
resultado. Termina en un punto exacto: cuando una oportunidad se gana, el CRM la entrega al ERP como
contrato, sin volver a escribir nada. Ese punto es la frontera, y se describe en el bloque
[Común](comun.md).

## Dónde está escrito

Las fuentes vigentes, de la más reciente a la más antigua:

1. **Diseño de pantallas del 13 oct 2026**, en `docs/diseno/fuentes/crm-pantallas-2026-10-13/`.
   Es lo más detallado y lo más nuevo: 63 pantallas y 5 componentes. Su lista legible está en el
   [inventario de pantallas](crm-pantallas.md).
2. **Especificación v4, parte 2** (21 set 2026), en `docs/diseno/v4/parte-2-crm.md`. Tiene las reglas
   y los recorridos por rol que las pantallas muestran.
3. **Prototipo 4** (set 2026), en `docs/diseno/fuentes/techquk-prototipo_4_.html`, para la navegación
   general entre el CRM y el ERP.

Si el diseño y la v4 dicen cosas distintas, se toma el diseño, por ser posterior, y la diferencia se
anota como observación para confirmarla en la validación. No se resuelve en silencio.

## Los módulos y su estado

| Módulo | Qué hace | Pantallas | Estado |
|---|---|---|---|
| **CRM-01** · Cuentas y contactos | Ficha única de cada cliente, con sus contactos (que rotan), actividades, oportunidades, cotizaciones, licitaciones y contratos | 13 | En validación |
| **CRM-02** · Oportunidades | El embudo de ventas: etapas, avance con motivo, competidores, presupuesto y cierre ganado o perdido (con motivo obligatorio) | 13 | En validación |
| **CRM-03** · Cotizaciones | Editor, versiones comparables, aprobación de descuentos y el PDF que recibe el cliente | 7 | En validación |
| **CRM-04** · Licitaciones | Desde la convocatoria hasta el resultado: requisitos, consultas, cronograma, análisis de precios con IA, propuesta, expediente e histórico de la entidad | 15 | En validación |
| **CRM-05** · Agenda y actividades | Llamadas, visitas y tareas, en semana y en escritorio | 3 | En validación |
| **CRM-06** · Catálogo comercial | Productos con precios, carga masiva y equivalencias con el catálogo del Estado | 5 | En validación |
| **CRM-07** · Campañas y leads | Campañas y conversión de un lead en cuenta y oportunidad | 4 | En validación |

Además: el inicio del ejecutivo comercial, el tablero de Gerencia Comercial (embudo, conversión por
etapa, motivos de pérdida) y la pantalla de traspaso a contrato.

«En validación» significa que el módulo se está revisando, no que ya pasó la revisión. Cuando una
sesión lo revise, cambia a *validado* o *con observaciones*, con la fecha y la persona, en el
[registro de validaciones](../validaciones.md).

## Lo que falta para llegar a 1.0

**Terminar la validación.** Cristhian revisa primero; Miguel da la versión final. Cada sesión se anota
en el registro, y cada observación se resuelve o queda escrita como pendiente consciente.

**Decidir qué pasa con lo que quedó fuera del diseño.** Hay ideas del 22 de septiembre que no
aparecen en las pantallas del 13 de octubre (se buscaron y no están):

- **Fuente automática de convocatorias.** La idea era usar Licitalab, que Techquk contrató para vigilar
  licitaciones, pero no se sabe si ofrece una API. El 20 de octubre se verificó otra vía: los datos
  abiertos del OECE traen cada proceso con el enlace de descarga directa de sus bases, y esos enlaces
  bajan el PDF sin captcha. La propuesta es el [ADR 0018](../../decisiones/0018-convocatorias-desde-datos-abiertos-oece.md):
  convocatorias diarias filtradas por rubro, bases adjuntas en la misma convocatoria e histórico de
  adjudicaciones desde el inicio, dentro del MVP (etapa 2) sin ser condición para cerrarla. Licitalab
  queda como complemento a evaluar. Falta el visto bueno de la Gerencia Comercial y que defina los
  filtros (rubros, palabras clave, regiones, montos).
- **Tablero «¿postulamos?»** con el histórico propio, para que Gerencia decida si conviene presentarse.
  El tablero de Gerencia Comercial del diseño mira el embudo, no esa decisión.
- **Dos carriles por contrato** (completo y simple, el llamado *small business*), con la decisión
  registrada. Toca la frontera con el ERP.
- **Expediente para firma de contrato** como lista de verificación con plazo. El diseño tiene el
  expediente de postulación, no el de firma.

Para cada una hay tres salidas posibles: entra al CRM 1.0, queda para una versión 1.x, o se descarta
con su razón.

**Validar con el área comercial los motivos de pérdida.** La lista inicial es precio, plazo, requisito,
competencia y sin presupuesto. Ahora usa el catálogo único de motivos del sistema: se edita desde
pantalla, se desactiva sin borrarse y siempre tiene «Otro», que obliga a escribir el detalle (plan 1.5,
D9b). Falta confirmar si esos cinco son los que el área necesita para analizar por qué se pierde.

**Confirmar los nombres de ejemplo.** Las personas que aparecen como clientes o contactos en el diseño
deben ser de ejemplo. Las de Techquk que figuran en roles deben ser las reales o quedar como cargos.

**Aprobación de la Gerencia Comercial.** Con ella el CRM pasa a 1.0 y queda listo para construirse.

## Decisiones pendientes que lo tocan

De `docs/decisiones/pendientes.md`: **CUBSO** (si Licitaciones lo necesita; la tabla existe sin carga)
y **aprobaciones** (los umbrales de descuento que fija Gerencia). Ninguna impide validar; ambas son
configurables. También **fuente de convocatorias** (el alcance del ADR 0018 y lo que ofrece Licitalab):
tampoco impide validar. Los datos del OECE clasifican cada ítem con su código CUBSO, así que los filtros
por rubro pueden usar ese catálogo si se decide cargarlo.
