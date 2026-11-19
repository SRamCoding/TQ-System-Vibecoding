# Cómo se versiona el análisis

Este documento explica qué significa el número de versión del análisis y qué hace falta para pasar
de un número al siguiente. La decisión de trabajar así está en
[ADR 0014](../decisiones/0014-versionado-del-analisis.md).

## La idea en una frase

**El número de versión dice qué tan maduro está el análisis.** Con solo verlo se sabe si lo escrito
es todavía una idea del analista, una propuesta detallada, algo que ya revisaron las personas que lo
van a usar o lo que la empresa acordó construir.

## Por qué así

Un análisis de sistemas crece siempre en el mismo orden. Primero se entiende la empresa tal como es.
Luego se propone cómo debería funcionar. Después se detalla esa propuesta hasta que un desarrollador
pueda construirla sin adivinar. Y al final la propuesta pasa por las personas que la usarán, que la
corrigen y la aprueban. Saltarse un paso no lo elimina: solo hace que el error aparezca más tarde,
cuando cuesta más arreglarlo.

Por eso el número no cuenta «cuántas veces se editó el documento», como hacían el «v2.1» de agosto o
el «v4» de septiembre. Cuenta en qué paso de ese camino está cada parte del sistema.

## Los bloques se versionan por separado

El análisis se divide en tres bloques, y cada uno lleva su propio número:

- **CRM**: la cara comercial (cuentas, oportunidades, cotizaciones, licitaciones, agenda, catálogo,
  campañas). Lo valida el área comercial.
- **ERP**: la operación (contratos, proyectos, compras, almacén, horas, dinero, cierre, postventa,
  personas, obra pública). Lo validan Operaciones, Administración y el contador.
- **Común**: lo que usan los dos (personas y roles, usuarios y permisos, estados, numeración,
  aprobaciones, auditoría, alertas, IA) y la **frontera** entre ambos, es decir, el traspaso de una
  oportunidad ganada a contrato.

Se separan porque avanzan a ritmos distintos y los validan personas distintas. Así, el CRM puede
llegar a su línea base y empezar a construirse mientras el ERP sigue madurando, sin que uno espere
al otro.

## El significado de cada número

Las versiones tienen tres números: `MAYOR.MENOR.PARCHE`.

### Antes de la línea base: 0.x

Mientras un bloque no está aprobado, su versión empieza con 0. El segundo número dice la etapa:

| Versión | Etapa | Qué contiene | Se cierra cuando… |
|---|---|---|---|
| **0.1** | Diagnóstico | La empresa como es hoy: áreas, personas, procesos actuales, sistemas que usa y sus problemas | Cada proceso actual está descrito y sus problemas tienen una fuente (quién lo contó y cuándo) |
| **0.2** | Propuesta | Cómo debería funcionar: flujos nuevos, módulos, alcance, lo que entra y lo que no | Hay un flujo propuesto para cada proceso del diagnóstico, o una razón escrita para dejarlo fuera |
| **0.3** | Especificación | El detalle construible: pantallas, documentos, estados, reglas, modelo de datos y criterios de aceptación con números | Un desarrollador puede construir cada flujo sin preguntar «¿y aquí qué pasa?» |
| **0.4** | Validación | La especificación pasa por las personas que la usarán; cada flujo queda validado o con observaciones | Todas las observaciones están resueltas o anotadas como pendientes conscientes |

El tercer número cuenta los avances dentro de la etapa: `0.3.0`, `0.3.1`, `0.3.2`… Sube cada vez que
entra algo con contenido, por ejemplo un flujo nuevo, una decisión resuelta o una sesión de
validación registrada.

### La línea base: 1.0

La **1.0** es un momento especial: significa «esto es lo que la empresa acordó construir». La aprueba
quien tiene autoridad sobre el bloque (Gerencia Comercial para el CRM, Gerencia General para el ERP
y lo común) y la aprobación queda registrada en [validaciones](validaciones.md).

Desde ahí, el bloque ya no se cambia libremente:

- **1.1, 1.2…** Cada cambio entra como una solicitud con su origen (quién lo pidió, por qué y qué
  afecta), se aprueba y sube el segundo número.
- **1.0.1, 1.0.2…** Correcciones que no cambian el significado: una errata, un ejemplo más claro, un
  enlace roto.
- **2.0** Solo ante un cambio grande de alcance, por ejemplo decidir que el ERP lleve la contabilidad
  oficial completa. Un cambio así vuelve a pasar por propuesta, especificación y validación.

## El estado de cada pieza

Además del número del bloque, cada flujo, módulo o regla tiene su propio estado. Así se ve qué parte
de un bloque está más avanzada:

1. **Propuesto**: escrito por el analista, todavía sin revisar con nadie.
2. **Validado**: revisado con la persona que lo usará, sin observaciones pendientes. Se anota con
   quién y cuándo.
3. **Con observaciones**: revisado, pero algo hay que corregir o decidir.
4. **Aprobado**: forma parte de una línea base (1.0 o posterior).
5. **Construido**: el código existe y está en `main`.
6. **Probado**: su escenario de aceptación pasa en las pruebas automáticas.

Los estados 5 y 6 los da el desarrollo, no el análisis. Se ponen aquí para que la cadena completa,
de la necesidad a la prueba, se pueda seguir desde un solo lugar.

## Identificadores

Para que una prueba, un plan o un PR puedan decir «esto implementa tal cosa», cada pieza del análisis
necesita un nombre que no cambie:

- **Bloques:** `CRM`, `ERP`, `COMUN`.
- **Módulos del CRM:** `CRM-01` a `CRM-07`, los mismos de la especificación v4 y del diseño de pantallas.
- **Flujos del ERP:** `F01` a `F10`, los diez flujos de la v4 (parte 4).
- **Mecanismos comunes:** `M1` a `M13`, los mismos que usa el mapa de etapas (por ejemplo `M4`
  auditoría, `M6` numeración).

Las reglas de negocio todavía no tienen identificador propio. Ponérselo es parte de llevar cada
bloque a 0.4.

## Cómo se registra un cambio

1. El cambio se hace en una rama, como el código, y entra por PR.
2. El PR actualiza el documento del bloque y agrega una línea en el [registro de cambios](cambios.md)
   con la nueva versión, la fecha, qué cambió y de dónde salió (una sesión, una decisión, una fuente).
3. Al fusionarse, la versión queda marcada en git con una etiqueta: `analisis-crm-v0.4.0`,
   `analisis-erp-v0.3.1`, `analisis-comun-v0.3.1`. Así se puede ver el análisis exactamente como
   estaba en cualquier versión.

## Relación con las versiones del software

El sistema tendrá sus propias versiones cuando se despliegue. Son números distintos y no tienen por
qué coincidir. Lo que sí se registra es contra qué versión del análisis se construyó cada entrega,
por ejemplo «la entrega 1 del CRM implementa CRM 1.0».

## Las versiones de antes de este esquema

Lo hecho antes del 15 de octubre de 2026 no seguía este esquema. Se le asignó una versión mirando
qué etapa cubría cada documento, y solo donde hay evidencia (un archivo con fecha, un commit, un
lienzo). Lo que no se sabe se dice así: no se inventaron versiones intermedias. El detalle está en la
[historia](historia.md) y en el [registro de cambios](cambios.md).

Los nombres viejos («v2.1», «v3.0», «v4») se mantienen dentro de los documentos que ya los tienen,
porque son parte de su historia, pero ya no se usan para hablar del estado del análisis.
