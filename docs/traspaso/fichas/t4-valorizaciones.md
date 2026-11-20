# Ficha · Valorizaciones y subcontratos (flujo 10)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T4
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t4-valorizaciones-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-10.md` (pasos 1 a 3); `parte-8-criterios-aceptacion.md` (F10).

## Qué hace

Cada periodo, la valorización del contrato se arma desde el avance por partida: porcentaje del periodo sobre su precio
contractual, más el reajuste por la fórmula polinómica con los índices del mes, menos la amortización del adelanto
directo (en la proporción del avance) y la del de materiales (según consumo), menos la retención si la garantía es por
retención, menos las penalidades del periodo. Al aprobarse queda lista para su comprobante por el neto (tramo 5). El
subcontrato es lo mismo en la dirección contraria: su alcance se asigna a partidas (queda comprometido), el
subcontratista valoriza su avance, se le amortiza el adelanto y se le retiene la garantía; cada valorización aprobada
pasa a ejecutado en las partidas del alcance.

## Decisiones de Sebastián

- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Valorizaciones» y «Subcontratos» (ERP).
- **Valorizaciones** `/valorizaciones?contrato=…`: subtítulo con el contrato, el adelanto directo y lo que falta
  amortizar. Tabla: val. y periodo, avance, reajuste (K debajo), amortización directa, de materiales, retención,
  penalidad, **neto a facturar** y estado («Aprobada · por facturar»). Botones: Contrato, Registrar adelanto, Nueva
  valorización (panel con el porcentaje del periodo de cada partida, su precio contractual y lo ya valorizado); en cada
  borrador, «Aprobar y habilitar factura».
- **Subcontratos** `/subcontratos`: subtítulo con cada subcontrato, su monto y su retención acumulada. Tabla de sus
  valorizaciones: subcontrato, val. y periodo, avance, monto, amortización, retención, **neto a pagar**, estado; en las
  por aprobar, «Aprobar». Botones: Nuevo subcontrato (proyecto, subcontratista, objeto, adelanto %, retención %,
  alcance por partida), Valorización del subcontratista.

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar adelanto | tipo (directo o materiales), monto sin IGV, % de amortización, fecha | — | adelanto del contrato |
| Nueva valorización | periodo AAAA-MM, % del periodo por partida, amortización de materiales, retención % | no hay otra en borrador; ninguna partida pasa del 100 % acumulado | borrador con la cuenta completa; base de la fórmula = el mes anterior a la firma |
| Aprobar valorización | — | borrador | aprobada, por facturar |
| Nuevo subcontrato | proyecto, subcontratista, objeto, adelanto %, retención %, alcance (partida y monto) | — | `SC-AAAA-NNNN`; cada partida del alcance queda comprometida por su monto |
| Valorización del subcontratista | subcontrato, periodo, avance % | el acumulado no pasa de 100 % | por aprobar: monto = total × avance; amortización = monto × adelanto %; retención = monto × retención %; neto = monto − ambas |
| Aprobar (subcontrato) | — | por aprobar | aprobada; cada partida del alcance, ejecutado += su monto × avance |

## Datos

Tablas nuevas `contrato_adelantos`, `valorizaciones` (contrato, número, periodo, periodo base, avance, factor K,
reajuste, amortizaciones, retención, penalidad, neto, estado), `valorizacion_lineas` (código y descripción de la
partida del presupuesto, total contractual, % y monto), `subcontratos`, `subcontrato_alcance` (origen de su
imputación) y `subcontrato_valorizaciones`. Permisos `erp.valorizaciones.ver` y `.gestionar`.

## Reglas y números

- Precio contractual de una partida = su parcial del presupuesto vigente × (monto vigente del contrato ÷ 1.18 ÷ costo
  directo). Reajuste = avance × (K − 1) (ficha `t2-formula-polinomica`).
- P8 (CT-2026-0001), adelanto directo 100,064.00 al 10 %: val. 1 (jul) avance 94,206.86, K 1.0092, reajuste 866.70,
  amortización 9,420.69, neto 85,652.87; val. 2 (ago) neto 182,095.27; val. 3 (set) neto 191,491.49; val. 4 (oct) en
  borrador, neto 178,095.76.
- Subcontrato SC-2026-0001 (Obras Civiles Norte E.I.R.L., 70,000.00, adelanto 20 %, retención 5 %): val. 1 (ago, 35 %)
  24,500.00 − 4,900.00 − 1,225.00 = **18,375.00**, pagada; val. 2 (set, 40 %) 28,000.00 − 5,600.00 − 1,400.00 =
  **21,000.00**, por aprobar; al aprobarla, **retención acumulada 2,625.00** y ejecutado +28,000.00 en el alcance.
- El ejemplo de la valorización 3 del v4 (CT-2026-030, neto 108,776.04) no se reproduce tal cual: su K de 1.0148 no
  sale de sus propios índices (ver `t2-formula-polinomica`).

## Supuestos sin confirmar

- El avance se ingresa como porcentaje por partida (no metrado), sobre el presupuesto vigente.
- La base de la fórmula es el mes anterior a la firma del contrato.
- La amortización del adelanto directo es el % del avance hasta agotarlo; la de materiales se escribe a mano.
- La retención de garantía se escribe por valorización; no hay devolución de la retención ni liquidación del contrato
  o del subcontrato todavía; tampoco consorcios.
- «Habilitar factura» solo marca la valorización (el comprobante llega en el tramo 5).

## Atajos tomados

- Estados como texto; sin acciones registradas; consultas directas en las pantallas.
- El subcontratista va por nombre (sin ficha de proveedor).
- «Pagada» del subcontrato se pone a mano en la demo (Tesorería, tramo 5).

## Dónde está en el código

`app/Modules/Erp/Valorizaciones/` (provider, migración, `Services/ValorizacionesService.php`,
`Filament/Pages/ValorizacionesDeContrato.php` y `Subcontratos.php`); datos en `DemoSeeder::tramo4()`.
