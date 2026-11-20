# Ficha · Compras y almacén: de la necesidad al costo (flujo 2)

- **Estado:** en curso (v2, ronda de cambios; la v1 quedó aprobada)
- **Versión:** v2
- **Tramo de la ruta:** T3
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t3-compras-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-4-flujo-02.md` (requerimiento, doce tipos de compra, aprobación con
  exceso, cuadro comparativo, orden con seguimiento, recepción en obra); `parte-8-criterios-aceptacion.md` (F1 y F2).

## Qué hace

El especialista arma un requerimiento desde el proyecto: cada línea declara su tipo de compra y su partida, y el
sistema muestra el stock y cómo queda cada partida («quedaría al 91 %»). Si una línea excede su partida, quien aprueba
tiene que escribir el motivo. Al aprobarse, lo que hay en el Almacén Central se reserva (su costo queda comprometido) y
se atiende con un despacho; lo demás va a Compras, que abre un cuadro comparativo con las ofertas de los
proveedores y adjudica con motivo obligatorio. Nace la orden de compra o de servicio, que hereda líneas, imputaciones y
precios; al aprobarse compromete el costo en cada partida (con motivo si excede). La recepción en obra (o la
conformidad del servicio) pasa a ejecutado lo recibido. Desde aquí **el costo de los proyectos entra por órdenes**,
no por el gasto de demostración: el tablero de cada proyecto se mueve con las compras reales.

## Decisiones de Sebastián

- **MV-027** (cruza módulos) · «Oferta de proveedor» (OFP-001); «cotización» solo para lo que Techquk ofrece a sus clientes.
- **MV-028** (negocio) · Control de herramientas propias en Abastecer; solo dónde está.
- **MV-003** (ficha) · El gasto de demostración se va con las órdenes: aquí se reemplaza en los costos de proyecto
  (queda solo para el gasto directo de la empresa).
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** cajas «Requerimientos», «Órdenes» y «Almacén» en el área ERP.
- **Requerimientos** `/requerimientos`: código (proyecto debajo), para qué (solicitante debajo), estimado, se necesita,
  estado. **Nuevo requerimiento** (panel ancho): proyecto, para qué, fecha, y líneas con tipo de compra, ítem del
  catálogo (llena descripción y unidad), descripción, unidad, cantidad, imputación (las partidas del proyecto) y precio
  estimado (vacío: el costo del catálogo).
- **Ficha del requerimiento**: «Líneas» (ítem, tipo, cantidad, stock en el Almacén Central, imputación, estimado y en
  qué quedó: por comprar, en cuadro, con orden, reservada, despachada), «Cómo queda cada partida» (presupuestado, ya
  comprometido, este requerimiento, «quedaría al» en ámbar desde 90 % y rojo sobre 100 %), «Cuadro comparativo» (un
  proveedor por columna: precio por ítem, total, plazo, forma de pago, evaluación histórica, «elegido» y el motivo) y
  «Aprobación». Botones según el estado: Enviar a aprobación, Aprobar (pide motivo si excede), Rechazar (comentario
  obligatorio), Despachar a obra, Abrir cuadro comparativo, Agregar oferta de proveedor, Adjudicar.
- **Órdenes** `/ordenes`: orden (tipo debajo), proveedor, proyecto o «Gasto directo», monto, entrega, estado; filtros
  por estado y clase. **Ficha**: ítems (cantidad, precio, subtotal, imputación, recibido con comprometido y ejecutado
  debajo) y «Seguimiento» (entrega pactada, lugar, forma de pago, quién aprobó y si con motivo de exceso, seguimiento,
  cuadro y su motivo). Botones: Aprobar, Registrar recepción (o «Dar conformidad» en servicios), Seguimiento, Marcar
  pagada.
- **Almacén** `/almacen`: stock del Almacén Central con reservado, disponible y costo promedio; el valor del stock en
  el subtítulo.
- **Contratos → Garantías:** la comisión muestra su orden de servicio («OS-2026-0012 › gastos generales»).

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Nuevo requerimiento | proyecto, para qué, fecha de necesidad, líneas (tipo, ítem, descripción, unidad, cantidad, imputación, precio estimado) | al menos una línea | `REQ-AAAA-NNNN` en borrador |
| Enviar a aprobación | — | todas las líneas con imputación («No se puede enviar: la línea 2 no tiene imputación.») | por aprobar |
| Aprobar requerimiento | motivo del exceso | si alguna partida se excede, el motivo es obligatorio | aprobado; las líneas de bienes con stock suficiente se reservan (comprometido = cantidad × costo promedio); las demás, por comprar |
| Rechazar | comentario (obligatorio) | por aprobar | rechazado (se puede corregir y volver a enviar) |
| Despachar a obra | línea reservada, fecha | reservada | sale del almacén; su comprometido pasa a ejecutado |
| Abrir cuadro comparativo | — | quedan líneas por comprar | `CC-AAAA-NNNN` abierto |
| Agregar oferta de proveedor | proveedor (ficha), plazo, forma de pago, evaluación, precio por línea | cuadro abierto | — |
| Adjudicar | proveedor elegido, motivo (obligatorio), lugar de entrega | — | `OC-` (si el tipo es un bien) u `OS-` en borrador con las líneas, imputaciones y precios elegidos |
| Aprobar orden | motivo del exceso | si alguna partida se excede, el motivo es obligatorio («excede PC-02.01 en S/ 48.00») | aprobada; cada línea comprometida en su partida (o centro de costo) |
| Registrar recepción / Dar conformidad | fecha, guía, dónde (obra o Almacén Central), cantidad por línea, observación | no más de lo pedido | recibida parcial o recibida; lo recibido pasa a ejecutado (en almacén, sin imputación, entra al stock a costo promedio) |
| Seguimiento | texto | — | — |
| Marcar pagada | — | — | lo ejecutado pasa a pagado (provisional hasta Tesorería, tramo 5) |
| (Contratos) Registrar garantía con comisión | — | árbol aprobado | OS «Garantía financiera» aprobada, conforme y pagada en la partida de gastos generales |

## Estados

Requerimiento: borrador → por aprobar → aprobado → atendido (todas sus líneas despachadas u ordenadas); por aprobar →
rechazado → por aprobar. Cuadro: abierto → adjudicado. Orden: borrador → aprobada → recibida parcial → recibida
(«anulada» existe en la lista, sin botón). Línea del requerimiento: pendiente, en cuadro, ordenada, reservada,
despachada.

## Datos

Tablas nuevas: `tipos_compra` (los doce del v4 como filas: bien para proyecto, importación, servicio puntual,
subcontrato, servicio con vigencia, garantía financiera, viáticos y caja chica, bien para la empresa, alquiler de
equipo, servicios profesionales, software y licencias, combustible y peajes; con su atención: recepción o conformidad),
`almacenes` (con el Almacén Central), `stock` (cantidad, reservado, costo promedio), `movimientos_almacen` (ingreso,
salida, reserva), `requerimientos` y `requerimiento_lineas`, `cuadros_comparativos`, `cuadro_cotizaciones` y
`cuadro_precios`, `ordenes` y `orden_lineas`, `recepciones` y `recepcion_lineas`. Los montos de cada línea están en
`imputaciones` (origen: la línea de la orden o la del requerimiento despachado). Permisos `erp.compras.ver`,
`.requerir`, `.aprobar` y `.comprar`.

## Reglas y números

Escenario del v4 en la demo, proyecto **«Videovigilancia de la Plaza de Armas de Valle Norte»** (PRY-2026-0003):

- Presupuesto con APU: costo directo **17,750.00**, gastos generales 1,775.00, utilidad 1,420.00, IGV 3,770.10, total
  **24,715.10**. Árbol de 9 partidas: **19,525.00** (PC-01.01 5,830; PC-01.02 600; PC-02.01 1,512; PC-02.02 3,880;
  PC-02.03 1,380; PC-03 4,548; PC-04 1,775).
- Contrato CT-2026-0003 a 30 días: penalidad diaria **205.96**, tope **2,471.51**. Carta fianza de 2,471.51 con
  comisión **180.00** → OS imputada a **PC-04**.
- REQ «Instalación de postes»: 4 kits de cimentación con 6 en almacén → reservados, **PC-02.02 comprometido +480.00**;
  la grúa (650.00 a PC-04) y los conectores (PC-01.01) van a un cuadro abierto con una oferta de proveedor.
- REQ del cable: 650 m a 2.40; cuadro con Proveedor Andino (2.40, 2 días, 30 días, 4.5/5) y Redes del Norte (2.60, 1 día,
  contado, 3.8/5); adjudicado a Andino con motivo → **OC por 1,560.00** aprobada con motivo porque **PC-02.01 queda en
  −48.00**; recibidos 520 m en obra → **ejecutado 1,248.00**, comprometido sigue en 1,560.00, orden «recibida
  parcial», seguimiento «faltan 130 m».
- Margen proyectado del proyecto: **1,372.00** (vendido 20,945.00 − proyección 19,573.00), el punto de partida del
  escenario del flujo 7.
- Los 9 proyectos de la cartera: sus 104 costos son ahora 104 órdenes directas (100 unidades de monto ÷ 100), con los
  mismos montos de antes; 111 órdenes en total.

## Supuestos sin confirmar

- La reserva de stock es solo del Almacén Central y al costo promedio; no hay almacenes de obra ni números de serie.
- La orden toma el tipo de la primera línea adjudicada (un cuadro mezcla bien y servicio en una sola orden).
- Desde qué monto el cuadro comparativo es obligatorio queda por validar (el v4 lo pregunta): hoy hay órdenes directas.
- El motivo del exceso no pasa por la bandeja de aprobaciones del núcleo ni por el aprobador según monto.
- La recepción en almacén de algo imputado a un proyecto no se maneja (lo imputado se recibe en obra).
- Importación (embarque y prorrateo), servicios con vigencia, viáticos y combustible tienen su tipo pero no su
  comportamiento propio todavía; la reclasificación de un gasto ya hecho sigue siendo la del plan 2.8 que ya existía.

## Atajos tomados

- Estados como texto; sin acciones registradas ni bloqueo optimista en pantallas.
- El almacén vive dentro del módulo de Compras.
- «Marcar pagada» mueve lo pagado sin Tesorería.
- Los proveedores de las órdenes directas de la demo van solo con su nombre (sin ficha).
- El gasto directo de la empresa sigue entrando por el gasto de demostración.

## Dónde está en el código

`app/Modules/Erp/Compras/` (provider, migración con los tipos y el Almacén Central, modelos,
`Services/ComprasService.php`, `Filament/Resources/Requerimientos`, `Filament/Resources/Ordenes`,
`Filament/Pages/Almacen.php`, `resources/views/cuadro.blade.php`); la comisión en `ContratosService::registrarGarantia()`;
datos en `DemoSeeder::compraDemo()` y `plazaDeArmas()`.

## Cambios desde la versión anterior

- **v2** (ronda 1, MV-027 y MV-028):
  - **Ofertas de proveedor:** cada una lleva su código `OFP-001`, `OFP-002`… (correlativo global; las que ya existían
    se numeraron por orden de creación). En el requerimiento: botón «Agregar oferta de proveedor», el cuadro dice
    «N ofertas de proveedor» y cada columna lleva el código encima del proveedor; al adjudicar se elige «OFP-00N ·
    proveedor». Columna nueva `cuadro_cotizaciones.codigo` (texto 20, única).
  - **Herramientas** `/herramientas` (caja en Abastecer, junto a Almacén): código `HER-001`, herramienta (tipo, marca y
    modelo, serie), dónde está (proyecto o «En almacén», «En mantenimiento»), quién la tiene (colaborador), debe volver
    (rojo y «atrasada N d» si pasó), estado. Primero las que están en obra, por fecha de vuelta. Subtítulo: total, en
    obra y atrasadas. Operaciones: **Nueva herramienta** (nombre, tipo: eléctrica, trabajo en altura, vehículo,
    medición, manual; marca y modelo; serie o placa) entra al almacén; **Enviar a obra** (proyecto en ejecución o
    preparación, quién la lleva, sale el, debe volver el; no antes de la salida; solo si está en el almacén);
    **Volvió** (fecha, «pasa a mantenimiento», nota). Cada paso queda en `herramienta_movimientos` (salida, devolución,
    mantenimiento). Tablas nuevas `herramientas` y `herramienta_movimientos`.
  - **Demo:** 10 herramientas: 6 en obra (la camioneta y la certificadora, atrasadas 3 y 2 días), 3 en el almacén y la
    fusionadora en mantenimiento.
  - **Pendiente de Sebastián:** si el uso de una herramienta se carga como costo al proyecto (no se construyó).
  - **Atajos:** el estado de la herramienta es texto; los códigos OFP y HER salen de contar filas (sin bloqueo); la
    tabla y el modelo siguen llamándose `cuadro_cotizaciones` y `CotizacionProveedor`; quién la tiene se lee de la
    tabla de colaboradores del módulo Personal con un join.
