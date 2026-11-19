# Documento 00-R — Reconciliación con la especificación v4

**Versión:** 1.0 · Octubre 2026
**Qué es:** el índice de qué sigue vigente del paquete de agosto, qué reemplazó la especificación v4 y qué decisiones están tomadas o abiertas. Si dos fuentes chocan, manda lo que dice aquí.

---

## 1. Orden de precedencia de las fuentes

| # | Fuente | Fecha | Qué gobierna |
|---|---|---|---|
| 1 | `Techquk-Sistema-CRM-ERP-v4.pdf` | 21 set 2026 | El **qué**: flujos, fichas de documentos, estados, efectos, criterios de aceptación |
| 2 | `Techquk-Flujograma-de-gastos-1.pdf` | v4 | Cómo se obtienen todos los gastos (selector de imputación, cuatro caminos de costo) |
| 3 | `techquk-prototipo_4_.html` | v4 | Pantallas, roles, navegación, nombres de módulos |
| 4 | Decisiones del equipo de desarrollo (sección 4 de este documento) | oct 2026 | El **cómo**: motor, hosting, respaldos, repositorio |
| 5 | Documentos de agosto (01, 05, 06, 08, 09B, 10, Flujo Central, DBML) | ago 2026 | Solo lo que 1 a 4 no contradicen |

Los documentos de agosto no se borraron: se actualizaron a v3.0 con las secciones reemplazadas marcadas (⛔ reemplazado, ⚠ matizado).

---

## 2. Lo que la v4 cambia respecto de agosto

| Tema | Agosto | v4 |
|---|---|---|
| **Eje del sistema** | D2 abierta (¿contrato o proyecto?) | **El proyecto es el eje.** Todo proyecto nace de un contrato (salvo los internos). El contrato es la cara hacia el cliente; el proyecto, hacia adentro |
| **Costo** | Tabla única `proyecto_costos` con `partida` como texto libre | **Árbol de partidas de control** por proyecto + **selector de imputación**: toda línea de costo va a una partida o al gasto directo con centro de costo. Nada sin destino |
| **Cuatro números por partida** | Presupuestado y real | Presupuestado → comprometido → ejecutado → pagado. `disponible = presupuestado − comprometido`. `margen estimado = venta sin IGV − máx(presupuestado, comprometido)` por partida |
| **Gastos** | Un solo tipo | Tres clases: general del proyecto (PC-04), general de la empresa (centro de costo), no presupuestado (**PC-99**, con motivo y aprobación; si el motivo es cambio del cliente → *posible adicional*) |
| **Presupuesto** | «Presupuestos de obra, APU y metrados no aplican» (doc 05 §10) | **Presupuesto con APU** (S10 tiene 44 presupuestos en uso), versionado, que pasa al proyecto sin copiarse. **Fórmula polinómica** incluida |
| **Obra pública** | No contemplada | Flujo 10: adelantos, valorizaciones con reajuste, retención, liquidación, consorcios, subcontratos, moneda extranjera |
| **Planilla** | Escenario 1 recomendado: contabilidad y planilla externas | **RRHH con planilla dentro del ERP** (boletas, PLAME, asientos). Contabilidad: asientos por plantilla exportados a Excel; los libros oficiales los lleva el contador fuera |
| **Facturación** | D4 abierta (¿emite el ERP?) | Los comprobantes de venta **se emiten en el facturador actual y se suben por XML**. Las facturas de proveedores también por XML. El ERP valida ante SUNAT y enlaza |
| **Tipos de compra** | Compras genéricas | **Doce tipos configurables** (ver `tipos_compra`) |
| **Aprobaciones** | Reglas por proyecto, moneda y monto | Se mantienen y se configuran **desde pantalla**: umbrales U1, U2, U3 fijados por Gerencia; matriz en doc 06 §17 |
| **IA** | No contemplada | **Mecanismo del núcleo**: sugiere, una persona confirma. Nunca calcula ni aplica reglas. Se activa por proceso. Datos sensibles nunca salen |
| **Seguridad** | Roles y permisos por módulo | Roles ≠ puestos. **Restricciones por campo** (costos, márgenes, sueldos, detalle médico). Separación de funciones con controles compensatorios |
| **Alertas** | Dispersas | **Catálogo único** de alertas y vencimientos; cada persona ajusta anticipación y canal; las obligatorias no se apagan |
| **Orden de construcción** | Fases F0–F8 | **Entregas 0–5** (doc 01 §7) |
| **Interfaz** | No definida | Interfaz única con barra lateral e inicio por mosaicos; móvil por acciones |

---

## 3. Estado de las decisiones del paquete de agosto

| # | Decisión (agosto) | Estado hoy | Fuente |
|---|---|---|---|
| D1 | ¿Multiempresa o empresa única? | ✅ **Empresa única** | v4 + equipo |
| D2 | ¿Eje: contrato o proyecto? | ✅ **Proyecto** | v4 idea 1 |
| D3 | ¿CUBSO en el núcleo? | ⚠ **No aparece en la v4** (ni SEACE ni RNP). La tabla `cubso` queda definida en M5 pero **no se carga** hasta que Licitaciones confirme que la necesita | — |
| D4 | ¿El ERP emite comprobantes? | ✅ **No: facturador actual + XML.** Queda abierta la emisión de **guías de remisión** | v4 parte 7 |
| D5 | ¿Importación directa o por terceros? | ✅ Directa | doc 03 |
| D6 | ¿Desarrollo de software dentro del ERP? | ⚠ Solo las horas (por confirmar con Jefatura de Desarrollo) | v4 |
| D7 | ¿Disponibilidad de servicios medida sola o a mano? | 🔶 Abierta; módulo de suscripciones en Entrega 5 y marcado «especulativo» | v4 |
| D8 | ¿Dedicación de los desarrolladores? | 🔶 Hoy trabaja **una persona**; el horizonte debe recalcularse | equipo |
| D9 | ¿Contabilidad y planilla externas o propias? | ✅ **Híbrido:** planilla dentro; contabilidad por asientos + Excel | v4 |
| D10 (agosto) | ¿Qué software contable y de planillas? | ⚠ Planilla ya no aplica. Falta saber qué programa usa el contador y qué columnas acepta | v4 |
| D10 (v4) | Base de datos: ¿SQL Server o PostgreSQL? | ✅ **PostgreSQL** | equipo, oct 2026 |

> **Ojo con la numeración.** En agosto «D10» era el software contable; en la v4 «D10» es la base de datos. Citar siempre con el nombre. Igualmente, los módulos «ERP-14 Contabilidad» y «ERP-15 Planillas» del documento 01 (escenario 2, descartado) ya no existen: esos números se reutilizan con otro contenido (ver doc 10).

---

## 4. Decisiones del equipo de desarrollo (octubre 2026)

| Tema | Decisión |
|---|---|
| Motor | PostgreSQL 16 |
| Arquitectura | Monolito modular (Laravel + Filament) |
| Alcance de empresa | Una sola empresa; sin `empresa_id` en ninguna tabla |
| Hosting | Servidor propio de Techquk, Linux, administrado por TI. Decisión de la empresa |
| Respaldos | A S3: semanal en desarrollo; diario antes de cargar datos reales; restauración probada en otra máquina antes de ese salto |
| Equipo | Una persona desarrolla, hasta nuevo aviso. Esa persona decide lo técnico |
| Repositorio | `github.com/SRamCoding/TechQuk-IntegralSystem`, cuenta personal. **Debe pasar a privado.** Condición de revisión: antes de sumar a otra persona o de desplegar con datos reales |
| Producción | Los agentes de IA nunca tocan producción ni datos reales |
| Concurrencia | Bloqueo optimista, jobs idempotentes, índices en llaves foráneas, unicidad en la base |
| Motivos (20 oct 2026) | Un solo catálogo (`tipos_motivo`, `motivos`), con una lista por transición y «Otro» con detalle obligatorio. `motivos_perdida` del CRM no se crea: la reemplaza la familia `perdida_oportunidad`. Lo que se analiza se elige, no se escribe libre. Plan 1.5, D9 y D9b |
| Anular (20 oct 2026) | Revierte todos los efectos vivos del documento en orden inverso, no solo los de la última transición como dice la v4 parte 6. Plan 1.5, D4 |

---

## 5. Lo que falta decidir o confirmar (tabla de la v4, con responsables)

| Tema | Qué hay que resolver | Con quién |
|---|---|---|
| Árbol de partidas | Quién responde por cada partida; cómo se ve un árbol real | Robert y especialistas |
| Centros de costo | Cuáles son los reales | Contabilidad |
| Obra pública | De los contratos de estos años, cuáles fueron obras, con adelanto, reajuste, consorcio o retención en lugar de fianza | Gerencia, Administración de Contratos |
| Normativa de obra | Deducciones de reajuste por adelantos, cuándo aplica la retención, plazos de liquidación (reglamento vigente de la Ley 32069) | Administración de Contratos |
| Presupuesto | Cuándo APU completo y cuándo precio por ítem; fórmula polinómica; umbral del camino corto | Ronal y Lisset |
| Tipos de compra | Cuáles ocurren y con qué frecuencia | Logística y compras |
| Almacén de obra | Cuánto material va directo a la obra | Almacén y especialistas |
| RRHH y planilla | Quién hace hoy RRHH; con qué se calcula la planilla; quién tarea en campo; cómo se contrata al eventual; si la diferencia real contra estándar se reparte a partidas | Gerencia, RRHH, contador |
| Aprobaciones | Valores iniciales de U1, U2, U3; quién autoriza excesos; desde qué monto el cuadro comparativo es obligatorio | Gerencia |
| Tesorería | Dónde anota hoy pagos y cobros; cómo decide qué pagar; ver una hoja real | Lisset |
| Interfaz | Mosaicos o barra lateral como inicio, elegido con una tarea real | Lisset, Robert |
| Penalidades | Fórmula y factores según el reglamento vigente y las bases | Administración de Contratos |
| Facturador actual | Qué programa es y si entrega el XML | Contabilidad |
| Columnas del Excel contable | Las que acepta el programa del contador | Contador |
| D6 · Desarrollo | Confirmar que solo las horas viven en el ERP | Jefatura de Desarrollo |
| Prioridad de importaciones | Si va con la entrega 1 o después | Gerencia |
| MS Project | Qué se espera derivar del cronograma; ver archivos reales | Ingeniero de proyectos |
| IA y datos | Qué información puede enviarse a un servicio externo y qué nunca | Gerencia |
| Módulos especulativos | Si suscripciones y servicios gestionados tienen contratos reales | Gerencia |
| **Guías de remisión** | Si se emiten desde el ERP o desde el facturador (la ficha de despacho dice que se generan; la decisión dice que se emite fuera) | Contabilidad |

La v4 aclara que **todas las decisiones registradas deben ratificarse con Gerencia en la primera reunión**.

---

## 6. Estado de cada documento

| Documento | Estado | Qué hacer con él |
|---|---|---|
| `techquk_modelo.dbml` | **v2 — reescrito** | Fuente del esquema. 210 tablas activas, PostgreSQL, 5 obsoletas marcadas |
| `Techquk-Flujo-Central.md` | **v2 — reescrito** | Recorrido del proyecto con partidas e imputación |
| `09B-Procesos-Propuestos-Techquk.md` | **v3 — reescrito** | Ahora es el mapa de los diez flujos de la v4 |
| `01-Plan-ERP-CRM.md` | v3.0 — actualizado | §1.2, §6, §7, §8-bis, §10 y §12 reemplazados o matizados |
| `05-Plan-Construccion-Tecnica.md` | v3.0 — actualizado | Patrones ampliados; §5 y §6 reemplazados |
| `06-Arquitectura-Nucleo-Datos.md` | v3.0 — ampliado | Nueva §17: M11 a M13 y ampliaciones del núcleo |
| `08-Detalle-Columnas-y-Funcionamiento.md` | v3.0 — ampliado | Nuevo anexo con las tablas nuevas y los cambios a las existentes |
| `10-Sistema-Modulos-Procesos-Paneles.md` | v3.0 — matizado | Cuadro de estado por módulo al inicio |
| Resto del paquete de agosto (00, 02, 02A, 03, 04, 04A, 06A, 07, 09A, informe, presentación) | **No actualizado** | Contexto histórico únicamente |

---

## 7. Inconsistencias detectadas en las propias fuentes

| # | Qué | Dónde | Cómo se trata aquí |
|---|---|---|---|
| 1 | El texto dice «ocho tipos de compra» y la tabla lista **doce** | v4 flujo 2 | Se toman **doce**, configurables |
| 2 | La ficha de despacho «genera la guía de remisión» y la muestra aceptada por SUNAT, pero la decisión dice que se emite en el facturador | v4 flujos 2 y 4, parte 7 | Marcada POR VALIDAR. `guias_remision` se conserva |
| 3 | «Los presupuestos de obra, APU y metrados no aplican» | doc 05 §10 | Contradicho por la v4. Reemplazado |
| 4 | «Escenario 1 recomendado» | doc 01 §8-bis | Reemplazado: la planilla va dentro |
| 5 | «149 tablas» | docs 01, 05, 06, Flujo Central | Ahora **210 activas** |
| 6 | La numeración de D10 cambia de significado | docs 01 y v4 | Ver sección 3 |

---

## 8. Lo que esta actualización NO cubre

Conviene saberlo para no asumir que todo está cotejado:

- **CRM (v4 parte 2):** las tablas CRM-01 a CRM-07 no se modificaron. Falta cotejar columna por columna (catálogo, precio de oferta sugerido desde el histórico de adjudicaciones, lectura de bases de licitación, conversión de leads).
- **Flujo 8 (postventa), suscripciones y arrendamiento de equipos:** no se revisaron en detalle.
- **Parte 5 de la v4 (interfaz y roles):** se tomó de ella solo lo que confirma el prototipo (roles y navegación).
- **Parte 8 (criterios de aceptación):** son la base de las pruebas. Se recomienda convertirlos a pruebas Pest cuando se construya cada flujo.
- **Los diseños de tabla nuevos son propuesta** (marcados `[S]`): la v4 define documentos, estados y efectos, no tablas. Conviene revisarlos con el primer módulo que se construya.
- **Horizonte y plazos:** ningún documento de agosto refleja el equipo actual (una persona) ni el alcance de la v4 (planilla, obra pública). Hay que recalcularlos.
