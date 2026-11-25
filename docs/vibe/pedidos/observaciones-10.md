# Observaciones 10 (décima tanda)

Pedido de Sebastián, desde el 2026-11-24. Cada punto se registra como decisión en `docs/traspaso/decisiones.md` y
actualiza su ficha (`t3-compras`).

## 1. Almacén: lo que S10 tenía y faltaba, y más

Lienzo: https://claude.ai/artifact/SiXNiw3MHNuUG8wQaCASVg (copia en `docs/vibe/pedidos/lienzos/almacen/`; la copia
manda). En S10 Techquk usó 11 de 55 tipos de movimiento; aquí se suman los que importan, **menos el ingreso sin orden**
(decisión de Sebastián: toda compra entra por una orden). Todo movimiento queda en el **kardex** de cada ítem (fecha,
tipo, documento, entrada, salida, saldo y costo promedio).

**Portada** (`Main.dc.html`): chips por almacén (Almacén Central y uno por obra, «+ Almacén de obra»); cifras (valor del
stock, reservado para obras, ítems bajo el mínimo, devoluciones de obra del mes y su efecto en costos, último conteo);
botones de cada movimiento; tabla de ítems (código, ítem, unidad, stock, reservado, disponible, costo promedio, series o
kit, y etiquetas «bajo el mínimo» y «contar este mes»). Cada ítem con **stock mínimo** (opcional). Clic en un ítem: su
ficha con kardex y series.

1. **Devolución de obra** (`Movimientos.dc.html`): lo que sobró en una obra vuelve a un almacén, con cantidad, estado
   (bueno o dañado) y series si las tiene. **El costo sale del proyecto** en la misma partida donde se cargó, al costo
   con que salió, y vuelve al stock; la ventana muestra cuánto baja el costo del proyecto y cómo queda su margen. Lo
   dañado entra a un estado «dañado» (no disponible). Valida no devolver más de lo despachado a esa obra.
2. **Números de serie** (`Series.dc.html`): **las series ya existen en Postventa** (ficha `t6-postventa`: ingresar
   series, instalar, rastreo de serie); usa ese mismo modelo y su rastreo, no crees otro. Lo nuevo es que el almacén
   las pida en cada movimiento: los ítems marcados «con serie» piden la serie de cada unidad al recibir
   (escrita, escaneada o pegada en lista; única en el sistema) y al despachar, transferir o devolver se elige cuáles.
   Cada serie tiene su historia (comprada, recibida, despachada a qué proyecto, instalada dónde y por quién, garantía
   hasta cuándo, tickets). Búsqueda de series desde Almacén, desde la ficha del proyecto («series instaladas aquí») y
   desde **Postventa**: al abrir un ticket por un equipo se elige su serie y se ve al instante si está en garantía, de
   qué proyecto es y quién lo instaló. La garantía sale de la del proveedor y de la del contrato con el cliente (la
   que venza después, anotado como supuesto).
3. **Conteo físico** (`Conteo.dc.html`): se abre un conteo por almacén, se anota lo contado (se puede guardar avance),
   el sistema muestra la diferencia en cantidad y soles y pide motivo por línea. Al cerrar, ajusta el stock (ingreso o
   egreso por inventario, en el kardex). Si el ajuste total pasa de un monto (parámetro, S/ 1,000.00 por omisión) lo
   aprueba alguien con permiso (Mien). El ajuste va a un gasto directo del almacén con su centro de costo.
4. **Armar kits** (`Conteo.dc.html`): un ítem puede ser **kit** con su receta (componentes y cantidades). «Armar»
   descuenta los componentes y suma kits al costo de sus piezas; «Desarmar» hace lo inverso. Los kits se piden,
   reservan y despachan como un ítem más.
5. **Almacenes de obra y transferencias** (`Movimientos.dc.html`): cada proyecto en ejecución puede tener su almacén.
   «Transferir» mueve material entre almacenes con guía de remisión (número) y series; **el costo no pasa al proyecto
   al transferir, sino al consumirse** («Consumir en obra», imputado a su partida, con su comprometido si venía de un
   requerimiento). Lo que queda en un almacén de obra al cerrar el proyecto se devuelve o se transfiere.
6. **Devolución al proveedor** (`Movimientos.dc.html`): contra su orden, con cantidad, series y motivo; sale del
   stock y queda una **nota de crédito pendiente** en Finanzas (cuenta por cobrar al proveedor) hasta que se registre.
7. **Vender material** (`Movimientos.dc.html`): a un cliente, con precio; sale del stock al costo promedio y muestra el
   margen de la venta; queda registrada para facturar (la factura se emite en el facturador, como hoy).

**Permisos:** uno por movimiento. **Datos de demo:** dos almacenes de obra, ítems con serie y su historia completa
(una con ticket de ayuda), una devolución de obra que bajó el costo de un proyecto, un conteo cerrado y uno en curso,
un kit armado, una transferencia, una devolución al proveedor con su nota de crédito pendiente y una venta.

**Alcance:** cruza módulos (Almacén, Compras, Proyectos por el costo, Postventa por las series, Finanzas por la nota de
crédito y la venta).

## 2. Abastecer: comprar más barato, a tiempo y con proveedores medidos

Lienzo: https://claude.ai/artifact/3ZsTRNLkeNSTU5ngPWCnCg (copia en `docs/vibe/pedidos/lienzos/abastecer/`; la
copia manda; cada pizarra lleva el número de la mejora).

1. **Pedir ofertas con un clic** (`Main.dc.html`, `Proveedor.dc.html`): desde el cuadro comparativo se eligen
   proveedores y se les envía una **solicitud de oferta** con un enlace único, sin cuenta, que vence con el plazo. El
   proveedor ve los ítems, cantidades y especificaciones y llena precio unitario, plazo de entrega, condiciones y un
   PDF opcional; al enviar, **su oferta entra sola al cuadro**. En el cuadro, el estado de cada solicitud (respondió,
   pendiente con su vencimiento) y «Recordar» o «+ Pedir a otro proveedor». En el MVP el enlace se copia para mandarlo
   por correo o WhatsApp a mano (el envío automático queda como punto de extensión). El enlace solo deja llenar esa
   solicitud; límite de envíos; nada más del sistema es visible.
2. **Último precio pagado**: al pedir y al comparar, cada ítem muestra a cuánto se compró la última vez, a quién y en
   qué orden, y su historial. Si un precio estimado u ofertado se aleja más de un % (parámetro, 10 % por omisión) del
   último, se marca en coral («estimas 15.8 % más»). En el cuadro, una línea «Las ofertas de hoy están dentro de lo
   normal (±3 %)» o lo contrario.
3. **Calificación de proveedores**: sale sola de lo registrado: **entregas a tiempo** (fecha pactada contra fecha de
   recepción, %), atraso promedio, **devoluciones por fallas**, **precio contra el promedio** de los demás y lo
   comprado en 12 meses; se resume en estrellas. Se ve en el cuadro comparativo, en la **ficha del proveedor**
   (`Plan.dc.html`) y se puede anotar un comentario. El cuadro marca «Mejor precio» y «Mejor entrega» y una
   recomendación que pondera precio, entrega y calificación; adjudicar a otro sigue pidiendo motivo.
4. **Entregas atrasadas** (`Entregas.dc.html`): la lista de órdenes marca las vencidas sin recepción («4 días de
   atraso») y las que vencen pronto (2 días, parámetro), con para cuándo la necesita la obra (la fecha del
   requerimiento). Aviso en Mien al comprador con «Llamar al proveedor» (registra la actividad) y «Nueva fecha» (con
   motivo; cuenta para la calificación).
5. **Juntar requerimientos de varias obras** (`Requerimiento.dc.html`): si hay requerimientos aprobados de otros
   proyectos que piden el mismo ítem en el mismo periodo, el sistema lo sugiere y «Juntar en un cuadro» arma un solo
   cuadro y una sola orden; **cada parte sigue imputada a su proyecto y partida**.
6. **Reposición automática**: cuando un ítem del almacén baja de su mínimo, se arma solo un requerimiento de
   reposición para el Almacén Central (cantidad para volver al máximo o al doble del mínimo; parámetro) en borrador,
   con aviso para enviarlo a aprobación. No se duplica si ya hay uno abierto.
7. **Plan de compras desde el presupuesto** (`Plan.dc.html`): para un proyecto, el sistema propone qué comprar y en qué
   semana, a partir de las partidas y cantidades del presupuesto y de cuándo empieza cada partida en el cronograma,
   restando lo que ya hay en almacén y considerando el plazo de entrega habitual de los proveedores. «Crear
   requerimientos de la semana» los arma en borrador. Si el proyecto no tiene cronograma por partida, usa la fecha de
   inicio del proyecto (anótalo como supuesto).
8. **Acuerdos de precio** (`Entregas.dc.html`): un precio fijo por ítem con un proveedor hasta una fecha. Al crear una
   orden con ese proveedor e ítem, el precio se aplica solo; al pedir, se avisa que hay acuerdo. Lista de acuerdos
   vigentes con cuántas veces se usaron; aviso en Mien un mes antes de que venza.

**Datos de demo:** un cuadro con solicitudes respondidas y una pendiente, historial de precios de varios ítems, tres
proveedores con calificaciones distintas, órdenes atrasadas y a tiempo, requerimientos de tres obras que se pueden
juntar, una reposición automática, un plan de compras de un proyecto y tres acuerdos de precio.

**Alcance:** cruza módulos (Compras, Almacén, Proyectos y Presupuestos por el plan, Mien, Directorio por la ficha del
proveedor).

## 3. Facturación y cobranza: cobrar al Estado sin que el dinero se trabe

Lienzo: https://claude.ai/artifact/7HnFbyNjY5QwzfwQeit2As (copia en `docs/vibe/pedidos/lienzos/cobranza/`; la copia
manda; cada pizarra lleva el número de la mejora). S10 no llevaba ventas ni cobranza (solo facturas de proveedores,
cronogramas de pago y asientos); esto se suma a lo que ya hace el sistema (facturar valorizaciones, facturas emitidas
con XML, detracción, cobranzas, «¿Cuánto se ganó?»). Ficha `t5-finanzas`.

**La ficha de una factura por cobrar** (`Main.dc.html`; nueva, clic en una factura de Facturas emitidas o Cobranzas):

1. **Dónde está el pago en la entidad (SIAF):** para facturas a entidades públicas, una línea con **Conformidad →
   Factura entregada → Devengado → Girado → Pagado**, cada fase con su fecha. Se registra el número de expediente SIAF
   de la factura (a mano o sugerido). **Investiga primero** cómo consultar el estado del expediente en la Consulta
   Amigable del MEF (pública) de forma automática y estable; si se puede, una consulta diaria (cada mañana, en
   segundo plano, con «Consultado hoy 08:00») actualiza las fases y avisa en Mien cuando se **gira**. Si no se puede,
   las fases se marcan a mano con su fecha (y lo anotas). Dime qué encontraste antes de construir la consulta.
2. **Requisitos para cobrar:** cada factura lleva la lista de documentos que exige la entidad para pagar (conformidad,
   informe de valorización, guías, panel fotográfico, constancias…), con estado entregado / falta y archivo. La lista
   se propone desde el contrato (plantilla por entidad o por contrato, editable) y aparece al facturar una
   valorización. Lo que falta, en coral, y aviso en Mien al responsable.
3. **De lo facturado a lo que llega:** el desglose de lo que la entidad descuenta: **detracción** (4 %, a la cuenta del
   Banco de la Nación, como hoy), **retención del IGV** (3 %, solo si la entidad es agente de retención y la factura no
   lleva detracción: nunca las dos), **penalidades** descontadas (del módulo de Contratos) y **retención de garantía**
   (10 % que algunas entidades retienen en vez de la carta fianza). Queda «Llega a la cuenta corriente». La garantía
   retenida queda como **por cobrar al final del contrato**, con aviso al culminar para reclamarla. Lo cobrado cuadra
   con lo facturado sumando todo eso.
4. **Gestión de cobranza:** registrar gestiones (llamada, correo, visita, nota) y **compromisos de pago** (fecha y
   monto); si llega la fecha sin cobro, aviso en Mien. Recordatorio automático como tarea N días antes del
   vencimiento (parámetro, 5).

**Cobranzas** (`Cobranzas.dc.html`):

5. Cifras: por cobrar, vencido, garantías retenidas, **cobrarás este mes y el siguiente** (proyección según fase SIAF,
   compromisos y vencimientos) y **días promedio de cobro** (contra una meta, parámetro 45). La **antigüedad de la
   deuda** en una barra por tramos (al día, 1-30, 31-60, 61-90, más de 90) con montos, y una tabla por cliente: te
   debe, vencido y **días promedio que tarda en pagar** (de sus cobros reales). Clic en un tramo o cliente filtra.

**Conciliación bancaria** (`Conciliacion.dc.html`):

6. Se sube el estado de cuenta del banco (Excel o PDF; empezar por Excel/CSV de los bancos más comunes y anotar los
   formatos). El sistema une cada abono con su factura (monto y RUC o nombre; también abonos de detracción con su
   cuenta) y los marca **coincide**, **probable** (revisar) o **sin factura** (asignar a mano). «Confirmar los N»
   registra los cobros de una vez. Lo ya conciliado no se vuelve a proponer.

**Documentos** (`Documentos.dc.html`):

7. **Estado de cuenta del cliente** en PDF (facturas, pagadas y pendientes, saldo, garantías retenidas, cuenta y CCI de
   Techquk), para descargar o enviar.
8. **Notas de crédito y débito** sobre una factura emitida (anulación, descuento, devolución, corrección): se sube el
   XML de la nota emitida en el facturador (o se registra a mano); ajusta lo por cobrar y el ingreso del proyecto.
9. **Factoring (venta de una factura):** banco o financiera, % de adelanto, tasa y plazo; muestra adelanto y **costo
   financiero**, que se carga al proyecto como gasto financiero (baja su margen real, visible al registrar). Al cobrar
   la entidad, la factura queda pagada al banco y se cierra la cesión.

**Datos de demo:** facturas en cada fase del SIAF (una girada con su aviso), una con requisito faltante, una con
penalidad y garantía retenida, gestiones y un compromiso vencido, deuda en todos los tramos, un estado de cuenta
bancario de ejemplo para conciliar, una nota de crédito y una factura cedida. Los RUC de demo, inventados.

**Alcance:** cruza módulos (Facturación y cobranza, Finanzas, Contratos por penalidades y garantías, Proyectos por el
margen, Mien).

## 4. Postventa: un servicio que el cliente valora y que no se come el margen

Lienzo: https://claude.ai/artifact/PzZsTBtB9oCDwGcKeT19gM (copia en `docs/vibe/pedidos/lienzos/postventa/`; la copia
manda; cada pizarra lleva el número de la mejora). Se suma a lo que ya hace Postventa (ficha `t6-postventa`: tickets
con su plazo por prioridad, rastreo de serie, garantía de Techquk y del fabricante, cotizar fuera de garantía, visitas,
suscripciones).

1. **Costo de postventa por proyecto** (`Main.dc.html`): cada visita en garantía (horas del técnico a su costo hora),
   repuesto y movilidad **se carga al proyecto** del equipo (por su serie) como costo de postventa, imputado a una
   partida o centro de costo de garantía. El margen real del proyecto lo incluye; una tabla muestra por proyecto el
   margen al entregar, las visitas, el costo en garantía y el **margen con postventa** (coral si bajó más de 2 puntos).
   Lo que se cobra (fuera de garantía) es ingreso, no costo.
2. **Mantenimiento preventivo programado** (`Preventivo.dc.html`): planes por cliente o contrato (equipos o sistema,
   frecuencia, incluido en contrato, por visita con precio o por suscripción). Cada plan crea sus visitas solas en la
   agenda del técnico con su **lista de chequeo** por tipo de equipo (plantillas editables). Al cerrar la visita, un
   **informe en PDF** para el cliente (chequeos, hallazgos, fotos de antes y después, firma) y los hallazgos que
   necesiten atención abren un ticket. Visitas vencidas, en coral y con aviso en Mien.
3. **El técnico en el celular** (`Campo.dc.html`): vista para pantalla angosta: su día (visitas con hora, cliente,
   lugar, prioridad), y en cada visita la lista de chequeo, **fotos** (antes y después, desde la cámara del celular),
   **materiales usados** (se descuentan del almacén o del stock de la camioneta, si existe como almacén), y la **firma
   del cliente en la pantalla** como conformidad. «Cerrar visita» registra horas y cierra o deja el ticket en atención.
4. **QR en cada equipo** (`Campo.dc.html`): cada serie instalada tiene su QR (etiqueta imprimible por lote para un
   proyecto). Escanearlo abre una página pública mínima: «Reportar una falla» con la serie ya puesta (nombre, teléfono,
   qué pasa, foto opcional) que crea el ticket; si quien escanea tiene sesión (técnico), abre la historia del equipo.
   La página pública no muestra datos del cliente; protección contra spam.
5. **Portal del cliente** (`Campo.dc.html`): un enlace por cliente (sin cuenta, revocable) donde ve sus equipos, sus
   garantías, sus tickets y en qué van, sus próximos mantenimientos e **informes para descargar**, y puede reportar una
   falla. Solo ve lo suyo.
6. **Encuesta al cerrar cada ticket** (`Ticket.dc.html`): llega por enlace (para mandarlo por WhatsApp o correo; en el
   MVP se copia): nota del 1 al 5 y comentario. Resumen del trimestre (promedio, respuestas, por técnico) y aviso a
   Gerencia si alguien pone 2 o menos.
7. **Garantía con el fabricante (RMA)** (`Ticket.dc.html`): seguimiento del equipo enviado (enviado, en reparación,
   devuelto o reemplazado, instalado) con fechas; si el fabricante lo reemplaza, la serie nueva toma el lugar de la
   vieja en la instalación (con su historia). **Equipo de respaldo:** un stock de respaldo en el almacén para prestar
   al cliente mientras tanto, con su devolución.
8. **Indicadores de servicio** (`Main.dc.html`): tiempo de respuesta y de solución promedio, % de plazos cumplidos
   contra meta (parámetro, 95 %), y **equipos que fallan una y otra vez** (3 o más fallas en 6 meses, parámetros) con
   «Reclamar al fabricante» o «Cambiar equipo».
9. **Soluciones anteriores** (`Ticket.dc.html`): al abrir un ticket, cómo se resolvieron fallas parecidas del mismo
   modelo (de los tickets cerrados con su solución) y una sugerencia de la IA de por dónde empezar («sugerido, lo
   decides tú»). En los datos de demo, ya sugeridas.

**Datos de demo:** tres proyectos con costo de postventa (uno que pierde margen), planes de mantenimiento (uno vencido)
con un informe hecho, un día de técnico con sus visitas, QR de un proyecto, un portal de cliente, encuestas con
resultados, un RMA en curso con equipo de respaldo, dos equipos con fallas repetidas y tickets con soluciones.

**Alcance:** cruza módulos (Postventa, Proyectos por el costo, Almacén por materiales y respaldo, Agenda, Mien).
