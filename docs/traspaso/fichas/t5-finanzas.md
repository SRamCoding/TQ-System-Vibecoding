# Ficha · El ciclo del dinero: facturación, pagos, caja, rendiciones y resultado real (flujos 4 a 7)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T5
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t5-finanzas-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-04.md` a `flujo-07.md`; `docs/vibe/ruta-mvp.md` (tramo 5).

## Qué hace

Cierra el camino del dinero y responde la pregunta del sistema: **¿cuánto se ganó realmente en cada proyecto?** La
valorización aprobada se factura por su neto (bandeja por facturar) y se cobra; la detracción del 4 % de las entidades
públicas entra a su cuenta del Banco de la Nación. La factura del proveedor se registra contra su orden y se cruza con
lo recibido (orden, recepción y factura); Tesorería la programa y la paga en su tablero, y el pago pasa a «pagado» en
las partidas. Los fondos por proyecto (rendiciones y caja chica) se rinden con sus comprobantes, que se aprueban como
costo; lo no gastado vuelve a caja. Todo movimiento queda en caja y bancos, conciliable, y el flujo de caja muestra lo
real y lo proyectado por mes. «¿Cuánto se ganó?» y la tarjeta «Resultado real» del proyecto juntan todo.

## Decisiones de Sebastián

- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «¿Cuánto se ganó?», «Comprobantes», «Tablero de pagos», «Flujo de caja» y «Rendiciones» (ERP).
- **¿Cuánto se ganó?** `/resultados`: por proyecto, vendido, facturado, por cobrar, costo real, por pagar, **margen
  real** (con su %; rojo si pierde) y caja neta. Cada fila abre el proyecto.
- **Proyecto → Resumen:** tarjeta **«Resultado real»** (facturado, por cobrar, costo real, por pagar a proveedores,
  margen real con su % y caja neta).
- **Comprobantes** `/comprobantes`: comprobante (venta o compra; XML, a mano o emitido), cliente o proveedor y RUC,
  proyecto, emisión y vencimiento, total (detracción debajo), cruce y SUNAT («Orden ✓ recepción ✓ factura ✓» o
  «Diferencia con la recepción»), estado. Botones: **Facturar valorización** (las aprobadas), **Registrar factura de
  proveedor** (orden recibida, serie y número, RUC, emisión, días de crédito, subtotal si difiere); en cada venta por
  cobrar, **Cobrar** (fecha y cuenta).
- **Tablero de pagos** `/tablero-pagos`: tres columnas (Por programar, Programado, Pagado de los últimos 15 días) con
  tarjetas: proveedor, total, comprobante, proyecto, vencimiento (rojo si venció) o fecha programada, aviso de
  diferencia en el cruce; botones Programar (al viernes siguiente) y Pagar.
- **Flujo de caja y bancos** `/flujo-caja`: por mes, ingresos, egresos, neto y acumulado (los meses desde el actual en
  cursiva, «proyectado»); debajo, los movimientos (fecha, cuenta, concepto, monto con signo, conciliado) con filtro por
  cuenta y conciliado, y «Conciliar».
- **Fondos y rendiciones** `/rendiciones`: subtítulo con cada fondo abierto (entregado, rendido, saldo); tabla de
  gastos (fondo y responsable, fecha, gasto con comprobante y «leído de la foto por IA, revisar», imputación, monto,
  estado) con Aprobar y Observar. Botones: Entregar fondo, Registrar gasto, Rendir fondo.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Facturar valorización | valorización aprobada, emisión | aprobada y sin facturar | factura F001-NNNNNNNN por el neto + IGV 18 %, vence a 30 días, detracción 4 % si el contrato es público; la valorización queda «facturada» |
| (demo/servicio) Facturar contra el contrato | contrato, subtotal, emisión | — | igual, sin valorización |
| Cobrar | fecha, cuenta | por cobrar | ingreso al banco por el total menos la detracción, y la detracción al Banco de la Nación; cobrado |
| Registrar factura de proveedor | orden recibida, serie y número, RUC, emisión, días de crédito, subtotal | hay algo recibido | comprobante de compra; cruce «ok» si el subtotal es lo recibido a precio de la orden (±0.10), si no «diferencia»; por programar |
| Programar | (fecha: el viernes siguiente) | por programar | programado |
| Pagar | fecha, cuenta | por pagar y cruce sin diferencia | egreso del banco por el total; cada línea de la orden, pagado += hasta su ejecutado; pagado |
| Entregar fondo | proyecto, tipo (rendición o caja chica), responsable, monto, fecha | — | `REN-` o `CCH-AAAA-NNNN`; egreso de caja |
| Registrar gasto | fondo, fecha, gasto, comprobante, monto, partida, leído por IA | fondo abierto | por aprobar |
| Aprobar gasto | — | por aprobar | comprometido, ejecutado y pagado en su partida |
| Rendir fondo | fondo | sin gastos por aprobar | rendido; lo no gastado vuelve a caja |
| Conciliar | — | — | conciliado o pendiente |

## Datos

Tablas nuevas `comprobantes` (clase venta o compra, tipo, serie y número, RUC, razón social, emisión, vencimiento,
subtotal, IGV, total, detracción, proyecto, contrato, valorización, orden, origen, SUNAT, cruce, estado, pago
programado, saldo; único por clase, RUC y número), `movimientos_caja` (fecha, ingreso o egreso, cuenta, monto,
concepto, comprobante, fondo, proyecto, conciliado), `fondos` y `fondo_gastos` (origen de su imputación). Permisos
`erp.finanzas.ver`, `.facturar`, `.pagar`.

## Reglas y números

- **Margen real** = facturado (sin IGV) − costo ejecutado (órdenes recibidas, horas cerradas, subcontratos y
  rendiciones aprobadas). **Caja neta** = ingresos − egresos del proyecto.
- Demo: P1 vendido = facturado 1,872,370.44 (con la penalidad), costo 1,978,015.94 → **margen real −105,645.50
  (−5.6 %)**; P4 +23,944.00 (26.2 %); P7 +41,034.80 (21.2 %); P6 facturado 1,916,824.75 de 2,738,321.08, margen
  94,040.33 (4.9 %), por cobrar 484,682.83; P8 factura sus 3 valorizaciones aprobadas y cobra 2.
- Plaza de Armas: la factura del cable F001-00004512 por 1,248.00 + IGV = 1,472.64, cruce ok, por programar; la
  rendición de Julio Chávez (800.00) con dos gastos aprobados y el almuerzo «leído por IA» por aprobar.
- 105 facturas de proveedor: 83 pagadas, 6 programadas para el 27/11/2026, el resto por programar. Movimientos hasta
  octubre de 2026 conciliados.

## Supuestos sin confirmar

- La lectura del XML, su validación con SUNAT y la lectura de notas por foto con IA son simuladas (se escriben los
  datos; la marca «leído por IA» queda para revisar).
- Detracción fija del 4 % para contratos públicos; sin retenciones del IGV ni percepciones.
- El tablero de pagos mueve tarjetas con botones (el v4 pide arrastrarlas).
- No hay moneda extranjera, plantillas de asientos ni exportación para el contador, servicios con vigencia, notas de
  crédito, liquidación formal del contrato, consorcios ni conciliación contra un extracto importado.
- La comisión de la carta fianza (OS) se paga sin factura ni movimiento de banco.

## Atajos tomados

- Estados como texto, sin acciones registradas; consultas directas en las pantallas.
- El flujo de caja y los resultados se calculan al abrir la página.
- «Programar» siempre al viernes siguiente.

## Dónde está en el código

`app/Modules/Erp/Finanzas/` (provider, migración, `Services/FinanzasService.php`, `Filament/Pages/` con Comprobantes,
TableroPagos, FlujoCaja, Rendiciones y Resultados, `Resumen/TarjetaResultado.php`, vistas `tablero-pagos` y `flujo`);
datos en `DemoSeeder::compraDemo()` (facturas y pagos) y `tramo5()`.
