# Documento 09-B — Los diez flujos del ERP

**Programa:** Ordenamiento Organizacional y Sistemas de Gestión — Techquk S.A.C.
**Versión:** 3.0 · Octubre 2026 · **Reemplaza a** la v2.1 de agosto
**Fuente:** Especificación v4 (21 set 2026), parte 4 y parte 6. El detalle de cada pantalla está en el PDF y en el prototipo.

---

## Registro de cambios

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 a 2.1 | Ago 2026 | Doce procesos **propuestos** (comercial privado, contratación pública, proyecto, abastecimiento, importación, almacén, servicios, contratos, suscripciones, garantías, facturación, aprobación) |
| **3.0** | **Oct 2026** | **Se reemplaza por los diez flujos de la v4.** Dejan de ser propuesta: la v4 los detalla con pantallas, efectos y criterios de aceptación |

### Qué desapareció y qué se movió

| Proceso de agosto | Dónde está ahora |
|---|---|
| Comercial privado y contratación pública | CRM, **v4 parte 2** (no se cotejó en esta actualización) |
| Proyecto | Flujos 1 y 6 |
| Abastecimiento | Flujo 2 |
| Importación | Flujo 2, variante «Importación» (Entrega 4) |
| Almacén | Flujo 2, pasos 5 y 6 |
| Servicios, suscripciones, reclamo de garantía al fabricante | Flujo 8 |
| Administración de contratos | Flujos 1, 4 y 10 |
| Facturación y cobranza | Flujo 4 |
| Aprobación | Transversal: doc 06 §17 y v4 parte 7 |
| Cómo converge el costo | `Techquk-Flujo-Central.md` |
| **Nuevos** | Flujos 3, 5, 7, 9 y 10 |

> **Sobre el estatus.** En agosto este documento decía «propuesta, no levantamiento». La v4 ya incorpora las sesiones de análisis de setiembre, pero **sigue marcando como POR VALIDAR** lo que depende del personal. Esos puntos están en la sección 3.

---

## 1. Mapa de los diez flujos

| # | De dónde a dónde | Módulos que atraviesa | Entrega |
|---|---|---|---|
| 1 | Del contrato al proyecto listo para ejecutar | Contratos · Proyectos | 1 |
| 2 | De la necesidad al costo | Compras · Importaciones · Almacén · Cuentas por pagar | 1 (importaciones: 4) |
| 3 | Las horas de la gente | Personal · Tareo · Horas de desarrollo | 1 (horas de desarrollo: 5) |
| 4 | De la conformidad al cobro | Contratos · Facturación y cobranzas | 1 y 2 |
| 5 | El dinero que sale y la contabilidad | Cuentas por pagar · Tesorería · Asientos | 2 |
| 6 | El cierre del proyecto | Proyectos · Contratos | 1 y 2 |
| 7 | Gastos generales, no presupuestados y servicios con vigencia | Compras · Proyectos · Cuentas por pagar | 1 y 2 |
| 8 | La postventa | Servicios · Almacén · Suscripciones | 5 |
| 9 | Las personas: de la necesidad a la salida | RRHH · Planilla · Tareo | 1 y 3 |
| 10 | Contratos de obra pública: adelantos, reajustes, valorizaciones, liquidación, consorcios, subcontratos y moneda | Contratos · Proyectos · Compras · Facturación · Tesorería | 1 y 2 |

---

## 2. Los flujos, paso a paso

### Flujo 1 · Del contrato al proyecto listo para ejecutar
*Empieza cuando el CRM marca una oportunidad como ganada y termina con presupuesto aprobado, árbol de partidas aprobado y garantía emitida. Es el flujo más corto y el más importante: si el presupuesto y el árbol quedan mal, todo se mide contra una referencia equivocada.*

| Paso | Quién | Qué produce |
|---|---|---|
| 1 · Se registra el contrato | Administración de Contratos | Contrato vigente con hitos y **penalidad calculada**; alertas por hito; proyecto «en preparación» |
| 2 · Se gestiona la carta fianza | Tesorería | Garantía con sus dos caras (monto garantizado y **costo**); alertas a 60, 30 y 15 días; orden de servicio de la comisión imputada a PC-04 |
| 3 · Se trae el presupuesto | Preventa y Costeo | El mismo presupuesto pasa al proyecto, congelado como versión aprobada |
| 4 · Se arma y aprueba el árbol de partidas | Robert con especialistas; aprueba la Jefatura de Proyectos | Árbol cuya suma iguala el costo presupuestado; **PC-99** creada; proyecto «en ejecución»; selector de imputación habilitado |

### Flujo 2 · De la necesidad al costo
*El más usado. Tronco común con variantes según el tipo de compra.*

| Paso | Quién | Efecto sobre los números |
|---|---|---|
| 1 · Se pide | Especialista del proyecto | Cada línea declara tipo de compra e imputación; el sistema revisa stock y muestra cómo queda la partida |
| 2 · Se aprueba | Jefatura de Proyectos; Gerencia según monto | Líneas con stock quedan **reservadas (comprometido)**; las demás pasan a Compras. Un exceso exige motivo |
| 3 · Se cotiza y se elige proveedor | Compras | Cuadro comparativo, obligatorio sobre un umbral por definir |
| 4 · Se emite la orden (OC u OS) | Compras | Al aprobarse queda **comprometido** en la partida |
| 5 · Se recibe | Almacén o especialista | Almacén central: sube stock. Almacén de obra: **ejecutado** en la partida. Habilita el cruce con la factura |
| 6 · Se despacha a obra | Almacén | Baja stock; **ejecutado** al costo del almacén; guía de remisión |
| 7 · Llega la factura del proveedor | Contabilidad | XML validado ante SUNAT, cruce de tres vías, asiento por plantilla; si coincide, tarjeta en el tablero de pagos |

*Variante Importación:* añade embarque, gastos y prorrateo (ejemplo de aceptación: factor 15.15 % sobre FOB).

### Flujo 3 · Las horas de la gente

| Paso | Qué ocurre |
|---|---|
| 1 · Se asigna a las personas | Tablero de asignación; alerta de sobreasignación sobre 100 % en una semana |
| 2 · Se registran las horas de obra | Por día y por partida; no se tarea sin SCTR vigente para el proyecto ni sin habilitación |
| 3 · Se cierra la semana | **Comprometido y ejecutado** en cada partida; la semana ya no se edita |
| 4 · Las horas de desarrollo | Mismo parte, imputadas a proyectos de software |

### Flujo 4 · De la conformidad al cobro

| Paso | Qué ocurre |
|---|---|
| 1 · Se vigilan los hitos y el plazo | Alertas 7 y 2 días antes, el mismo día y vencido; simulador de penalidad |
| 2 · Se registra la conformidad | **Habilita facturar** |
| 3 · Se factura en el facturador de siempre y se sube | XML validado ante SUNAT; hito **sugerido** por el sistema y confirmado por la persona; cuenta por cobrar y asiento de venta |
| 4 · Se cobra | Cobranza aplicada; retención y detracción registradas aparte |

### Flujo 5 · El dinero que sale y la contabilidad

| Paso | Qué ocurre |
|---|---|
| 1 · Se ordenan los pagos en el tablero | Tarjetas por vencimiento que Tesorería arrastra; suma y caja restante |
| 2 · Se reciben las alertas propias del rol | Configurables por persona |
| 3 · Se capturan las notas en papel | Foto de la hoja; la IA sugiere, Tesorería confirma |
| 4 · Se paga y se proyecta la caja | Pago aplicado (**pagado**); flujo de caja y escenarios |
| 5 · Los asientos se generan solos | Por plantilla; si falta plantilla, el asiento queda «fallido» |
| 6 · Se exportan al programa del contador | Excel de formato fijo; el lote fallido no frena los demás |

### Flujo 6 · El cierre del proyecto
1 · Se revisa presupuesto contra real · 2 · Se verifica que no quede nada abierto (órdenes, tareo, valorizaciones) · 3 · Se calcula el margen real y se aprende · 4 · Se devuelve la garantía.

### Flujo 7 · Gastos generales, no presupuestados y servicios con vigencia

| Paso | Qué ocurre |
|---|---|
| 1 · Gasto no presupuestado | Va a **PC-99**; motivo (lista cerrada) y comentario obligatorios; **siempre** requiere aprobación; muestra el nuevo margen. Si el motivo es «cambio pedido por el cliente» → *posible adicional* y alerta a Administración de Contratos |
| 2 · Servicios con vigencia | SCTR, pólizas, alquileres: alerta 30 días antes |
| 3 · Viáticos de campo | Entrega de fondos y rendición con foto de comprobantes; ejemplo: rendición de 27.00 sobre 50.00 → saldo a devolver 23.00 |

### Flujo 8 · La postventa
Tickets con SLA, rastreo por número de serie, garantías de equipos, suscripciones con margen por periodo. Un ticket fuera de garantía vuelve al CRM como oportunidad. **No cotejado en detalle en esta actualización**; ver v4 flujo 8 y `09B v2.1` (archivado) para el reclamo de garantía al fabricante.

### Flujo 9 · Las personas: de la necesidad a la salida

| Paso | Qué ocurre |
|---|---|
| 1 · Se prevé la demanda con 90 días | Brecha entre proyectos en cartera y personal disponible |
| 2 · Se selecciona y se contrata | Selección de candidatos; contrato laboral con modalidad, vigencia, remuneración y régimen de pensiones |
| 3 · Se habilita para campo | Examen ocupacional (solo el resultado sale de RRHH), SCTR por proyecto, inducción, equipo de protección |
| 4 · Se asigna y se tarea | Ver flujo 3 |
| 5 · Se calcula la planilla del mes | Boletas, archivo PLAME y asientos; **diferencia real contra estándar** por proyecto |
| 6 · Se actualizan competencias y certificaciones | Alerta 90 días antes de vencer |
| 7 · Se gestiona la salida | Lista de traspasos; se revocan accesos; liquidación en planilla |

### Flujo 10 · Contratos de obra pública
Un mismo mecanismo en dos direcciones: sirve con el cliente (se cobra) y con el subcontratista (se paga).

| Paso | Qué ocurre |
|---|---|
| 1 · Condiciones del contrato de obra | Adelantos, retención en lugar de fianza, fórmula polinómica |
| 2 · Se valoriza el avance de cada periodo | `avance (metrado × precio) + reajuste − amortizaciones − retención − penalidades = neto`; no se valoriza un periodo con la anterior en borrador ni sin índices |
| 3 · Se ejecuta con subcontratistas | Alcance asignado a partidas; valorización del subcontratista = ejecutado + cuenta por pagar por el neto |
| 4 · Consorcio, retención y moneda | Porcentajes que suman 100; reparto según el modo |
| 5 · Se liquida el contrato | Conceptos: contratado, adicionales, reducciones, reajustes, penalidades, adelantos, retenciones, cobrado |
| 6 · Arrendamiento de equipos | **FASE POSTERIOR** |

---

## 3. Lo que sigue POR VALIDAR en los flujos

| Flujo | Qué falta confirmar |
|---|---|
| 1 | Cuándo se usa APU completo y cuándo precio por ítem; fórmula y factores de la penalidad según la Ley 32069 |
| 2 | Umbral del cuadro comparativo obligatorio; cuáles de los doce tipos de compra ocurren; cuánto material va directo a obra |
| 3 | Si la diferencia real contra estándar se reparte a las partidas o se deja como gasto |
| 4 | Qué programa es el facturador actual y si entrega el XML; qué operaciones llevan retención o detracción |
| 5 | Una hoja real de Tesorería; columnas del Excel que acepta el contador |
| 7 | Qué motivos de gasto no presupuestado existen realmente |
| 9 | Quién hace hoy RRHH y con qué se calcula la planilla; cómo se contrata al eventual |
| 10 | Qué contratos fueron obras, con adelanto, reajuste, consorcio o retención; plazos de liquidación |
| 2 y 4 | **Guías de remisión:** si las emite el ERP o el facturador |

---

## 4. Cómo usar este documento

`[S]` Cada flujo tiene su ficha de documentos (estados, transiciones, efectos y validaciones) en la v4 parte 6, y sus escenarios de prueba con números exactos en la parte 8. **Un flujo se considera terminado cuando sus escenarios de aceptación dan exactamente el resultado indicado.**

*Fin del documento 09-B v3.0.*
