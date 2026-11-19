# Análisis de sistemas · TechQuk Integral System

Este es el punto de partida para entender qué sistema se está construyendo para Techquk S.A.C., por qué
es así y en qué estado está cada parte. Lo usan las personas del proyecto y también los agentes de IA
que trabajan en él: todos parten de aquí.

## Estado actual

*Al 20 de octubre de 2026.*

| Bloque | Versión | Etapa | Qué sigue |
|---|---|---|---|
| [**CRM**](bloques/crm.md) · la cara comercial | **0.4.2** | Validación | Terminar la revisión con Cristhian y luego con Miguel, gerente comercial, para llegar a 1.0 |
| [**ERP**](bloques/erp.md) · la operación | **0.3.1** | Especificación | Reescribir cada flujo en presente y dar identificador a sus reglas, para salir a validar |
| [**Común**](bloques/comun.md) · lo compartido y la frontera | **0.3.14** | Especificación | Seguir construyendo el núcleo (tareas 1.3 en adelante) y validar la frontera con ambos lados |

**Ningún bloque tiene todavía línea base (1.0).** Eso significa que lo escrito es una propuesta
detallada y, en el caso del CRM, una propuesta en revisión. Lo que la empresa acordó construir
recién quedará fijado cuando cada bloque llegue a 1.0.

## Cómo se lee este análisis

**Si es la primera vez,** empieza por la [historia](historia.md). En unos minutos explica de dónde viene
el sistema, qué se descartó en el camino y por qué.

**Si quieres saber qué significan los números,** lee [cómo se versiona](versionado.md). La idea
central: el número no cuenta ediciones, dice qué tan maduro está cada bloque.

**Si buscas una parte concreta,** ve al documento de su bloque: [CRM](bloques/crm.md),
[ERP](bloques/erp.md) o [Común](bloques/comun.md). Cada uno dice qué cubre, dónde está escrito al
detalle, el estado de cada módulo o flujo y qué falta para la siguiente versión.

**Si quieres saber qué cambió y cuándo,** está en el [registro de cambios](cambios.md). Y lo que se
revisó con las personas de Techquk, con quién y con qué resultado, está en el
[registro de validaciones](validaciones.md).

## Cómo se relaciona con el resto de la documentación

Este análisis dice **en qué estado está cada parte y de dónde viene**. El detalle de **qué** hace cada
flujo sigue en `docs/diseno/` (la especificación v4 por partes, el diseño de pantallas del CRM, el
modelo de datos). El **cómo** se construye está en `docs/convenciones/` y `ARCHITECTURE.md`. Y las
decisiones tomadas, en `docs/decisiones/`.

```text
  necesidad          análisis             diseño            construcción          prueba
 (una persona) ──▶  (bloque y versión) ──▶ (flujo, pantalla) ──▶ (plan, rama, PR) ──▶ (escenario v4)
      │                    │                                         │                    │
      └──── validaciones ──┘                                         └──── mapa de etapas ┘
```

La cadena completa es lo que permite responder, para cualquier parte del sistema, quién la pidió,
cuándo se decidió, dónde se construyó y qué prueba demuestra que funciona.

## Reglas de este análisis

1. **Todo cambio entra por PR,** igual que el código, y deja una línea en el registro de cambios.
2. **Nada se valida en silencio.** Si se revisó con alguien, se anota en el registro de validaciones.
3. **Lo que no se sabe se dice.** Las partes reconstruidas llevan su marca; no se inventan fechas ni
   versiones.
4. **Las fuentes no se corrigen.** Los PDF, los prototipos y los lienzos de diseño se guardan como
   llegaron. Si algo cambia, se anota en el análisis.
