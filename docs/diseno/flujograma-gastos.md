# Flujograma de gastos (v4)

> Extracto de texto de `fuentes/Techquk-Flujograma-de-gastos-1.pdf`. Es un diagrama: si el orden no se entiende, consulta el PDF.

```text
Cómo se obtienen todos los gastos
Techquk · sistema CRM + ERP v4 · Todo gasto entra por un documento, cada línea elige su destino, y los totales salen solos. Nadie arma una hoja de cálculo al
final.
  |  1 · Todo lo que genera costo entra por un documento (sin documento, no existe)
  |  Órdenes de compra Recepción / despacho  |  Importaciones  |  Tareo y planilla  |  Subcontratos  |  Viáticos y caja chica
  |  12 tipos configurables  |  almacén central o directo en obra  |  costo puesto, prorrateado  |  horas por partida · costo real  |  valorizaciones y retención  |  rendición con foto
  Servicios con vigencia  |  Carta fianza  |  Moneda extranjera  |  Penalidades  |  No presupuestado  |  Gasto de la empresa
  |  SCTR, pólizas, alquileres  |  solo su comisión es gasto  |  diferencia de cambio al pagar  |  aplicadas por la entidad  |  con motivo y aprobación  |  oficina, contador, luz
  |  2 · Selector de imputación
  |  cada línea elige exactamente un destino · no existe línea sin destino
  |  Proyecto › partida de control  |  Empresa › centro de costo
  |  PC-01 a PC-03: lo presupuestado · PC-04: generales del proyecto (SCTR, movilidad, fianza)  |  gasto directo sin proyecto · Administración, Comercial, Operaciones, Suministro, RRHH
  |  PC-99: gasto directo con proyecto, con motivo · si lo pidió el cliente → posible adicional  |  alquiler, pólizas del local, honorarios, licencias, soporte interno
  |  3 · Costo real del proyecto  |  3 · Gasto general de la empresa
  |  presupuestado → comprometido → ejecutado → pagado, por partida  |  por centro de costo y por mes, contra lo previsto
  |  disponible = presupuestado − comprometido · margen est. = venta − máx(presup., comprom.)  |  repartible a proyectos para margen neto (análisis de gerencia, después)
  |  4 · Contabilidad y caja: el mismo número por dos caminos
  |  gasto total = ejecutado en todos los proyectos + gasto directo sin proyecto · cada documento genera su asiento por plantilla → Excel del contador
  |  caja ≠ costo: la caja es cuándo salió el dinero (pagos aplicados, tesorería, flujo de caja) · un gasto puede estar ejecutado y no pagado
```
