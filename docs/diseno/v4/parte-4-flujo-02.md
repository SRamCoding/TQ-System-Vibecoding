# v4 · Flujo 2 · De la necesidad al costo

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 2 · De la necesidad al costo
Es el flujo que más se usa en Techquk, y el que hoy mejor se conoce porque vive en parte en S10.
Empieza cuando alguien en el proyecto necesita algo y termina cuando ese algo está en la obra, con
su costo en la partida correcta y la factura del proveedor lista para pagar. Tiene un tronco común y
ocho variantes según el tipo de compra.
|  1  |  Se pide
|  Especialista del proyecto
|  El especialista arma un requerimiento desde el proyecto. Cada línea declara qué tipo de compra
|  es y a qué partida va. El sistema revisa stock en el momento: lo que hay en almacén se atenderá
|  con un despacho, no con una compra, y muestra cómo queda cada partida.
|  REQ-2026-0311 · Requerimiento  |  Borrador  |  Enviar a aprobación
|  Solicitante  |  Necesario para
|  Especialista de redes  |  19 set · instalación de postes
|  Ítem  |  Tipo de compra  |  Cant.  |  Stock  |  Imputación  |  Estimado
|  Materiales de cimentación  |  Bien para proyecto  |  4 und  |  6 en Almacén Central  |  PRY-012 › PC-02.02  |  480.00
|  Alquiler de grúa · 1 día  |  Servicio puntual  |  1  |  —  |  PRY-012 › PC-04  |  650.00
|  Conectores RJ45 de repuesto  |  Bien para proyecto  |  10 und  |  0  |  PRY-012 › PC-01.01  |  15.00
|  Hay stock de cimentación: esa línea se atenderá con  |  PC-04 quedaría al 91 % con la grúa.
|  despacho, no con compra.
Requerimiento. Cada línea declara tipo de compra e imputación; el sistema revisa stock y muestra cómo queda cada partida.
Los ocho tipos de compra son configuración, no código, y cada uno activa comportamientos distintos
sobre el mismo tronco: AJUSTE SET 2026
|  Tipo  |  Ejemplo  |  Qué activa
|  Bien para proyecto  |  Cámaras, cable, postes  |  Recepción, stock, series, despacho a obra
|  Importación  |  Switches del exterior  |  Todo lo anterior más embarque, gastos y
|  prorrateo
|  Servicio puntual  |  Transporte, grúa, instalación tercerizada  |  Conformidad del servicio en lugar de
|  recepción
|  Subcontrato  |  Obra civil  |  Valorizaciones del subcontratista y
|  retenciones
|  Servicio con vigencia  |  SCTR, pólizas, alquileres, licencias  |  Vigencia, órdenes periódicas, alertas de
|  vencimiento (flujo 7)
|  Garantía financiera  |  Comisión de carta fianza  |  Enlace a la garantía del contrato (flujo 1)
|  Viáticos y caja chica  |  Pasajes y comida en campo  |  Entrega de fondos y rendición con
|  comprobantes (flujo 7)
|  Bien para la empresa  |  Laptop, camioneta  |  Alta como activo fijo FASE POSTERIOR
|  Alquiler de equipo o  |  Grúa, andamios, camioneta por días  |  Servicio con fechas de uso y devolución;
|  maquinaria  |  costo por día imputado a la partida
|  Honorarios  |  Ingeniero externo, notario, broker  |  Recibo por honorarios; retención de renta
|  profesionales  |  de cuarta categoría cuando aplica
|  Software y licencias  |  Licencias S10, herramientas de desarrollo,  |  Servicio con vigencia, con usuarios o
|  suscripciones a servicios en la nube  |  equipos asignados
|  Combustible y peajes  |  Movilidad propia a obra  |  Vale por vehículo y proyecto; se puede
|  rendir por foto
Decisión: el inventario de tipos es amplio desde el inicio, con estos doce, y como cada tipo es un
registro y no código, agregar otro es configuración. POR VALIDAR En las sesiones se confirma cuáles
ocurren de verdad y con qué frecuencia.
|  2  |  Se aprueba
|  Jefatura de Proyectos, Gerencia según monto
|  El requerimiento cae en la bandeja del aprobador. Si alguna línea excede el saldo de su partida,
|  la fila lo muestra y el aprobador tiene que registrar el motivo: nadie autoriza un sobrecosto sin
|  saberlo.
|  Mis aprobaciones  |  4 pendientes
|  Pendientes  |  Aprobadas por mí  |  Rechazadas por mí
|  Documento  |  Qué es  |  Imputación  |  Monto Espera
|  OC-2026-0142 Orden · 650 m cable UTP  |  PRY-012 › PC-02.01  |  1,560.00 2 h  |  Aprobar
|  excede S/ 48  |  Rechazar
|  REQ-2026-  |  Requerimiento · cimentación,  |  PRY-012 › 3 partidas  |  1,145.00 5 h  |  Aprobar
|  0311  |  grúa, conectores  |  Rechazar
|  OS-2026-0057 Servicio · alquiler de local,  |  Gasto directo ›  |  2,800.00 1 día  |  Aprobar
|  setiembre  |  Administración  |  Rechazar
|  AD-2026-004  |  Adenda · ampliación de plazo 10  |  CT-2026-011  |  — 2 días  |  Abrir
|  días  |  vence
Bandeja única de aprobación. El exceso de partida aparece en la fila: el aprobador sabe que autoriza un sobrecosto. Rechazar
exige comentario.
|  3  |  Se cotiza y se elige proveedor
|  Compras e Importaciones
|  Para lo que hay que comprar, Compras pide cotizaciones y las registra en un cuadro
|  comparativo. Hoy esto ocurre por correo o WhatsApp y el precio se pasa a mano (en S10 la tabla
|  de cotizaciones está vacía). El cuadro deja escrita la decisión y su motivo.
|  Cuadro comparativo · Cable UTP Cat6 exterior · 650 m  |  2 cotizaciones  |  Adjudicar
|  Proveedor Andino S.A.C.  |  Redes del Norte E.I.R.L.
|  Precio unitario  |  2.40  |  2.60
|  Total  |  1,560.00  |  1,690.00
|  Plazo de entrega  |  2 días  |  1 día
|  Forma de pago  |  30 días  |  contado
|  Evaluación histórica  |  4.5 / 5 · 12 órdenes  |  3.8 / 5 · 3 órdenes
|  Decisión  |  elegido
|  MOTIVO · OBLIGATORIO
|  Menor precio, crédito a 30 días y buen historial; el día adicional no afecta el hito del 19.
Cuadro comparativo. Dentro de seis meses alguien va a preguntar por qué se le compró a ese proveedor; la respuesta queda
escrita.
|  POR VALIDAR  |  Definir desde qué monto el cuadro es obligatorio, y si basta con adjuntar las cotizaciones
recibidas por correo.
|  4  |  Se emite la orden
|  Compras · aprueba según monto
|  La orden hereda las líneas, las imputaciones y los precios adjudicados. Al aprobarse, su monto
|  pasa a comprometido en cada partida. En el ejemplo, la orden de cable excede su partida en S/
|  48 y se aprobó con motivo.
|  OC-2026-0142 · Proveedor Andino S.A.C.  |  Recibida parcial  |  Registrar recepción
|  Detalle  |  Recepciones  |  Facturas del proveedor  |  Documentos  |  Historial
|  Ítem  |  Cant.  |  P. unit  |  Subtotal Imputación  |  Recibido
|  Cable UTP Cat6 exterior  |  650 m  |  2.40  |  1,560.00 PRY-012 › PC-02.01 excede 48  |  520 m
|  Entrega pactada  |  Lugar
|  SEGUIMIENTO
|  18 set  |  Obra · Plaza de Armas
|  Faltan 130 m · el proveedor confirmó para el 19 set
|  Forma de pago  |  Aprobó
|  30 días  |  R. Díaz · motivo del exceso
|  registrado
Orden de compra con seguimiento. Esta se entrega directo en obra, así que la recepción la registra el especialista en campo.
|  Qué queda registrado · OC-2026-0142 aprobada · comprometido de PC-02.01: S/ 1,560.00 sobre un
|  presupuestado de S/ 1,512.00 · compromiso de pago al proveedor a 30 días
|  4b  |  Si es importación: embarque, gastos y prorrateo
|  Compras e Importaciones
|  La orden de tipo importación abre un expediente. Se sigue el embarque, se registran los gastos
|  (flete, seguro, agente, terminal, transporte) y se prorratean entre los ítems. El embarque que
|  abastece a un proyecto se cruza con los hitos de su contrato: si la llegada estimada los
|  compromete, se alerta.
|  Embarques  |  Buscar por número, proveedor o proyecto…  |  + Orden de importación
|  En tránsito  |  En aduana  |  Por prorratear  |  Cerrados
|  Llegada  |  Hito que
|  Embarque Proveedor  |  Proyecto  |  Etapa  |  Riesgo
|  est.  |  depende
|  EMB-031  |  Proveedor Asia  |  PRY-2026-  |  Tránsito marítimo  |  24 set  |  Entrega 21 set  |  3 días tarde
|  Ltd.  |  008
|  EMB-030  |  Proveedor USA  |  PRY-2026-  |  Aduana · canal  |  19 set  |  Suministro 30 set  |  en plazo
|  Inc.  |  010  |  naranja
|  EMB-029  |  Proveedor Asia  |  Stock  |  Recibido  |  —  |  —  |  por prorratear
|  Ltd.
Seguimiento de embarques. «Hito que depende» cruza la importación con el contrato: un embarque vale por lo que retrasa.
|  EMB-029 · Expediente  |  Recibido  |  Aprobar prorrateo
|  Resumen  |  Gastos  |  Prorrateo  |  Declaración aduanera  |  Documentos
|  GASTOS · TC 3.74  |  PRORRATEO POR VALOR FOB · FACTOR 15.15 %
|  Concepto  |  Monto S/  |  Ítem  |  FOB S/  |  Gastos Costo puesto  |  Unitario
|  Flete (USD 1,250)  |  4,675.00  |  Switch × 3  |  26,928.00  |  4,080.21  |  31,008.21 10,336.07
|  Seguro (USD 180)  |  673.20  |  NVR × 2  |  23,936.00  |  3,626.85  |  27,562.85 13,781.43
|  Agente de aduana (USD 350)  |  1,309.00  |  Cámara × 40  |  17,952.00  |  2,720.14  |  20,672.14  |  516.80
|  Terminal y almacenaje  |  1,570.00  |  Total  |  68,816.00 10,427.20  |  79,243.20
|  Transporte Callao–Chiclayo  |  2,200.00
|  Derechos arancelarios  |  0.00
|  Total  |  10,427.20
|  El IGV de importación es crédito fiscal, no costo.
Gastos y prorrateo. El costo unitario puesto en almacén es el que entra al inventario y el que después se carga a la partida.
|  5  |  Se recibe
|  Almacén, o el especialista si se entrega en obra
|  La recepción precarga lo que la orden espera; se confirma cantidad y, para equipos, número de
|  serie. Decisión: el material a veces va al almacén central y a veces directo a la obra, y el
|  sistema soporta los dos casos con la misma pantalla. Si la entrega es en almacén, sube el stock y
|  el costo pasa a ejecutado recién al despacharlo. Si es directa en obra, el especialista la registra
|  desde el celular en el almacén de obra del proyecto, con la serie, y el costo pasa en el mismo
|  momento a ejecutado. Cada orden dice desde su emisión cuál es su lugar de entrega.
|  Recepción · OC-2026-0138 · Proveedor Andino S.A.C.  |  Guardar parcial  |  Confirmar recepción
|  Guía del proveedor  |  Almacén
|  EG01-000457  |  Central · ubicación A-03
|  Ítem  |  Esperado  |  Recibido  |  Series  |  Observación
|  Cámara domo IP 4MP  |  10  |  10  |  10 de 10 escaneadas
|  Conectores RJ45  |  20  |  20  |  no aplica
|  Las series se capturan con lector o con la cámara del celular. Un equipo sin serie no puede despacharse.
Recepción contra orden. Precarga lo esperado; el almacenero confirma o corrige.
El almacén de obra es un almacén más del sistema, ligado al proyecto: permite saber qué material
está en la obra sin instalar todavía, y devolverlo al central si sobra.
|  6  |  Se despacha a obra
|  Almacén
|  Lo que está en almacén sale a la obra con un despacho: se eligen las series, el costo del almacén
|  se carga a la partida (pasa a ejecutado) y, si el material sale del local, se genera la guía de
|  remisión electrónica.
|  Despacho DES-2026-0077 · a PRY-2026-012  |  Despachar y generar guía
|  Ítem  |  Cant.  |  Series  |  Costo unit. Imputación
|  Cámara domo IP 4MP  |  10  |  DS8K…4411 a DS8K…4420  |  575.00 PRY-012 › PC-01.01
|  Conectores RJ45  |  20  |  no aplica  |  1.50 PRY-012 › PC-01.01
|  Destino  |  Recibe
|  Obra · Plaza de Armas  |  Esp. CCTV · firma en el celular
Despacho a obra. Lleva el costo del almacén a la partida (como RegistroConsumo en S10) y genera la guía de remisión.
|  Qué queda registrado · 10 cámaras con serie despachadas a PRY-012 · ejecutado de PC-01.01: S/ 5,780.00 ·
|  guía de remisión T001-000215 aceptada por SUNAT
|  7  |  Llega la factura del proveedor
|  Contabilidad
|  El proveedor manda su factura con el XML. Se sube, el sistema lee los datos exactos, valida el
|  comprobante ante SUNAT, lo asocia a su orden y hace el cruce de tres vías: lo ordenado, lo
|  recibido y lo facturado. Si coinciden, pasa al tablero de pagos (flujo 5) y se genera su asiento. Si
|  no, queda retenida con el motivo a la vista.
|  Por pagar  |  Subir XML de proveedor  |  Retenidas  |  Ir al tablero de pagos
|  Factura del
|  Proveedor  |  Orden  |  Cruce de tres vías  |  Vence  |  Base  |  Total
|  proveedor
|  E001-000331  |  Proveedor Andino  |  OC-2026-  |  orden = recepción = factura  |  12 oct  |  5,780.00 6,820.40
|  S.A.C.  |  0138
|  E001-000347  |  Proveedor Andino  |  OC-2026-  |  factura 650 m · recibido 520 m  |  18 oct  |  1,560.00 1,840.80
|  S.A.C.  |  0142
|  F001-002231  |  Transportes  |  OS-2026-  |  conformidad ✓  |  25 set  |  600.00  |  708.00
|  Lambayeque  |  0052
|  Las facturas llegan como XML y se suben: el sistema lee los datos, valida el comprobante ante SUNAT y hace el cruce. Si orden,
|  recepción y factura no coinciden, queda retenida.
Cuentas por pagar. La segunda factura no se paga todavía: el proveedor facturó 650 m y en obra se recibieron 520.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Recepción parcial. La orden queda en «recibida parcial»; lo pendiente sigue comprometido y
|  aparece en el seguimiento. Si el proveedor no completa, la orden se cierra con motivo y se
|  libera el compromiso restante.
|  Exceso de partida. Se puede continuar, pero la línea exige motivo y aprobación; el exceso
|  queda visible en presupuesto contra real y en el tablero del jefe de proyecto.
|  La factura no coincide. Queda retenida. Se resuelve con una recepción complementaria, una
|  nota de crédito del proveedor o una aprobación con motivo.
|  Devolución al proveedor. Un movimiento de salida con motivo; baja el stock y, si ya estaba
|  ejecutado, revierte el costo en la partida.
|  Embarque en riesgo. La alerta llega al jefe de proyecto y a Administración de Contratos, que
|  puede evaluar una adenda de plazo (flujo 4) con el simulador de penalidad.
|  No hay partida adecuada. Si el gasto estaba previsto pero mal ubicado, Robert crea la partida
|  en una nueva versión del árbol. Si el gasto no estaba previsto, va a la PC-99 con su motivo (flujo
|  7).
|  La factura del proveedor llega solo en PDF. Se lee por visión y cada dato leído con poca
|  seguridad se marca para revisar antes de confirmar.
```
