# Ficha · Valorizaciones y subcontratos (flujo 10)

- **Estado:** en curso (v2, observaciones-6; la v1 quedó aprobada)
- **Versión:** v2
- **Tramo de la ruta:** T4
- **Aprobada (v1):** 2026-11-20 · etiqueta `aprobado/t4-valorizaciones-v1`
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

- **MV-072** (ficha; la alarma lee Valorizaciones y los avisos tocan Mien) · Subcontratos en tarjetas, con la alarma de pagar de más y la liberación de la retención.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Valorizaciones» y «Subcontratos» (ERP).
- **Valorizaciones** `/valorizaciones?contrato=…`: subtítulo con el contrato, el adelanto directo y lo que falta
  amortizar. Tabla: val. y periodo, avance, reajuste (K debajo), amortización directa, de materiales, retención,
  penalidad, **neto a facturar** y estado («Aprobada · por facturar»). Botones: Contrato, Registrar adelanto, Nueva
  valorización (panel con el porcentaje del periodo de cada partida, su precio contractual y lo ya valorizado); en cada
  borrador, «Aprobar y habilitar factura».
- **Subcontratos** `/subcontratos` (v2, MV-072). Botones: Nuevo subcontrato (proyecto, subcontratista, objeto,
  adelanto %, retención %, alcance por partida), Valorización del subcontratista.
  - **Cinco cifras:** subcontratos vigentes; comprometido; por aprobar (cantidad · monto); por pagar (aprobado sin
    pagar); retenciones por devolver.
  - **Chips** Por aprobar, Por pagar, Con aviso, Terminados (con su cuenta); buscador (subcontratista, objeto o código);
    proyecto; «Limpiar filtros».
  - **Bandeja «Por aprobar · N»** (no aparece si no hay): cada valorización pendiente con avatar, subcontratista,
    código, val. y periodo, avance y días de espera; la cuenta «monto − amortización − retención = **neto**»; la alarma
    en coral si la hay; botón **Aprobar** (lo mismo de siempre).
  - **Tarjetas** en dos columnas (una en angostas), por urgencia: con algo por aprobar, con alarma, con algo por pagar,
    el resto, terminados al final; se reacomodan con la animación. Cada una: iniciales del subcontratista en círculo,
    nombre, objeto, código y proyecto, monto y su estado (Por aprobar, Listo para liberar, Terminado…); **barra
    apilada** pagado · aprobado por pagar · por aprobar · por ejecutar sobre el monto (montos al pasar el puntero);
    avance, adelanto por amortizar y retención acumulada; la **alarma** «Le pagas 75 %, cobraste 41 %» en coral (más de
    10 puntos), o «sin valorizar al cliente» en gris.
  - **Clic:** panel deslizante con el resumen y Valorización del subcontratista, Aprobar, Liberar retención y Abrir
    ficha. **Doble clic:** la ficha.
- **Ficha del subcontrato** `/subcontratos/ficha?id=…`: sus valorizaciones como línea de pasos (periodo, avance, neto,
  estado) y su alcance por partida con barras de ejecutado contra comprometido.
- **Mien (v2):** «La valorización de [subcontratista] espera aprobación hace N días» (desde 3 días) y «A
  [subcontratista] le pagas más avance del que cobraste en [proyecto]».

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar adelanto | tipo (directo o materiales), monto sin IGV, % de amortización, fecha | — | adelanto del contrato |
| Nueva valorización | periodo AAAA-MM, % del periodo por partida, amortización de materiales, retención % | no hay otra en borrador; ninguna partida pasa del 100 % acumulado | borrador con la cuenta completa; base de la fórmula = el mes anterior a la firma |
| Aprobar valorización | — | borrador | aprobada, por facturar |
| Nuevo subcontrato | proyecto, subcontratista, objeto, adelanto %, retención %, alcance (partida y monto) | — | `SC-AAAA-NNNN`; cada partida del alcance queda comprometida por su monto |
| Valorización del subcontratista | subcontrato, periodo, avance % | el acumulado no pasa de 100 % | por aprobar: monto = total × avance; amortización = monto × adelanto %; retención = monto × retención %; neto = monto − ambas |
| Aprobar (subcontrato) | — | por aprobar | aprobada; cada partida del alcance, ejecutado += su monto × avance |
| Liberar retención (v2; acción registrada, permiso `erp.valorizaciones.liberar-retencion`) | fecha de conformidad, nota | 100 % aprobado; retención acumulada por devolver | la retención acumulada queda por pagar al subcontratista (`retencion_liberada`); el subcontrato pasa a «terminado» con su conformidad |

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
- La retención de garantía se escribe por valorización; desde la v2 se devuelve al liberar la retención; no hay
  liquidación del contrato ni del subcontrato todavía; tampoco consorcios.
- (v2) Alarma: avance acumulado del subcontratista contra el avance valorizado al cliente en las mismas partidas del
  alcance (ponderado por el monto de cada partida; aprobadas y facturadas); aviso desde más de 10 puntos.
- (v2) El «logo o avatar» del subcontratista son sus iniciales (no hay ficha de proveedor con logo).
- «Habilitar factura» solo marca la valorización (el comprobante llega en el tramo 5).

## Atajos tomados

- Estados como texto; sin acciones registradas; consultas directas en las pantallas.
- El subcontratista va por nombre (sin ficha de proveedor).
- «Pagada» del subcontrato se pone a mano en la demo (Tesorería, tramo 5); también `retencion_pagada` del terminado.
- (v2) Tarjetas, cifras y alarma se calculan en cada carga (`SubcontratosService::estados()`), sin guardarse.

## Dónde está en el código

`app/Modules/Erp/Valorizaciones/` (provider, migración, `Services/ValorizacionesService.php`,
`Filament/Pages/ValorizacionesDeContrato.php` y `Subcontratos.php`); datos en `DemoSeeder::tramo4()`. v2:
`Services/SubcontratosService.php`, `Acciones/LiberarRetencion.php`, `Filament/Pages/FichaSubcontrato.php`,
`AccionesDeSubcontrato.php`, vistas `resources/views/subcontratos/`; demo `Demo\SubcontratosCasosSeeder`.

## Cambios desde la versión anterior

- **v2** (observaciones-6, MV-072): `/subcontratos` pasa de tabla a bandeja + tarjetas con barra apilada, alarma de
  pagar de más, cinco cifras, chips, ficha, panel y avisos en Mien; nueva operación Liberar retención. El acumulado al
  cliente cuenta también las valorizaciones facturadas. Demo: SC-2026-0001 igual que en el v4 (val. 2 por aprobar,
  «le pagas 75 %, cobraste 41 %»); SC-2026-0002 con 11,250.00 por pagar; SC-2026-0003 con la alarma (65 % contra
  30 %); SC-2026-0004 al 100 %, listo para liberar 160.00, sin valorizar al cliente; SC-2026-0005 terminado con
  2,250.00 devueltos.
