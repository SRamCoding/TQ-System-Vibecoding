# Ficha · El ciclo del dinero: facturación, pagos, caja, rendiciones y resultado real (flujos 4 a 7)

- **Estado:** en curso (v4, ronda de cambios; la v3 quedó aprobada)
- **Versión:** v4
- **Tramo de la ruta:** T5
- **Aprobada (v3):** 2026-11-20 · etiqueta `aprobado/t5-finanzas-v3`
- **Aprobada (v2):** 2026-11-20 · etiqueta `aprobado/t5-finanzas-v2`
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t5-finanzas-v1`
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

- **MV-021** (cruza módulos) · Comprobantes se parte: Facturas emitidas y Cobranzas (Facturación y cobranza) y
  Facturas de proveedor (Finanzas).
- **MV-016** (cruza módulos) · Sus pantallas se reparten entre Techquk, Facturación y cobranza y Finanzas.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio (MV-016, MV-021):** «¿Cuánto se ganó?» en Techquk; «Facturas emitidas» y «Cobranzas» en Facturación y
  cobranza; «Facturas de proveedor», «Tablero de pagos», «Flujo de caja y conciliación», «Rendiciones», «Servicios con
  vigencia» y «Para el contador» en Finanzas.
- **¿Cuánto se ganó?** `/resultados`: por proyecto, vendido, facturado, por cobrar, costo real, por pagar, **margen
  real** (con su %; rojo si pierde) y caja neta. Cada fila abre el proyecto.
- **Proyecto → Resumen:** tarjeta **«Resultado real»** (facturado, por cobrar, costo real, por pagar a proveedores,
  margen real con su % y caja neta).
- **Facturas emitidas** `/facturas-emitidas` (MV-021): las ventas. Comprobante (XML, a mano o emitido), cliente y RUC,
  proyecto, emisión y vencimiento, total (detracción o tipo de cambio debajo), estado (por cobrar, cobrado, anulado).
  Subtítulo: cuántas valorizaciones aprobadas faltan facturar. Botón **Facturar valorización** (las aprobadas); en cada
  venta por cobrar, **Cobrar** (fecha y cuenta).
- **Cobranzas** `/cobranzas` (MV-021): las ventas por cobrar, lo vencido primero, y las cobradas en los últimos 60
  días. Columna **Plazo**: «Vencida hace N d» (rojo), «Vence hoy» o «Vence en N d» (ámbar si faltan 7 días o menos),
  «Cobrada» (verde). Subtítulo: «Por cobrar S/ … · vencido S/ … (N facturas)». En cada una, **Cobrar**.
- **Facturas de proveedor** `/facturas-proveedor` (MV-021): las compras con su columna de cruce y SUNAT («Orden ✓
  recepción ✓ factura ✓» o «Diferencia con la recepción») y su estado (por programar, programado, pagado, anulado).
  Botones **Subir XML de factura** y **Registrar factura de proveedor** (orden recibida, serie y número, RUC, emisión,
  días de crédito, subtotal si difiere). `/comprobantes` lleva a Facturas emitidas.
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

- La validación con SUNAT es real pero se activa con las credenciales de la empresa (`SUNAT_CLIENT_ID`,
  `SUNAT_CLIENT_SECRET`, `SUNAT_RUC`); sin ellas queda «Simulado». La lectura de notas por foto llama a la IA solo con
  `IA_CLAVE_ANTHROPIC`; sin ella, el gasto se escribe a mano.
- Detracción fija del 4 % para contratos públicos; sin retenciones del IGV ni percepciones.
- El tablero de pagos mueve tarjetas con botones (el v4 pide arrastrarlas).
- No hay notas de crédito ni conciliación contra un extracto importado. La liquidación compara monto final y facturado
  (sin recálculo de mayores gastos generales ni intereses). En consorcio «todo el contrato», no se separan los costos de
  los socios.
- La comisión de la carta fianza (OS) se paga sin factura ni movimiento de banco.

## Atajos tomados

- Estados como texto, sin acciones registradas; consultas directas en las pantallas.
- El flujo de caja y los resultados se calculan al abrir la página.
- «Programar» siempre al viernes siguiente.

## Dónde está en el código

`app/Modules/Erp/Finanzas/` (provider, migración, `Services/FinanzasService.php`, `Filament/Pages/` con Comprobantes (la base común) y sus tres pantallas FacturasEmitidas, Cobranzas y
FacturasProveedor,
TableroPagos, FlujoCaja, Rendiciones y Resultados, `Resumen/TarjetaResultado.php`, vistas `tablero-pagos` y `flujo`);
datos en `DemoSeeder::compraDemo()` (facturas y pagos) y `tramo5()`.

## Cambios desde la versión anterior

- **v4** (ronda 1, MV-021): Comprobantes se parte en tres pantallas: Facturas emitidas y Cobranzas (nueva: lo que
  deben los clientes con su plazo) en Facturación y cobranza; Facturas de proveedor en Finanzas. El tablero de
  Tesorería lleva «Qué cobro» a Cobranzas.

v2 (2026-11-20): **Exportar para el contador** (`/exportar-contador`, caja «Para el contador»): por mes, en CSV para
Excel (punto y coma, UTF-8 con BOM), el registro de ventas, el de compras y caja y bancos, cada fila con las cuentas
de su plantilla de asiento (ventas 12121 / 40111 / 70321; compras 60xx-63xx / 40111 / 42121; caja 10411 / 1013). La
plantilla es fija en el código (atajo); el v4 la quiere configurable.

v3 (2026-11-20, MV-014):
- **Subir XML de factura** (`/comprobantes`): se lee número, emisión, RUC, razón social, moneda, base, IGV y total del
  XML UBL 2.1 de SUNAT y se registra contra la orden (cruce de tres vías). La **validación con SUNAT** usa la API de
  consulta integrada (token y `validarcomprobante`) con las credenciales del `.env`; sin ellas, «Simulado». La columna
  Cruce / SUNAT muestra el detalle.
- **Leer nota por foto** (`/rendiciones`): la IA lee fecha, comprobante, descripción y monto, y deja el gasto por
  aprobar marcado para revisar. Atajo: llama al cliente del modelo directo, sin el proceso registrado ni la cola.
- **Moneda extranjera:** el comprobante guarda moneda y tipo de cambio; una factura del extranjero no lleva IGV; el
  cruce compara su equivalente en soles; el pago sale al tipo de cambio del día del pago y guarda la **diferencia de
  cambio**. Demo (v4 F10): USD 6,000.00 a 3.740 = 22,440.00, pagada a 3.752 = 22,512.00 → diferencia **72.00**.
- **Servicios con vigencia** (`/servicios-vigencia`, caja propia): tipo (SCTR, póliza, alquiler, licencia,
  suscripción), proveedor, proyecto o empresa, monto por periodo, vigencia (rojo a 15 días, ámbar a 30, azul a 60) y
  «Orden del periodo» (OS aprobada y conforme, en los gastos generales del proyecto o en un centro de costo). Demo: 5
  servicios; el SCTR de P8 vence el 05/12/2026.
- **Liquidación del contrato** (botón «Liquidar»): monto final (vendido neto + reajustes) − facturado = saldo a favor
  de Techquk o de la entidad; no se liquida con una valorización en borrador; el contrato pasa a «Liquidado». Demo:
  CT-2024-0002 y CT-2025-0004 liquidados con saldo 0.00.
- **Consorcios** (botón «Consorcio»): nombre, participación de Techquk y modo (solo su participación, o todo el
  contrato). En «participación», lo vendido es su porcentaje. Demo: P9 en el Consorcio Salud Digital Norte al 40 %.
