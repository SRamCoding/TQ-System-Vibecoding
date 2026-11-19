# La historia del análisis

Este documento cuenta cómo llegó el análisis a donde está: qué se hizo, en qué orden, qué se
descartó y por qué. Sirve para entender las decisiones de hoy sin tener que reconstruirlas, y para
que nadie reabra una discusión sin saber cómo se cerró.

La historia se escribió el 15 de octubre de 2026, mirando hacia atrás. Hasta esa fecha el análisis no
se versionaba con un esquema, así que cada capítulo dice **en qué se apoya**:

- **[V] Verificado:** hay un archivo con fecha en el repositorio, un commit o un lienzo que lo prueba.
- **[S] Reconstruido:** se deduce de la estructura de los documentos o de lo que se conversó en su
  momento, pero no hay un archivo que lo fije con fecha exacta.

Las versiones que aparecen entre paréntesis son las que se asignaron a posteriori con el
[esquema de versionado](versionado.md). El detalle de cada una está en el [registro de cambios](cambios.md).

---

## 1. El punto de partida: Techquk y S10

Techquk S.A.C. es un integrador tecnológico de Chiclayo, con unas veintidós personas, que trabaja
sobre todo para el sector público: videovigilancia, redes, electrónica de seguridad y software. Gana
sus contratos por licitación y los ejecuta como proyectos.

Para su gestión usa **S10**, un ERP comercial sobre SQL Server, con los módulos de Presupuestos,
Gerencia de Proyectos, Compras, Almacén, Administración, Facturación y Contabilidad. La contabilidad
pasó antes por Concar y por Navasoft. **[S]**

La pregunta que dio origen a todo, y que sigue justificando el sistema, es una sola: **¿cuánto se gana
realmente en cada proyecto?** S10 registra compras y presupuestos, pero no responde esa pregunta de
forma directa, y la parte comercial (clientes, oportunidades, licitaciones) vive fuera de él.

## 2. Agosto de 2026: el primer análisis

*(ERP 0.1 y 0.2 · CRM 0.2)*

El primer paquete de análisis se armó en agosto. Tuvo dos partes que hoy se distinguen bien.

La primera fue **entender la empresa**. Se propuso un organigrama de cinco gerencias, se levantaron
procesos con un formulario en Jotform, se dibujaron flujos en BPMN (Recursos Humanos entre ellos) y se
registraron las brechas entre lo que la empresa hace y lo que necesita. Eso es un diagnóstico. **[S]**
Ese diagnóstico se conserva íntegro en el [archivo de agosto](archivo/2026-08-24-analisis-funcional/README.md):
resumen ejecutivo, procesos, organigrama, formulario de levantamiento y el análisis completo de la
base de S10. **[V]**

La segunda fue **proponer el sistema**. Son los documentos 01 (plan del ERP y el CRM, versión 2.1
«para revisión de Gerencia»), 05 (construcción técnica), 06 (núcleo de datos), 08 (columnas), 09-B
(procesos propuestos), 10 (módulos, procesos y paneles), el recorrido central y un modelo de datos de
149 tablas. **[V]** Su versión actual está en `docs/diseno/`, con la fecha de agosto en la cabecera,
y la original está en el archivo. El modelo de 149 tablas no se conserva. En
paralelo hubo un documento propio del CRM, «El CRM de Techquk». **[V]** Lo nombra la especificación
v4 como uno de los que reemplaza, pero el archivo no está en el repositorio.

Varias preguntas grandes quedaron abiertas en agosto: si el eje del sistema era el contrato o el
proyecto, si el ERP emitiría facturas, si la planilla y la contabilidad se llevarían dentro o fuera.

## 3. Septiembre: el desvío por S10 y la vuelta

*(sin versión: fue una evaluación, no un avance del análisis)*

A comienzos de septiembre se exploró otro camino: en vez de un sistema propio, construir una capa web
que leyera y escribiera directamente en la base de S10. Para evaluarlo se estudió la base a fondo:
cerca de 1,916 tablas, unas 519 con datos, y más de 11,000 objetos programables, casi todos
procedimientos almacenados. **[S]** El catálogo de tablas, el diccionario de columnas y el mapa de
relaciones quedaron como archivos del proyecto de Claude.

La conclusión fue que escribir en S10 era riesgoso. Sus procedimientos no son una interfaz pensada
para terceros, el proveedor puede cambiarlos sin aviso, escribir en su base probablemente anula el
soporte y su forma de bloquear registros choca con el uso web. Además, la base mostró que S10 funciona
sobre todo como sistema de compras: lo comercial vive en otro lado. **[S]**

A mediados de septiembre se retomó la idea original, ahora con más claridad: **un sistema propio,
hecho desde cero, que toma de S10 lo que funciona** (sus conceptos de presupuesto, partidas de control
y compras) **pero no depende de él**. En esas semanas se cerraron decisiones de fondo: una sola empresa,
planilla dentro del sistema con un módulo de RRHH propio, gasto directo enlazado o no a un proyecto,
material que a veces va a almacén y a veces directo a obra, fórmula polinómica incluida y asientos
contables exportados a Excel para el contador. **[S]**

## 4. El 21 de septiembre: la especificación unificada

*(ERP 0.3.0 · CRM 0.3.0 · Común 0.3.0)*

Todo lo anterior se consolidó en la **especificación funcional unificada, versión 4**, fechada el 21
de septiembre de 2026, que reemplazó a «El CRM de Techquk» y a «El ERP de Techquk v3». **[V]** Por esa
mención se sabe que entre agosto y septiembre existió al menos una versión 3 del ERP, aunque no se
conserva.

La v4 trajo cambios de fondo respecto de agosto:

- El **proyecto es el eje**; el contrato es la cara hacia el cliente.
- Cada línea de costo va a una **partida de control** o al gasto directo con centro de costo: nada sin
  destino.
- Cada partida tiene **cuatro números**: presupuestado, comprometido, ejecutado y pagado.
- Entran el **presupuesto con APU**, la **obra pública** completa (adelantos, valorizaciones, reajuste,
  retención, liquidación, consorcios), la **planilla** y la **IA** como mecanismo que sugiere y deja
  que una persona confirme.

Con ella llegaron el **prototipo navegable 4** y el **flujograma de gastos**. **[V]** Los tres están
en `docs/diseno/fuentes/`.

La v4 es una especificación, no una propuesta general: describe pantallas, documentos, estados,
efectos y escenarios de aceptación con números exactos. Por eso los tres bloques quedan en 0.3. Sin
embargo, buena parte de lo que dice no se había revisado todavía con las personas que lo usarían:
su propia parte 9 y la lista de pendientes dejan más de veinte temas esperando a Gerencia, a Robert,
a Lisset, a Ronal, al contador o a Logística.

Al día siguiente, el 22 de septiembre, aparecieron ideas nuevas para el CRM que la v4 no alcanzó a
recoger: usar **Licitalab**, que la empresa acababa de contratar, como fuente de convocatorias; un
tablero «¿postulamos?» con el histórico propio para que Gerencia decida; dos carriles por contrato
(completo y simple); el paso «expediente para firma de contrato»; y la **sede** como dimensión de los
gastos generales. **[S]** Siguen pendientes de incorporar (ver [CRM](bloques/crm.md)).

## 5. Del 6 al 10 de octubre: el repositorio y la reconciliación

*(ERP 0.3.1 · Común 0.3.1)*

El 6 de octubre se creó el repositorio `TechQuk-IntegralSystem`. **[V]** El 9 de octubre se le cargó
el «harness»: las reglas para trabajar con agentes, las convenciones de código y base de datos, las
primeras decisiones de arquitectura y, sobre todo, el análisis. **[V]**

Al traerlo se hizo un trabajo de **reconciliación**. Los documentos de agosto no se borraron: se
actualizaron a una «versión 3.0» con las secciones superadas marcadas, se escribió el documento
`00-Reconciliacion-con-v4.md` para decir qué manda cuando dos fuentes chocan, la v4 se partió en veinte
archivos Markdown para leerla por partes, y el modelo de datos pasó a su versión 2, con 210 tablas en
PostgreSQL. **[V]** Ese ordenamiento es el ERP 0.3.1 y el Común 0.3.1.

El mismo 9 de octubre se cerró la **etapa 0** de construcción (Laravel, Filament, PostgreSQL, Docker y
la navegación de los dos modos, CRM y ERP), y entre el 9 y el 10 se construyeron las primeras piezas
del núcleo: auditoría y registro de acciones, catálogos y parámetros con tipo de cambio automático de
SUNAT, y su edición desde pantalla. **[V]**

Vale anotarlo con honestidad: **la construcción empezó antes de que el análisis tuviera una línea
base.** Para estas piezas el riesgo es bajo, porque son mecanismos que cualquier versión del negocio
necesita y no dependen de cómo trabaje un área en particular. Para los flujos de negocio, en cambio,
conviene esperar la validación de cada bloque.

## 6. El 13 de octubre: el diseño del CRM

*(CRM 0.3.1)*

En otra cuenta de Claude se llevó el CRM mucho más allá de la v4: un lienzo de diseño con **63
pantallas y 5 componentes**, que respeta la misma numeración de módulos (CRM-01 a CRM-07) y agrega el
inicio del ejecutivo comercial, el tablero de Gerencia Comercial y la pantalla de **traspaso a
contrato**, que es la frontera exacta donde el CRM le entrega al ERP. **[V]** El lienzo dice haberse
creado el 13 de octubre.

Es especificación de pantallas, con detalle suficiente para construir, así que el CRM sube dentro de
la misma etapa, a 0.3.1.

## 7. El 15 de octubre: validación del CRM y orden del análisis

*(CRM 0.4.0 · se adopta el versionado)*

Se decidió que **el CRM va primero**. Su validación empezó con Cristhian, que está guiando la revisión de
cómo funcionan hoy las cosas, y seguirá con Miguel, el gerente comercial, que dará la versión final. Con eso el
CRM entra a la etapa de validación: CRM 0.4.0. **[V]** (este repositorio, 15 oct 2026).

Ese mismo día se trajo el lienzo del CRM al repositorio como fuente fechada, para que no dependa de
otra cuenta, y se ordenó el análisis en `docs/analisis/` con el esquema de versionado por bloques
([ADR 0014](../decisiones/0014-versionado-del-analisis.md)). Desde aquí, cada cambio del análisis
deja su rastro en el [registro de cambios](cambios.md).
