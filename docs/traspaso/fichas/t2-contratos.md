# Ficha · Contratos, garantías, penalidad y adendas (flujo 1)

- **Estado:** aprobada (MV-009)
- **Versión:** v2
- **Tramo de la ruta:** T2
- **Aprobada (v2):** 2026-11-20 · etiqueta `aprobado/t2-contratos-v2`
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t2-contratos-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-01.md` (pasos 1 y 2: contrato y carta fianza);
  `parte-8-criterios-aceptacion.md` (F1: penalidad 205.96, tope 2,471.51; carta fianza con comisión);
  `docs/planes/activos/2026-11-16-datos-desarrollo-cartera.md` (P1 lleva la penalidad de S/ 250,498.54).

## Qué hace

Al ganar una oportunidad nace, junto con el proyecto, su contrato en borrador con el cliente, el monto y las
condiciones de la cotización aceptada. Administración de Contratos completa lo que la cotización no trae (número de
contrato de la entidad, firma, forma de pago, factor y tope de penalidad) y lo activa, y el sistema programa los
hitos. La ficha muestra los tres números que alguien mira todos los días: días restantes, penalidad diaria (calculada,
no escrita) y garantía. Las cartas fianza se registran con sus dos caras: el monto garantizado y su vencimiento, y la
comisión del banco, que es costo del proyecto y cae en sus gastos generales. Las adendas cambian monto y plazo; las
penalidades aplicadas bajan lo vendido. Desde aquí, **lo vendido del proyecto sale del contrato**.

## Decisiones de Sebastián

- **MV-006** (cruza módulos) · Al ganar nace todo en un paso; el contrato se suma a ese paso.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Contratos» en el área ERP.
- **Contratos** `/contratos`: contrato (número de la entidad debajo), objeto (cliente debajo), monto con IGV y
  adendas, plazo («13 días restantes de 30», en rojo «vencido hace 5 días»), penalidad diaria y estado. No hay «nuevo»:
  nacen al ganar.
- **Ficha** `/contratos/{id}`: pestañas Resumen, Hitos, Garantías, Adendas y Penalidades.
  - Resumen: sección «Lo que hay que mirar todos los días» (plazo, penalidad diaria con su fórmula y tope debajo,
    penalidad aplicada en rojo, garantía vigente) y «El contrato» (cliente, número de la entidad, origen, objeto, monto
    contractual, con adendas, vendido neto, firma, inicio, fin con adendas, forma de pago, garantía técnica y enlace al
    proyecto).
  - Garantías: tipo y número, banco, monto, vence (con «en N días» a partir de 60 y en rojo a 30), comisión con el
    gasto que generó («GD-2026-0123 › gastos generales») y estado. Botones «Registrar garantía» y «Cambiar estado».
  - Adendas: número, fecha, motivo, monto adicional y días. Botón «Registrar adenda».
- **Botones de la ficha:** Completar y activar (borrador), Aplicar penalidad, Culminar, Abrir proyecto.
- **Proyecto → Resumen:** tarjeta «Contrato CT-…» en la sección de plazo, con plazo, penalidad diaria (tope debajo),
  penalidad aplicada y garantía; avisos «está en borrador» y «la carta fianza vence el …: renovarla» (a 30 días).
- **Proyecto → Resumen → «Costo del proyecto»:** el margen previsto o proyectado negativo sale en rojo.
- **Oportunidades → faja → ganadas:** aparece la columna «Contrato» con su código.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| (al ganar) Crear contrato | — | — | contrato `CT-AAAA-NNNN` en borrador: cliente, objeto (nombre de la oportunidad), monto con IGV de la cotización aceptada, inicio y plazo (días entre inicio y fin del proyecto), forma de pago y garantía de la cotización, factor F según plazo; enlazado al proyecto |
| Completar y activar | n.° de contrato de la entidad (obligatorio), firma, forma de pago, garantía técnica (meses), factor F (0.40 o 0.25), tope (%) | contrato en borrador | vigente; hitos: firma (cumplida), entrega de equipos (45 % del plazo), instalación (85 %) y conformidad (fin) |
| Registrar garantía | tipo (fiel cumplimiento, adelanto, seriedad, monto diferencial), banco, n.° de carta fianza, monto (propone el tope), emisión, vencimiento (propone fin + 30 días), comisión, estado | vencimiento ≥ emisión | garantía; la comisión entra como costo (comprometido, ejecutado y pagado) en la partida de gastos generales del proyecto, si su árbol está aprobado |
| Cambiar estado de garantía | estado (solicitada, vigente, renovada, liberada, devuelta), nuevo vencimiento | — | — |
| Registrar adenda | número, fecha, motivo, monto adicional con IGV (negativo si deductivo), días adicionales | contrato vigente | cambian el monto vigente, el plazo vigente, la penalidad diaria, el tope y lo vendido |
| Aplicar penalidad | días de atraso (≥ 1), fecha, motivo | queda tope disponible | monto = días × penalidad diaria, recortado a lo que falta del tope; baja lo vendido |
| Culminar | — | vigente | culminado (también al cerrar el proyecto) |
| Anular ganada (cambia) | — | el contrato sigue en borrador | contrato anulado; si está vigente, no deja anular la ganada |

## Estados

Contrato: borrador → vigente → culminado; borrador → anulado (al anular la ganada). «Resuelto» existe en la lista pero
no tiene botón. Garantía: solicitada, vigente, renovada, liberada, devuelta (se cambia libremente).

## Datos

Tablas nuevas `contratos` (código, n.° de la entidad, proyecto único, oportunidad única, cliente, objeto, es público,
monto con IGV, IGV %, firma, inicio, plazo en días, forma de pago, garantía técnica en meses, factor de penalidad, tope
%, estado, versión, auditoría), `contrato_hitos`, `contrato_garantias` (con el gasto de su comisión),
`contrato_adendas` y `contrato_penalidades`. `proyectos.contrato_id` se llena al ganar. Permisos `erp.contratos.ver` y
`erp.contratos.gestionar`.

## Reglas y números

- **Penalidad diaria** = 0.10 × monto vigente ÷ (F × plazo vigente), a dos decimales; F = 0.40 hasta 60 días, 0.25 si
  es mayor. **Tope** = tope % × monto vigente. Ejemplo del v4: 0.10 × 24,715.10 ÷ (0.40 × 30) = **205.96**; tope 10 % =
  **2,471.51**.
- **Penalidad aplicada** = días × diaria, sin pasar el tope (sumando lo ya aplicado).
- **Lo vendido** = (monto + adendas) ÷ (1 + IGV) − penalidades aplicadas. Sin contrato, la cotización aceptada (ficha
  `t1-puente-erp`).
- Demo P1 (CT-2024-0001, 241 días, F 0.25): diaria 4,157.65, tope 250,498.54; 70 días de atraso darían 291,035.50 →
  se aplica el tope, **250,498.54**. Vendido 2,122,868.98 − 250,498.54 = **1,872,370.44**; margen previsto
  **−119,993.56** (pierde dinero).
- Demo P6: adenda N.° 1 por 49,625.14 con IGV y 30 días (12 computadoras para emergencia) → monto 3,231,218.88, plazo
  486 días, vendido 2,738,321.08, margen previsto 165,617.89.
- Comisión de la carta fianza en la demo: 2.5 % del monto garantizado (5 cartas fianza con comisión imputada).
- P8: su carta fianza vence el 12/12/2026 (aviso a 30 días en el tablero).

## Supuestos sin confirmar

- La penalidad se descuenta de lo vendido tal cual (se calcula sobre el monto con IGV y se resta del vendido sin IGV).
- Fórmula, factores y tope: los del v4, «por validar» contra el reglamento de la Ley 32069; se pueden ajustar por
  contrato.
- Los hitos por omisión (45 % y 85 % del plazo) son inventados.
- Solo las entidades públicas piden carta fianza en la demo.
- La comisión entra entera como comprometida, ejecutada y pagada el día de la emisión.
- Las alertas a 60, 30 y 15 días se ven en pantalla; no se envían avisos.
- Quedan fuera: conformidades como documento, resolución del contrato, consorcios y la pestaña «Documentos».

## Atajos tomados

- Estados de contrato y garantía como texto, sin el motor de estados; sin acciones registradas.
- La comisión entra por un gasto de demostración (MV-003), no por una orden de servicio OS-… (llegan en el tramo 3).
- El número de contrato de la entidad es texto libre.
- Monto vigente, plazo y penalidad se recalculan en cada pantalla (no se guardan).

## Dónde está en el código

`app/Modules/Erp/Contratos/` (provider, `Services/ContratosService.php`, `VentaDesdeElContrato.php`,
`ContratosDesdeElErp.php`, `Resumen/TarjetaContrato.php`, `Filament/Resources/Contratos/`); al ganar,
`Erp/Presupuestos/Services/ProyectoDesdeLaGanada.php`; la comisión, `GastosDemoService::enGastosGenerales()`; datos en
`DemoSeeder::contratoDe()`.

## Cambios desde la versión anterior

v2 (2026-11-20, por `t3-compras`): la comisión de la carta fianza ya no entra por un gasto de demostración: es una
orden de servicio de tipo «Garantía financiera», aprobada, conforme y pagada, imputada a la partida de gastos generales
(OS por 180.00 a PC-04 en el escenario del v4).
